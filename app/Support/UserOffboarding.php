<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Offboarding a user (DESIGN_HIERARCHY.md batch H7c) — the EXPLICIT counterpart to
 * H7b's reversible deactivate switch.
 *
 * Offboard = TRANSFER + DEACTIVATE, never delete. Everything the leaver holds moves
 * to a named successor in one audited act, then their access is switched off; the
 * row survives so history stays readable. Permanent deletion remains a separate
 * admin action, and it is now BLOCKED while a user still holds anything.
 *
 * Three rules make this safe:
 *
 *  1. ONE successor for everything (decision H7c). Splitting a book across several
 *     people is a rare need and stays possible afterwards through the ordinary
 *     reassign / assignment screens — but the offboard itself is one act with one
 *     audit trail, not a fan-out of half-moves that can be abandoned midway.
 *
 *  2. created_by is NEVER rewritten. Who entered a customer is historical
 *     attribution and is immutable (DESIGN_HIERARCHY.md H-7); only assigned_to —
 *     the live "who works this" pointer — moves.
 *
 *  3. The team OUTLIVES its manager (free from the DH1 teams-as-entity choice).
 *     Membership is untouched, so no rep is ever orphaned; the successor simply
 *     takes the manager seat.
 */
final class UserOffboarding
{
    /**
     * Everything $user still holds. An offboard/delete is refused while any of
     * these is non-zero, so this is both the guard's input and what the UI shows
     * the operator before they choose a successor.
     *
     * @return array{customers: int, assignees: int, reps: int, teams_led: int, teams: int}
     */
    public static function holdings(User $user): array
    {
        return [
            // Aggregates only — no rows leave this method (ScopeGuardTest).
            'customers' => Customer::where('assigned_to', $user->id)->count(),
            'assignees' => $user->assignees()->count(),
            'reps' => $user->assignedSalesFor()->count(),
            'teams_led' => DB::table('team_user')
                ->where('user_id', $user->id)
                ->where('role_in_team', 'manager')
                ->count(),
            'teams' => DB::table('team_user')->where('user_id', $user->id)->count(),
        ];
    }

