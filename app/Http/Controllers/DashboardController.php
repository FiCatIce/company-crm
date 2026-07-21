<?php

namespace App\Http\Controllers;

use App\Enums\CustomerSource;
use App\Enums\CustomerStatus;
use App\Enums\InteractionType;
use App\Enums\PermissionName;
use App\Models\Customer;
use App\Models\Interaction;
use App\Models\Transaction;
use App\Models\User;
use App\Support\HierarchyResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->can(PermissionName::DashboardView->value), 403);

        // The dashboard is composed PER PERMISSION (DESIGN_RBAC.md §4.4), never one
        // blob — EVERY number is gated by the permission for the data it summarises,
        // so a role only ever reads totals it is entitled to. Do NOT add a
        // Gate::before admin bypass here.
        $canSeeAggregate = $user->can(PermissionName::DashboardStatsAggregate->value);
        $canViewAllCustomers = $user->can(PermissionName::CustomerViewAll->value);
        $canViewOwnCustomers = $user->can(PermissionName::CustomerViewOwn->value);
        // A "scoped" viewer sees only a SLICE of the book, never the whole org —
        // Sales (own), Manager (team), or CS/maintenance (assigned sales' books).
        // Org-wide totals are global data they must not read, so a scoped viewer
        // gets the personal "Ringkasan Saya" band only — never the aggregate band.
        // (The band's numbers now follow their hierarchy via Customer::visibleTo.)
        $isScopedViewer = ! $canViewAllCustomers && (
            $canViewOwnCustomers
            || $user->can(PermissionName::CustomerViewTeam->value)
            || $user->can(PermissionName::CustomerViewAssigned->value)
        );
        $canSeeOrgStats = $canSeeAggregate && ! $isScopedViewer;

        // Each aggregate is gated by the permission for its data domain: transaction
        // counts/trend need transaction.view.all (so admin, which holds neither,
        // never sees "Total Transaksi"); warranty needs customer.view.all; money
        // needs revenue.view.
        $canViewAllTransactions = $user->can(PermissionName::TransactionViewAll->value);
        // L2-B: the per-Sales widgets (Top Sales / Revenue by Sales) replace the old
        // reseller breakdowns. They rank the books of MULTIPLE sales reps, so they
        // are meaningful only for a viewer who spans more than their own book: a
        // global role (org) or a manager over a team. A rep (view.own), CS/maintenance
        // (view.assigned) and admin (no customer view) get neither — a rep already
        // has their personal band + own revenue.
        $canSpanSalesBooks = $canViewAllCustomers
            || $user->can(PermissionName::CustomerViewTeam->value);
        // Revenue (H7d). The band is SCOPED, not org-wide: the figure is the SUM of
        // the transactions this viewer may actually see (Transaction::visibleTo), so
        // it can never exceed their entitlement — org-wide for a global role, the
        // team book for a manager, their own book for a rep. Before H7d it needed
        // transaction.view.all, which H3 took away from managers, so a manager saw
        // NO money band at all despite reading amounts per row in /transactions.
        //
        // Requiring a transaction view TIER alongside revenue.view keeps the old
        // guarantee that a dangling revenue.view cannot surface money on its own,
        // and it is the same rule that gates the per-row amount elsewhere
        // (ProvidesModelAbilities::canSeeAmount) — so the total and the rows it sums
        // are gated identically. CS/maintenance hold neither tier: money stays
        // OMITTED for them even though H3/H5 widened which customers they can see.
        $canSeeRevenue = $user->can(PermissionName::RevenueView->value)
            && ($user->can(PermissionName::TransactionViewAll->value)
                || $user->can(PermissionName::TransactionViewOwn->value));
        // The call feed IS scoped per-viewer (Interaction::visibleTo), so anyone
        // who may see any calls gets it — Sales just sees their own customers'.
        $canViewCalls = $user->can(PermissionName::InteractionViewAll->value)
            || $user->can(PermissionName::InteractionViewOwn->value);

        $props = [
            'me' => $this->personalStats($user),
        ];

        // Hierarchy band (H6) — the PEOPLE side of the viewer's position. Absent
        // for anyone without team.view (admin). The customer figure deliberately
        // lives in `me.myCustomers` alone: for every role that is already the
        // hierarchy-scoped Customer::visibleTo count (own / team roll-up /
        // assigned), so repeating it here would print the same number twice under
        // two labels. One number, one source — the Vue relabels it per team.kind.
        $team = $this->teamStats($user);

        if ($team !== null) {
            $props['team'] = $team;
        }

        // Org-wide aggregate band — composed per permission, omitted entirely for a
        // scoped (own-only) viewer. `customers` is the base aggregate; the rest are
        // added only with the matching data permission.
        if ($canSeeOrgStats) {
            $warranty = $this->warrantyBreakdown();

            $stats = [
                'customers' => Customer::count(),
                'customersThisMonth' => Customer::where('created_at', '>=', now()->startOfMonth())->count(),
            ];

            if ($canViewAllTransactions) {
                $stats['transactions'] = Transaction::count();
                $stats['transactionsThisMonth'] = Transaction::where('purchased_at', '>=', now()->startOfMonth()->toDateString())->count();
            }

            if ($canViewAllCustomers) {
                $stats['activeWarranties'] = $warranty['active'];
            }

            // L2-B: active-sales headcount (replaces the old active-reseller count).
            // A harmless roster figure shown to the aggregate band (admin + global),
            // counting active accounts that hold the sales marker (user.assign) — the
            // same capability that identifies a rep in teamStats and the per-Sales
            // widgets below, so the count matches who can appear in "Top Sales".
            $stats['activeSales'] = User::query()
                ->active()
                ->permission(PermissionName::UserAssign->value)
                ->count();

            $props['stats'] = $stats;

            // Warranty donut mirrors the warranty aggregate; monthly transaction
            // trend is transaction data — gate each on its domain permission.
            if ($canViewAllCustomers) {
                $props['warrantyBreakdown'] = $warranty;
            }

            if ($canViewAllTransactions) {
                $props['trend'] = $this->transactionTrend();
            }
        }

        // Org-wide detail widgets exposing every customer's name / purchase rows.
        if ($canViewAllCustomers) {
            $props['recentTransactions'] = $this->recentTransactions();
            $props['expiringSoon'] = $this->expiringSoon();
        }

        // Per-Sales ranking band (L2-B, replaces the reseller breakdowns). Present
        // for a viewer who spans multiple reps' books; `salesScope` labels the card
        // (org for a global role, team for a manager) — the numbers are already
        // bounded by Customer::visibleTo, so the label never widens what is counted.
        if ($canSpanSalesBooks) {
            $props['topSales'] = $this->topSales($user);
            $props['salesScope'] = $canViewAllCustomers ? 'org' : 'team';
        }

        // Call feed — scoped per viewer: all for managers/CS/maintenance/admin,
        // only their own customers' calls for Sales.
        if ($canViewCalls) {
            $props['recentCalls'] = $this->recentCalls($user);
        }

        // Revenue band — its OWN prop, deliberately not inside `stats`: `stats` is
        // the org-wide aggregate band that a scoped viewer never receives, and a
        // manager/rep must still get their (scoped) money. Absent entirely — key
        // missing, not null or 0 — for anyone without the money gate.
        if ($canSeeRevenue) {
            $props['revenue'] = $this->revenueStats($user);
        }

        // Revenue-by-Sales — the money twin of Top Sales. Same span gate PLUS the
        // money gate (canSeeRevenue), so CS/maintenance (no revenue.view) and admin
        // (no money) never receive it, and a plain rep (no span) does not either.
        if ($canSpanSalesBooks && $canSeeRevenue) {
            $props['revenueBySales'] = $this->revenueBySales($user);
        }

        return Inertia::render('Dashboard', $props);
    }

    /**
     * The ten most recent calls the viewer may see (CTI + manual), newest first —
     * a monitoring feed of live phone activity. Scoped via Interaction::visibleTo:
     * org-wide for managers/CS/maintenance/admin, but only the viewer's own
     * customers' calls for Sales (DESIGN_RBAC.md §4.4). Calls for a customer that
     * is still a CTI-sourced lead are flagged so agents can spot fresh prospects.
     *
     * @return array<int, array<string, mixed>>
     */
    private function recentCalls(User $user): array
    {
        return Interaction::query()
            ->visibleTo($user)
            ->where('type', InteractionType::Call)
            ->with(['customer:id,name,status,source', 'user:id,name'])
            ->latest('occurred_at')
            ->latest('id')
            ->take(10)
            ->get()
            ->map(fn (Interaction $call) => [
                'id' => $call->id,
                'customer' => $call->customer
                    ? ['id' => $call->customer->id, 'name' => $call->customer->name]
                    : null,
                'direction' => $call->direction?->value,
                'outcome' => $call->outcome?->value,
                'outcome_label' => $call->outcome?->label(),
                'duration_sec' => $call->duration_sec,
                'occurred_at' => $call->occurred_at->toIso8601String(),
                'source' => $call->source->value,
                'user' => $call->user
                    ? ['id' => $call->user->id, 'name' => $call->user->name]
                    : null,
                'is_cti_lead' => $call->customer !== null
                    && $call->customer->source === CustomerSource::Cti
                    && $call->customer->status === CustomerStatus::Lead,
            ])
            ->all();
    }

    /**
     * Revenue totals for THIS viewer (H7d) via DB SUM (nulls are ignored by SQL SUM
     * — no PHP scan). All-time plus this/last month so the UI can show a
     * month-over-month delta.
     *
     * Every figure runs through Transaction::visibleTo, the SAME scope /transactions
     * uses, so the headline total is by construction the sum of the rows the viewer
     * can open — the mismatch class that produced the old "dashboard says 0 but the
     * list shows 5" bug cannot arise here.
     *
     * `scope` is a label for the UI heading only; the number is already bounded by
     * the scope itself, never by this string.
     *
     * @return array{total: float, thisMonth: float, lastMonth: float, scope: 'org'|'team'|'own'}
     */
    private function revenueStats(User $user): array
    {
        $thisMonthStart = now()->startOfMonth()->toDateString();
        $lastMonthStart = now()->subMonthNoOverflow()->startOfMonth()->toDateString();
        $lastMonthEnd = now()->startOfMonth()->subDay()->toDateString();

        $scope = match (true) {
            $user->can(PermissionName::TransactionViewAll->value) => 'org',
            $user->can(PermissionName::CustomerViewTeam->value) => 'team',
            default => 'own',
        };

        return [
            'total' => (float) Transaction::query()->visibleTo($user)->sum('amount'),
            'thisMonth' => (float) Transaction::query()->visibleTo($user)
                ->where('purchased_at', '>=', $thisMonthStart)->sum('amount'),
            'lastMonth' => (float) Transaction::query()->visibleTo($user)
                ->whereBetween('purchased_at', [$lastMonthStart, $lastMonthEnd])->sum('amount'),
            'scope' => $scope,
        ];
    }

    /**
     * The five sales reps with the highest total sales value (revenue > 0) within
     * the viewer's scope (L2-B). Bounded by Transaction::visibleTo — org-wide for a
     * global role, the team book for a manager — and attributed to the responsible
     * sales rep (the customer's assigned owner, else whoever entered it). The
     * per-rep SUM is computed in SQL, not scanned in PHP.
     *
     * @return array<int, array{id: int, name: string, revenue: float}>
     */
    private function revenueBySales(User $user): array
    {
        $salesIds = $this->scopedSalesIds($user);

        if ($salesIds === []) {
            return [];
        }

        $owner = 'COALESCE(customers.assigned_to, customers.created_by)';

        $rows = Transaction::query()
            ->visibleTo($user)
            ->join('customers', 'transactions.customer_id', '=', 'customers.id')
            ->selectRaw("{$owner} as owner_id, SUM(transactions.amount) as revenue")
            ->whereIn(DB::raw($owner), $salesIds)
            ->groupByRaw($owner)
            ->orderByDesc('revenue')
            ->orderBy('owner_id')
            ->limit(5)
            ->get();

        $names = User::query()->whereIn('id', $rows->pluck('owner_id'))->pluck('name', 'id');

        return $rows
            ->filter(fn (Transaction $row): bool => (float) $row->getAttribute('revenue') > 0)
            ->map(function (Transaction $row) use ($names): array {
                $ownerId = (int) $row->getAttribute('owner_id');

                return [
                    'id' => $ownerId,
                    'name' => $names[$ownerId] ?? '—',
                    'revenue' => (float) $row->getAttribute('revenue'),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * The sales reps whose books this viewer may aggregate for the per-Sales widgets:
     * the whole roster for a global (customer.view.all) viewer, the viewer's team
     * for a manager (customer.view.team). "Sales" is the user.assign capability —
     * the same rep marker teamStats uses — so managers/CS/system accounts that
     * happen to own a record are never ranked as sales.
     *
     * @return list<int>
     */
    private function scopedSalesIds(User $user): array
    {
        $salesIds = array_values(array_map('intval', User::query()
            ->permission(PermissionName::UserAssign->value)
            ->pluck('id')
            ->all()));

        if ($user->can(PermissionName::CustomerViewAll->value)) {
            return $salesIds;
        }

        // Manager (view.team): keep only reps inside their own team, so the ranking
        // can never reach across teams — the same isolation Customer::visibleTo holds.
        return array_values(array_intersect($salesIds, HierarchyResolver::teamMemberIds($user)));
    }

    /**
     * Per-agent block scoped to the signed-in user (attribution/authorship only —
     * every role still sees all the org-wide widgets above). A user with no
     * assigned customers or interactions simply reads zeros / empty lists.
     *
     * @return array{myCustomers: int, myInteractionsToday: int, myExpiringWarranties: int, myRecentInteractions: array<int, array<string, mixed>>}
     */
    private function personalStats(User $user): array
    {
        $userId = (int) $user->id;
        $threshold = now()->startOfDay()->addDays(30);

        // "My" customers now follow the viewer's HIERARCHY visibility (H3): the own
        // book for Sales, the whole team for a Manager, the assigning sales' books
        // for CS/maintenance — the very same Customer::visibleTo the /customers page
        // uses, so the personal band always matches the list the viewer can open.
        $myExpiringWarranties = Transaction::query()
            ->visibleTo($user)
            ->with('product:id,warranty_months')
            ->get(['id', 'customer_id', 'product_id', 'purchased_at'])
            ->filter(fn (Transaction $transaction) => $transaction->product->warranty_months > 0
                && $transaction->is_under_warranty
                && $transaction->warranty_expires_at->lte($threshold))
            ->count();

        return [
            'myCustomers' => Customer::query()->visibleTo($user)->count(),
            'myInteractionsToday' => Interaction::where('user_id', $userId)
                ->whereDate('occurred_at', today())
                ->count(),
            'myExpiringWarranties' => $myExpiringWarranties,
            'myRecentInteractions' => $this->myRecentInteractions($userId),
        ];
    }

    /**
     * The viewer's place in the hierarchy, counted from the SAME sources the
     * /team page renders (H6), so the two can never disagree:
     *
     *   manager → reps + support agents in their team
     *   sales   → support agents assigned to them
     *   support → reps who assigned them
     *
     * The variant is chosen by CAPABILITY, not role name (so a custom role holding
     * user.assign counts as a rep). Returns null for a viewer without team.view —
     * admin, which has no team — so the band is omitted rather than rendered empty.
     *
     * @return array<string, mixed>|null
     */
    private function teamStats(User $user): ?array
    {
        if (! $user->can(PermissionName::TeamView->value)) {
            return null;
        }

        if ($user->can(PermissionName::CustomerViewTeam->value)) {
            $memberIds = array_values(array_diff(
                HierarchyResolver::teamMemberIds($user),
                [(int) $user->id],
            ));

            $members = User::query()->whereIn('id', $memberIds)->get();

            $reps = $members->filter(fn (User $member): bool => $member->can(PermissionName::UserAssign->value));

            return [
                'kind' => 'manager',
                'repCount' => $reps->count(),
                'supportCount' => $members->filter(
                    fn (User $member): bool => ! $member->can(PermissionName::UserAssign->value)
                        && $member->can(PermissionName::CustomerViewAssigned->value)
                )->count(),
            ];
        }

        if ($user->can(PermissionName::UserAssign->value)) {
            return ['kind' => 'sales', 'supportCount' => $user->assignees()->count()];
        }

        if ($user->can(PermissionName::CustomerViewAssigned->value)) {
            return ['kind' => 'support', 'repCount' => $user->assignedSalesFor()->count()];
        }

        return null;
    }

    /**
     * The five most recent interactions authored by this user, for quick-continue
     * (each links back to the customer 360).
     *
     * @return array<int, array<string, mixed>>
     */
    private function myRecentInteractions(int $userId): array
    {
        // unscoped-ok: personal band — interactions authored by the caller only.
        return Interaction::query()
            ->where('user_id', $userId)
            ->with('customer:id,name')
            ->latest('occurred_at')
            ->latest('id')
            ->take(5)
            ->get()
            ->map(fn (Interaction $interaction) => [
                'id' => $interaction->id,
                'customer' => $interaction->customer
                    ? ['id' => $interaction->customer->id, 'name' => $interaction->customer->name]
                    : null,
                'type' => $interaction->type->value,
                'type_label' => $interaction->type->label(),
                'direction' => $interaction->direction?->value,
                'occurred_at' => $interaction->occurred_at->toIso8601String(),
                'subject' => $interaction->subject,
            ])
            ->all();
    }

    /**
     * Split every transaction into mutually exclusive warranty buckets. Warranty
     * state comes from the model accessor, so it is resolved in PHP. A product
     * sold without warranty is "none" — never "expired".
     *
     * @return array{active: int, expired: int, none: int}
     */
    private function warrantyBreakdown(): array
    {
        // unscoped-ok: aggregate — reads warranty columns only (no customer/amount);
        // org-wide counts shown to every dashboard viewer (dashboard.stats.aggregate).
        $transactions = Transaction::query()
            ->with('product:id,warranty_months')
            ->get(['id', 'product_id', 'purchased_at']);

        $active = 0;
        $expired = 0;
        $none = 0;

        foreach ($transactions as $transaction) {
            if ($transaction->product->warranty_months > 0) {
                $transaction->is_under_warranty ? $active++ : $expired++;
            } else {
                $none++;
            }
        }

        return ['active' => $active, 'expired' => $expired, 'none' => $none];
    }

    /**
     * The six most recent transactions for the activity feed.
     *
     * @return array<int, array<string, mixed>>
     */
    private function recentTransactions(): array
    {
        // unscoped-ok: org-wide feed, added to props only for customer.view.all
        // roles (gated at the __invoke call site) — a Sales user never receives it.
        return Transaction::query()
            ->with(['customer:id,name', 'product:id,name,warranty_months'])
            ->latest('purchased_at')
            ->latest('id')
            ->take(6)
            ->get()
            ->map(fn (Transaction $transaction) => [
                'id' => $transaction->id,
                'customer' => $transaction->customer?->name,
                'product' => $transaction->product?->name,
                'purchased_at' => $transaction->purchased_at->toDateString(),
                'warranty_expires_at' => $transaction->warranty_expires_at->toDateString(),
                'is_under_warranty' => $transaction->is_under_warranty,
                'warranty_months' => $transaction->product?->warranty_months,
            ])
            ->all();
    }

    /**
     * Active warranties expiring within the next 30 days, soonest first (max five).
     *
     * @return array<int, array<string, mixed>>
     */
    private function expiringSoon(): array
    {
        $today = now()->startOfDay();
        $threshold = $today->copy()->addDays(30);

        // unscoped-ok: org-wide watchlist, added to props only for customer.view.all
        // roles (gated at the __invoke call site) — a Sales user never receives it.
        return Transaction::query()
            ->with(['customer:id,name', 'product:id,name,warranty_months'])
            ->get(['id', 'customer_id', 'product_id', 'purchased_at'])
            ->filter(fn (Transaction $transaction) => $transaction->product->warranty_months > 0
                && $transaction->is_under_warranty
                && $transaction->warranty_expires_at->lte($threshold))
            ->sortBy(fn (Transaction $transaction) => $transaction->warranty_expires_at->getTimestamp())
            ->take(5)
            ->map(fn (Transaction $transaction) => [
                'id' => $transaction->id,
                'customer' => $transaction->customer?->name,
                'product' => $transaction->product?->name,
                'warranty_expires_at' => $transaction->warranty_expires_at->toDateString(),
                'days_left' => (int) $today->diffInDays($transaction->warranty_expires_at->startOfDay()),
            ])
            ->values()
            ->all();
    }

    /**
     * The five sales reps with the most customers within the viewer's scope (L2-B).
     * Bounded by Customer::visibleTo (so the counts never exceed the viewer's
     * entitlement) and attributed to the responsible sales rep (assigned owner, else
     * whoever entered the record); only actual reps (user.assign) are ranked.
     *
     * @return array<int, array{id: int, name: string, customers_count: int}>
     */
    private function topSales(User $user): array
    {
        $salesIds = $this->scopedSalesIds($user);

        if ($salesIds === []) {
            return [];
        }

        $owner = 'COALESCE(assigned_to, created_by)';

        $rows = Customer::query()
            ->visibleTo($user)
            ->selectRaw("{$owner} as owner_id, COUNT(*) as customers_count")
            ->whereIn(DB::raw($owner), $salesIds)
            ->groupByRaw($owner)
            ->orderByDesc('customers_count')
            ->orderBy('owner_id')
            ->limit(5)
            ->get();

        $names = User::query()->whereIn('id', $rows->pluck('owner_id'))->pluck('name', 'id');

        return $rows
            ->map(function (Customer $row) use ($names): array {
                $ownerId = (int) $row->getAttribute('owner_id');

                return [
                    'id' => $ownerId,
                    'name' => $names[$ownerId] ?? '—',
                    'customers_count' => (int) $row->getAttribute('customers_count'),
                ];
            })
            ->all();
    }

    /**
     * Transaction counts per month for the trailing 12 months (gaps filled with 0).
     *
     * @return array<int, array{month: string, label: string, count: int}>
     */
    private function transactionTrend(): array
    {
        $start = now()->startOfMonth()->subMonths(11);

        // unscoped-ok: aggregate trend — reads only purchase dates (no customer or
        // amount data), collapsed to monthly counts shown to all viewers.
        $counts = Transaction::query()
            ->where('purchased_at', '>=', $start->toDateString())
            ->get(['purchased_at'])
            ->groupBy(fn (Transaction $transaction) => $transaction->purchased_at->format('Y-m'))
            ->map->count();

        $trend = [];

        for ($i = 0; $i < 12; $i++) {
            $month = $start->copy()->addMonths($i);
            $key = $month->format('Y-m');

            $trend[] = [
                'month' => $key,
                'label' => $month->format('M Y'),
                'count' => $counts->get($key, 0),
            ];
        }

        return $trend;
    }
}
