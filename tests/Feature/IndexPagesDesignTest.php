<?php

use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Guards that Products / Transactions / Resellers index pages share the
 * corporate card/table pattern established on the Customers index (page
 * header, off-white card, uppercase muted headers, subtle blue row hover)
 * and no longer carry the old starter-kit table styling. Assertions use
 * single Tailwind class tokens so they survive prettier class sorting.
 */

function pageSource(string $relative): string
{
    return file_get_contents(resource_path("js/pages/{$relative}"));
}

test('the products index adopts the corporate card/table pattern', function () {
    expect(pageSource('Products/Index.vue'))
        ->toContain('text-2xl') // page header hierarchy
        ->toContain('bg-card') // cohesive content card
        ->toContain('uppercase') // uppercase muted headers
        ->toContain('hover:bg-accent/50') // subtle blue row hover
        ->toContain('border-t') // card pagination footer
        ->not->toContain('bg-muted/50') // old filled thead
        ->not->toContain('hover:bg-muted/40') // old neutral row hover
        ->not->toContain('text-red-600'); // old delete color
});

test('the transactions index adopts the pattern and keeps the warranty badge', function () {
    expect(pageSource('Transactions/Index.vue'))
        ->toContain('text-2xl')
        ->toContain('uppercase')
        ->toContain('hover:bg-accent/50')
        ->toContain('border-t')
        ->toContain('WarrantyBadge') // domain logic preserved
        ->not->toContain('bg-muted/50')
        ->not->toContain('hover:bg-muted/40')
        ->not->toContain('text-red-600');
});

test('the resellers index adopts the card pattern and keeps the tree view', function () {
    expect(pageSource('Resellers/Index.vue'))
        ->toContain('text-2xl')
        ->toContain('bg-card')
        ->toContain('uppercase')
        ->toContain('ResellerTreeNode'); // recursive tree preserved
});

test('the reseller tree node matches the pattern styling', function () {
    $node = file_get_contents(
        resource_path('js/components/ResellerTreeNode.vue'),
    );

    expect($node)
        ->toContain('hover:bg-accent/50') // subtle blue row hover
        ->toContain('text-destructive') // themed delete action
        ->not->toContain('hover:bg-muted/50') // old neutral hover
        ->not->toContain('text-red-600'); // old delete color
});

test('the index stat card shows a muted icon in a soft rounded container', function () {
    $card = file_get_contents(resource_path('js/components/IndexStatCard.vue'));

    expect($card)
        ->toContain('bg-muted') // soft neutral icon container (blue stays accent-only)
        ->toContain('text-muted-foreground') // muted icon + label
        ->toContain('rounded-lg') // soft rounded container
        ->toContain('items-start') // icon tucked into the top-right corner
        ->toContain('tabular-nums'); // large, aligned metric number
});

test('every index page carries the summary stat card row and a context sub-header', function () {
    foreach ([
        'Customers/Index.vue',
        'Products/Index.vue',
        'Resellers/Index.vue',
        'Transactions/Index.vue',
    ] as $page) {
        expect(pageSource($page))
            ->toContain('IndexStatCard') // reused summary card component
            ->toContain('lg:grid-cols-3') // three-up responsive stat row
            ->toContain('Kelola'); // thin descriptive context sub-header line
    }
});

test('the redesigned index pages still render their data contract', function () {
    $this->seed(RoleSeeder::class);
    $this->actingAs(userWithRole('admin'));
    $this->withoutVite();

    $this->get('/products')
        ->assertOk()
        ->assertInertia(
            fn (Assert $p) => $p
                ->component('Products/Index')
                ->has('products.data')
                ->has('can'),
        );

    $this->get('/transactions')
        ->assertOk()
        ->assertInertia(
            fn (Assert $p) => $p
                ->component('Transactions/Index')
                ->has('transactions.data')
                ->has('can'),
        );

    $this->get('/resellers')
        ->assertOk()
        ->assertInertia(
            fn (Assert $p) => $p
                ->component('Resellers/Index')
                ->has('tree')
                ->has('can'),
        );
});
