<?php

use Database\Seeders\RoleSeeder;

/*
 * Guards that the app is permanently light-themed: no dark: utility classes,
 * no .dark CSS variant, no OS/browser preference detection, and no appearance
 * switcher. These are static source assertions (there is no JS/CSS runner)
 * plus one HTTP check that the removed appearance route is truly gone.
 */

test('no dark: utility classes remain anywhere in the frontend source', function () {
    $offenders = [];

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            resource_path('js'),
            FilesystemIterator::SKIP_DOTS,
        ),
    );

    foreach ($files as $file) {
        if (! in_array($file->getExtension(), ['vue', 'ts', 'js'], true)) {
            continue;
        }

        if (str_contains(file_get_contents($file->getPathname()), 'dark:')) {
            $offenders[] = $file->getPathname();
        }
    }

    expect($offenders)->toBe([]);
});

test('the app shell has no dark class or OS preference detection', function () {
    $blade = file_get_contents(resource_path('views/app.blade.php'));

    expect($blade)
        ->not->toContain('prefers-color-scheme') // no OS/browser detection
        ->not->toContain("classList.add('dark')") // no runtime dark toggle
        ->not->toContain('html.dark') // no dark background rule
        ->not->toContain('appearance'); // no appearance cookie plumbing
});

test('the bootstrap does not initialize or switch a theme', function () {
    expect(file_get_contents(resource_path('js/app.ts')))
        ->not->toContain('initializeTheme')
        ->not->toContain('useAppearance');
});

test('the appearance settings route no longer exists', function () {
    $this->seed(RoleSeeder::class);

    $this->actingAs(userWithRole('supervisor'))
        ->get('/settings/appearance')
        ->assertNotFound();
});
