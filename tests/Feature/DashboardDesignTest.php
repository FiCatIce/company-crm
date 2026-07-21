<?php

use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Phase 2 of the dashboard redesign: the four new widgets and the assembled
 * three-band Dashboard.vue. No JS/CSS runner, so widgets are guarded by source
 * token/markup assertions plus one Inertia render that the page is wired to the
 * full data contract. Light-only is enforced globally by LightThemeTest.
 */

function dashSource(string $relative): string
{
    return file_get_contents(resource_path("js/{$relative}"));
}

test('the warranty donut is a dependency-free conic ring with a legend', function () {
    expect(dashSource('components/WarrantyDonut.vue'))
        ->toContain('conic-gradient') // no charting library
        ->toContain('Status Garansi')
        ->toContain('tabular-nums')
        ->toContain('Belum ada transaksi'); // empty state
});

test('the recent transactions card reuses the warranty badge', function () {
    expect(dashSource('components/RecentTransactionsCard.vue'))
        ->toContain('WarrantyBadge') // domain badge reused, not reimplemented
        ->toContain('Transaksi Terbaru')
        ->toContain('hover:bg-accent/50'); // shared table row hover
});

test('the expiring warranty card uses an amber watchlist pill', function () {
    expect(dashSource('components/ExpiringWarrantyCard.vue'))
        ->toContain('Garansi Segera Berakhir')
        ->toContain('bg-amber-100') // warning colour, distinct from the blue accent
        ->toContain('hari lagi');
});

test('the top sales card ranks with a proportional bar', function () {
    expect(dashSource('components/TopSalesCard.vue'))
        ->toContain('Sales Teratas')
        ->toContain('bg-primary') // blue proportion fill
        ->toContain('bg-muted'); // track
});

test('the dashboard assembles every widget across three bands', function () {
    expect(dashSource('pages/Dashboard.vue'))
        ->toContain('StatCard')
        ->toContain('TransactionTrendChart')
        ->toContain('WarrantyDonut')
        ->toContain('RecentTransactionsCard')
        ->toContain('ExpiringWarrantyCard')
        ->toContain('TopSalesCard')
        ->toContain('lg:col-span-2'); // 2fr / 1fr split
});

test('the dashboard has a personal band wired to the me block', function () {
    expect(dashSource('pages/Dashboard.vue'))
        ->toContain('Ringkasan Saya')
        ->toContain('MyInteractionsCard')
        ->toContain('me.myCustomers');
});

test('the my-interactions card links to the customer 360 and has an empty state', function () {
    expect(dashSource('components/MyInteractionsCard.vue'))
        ->toContain('Interaksi Terakhir Saya')
        ->toContain('CustomerController.show') // quick-continue link
        ->toContain('WidgetEmptyState');
});

test('the dashboard has a revenue band wired to the revenue props', function () {
    expect(dashSource('pages/Dashboard.vue'))
        ->toContain('Total Pendapatan')
        ->toContain('RevenueBySalesCard')
        ->toContain('revenue?.total'); // optional — the band is permission-gated
});

test('the revenue-by-sales card formats IDR with a proportional bar', function () {
    expect(dashSource('components/RevenueBySalesCard.vue'))
        ->toContain('per pendapatan')
        ->toContain('formatIdr') // shared IDR formatter reused
        ->toContain('bg-primary') // blue proportion fill
        ->toContain('WidgetEmptyState');
});

test('the recent calls card links to the 360, reuses call badges, flags leads, and has an empty state', function () {
    expect(dashSource('components/RecentCallsCard.vue'))
        ->toContain('Panggilan Terbaru')
        ->toContain('CustomerController.show') // 360 link
        ->toContain('InteractionTypeIcon') // reused call icon
        ->toContain('InteractionOutcomeBadge') // reused outcome badge
        ->toContain('formatDuration') // shared mm:ss formatter
        ->toContain('Otomatis') // cti source chip
        ->toContain('Prospek baru') // unmatched-lead highlight
        ->toContain('bg-amber-50') // subtle lead row tint
        ->toContain('WidgetEmptyState');
});

test('the dashboard wires the recent calls band', function () {
    expect(dashSource('pages/Dashboard.vue'))
        ->toContain('RecentCallsCard')
        ->toContain('recentCalls');
});

test('the dashboard page is wired to the full data contract', function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();

    $this->actingAs(userWithGlobalView())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->hasAll([
                'stats', 'trend', 'warrantyBreakdown',
                'recentTransactions', 'expiringSoon', 'topSales',
                'revenueBySales', 'salesScope', 'recentCalls', 'me',
            ]));
});

test('every widget falls back to the shared muted-icon empty state', function () {
    expect(dashSource('components/WidgetEmptyState.vue'))
        ->toContain('rounded-full') // soft icon circle
        ->toContain('bg-muted')
        ->toContain('text-muted-foreground');

    $widgets = [
        'components/WarrantyDonut.vue',
        'components/RecentTransactionsCard.vue',
        'components/ExpiringWarrantyCard.vue',
        'components/TopSalesCard.vue',
        'components/TransactionTrendChart.vue',
        'components/RecentCallsCard.vue',
    ];

    foreach ($widgets as $widget) {
        expect(dashSource($widget))->toContain('WidgetEmptyState');
    }
});

test('the dashboard renders calmly with zero data', function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();

    // A fresh install: KPIs read 0, the warranty split is all zeros, and every
    // list is empty — the page must still render (calm, not broken).
    $this->actingAs(userWithGlobalView())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('stats.customers', 0)
            ->where('stats.transactions', 0)
            ->where('stats.customersThisMonth', 0)
            ->where('stats.activeWarranties', 0)
            ->where('revenue.total', 0)
            ->where('revenue.thisMonth', 0)
            ->has('revenueBySales', 0)
            ->where('warrantyBreakdown.active', 0)
            ->where('warrantyBreakdown.expired', 0)
            ->where('warrantyBreakdown.none', 0)
            ->has('recentTransactions', 0)
            ->has('expiringSoon', 0)
            ->has('topSales', 0)
            ->has('recentCalls', 0));
});
