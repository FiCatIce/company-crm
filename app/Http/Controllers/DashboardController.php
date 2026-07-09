<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Interaction;
use App\Models\Reseller;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Roles allowed to view the dashboard (same set that may manage the CRM).
     *
     * @var list<string>
     */
    private const VIEW_ROLES = ['admin', 'supervisor', 'cs'];

    public function __invoke(Request $request): Response
    {
        abort_unless($request->user()->hasAnyRole(self::VIEW_ROLES), 403);

        $warranty = $this->warrantyBreakdown();

        return Inertia::render('Dashboard', [
            'stats' => [
                'customers' => Customer::count(),
                'customersThisMonth' => Customer::where('created_at', '>=', now()->startOfMonth())->count(),
                'transactions' => Transaction::count(),
                'transactionsThisMonth' => Transaction::where('purchased_at', '>=', now()->startOfMonth()->toDateString())->count(),
                'activeWarranties' => $warranty['active'],
                'activeResellers' => Reseller::has('customers')->orHas('transactions')->count(),
            ],
            'trend' => $this->transactionTrend(),
            'warrantyBreakdown' => $warranty,
            'recentTransactions' => $this->recentTransactions(),
            'expiringSoon' => $this->expiringSoon(),
            'topResellers' => $this->topResellers(),
            'me' => $this->personalStats((int) $request->user()->id),
        ]);
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
