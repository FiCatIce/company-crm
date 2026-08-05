<?php

/*
 * R-B persistent-shell guards. The sidebar (and the dormant CallDock that
 * CTI 4c will light up) must survive Inertia navigations, which holds only
 * while exactly ONE AppLayout instance exists — mounted by the app.ts layout
 * callback. A page that wraps <AppLayout> itself double-mounts the sidebar,
 * and nothing in type-checking catches that; these static guards do.
 */

/** All page components under resources/js/pages. */
function shellGuardPages(): array
{
    $found = [];

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            resource_path('js/pages'),
            FilesystemIterator::SKIP_DOTS,
        ),
    );

    foreach ($files as $file) {
        if ($file->getExtension() === 'vue') {
            $found[] = $file->getPathname();
        }
    }

    return $found;
}

test('no page component imports a layout itself', function () {
    $offenders = [];

    foreach (shellGuardPages() as $page) {
        if (str_contains(file_get_contents($page), '@/layouts/')) {
            $offenders[] = $page;
        }
    }

    // Layouts come exclusively from the app.ts callback; a page-level wrapper
    // means a second sidebar instance the moment it ships.
    expect($offenders)->toBe([]);
});

test('the app bootstrap mounts one persistent shell per page group', function () {
    $app = file_get_contents(resource_path('js/app.ts'));

    expect($app)
        ->toContain("case name === 'Welcome':")
        ->toContain("case name.startsWith('auth/'):")
        ->toContain('[AppLayout, SettingsLayout]')
        ->toContain('return AppLayout;');
});

test('every content page registers breadcrumbs through the shared store', function () {
    $offenders = [];

    foreach (shellGuardPages() as $page) {
        $relative = str_replace('\\', '/', $page);

        // Guests (Welcome, auth/*) render outside the shell; settings pages
        // inherit the settings sub-layout heading instead of a trail.
        if (preg_match('~/pages/(auth|settings)/|/pages/Welcome\.vue$~', $relative)) {
            continue;
        }

        if (! str_contains(file_get_contents($page), 'setBreadcrumbs(')) {
            $offenders[] = $page;
        }
    }

    expect($offenders)->toBe([]);
});

test('the call dock is pinned in the shell above the page content', function () {
    $layout = file_get_contents(resource_path('js/layouts/app/AppSidebarLayout.vue'));

    expect($layout)
        ->toContain("import CallDock from '@/components/CallDock.vue'")
        ->toContain('<CallDock />');

    // Above the content: the dock must render before the page slot.
    expect(strpos($layout, '<CallDock />'))->toBeLessThan(strpos($layout, '<slot />'));

    // Dormant until CTI 4c: it renders nothing while no call is active.
    expect(file_get_contents(resource_path('js/components/CallDock.vue')))
        ->toContain('data-slot="call-dock"')
        ->toContain('v-if="activeCall"');
});

test('page padding lives in the shell content wrapper, not in pages', function () {
    expect(file_get_contents(resource_path('js/layouts/app/AppSidebarLayout.vue')))
        ->toContain('p-4 lg:p-6');

    $offenders = [];

    foreach (shellGuardPages() as $page) {
        if (str_contains(file_get_contents($page), 'p-4 sm:p-6')) {
            $offenders[] = $page;
        }
    }

    expect($offenders)->toBe([]);
});

test('the dead shell variants stay deleted', function () {
    expect(file_exists(resource_path('js/layouts/app/AppHeaderLayout.vue')))->toBeFalse()
        ->and(file_exists(resource_path('js/components/AppHeader.vue')))->toBeFalse()
        ->and(file_exists(resource_path('js/layouts/auth/AuthCardLayout.vue')))->toBeFalse()
        ->and(file_exists(resource_path('js/layouts/auth/AuthSplitLayout.vue')))->toBeFalse();
});

test('the breadcrumb store is keyed by the displayed page component', function () {
    // Stale-crumb protection: the layout may only read an entry registered by
    // the component currently on screen, so unregistered pages show an empty
    // trail instead of the previous page's leftovers.
    expect(file_get_contents(resource_path('js/composables/useBreadcrumbs.ts')))
        ->toContain('usePage().component')
        ->toContain('registration.value.component === page.component');
});
