<?php

namespace App\Http\Controllers;

use App\Enums\PermissionName as P;
use App\Models\Customer;
use App\Models\User;
use App\Support\HierarchyResolver;
use App\Support\TeamRoleLabels;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Tim Saya" — one page that shows the hierarchy from the viewer's own vantage
 * point (DESIGN_HIERARCHY.md batch H6). Read-only; the management surfaces are
 * /team/members (H4) and /team/assignments (H5).
 *
 *   manager  → the reps in their team, each with their book size and who supports
 *              them (the team-wide "who covers whom" view deferred from H5)
 *   sales    → the support agents helping them, linking out to manage
 *   support  → the reps they serve
 *
 * The variant is chosen by CAPABILITY, never by role name, so a custom role that
 * holds user.assign is treated as a rep without any code change (anti-hardcode,
 * same principle as the TeamRoleLabels naming seam).
 */
class TeamController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->can(P::TeamView->value), 403);

        $team = $user->team();

        $props = [
            'team' => $team !== null ? ['id' => $team->id, 'name' => $team->name] : null,
        ];

        if ($user->can(P::CustomerViewTeam->value)) {
            return Inertia::render('Team/Index', [
                ...$props,
                'kind' => 'manager',
                ...$this->managerView($user),
            ]);
        }

        if ($user->can(P::UserAssign->value)) {
            return Inertia::render('Team/Index', [
                ...$props,
                'kind' => 'sales',
                'reps' => [],
                'agents' => $this->agentRows($user->assignees()->with('roles:id,name')->orderBy('name')->get()),
            ]);
        }

        return Inertia::render('Team/Index', [
            ...$props,
            'kind' => 'support',
            'reps' => [],
            'agents' => $this->agentRows($user->assignedSalesFor()->with('roles:id,name')->orderBy('name')->get()),
        ]);
    }

    /**
     * The manager variant: every teammate split into reps (they own a book and
     * assign support) and support agents — by capability, not role name.
     *
     * @return array{reps: list<array<string, mixed>>, agents: list<array<string, mixed>>}
     */
    private function managerView(User $manager): array
    {
        $memberIds = array_values(array_diff(
            HierarchyResolver::teamMemberIds($manager),
            [(int) $manager->id],
        ));

        /** @var Collection<int, User> $members */
        $members = User::query()
            ->whereIn('id', $memberIds)
            ->with(['roles:id,name', 'assignees:id,name', 'assignees.roles:id,name'])
            ->orderBy('name')
            ->get();

        $reps = $members->filter(fn (User $member): bool => $member->can(P::UserAssign->value));
        $support = $members->filter(fn (User $member): bool => ! $member->can(P::UserAssign->value)
            && $member->can(P::CustomerViewAssigned->value));

        return [
            'reps' => array_values($reps
                ->map(fn (User $rep): array => [
                    'id' => $rep->id,
                    'name' => $rep->name,
                    'email' => $rep->email,
                    'type' => $this->typeView($rep),
                    'customers_count' => $this->bookSize($manager, $rep),
                    'assignees' => $this->agentRows($rep->assignees),
                ])
                ->all()),
            'agents' => $this->agentRows($support),
        ];
    }

    /**
     * How many customers $rep owns, counted WITHIN the manager's own visibility —
     * so the figure can never exceed what the manager is entitled to see, and it
     * matches the same Customer::visibleTo the /customers page uses.
     */
    private function bookSize(User $manager, User $rep): int
    {
        return Customer::query()
            ->visibleTo($manager)
            ->where(function (Builder $owned) use ($rep): void {
                $owned->where('created_by', $rep->id)->orWhere('assigned_to', $rep->id);
            })
            ->count();
    }

    /**
     * @param  Collection<int, User>  $agents
     * @return list<array<string, mixed>>
     */
    private function agentRows(Collection $agents): array
    {
        return array_values($agents
            ->map(fn (User $agent): array => [
                'id' => $agent->id,
                'name' => $agent->name,
                'email' => $agent->email,
                'type' => $this->typeView($agent),
            ])
            ->all());
    }

    /**
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
