<?php

/*
 * R-C shared-table guards: ui/table is the ONLY source of table markup, so
 * density and column conventions can't fork per page again (the pre-R-C state
 * was 28 copies of the same <th> class string across 7 index pages).
 */

/** All frontend component/page sources outside the ui kit's table folder. */
function tableGuardSources(): array
{
    $found = [];

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            resource_path('js'),
            FilesystemIterator::SKIP_DOTS,
        ),
    );

    foreach ($files as $file) {
        if ($file->getExtension() !== 'vue') {
            continue;
        }

        if (str_contains(str_replace('\\', '/', $file->getPathname()), '/components/ui/table/')) {
            continue;
        }

        $found[] = $file->getPathname();
    }

    return $found;
}

test('raw table markup exists only inside the ui/table primitive', function () {
    $offenders = [];

    foreach (tableGuardSources() as $file) {
        $source = file_get_contents($file);

        if (preg_match('/<(table|thead|tbody|th|td)[\s>]/', $source)) {
            $offenders[] = $file;
        }
    }

    expect($offenders)->toBe([]);
});

test('the primitive encodes the corporate density spec', function () {
    $head = file_get_contents(resource_path('js/components/ui/table/TableHead.vue'));
    $cell = file_get_contents(resource_path('js/components/ui/table/TableCell.vue'));
    $table = file_get_contents(resource_path('js/components/ui/table/Table.vue'));
    $row = file_get_contents(resource_path('js/components/ui/table/TableRow.vue'));

    // 12px uppercase headers at py-2.5, 13px body at py-2, 16px gutters.
    expect($head)->toContain('px-4 py-2.5')->toContain('text-xs')->toContain('uppercase');
    expect($cell)->toContain('px-4 py-2');
    expect($table)->toContain('text-[13px]');
    expect($row)->toContain('hover:bg-accent/50'); // shared row hover lives here now

    // Code/number columns are explicit variants, never hand-rolled classes —
    // and code values never wrap.
    expect($cell)
        ->toContain("kind === 'mono' && 'font-mono tabular-nums whitespace-nowrap'")
        ->toContain("kind === 'money' && 'text-right font-mono tabular-nums whitespace-nowrap'");
});

test('every index page and the recent-transactions card use the shared table', function () {
    $consumers = [
        'js/pages/Customers/Index.vue',
        'js/pages/Products/Index.vue',
        'js/pages/Transactions/Index.vue',
        'js/pages/Users/Index.vue',
        'js/pages/TeamMembers/Index.vue',
        'js/pages/ServiceTickets/Index.vue',
        'js/pages/Roles/Index.vue',
        'js/components/RecentTransactionsCard.vue',
    ];

    foreach ($consumers as $consumer) {
        expect(file_get_contents(resource_path($consumer)))
            ->toContain("from '@/components/ui/table'");
    }
});

test('money and code columns go through the explicit cell variants', function () {
    // The columns that motivated the variants stay wired to them.
    expect(file_get_contents(resource_path('js/pages/Transactions/Index.vue')))
        ->toContain('kind="money"'); // Nilai (IDR)
    expect(file_get_contents(resource_path('js/pages/Customers/Index.vue')))
        ->toContain('kind="mono"'); // Telepon
    expect(file_get_contents(resource_path('js/pages/Users/Index.vue')))
        ->toContain('kind="mono"'); // Extension
});