    /**
     * Whether $user still holds anything that would be stranded by removing them.
     */
    public static function hasHoldings(User $user): bool
    {
        foreach (self::holdings($user) as $count) {
            if ($count > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * A human-readable reason an offboard/delete is required first, or null when
     * the user holds nothing. Deliberately concrete ("masih memegang 12 customer")
     * — a bare "cannot delete" tells the operator nothing about what to fix.
     */
    public static function blockingReason(User $user): ?string
    {
        $h = self::holdings($user);
        $parts = [];

        if ($h['customers'] > 0) {
            $parts[] = "{$h['customers']} customer";
        }
        if ($h['assignees'] > 0) {
            $parts[] = "{$h['assignees']} penugasan support";
        }
        if ($h['reps'] > 0) {
            $parts[] = "{$h['reps']} sales yang dilayani";
        }
        if ($h['teams_led'] > 0) {
            $parts[] = "{$h['teams_led']} tim yang dipimpin";
        }

        if ($parts === [] && $h['teams'] > 0) {
            $parts[] = 'keanggotaan tim';
        }

        return $parts === []
            ? null
            : "{$user->name} masih memegang ".implode(', ', $parts).'. Pilih pengganti terlebih dahulu.';
    }

    /**
     * Who may take over from $user: an ACTIVE account with the SAME role that is
     * either already on $user's team or on no team at all.
     *
     * Same role, because the successor inherits the leaver's work and must be able
     * to do it — a CS cannot hold a rep's book. Same team OR teamless, because
     * DH2 keeps one user to one team: pulling a rep out of team B to cover team A
     * would silently break that, whereas a teamless successor can simply be joined
     * to the team as part of the transfer.
     *
     * @return Collection<int, User>
     */
    public static function eligibleSuccessors(User $user): Collection
    {
        $role = $user->getRoleNames()->first();

        if (! is_string($role)) {
            return new Collection;
        }

        $teamIds = DB::table('team_user')->where('user_id', $user->id)->pluck('team_id')->all();

        return User::query()
            ->active()
            ->whereKeyNot($user->id)
            ->whereHas('roles', fn (Builder $r) => $r->where('name', $role))
            ->where(function (Builder $reach) use ($teamIds): void {
                // Already on the leaver's team…
                if ($teamIds !== []) {
                    $reach->whereHas('teams', fn (Builder $t) => $t->whereIn('teams.id', $teamIds));
                }
                // …or on no team at all (joined to it by the transfer below).
                $reach->orWhereDoesntHave('teams');
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * Move everything $user holds to $successor, then switch $user off.
     *
     * Runs in a transaction: a half-transferred book (customers moved but the team
     * seat left empty) is worse than a failed offboard.
     *
     * @throws AuthorizationException
     */
    public static function offboard(User $actor, User $user, User $successor): void
    {
        if (! $actor->can('offboard', $user)) {
            throw new AuthorizationException('Anda tidak berwenang meng-offboard pengguna ini.');
        }

        if ($successor->id === $user->id) {
            throw new AuthorizationException('Pengganti tidak boleh orang yang sama.');
        }

        if (! self::eligibleSuccessors($user)->contains('id', $successor->id)) {
            throw new AuthorizationException('Pengganti tidak memenuhi syarat.');
        }

        if (AccountStatus::isLastAdmin($user)) {
            throw new AuthorizationException('Tidak dapat meng-offboard admin terakhir.');
        }

        $moved = self::holdings($user);

        DB::transaction(function () use ($user, $successor): void {
            // 1. The live "who works this" pointer moves; created_by does NOT.
            Customer::where('assigned_to', $user->id)->update(['assigned_to' => $successor->id]);

            // 2. Support wiring, in both directions. Rows the successor already has
            //    are dropped rather than duplicated (the pivot is unique per pair).
            self::movePivot('sales_user_id', 'assignee_user_id', $user->id, $successor->id);
            self::movePivot('assignee_user_id', 'sales_user_id', $user->id, $successor->id);

            // 3. Team seats. The TEAM SURVIVES: membership of everyone else is
            //    untouched, the successor simply takes the leaver's seat (and role
            //    in it, so a manager is replaced by a manager). No rep is orphaned.
            $seats = DB::table('team_user')->where('user_id', $user->id)->get();

            foreach ($seats as $seat) {
                $already = DB::table('team_user')
                    ->where('team_id', $seat->team_id)
                    ->where('user_id', $successor->id)
                    ->first();

                if ($already === null) {
                    DB::table('team_user')->insert([
                        'team_id' => $seat->team_id,
                        'user_id' => $successor->id,
                        'role_in_team' => $seat->role_in_team,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } elseif ($seat->role_in_team === 'manager') {
                    // An existing member promoted into the vacated manager seat.
                    DB::table('team_user')
                        ->where('team_id', $seat->team_id)
                        ->where('user_id', $successor->id)
                        ->update(['role_in_team' => 'manager', 'updated_at' => now()]);
                }
            }

            DB::table('team_user')->where('user_id', $user->id)->delete();

            // 4. Access off. The row stays so history remains readable.
            $user->forceFill(['is_active' => false])->save();
        });

        AuditLog::record($actor, $user, 'user.offboarded', [
            'successor' => ['id' => $successor->id, 'name' => $successor->name],
            'transferred' => $moved,
        ]);
    }

    /**
     * Re-point one side of the sales_assignee pivot from $from to $to, discarding
     * rows that would collide with a pair $to already has.
     */
    private static function movePivot(string $column, string $otherColumn, int $from, int $to): void
    {
        $existing = DB::table('sales_assignee')->where($column, $to)->pluck($otherColumn)->all();

        DB::table('sales_assignee')
            ->where($column, $from)
            ->whereIn($otherColumn, $existing === [] ? [0] : $existing)
            ->delete();

        // A user is never their own support: drop a row that would self-pair.
        DB::table('sales_assignee')->where($column, $from)->where($otherColumn, $to)->delete();

        DB::table('sales_assignee')->where($column, $from)->update([$column => $to]);
    }
}
