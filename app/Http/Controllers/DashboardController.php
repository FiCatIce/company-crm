<?php

namespace App\Http\Controllers;

use App\Enums\CustomerSource;
use App\Enums\CustomerStatus;
use App\Enums\InteractionType;
use App\Enums\PermissionName;
use App\Models\Customer;
use App\Models\Interaction;
use App\Models\Reseller;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
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
        // A "scoped" viewer sees only their own book (Sales: view.own without
        // view.all). Org-wide totals are GLOBAL data they must not read, so they
        // get the personal "Ringkasan Saya" band only — never the aggregate band.
        $isScopedViewer = $canViewOwnCustomers && ! $canViewAllCustomers;
        $canSeeOrgStats = $canSeeAggregate && ! $isScopedViewer;

        // Each aggregate is gated by the permission for its data domain: transaction
        // counts/trend need transaction.view.all (so admin, which holds neither,
        // never sees "Total Transaksi"); warranty needs customer.view.all; the
        // reseller count needs reseller.view; money needs revenue.view.
        $canViewAllTransactions = $user->can(PermissionName::TransactionViewAll->value);
        $canViewResellers = $user->can(PermissionName::ResellerView->value);
        $canSeeRevenue = $user->can(PermissionName::RevenueView->value);
        // The call feed IS scoped per-viewer (Interaction::visibleTo), so anyone
        // who may see any calls gets it — Sales just sees their own customers'.
        $canViewCalls = $user->can(PermissionName::InteractionViewAll->value)
            || $user->can(PermissionName::InteractionViewOwn->value);

        $props = [
            'me' => $this->personalStats((int) $user->id),
        ];

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

            if ($canViewResellers) {
                $stats['activeResellers'] = Reseller::has('customers')->orHas('transactions')->count();
            }

            if ($canSeeRevenue) {
                $stats = [...$stats, ...$this->revenueStats()];
            }

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
            $props['topResellers'] = $this->topResellers();
        }

        // Call feed — scoped per viewer: all for managers/CS/maintenance/admin,
        // only their own customers' calls for Sales.
        if ($canViewCalls) {
            $props['recentCalls'] = $this->recentCalls($user);
        }

        if ($canSeeRevenue) {
            $props['topResellersByRevenue'] = $this->topResellersByRevenue();
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
     * Revenue totals via DB SUM (nulls are ignored by SQL SUM — no PHP scan).
     * All-time plus this/last month so the UI can show a month-over-month delta.
     *
     * @return array{revenue: float, revenueThisMonth: float, revenueLastMonth: float}
     */
    private function revenueStats(): array
    {
        $thisMonthStart = now()->startOfMonth()->toDateString();
        $lastMonthStart = now()->subMonthNoOverflow()->startOfMonth()->toDateString();
        $lastMonthEnd = now()->startOfMonth()->subDay()->toDateString();

        return [
            'revenue' => (float) Transaction::sum('amount'),
            'revenueThisMonth' => (float) Transaction::where('purchased_at', '>=', $thisMonthStart)->sum('amount'),
            'revenueLastMonth' => (float) Transaction::whereBetween('purchased_at', [$lastMonthStart, $lastMonthEnd])->sum('amount'),
        ];
    }

    /**
     * The five resellers with the highest total sales value (revenue > 0). The
     * per-reseller SUM is computed in SQL via withSum, not by scanning in PHP.
     *
     * @return array<int, array{id: int, name: string, revenue: float}>
     */
    private function topResellersByRevenue(): array
    {
        return Reseller::query()
            ->withSum('transactions as revenue', 'amount')
            ->orderByDesc('revenue')
            ->take(5)
            ->get()
            ->filter(fn (Reseller $reseller) => (float) $reseller->getAttribute('revenue') > 0)
            ->map(fn (Reseller $reseller) => [
                'id' => $reseller->id,
                'name' => $reseller->name,
                'revenue' => (float) $reseller->getAttribute('revenue'),
            ])
            ->values()
            ->all();
    }

    /**
     * Per-agent block scoped to the signed-in user (attribution/authorship only —
     * every role still sees all the org-wide widgets above). A user with no
     * assigned customers or interactions simply reads zeros / empty lists.
     *
     * @return array{myCustomers: int, myInteractionsToday: int, myExpiringWarranties: int, myRecentInteractions: array<int, array<string, mixed>>}
     */
    private function personalStats(int $userId): array
    {
        $threshold = now()->startOfDay()->addDays(30);

        // Reuses the expiringSoon rule (active warranty ending within 30 days),
        // scoped to transactions of customers this user owns.
        // unscoped-ok: personal band — scoped to the caller's own assigned customers.
        $myExpiringWarranties = Transaction::query()
            ->whereHas('customer', fn ($query) => $query->where('assigned_to', $userId))
            ->with('product:id,warranty_months')
            ->get(['id', 'customer_id', 'product_id', 'purchased_at'])
            ->filter(fn (Transaction $transaction) => $transaction->product->warranty_months > 0
                && $transaction->is_under_warranty
                && $transaction->warranty_expires_at->lte($threshold))
            ->count();

        return [
            'myCustomers' => Customer::where('assigned_to', $userId)->count(),
            'myInteractionsToday' => Interaction::where('user_id', $userId)
                ->whereDate('occurred_at', today())
                ->count(),
            'myExpiringWarranties' => $myExpiringWarranties,
            'myRecentInteractions' => $this->myRecentInteractions($userId),
        ];
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
            ->with(['customer:id,name', 'product:id,name,warranty_months', 'reseller:id,name'])
            ->latest('purchased_at')
            ->latest('id')
            ->take(6)
            ->get()
            ->map(fn (Transaction $transaction) => [
                'id' => $transaction->id,
                'customer' => $transaction->customer?->name,
                'product' => $transaction->product?->name,
                'reseller' => $transaction->reseller?->name,
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
     * The five resellers with the most customers (only those with at least one).
     *
     * @return array<int, array{id: int, name: string, customers_count: int}>
     */
    private function topResellers(): array
    {
        return Reseller::query()
            ->has('customers')
            ->withCount('customers')
            ->orderByDesc('customers_count')
            ->orderBy('name')
            ->take(5)
            ->get(['id', 'name'])
            ->map(fn (Reseller $reseller) => [
                'id' => $reseller->id,
                'name' => $reseller->name,
                'customers_count' => (int) $reseller->customers_count,
            ])
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
