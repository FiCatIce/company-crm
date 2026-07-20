<?php

namespace App\Http\Controllers;

use App\Http\Requests\ResetTeamMemberPasswordRequest;
use App\Http\Requests\StoreTeamMemberRequest;
use App\Http\Requests\UpdateAccountStatusRequest;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\AccountStatus;
use App\Support\CapabilityResolver;
use App\Support\DelegatedUserCreator;
use App\Support\TeamRoleLabels;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Delegated team-member management (DESIGN_HIERARCHY.md batch H4). The manager's
 * scoped counterpart to the admin /users area (UserController / batch B5): a
 * manager creates whitelisted members (sales/cs/maintenance) and resets their
 * passwords, but never assigns roles, toggles permissions, or sees users outside
 * their own book. The capability + escalation guards live in CapabilityResolver /
 * DelegatedUserCreator (batch H2); this controller is the UI + wiring.
 */
class TeamMemberController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        $this->authorize('manageTeamMembers', User::class);

        $actor = $request->user();

        $members = $this->teamMembersQuery($actor)
            ->with('roles:id,name')
            ->orderBy('name')
            ->paginate(15)
            ->through(fn (User $member): array => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'extension' => $member->extension,
                'type' => $this->typeView($member),
                'created_at' => $member->created_at?->toIso8601String(),
                'is_active' => $member->is_active,
                'can_reset' => $actor->can('manageTeamMember', $member),
                'can_set_status' => $actor->can('setStatus', $member),
            ]);

        return Inertia::render('TeamMembers/Index', [
            'members' => $members,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('manageTeamMembers', User::class);

        return Inertia::render('TeamMembers/Create', [
            'types' => $this->typeOptions($request->user()),
        ]);
    }

    public function store(StoreTeamMemberRequest $request): RedirectResponse
    {
        $data = $request->validated();

        // The capability + team-join + audit trail all live in the H2 service — a
        // delegate never sets permissions; the role preset is the source of truth.
        DelegatedUserCreator::create($request->user(), $data['type'], [
            'name' => $data['name'],
            'email' => $data['email'],
            'extension' => $data['extension'] ?? null,
            'password' => $data['password'],
        ]);

        return redirect()->route('team.members.index')
            ->with('success', 'Anggota tim berhasil ditambahkan.');
    }

    public function resetPassword(ResetTeamMemberPasswordRequest $request, User $member): RedirectResponse
    {
        // Area gate is in the request; this pins it to THIS member (own book only).
        $this->authorize('manageTeamMember', $member);

        $member->password = $request->validated()['password']; // hashed by the model cast
        $member->save();

        AuditLog::record($request->user(), $member, 'user.password.reset.delegated');

        return redirect()->route('team.members.index')
            ->with('success', "Password {$member->name} berhasil direset.");
    }

    /**
     * Activate/deactivate a member (H7b). Access is revoked; the member's customers
     * and support assignments are deliberately left in place — reactivation restores
     * the account whole, and handing the book over is the separate H7c transfer.
     */
    public function updateStatus(UpdateAccountStatusRequest $request, User $member): RedirectResponse
    {
        $this->authorize('setStatus', $member);

        $active = $request->boolean('is_active');

        // Friendly flash rather than a 403 page; AccountStatus re-checks it anyway.
        if (! $active && AccountStatus::isLastAdmin($member)) {
            return back()->with('error', 'Tidak dapat menonaktifkan admin terakhir.');
        }

        AccountStatus::set($request->user(), $member, $active);

        return back()->with('success', $active
            ? "{$member->name} berhasil diaktifkan kembali."
            : "{$member->name} dinonaktifkan. Data pelanggan dan penugasannya tetap utuh.");
    }

    /**
     * Members this manager may manage: a delegable type (sales/cs/maintenance) that
     * they provisioned OR that sits on their team. Mirrors UserPolicy::manageTeamMember
     * so the list and the per-row action stay in lockstep. Not a scope-sensitive
     * model (User), so it is outside the ScopeGuardTest tree.
     *
     * @return Builder<User>
     */
    private function teamMembersQuery(User $actor): Builder
    {
        $types = CapabilityResolver::assignableTypes($actor);
        $teamId = $actor->team()?->id;

        return User::query()
            ->whereHas('roles', fn (Builder $role) => $role->whereIn('name', $types))
            ->where(function (Builder $reach) use ($actor, $teamId): void {
                $reach->where('created_by_user', $actor->id);

                if ($teamId !== null) {
                    $reach->orWhereHas('teams', fn (Builder $team) => $team->whereKey($teamId));
                }
            });
    }

    /**
     * The creatable types as {value,label} dropdown options — labels via the L3
     * naming seam, never hardcoded.
     *
     * @return list<array{value: string, label: string}>
     */
    private function typeOptions(User $actor): array
    {
        return array_map(
            fn (string $slug): array => ['value' => $slug, 'label' => TeamRoleLabels::label($slug)],
            CapabilityResolver::creatableTypes($actor),
        );
    }

    /**
     * A member's role as a {value,label} pair for display, or null.
     *
     * @return array{value: string, label: string}|null
     */
    private function typeView(User $member): ?array
    {
        $slug = $member->getRoleNames()->first();

        return is_string($slug)
            ? ['value' => $slug, 'label' => TeamRoleLabels::label($slug)]
            : null;
    }
}
