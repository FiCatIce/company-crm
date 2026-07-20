<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupportAssignmentRequest;
use App\Models\User;
use App\Support\CapabilityResolver;
use App\Support\HierarchyResolver;
use App\Support\SupportAssignments;
use App\Support\TeamRoleLabels;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Support assignment UI (DESIGN_HIERARCHY.md DH5 / batch H5). A sales user picks
 * which CS/Maintenance agents help with their customers; the pivot they write is
 * read LIVE by Customer::scopeVisibleTo (H3), so assigning grants sight of their
 * book immediately and unassigning revokes it just as fast.
 *
 * Always SELF-scoped: the actor is the authenticated user and no route or field
 * names another sales, so a rep can only ever wire support to their own book.
 * The candidate pool is team-scoped — see HierarchyResolver::supportCandidateIds.
 */
class SupportAssignmentController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        $this->authorize('manageAssignments', User::class);

        $sales = $request->user();
        $types = CapabilityResolver::assignableCandidateTypes($sales);

        // Pivot timestamps in one read, keyed by assignee — avoids leaning on the
        // pivot accessor for a value we only need to display.
        $assignedAt = DB::table('sales_assignee')
            ->where('sales_user_id', $sales->id)
            ->pluck('created_at', 'assignee_user_id');

        $assignees = $sales->assignees()
            ->with('roles:id,name')
            ->orderBy('name')
            ->get()
            ->map(function (User $agent) use ($assignedAt): array {
                $raw = $assignedAt->get($agent->id);

                return [
                    'id' => $agent->id,
                    'name' => $agent->name,
                    'email' => $agent->email,
                    'type' => $this->typeView($agent),
                    'assigned_at' => $raw !== null ? Carbon::parse((string) $raw)->toIso8601String() : null,
                ];
            })
            ->values()
            ->all();

        $candidateIds = array_values(array_diff(
            HierarchyResolver::supportCandidateIds($sales, $types),
            $assignedAt->keys()->map(fn ($id): int => (int) $id)->all(),
        ));

        $candidates = User::query()
            ->whereIn('id', $candidateIds)
            ->with('roles:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (User $agent): array => [
                'id' => $agent->id,
                'name' => $agent->name,
                'email' => $agent->email,
                'type' => $this->typeView($agent),
            ])
            ->values()
            ->all();

        return Inertia::render('TeamAssignments/Index', [
            'assignees' => $assignees,
            'candidates' => $candidates,
            'hasTeam' => $sales->team() !== null,
        ]);
    }

    public function store(StoreSupportAssignmentRequest $request): RedirectResponse
    {
        $sales = $request->user();

        /** @var list<int> $ids */
        $ids = $request->validated()['assignee_ids'];

        foreach (User::query()->whereIn('id', $ids)->get() as $agent) {
            // Re-checks the type + audits (H2 service) — the security backstop.
            SupportAssignments::assign($sales, $agent);
        }

        $count = count($ids);

        return redirect()->route('team.assignments.index')
            ->with('success', "{$count} support berhasil di-assign.");
    }

    public function destroy(Request $request, User $assignee): RedirectResponse
    {
        $this->authorize('manageAssignments', User::class);

        // Self-scoped by construction: detaches only from the ACTOR's own pivot
        // rows, so a rep can never sever someone else's assignment.
        SupportAssignments::unassign($request->user(), $assignee);

        return redirect()->route('team.assignments.index')
            ->with('success', "{$assignee->name} tidak lagi menangani customer Anda.");
    }

    /**
     * An agent's role as a {value,label} pair — label via the L3 naming seam.
     *
     * @return array{value: string, label: string}|null
     */
    private function typeView(User $agent): ?array
    {
        $slug = $agent->getRoleNames()->first();

        return is_string($slug)
            ? ['value' => $slug, 'label' => TeamRoleLabels::label($slug)]
            : null;
    }
}
