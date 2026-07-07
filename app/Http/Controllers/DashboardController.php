<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Reseller;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        return Inertia::render('Dashboard', [
            'stats' => [
                'customers' => Customer::count(),
                'transactions' => Transaction::count(),
                'productsUnderWarranty' => $this->transactionsUnderWarranty(),
                'activeResellers' => Reseller::has('customers')->orHas('transactions')->count(),
            ],
            'trend' => $this->transactionTrend(),
        ]);
    }

    /**
     * Count transactions whose product warranty is still active (uses the model accessor).
     */
    private function transactionsUnderWarranty(): int
    {
        return Transaction::query()
            ->with('product:id,warranty_months')
            ->get(['id', 'product_id', 'purchased_at'])
            ->filter(fn (Transaction $transaction) => $transaction->is_under_warranty)
            ->count();
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
