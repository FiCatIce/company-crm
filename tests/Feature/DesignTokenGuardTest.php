<?php

/*
 * R-A token-layer structural guards. DesignSystemTest pins the resolved
 * VALUES; this file guards the two-layer ARCHITECTURE itself:
 *
 *   1. hex/rgb literals live only on --p-* palette lines,
 *   2. every token resolves through the palette,
 *   3. the Tailwind @theme mapping stays var()-based (never baked into the
 *      build, so tenants can override at runtime),
 *   4. no frontend source reaches into the raw palette,
 *   5. the rewritten primitives carry no raw colors,
 *   6. fonts are vendored — no CDN anywhere in the pipeline.
 *
 * Static source assertions (the project has no JS/CSS test runner), same
 * pattern as ScopeGuardTest / LabelGuardTest.
 */

/** Recursively collect files with the given extensions under a directory. */
function designGuardFiles(string $dir, array $extensions): array
{
    $found = [];

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
    );

    foreach ($files as $file) {
        if (in_array($file->getExtension(), $extensions, true)) {
            $found[] = $file->getPathname();
        }
    }

    return $found;
}

/** The primitives rewritten in R-A — the raw-color ban applies to these. */
function designGuardPrimitives(): array
{
    $files = [
        resource_path('js/components/InputError.vue'),
        resource_path('js/components/TextLink.vue'),
    ];

    foreach (['button', 'input', 'label', 'checkbox', 'badge', 'dialog', 'dropdown-menu', 'status-dot'] as $dir) {
        $files = array_merge(
            $files,
            designGuardFiles(resource_path("js/components/ui/{$dir}"), ['vue', 'ts']),
        );
    }

    return $files;
}

test('hex and rgb literals live only on layer-1 palette lines', function () {
    $offenders = [];

    foreach (designTokenMap() as $token => $value) {
        if (str_starts_with($token, '--p-')) {
            continue;
        }

        // Semantic tokens may reference vars or carry non-color literals
        // (e.g. --radius) — never a color literal of their own.
        if (preg_match('/#[0-9a-fA-F]{3,8}\b|rgba?\(|hsla?\(/', $value)) {
            $offenders[$token] = $value;
        }
    }

    expect($offenders)->toBe([]);
});

test('every token in :root resolves to a concrete value', function () {
    foreach (array_keys(designTokenMap()) as $token) {
        $resolved = resolveDesignToken($token); // throws on dangling/cyclic refs

        expect($resolved)->not->toBeEmpty()
            ->and(str_starts_with($resolved, 'var('))->toBeFalse();
    }
});

test('the tailwind color mapping reads vars, not baked values', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    preg_match('/@theme inline\s*\{(.*?)\}/s', $css, $m);
    expect($m)->not->toBeEmpty();

    preg_match_all('/(--color-[\w-]+)\s*:\s*([^;]+);/', $m[1], $decls, PREG_SET_ORDER);
    expect($decls)->not->toBeEmpty();

    // Every color utility must map to a var() so tenant overrides work at
    // runtime — a baked value would be frozen into the build.
    $offenders = [];

    foreach ($decls as [, $token, $value]) {
        if (! preg_match('/^var\(--[\w-]+\)$/', trim($value))) {
            $offenders[$token] = $value;
        }
    }

    expect($offenders)->toBe([]);
});

test('no frontend source reaches into the raw palette', function () {
    $offenders = [];

    $files = array_merge(
        designGuardFiles(resource_path('js'), ['vue', 'ts', 'js']),
        designGuardFiles(resource_path('views'), ['php']),
    );

    foreach ($files as $file) {
        if (str_contains(file_get_contents($file), '--p-')) {
            $offenders[] = $file;
        }
    }

    expect($offenders)->toBe([]);
});

test('rewritten primitives carry no raw hex or tailwind palette colors', function () {
    $rawPalette = '/(?:^|[\s\'"`:!])(?:bg|text|border|ring|outline|fill|stroke|decoration|divide|from|via|to|caret|accent|shadow)-'
        .'(?:slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose|black|white)'
        .'(?:-\d{2,3})?(?![\w-])/';

    $offenders = [];

    foreach (designGuardPrimitives() as $file) {
        $source = file_get_contents($file);

        if (preg_match('/#[0-9a-fA-F]{3,8}\b/', $source) || preg_match($rawPalette, $source)) {
            $offenders[] = $file;
        }
    }

    expect($offenders)->toBe([]);
});

test('the whole frontend is free of raw colors outside the allowlist', function () {
    /*
     * R-D endgame guard — the sweep is only done while it STAYS done. Every
     * .vue/.ts under resources/js must read the token layer; raw Tailwind
     * palette classes and hex literals are banned.
     *
     * Explicit allowlist (one entry, with reason):
     * - pages/Welcome.vue — the Laravel starter guest landing: renders
     *   outside the app shell with its own self-contained illustration art
     *   direction (hex-tinted SVG art). It is not part of the themeable
     *   product UI and will be replaced by a real landing page, not themed.
     */
    $allowlist = [
        str_replace('\\', '/', resource_path('js/pages/Welcome.vue')),
    ];

    $rawPalette = '/(?:^|[\s\'"`:!])(?:bg|text|border|ring|outline|fill|stroke|decoration|divide|from|via|to|caret|accent|shadow)-'
        .'(?:slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose|black|white)'
        .'(?:-\d{2,3})?(?![\w-])/';
    $rawHex = '/#[0-9a-fA-F]{3,8}\b/';

    $offenders = [];

    foreach (designGuardFiles(resource_path('js'), ['vue', 'ts']) as $file) {
        $normalized = str_replace('\\', '/', $file);

        if (in_array($normalized, $allowlist, true)) {
            continue;
        }

        $source = file_get_contents($file);

        if (preg_match($rawPalette, $source) || preg_match($rawHex, $source)) {
            $offenders[] = $normalized;
        }
    }

    expect($offenders)->toBe([]);
});

test('the status primitives read only the locked state tokens', function () {
    expect(file_get_contents(resource_path('js/components/ui/status-dot/index.ts')))
        ->toContain('bg-state-ok')
        ->toContain('bg-state-warn')
        ->toContain('bg-state-busy')
        ->toContain('bg-state-idle')
        ->not->toContain('bg-primary')
        ->not->toContain('bg-accent');

    expect(file_get_contents(resource_path('js/components/ui/badge/index.ts')))
        ->toContain('bg-state-ok-soft')
        ->toContain('bg-state-warn-soft')
        ->toContain('bg-state-busy-soft')
        ->toContain('bg-state-idle-soft');
});

test('fonts are vendored with no CDN anywhere in the pipeline', function () {
    // The woff2 files themselves live in the repo (on-prem builds are offline).
    expect(file_exists(resource_path('fonts/ibm-plex-sans-latin.woff2')))->toBeTrue()
        ->and(file_exists(resource_path('fonts/ibm-plex-mono-latin-400.woff2')))->toBeTrue()
        ->and(file_exists(resource_path('fonts/ibm-plex-mono-latin-500.woff2')))->toBeTrue();

    // fonts.css declares both families from relative, repo-local sources.
    $fonts = file_get_contents(resource_path('css/fonts.css'));
    expect($fonts)
        ->toContain("font-family: 'IBM Plex Sans'")
        ->toContain("font-family: 'IBM Plex Mono'")
        ->not->toContain('http');

    // The build-time font provider (CDN fetch) and its Blade directive are gone.
    expect(file_get_contents(base_path('vite.config.ts')))
        ->not->toContain('laravel-vite-plugin/fonts');
    expect(file_get_contents(resource_path('views/app.blade.php')))
        ->not->toContain('@fonts');

    // No trace of the old CDN-served family anywhere in resources.
    $offenders = [];
    $files = array_merge(
        designGuardFiles(resource_path('js'), ['vue', 'ts', 'js']),
        designGuardFiles(resource_path('css'), ['css']),
        designGuardFiles(resource_path('views'), ['php']),
    );

    foreach ($files as $file) {
        $source = file_get_contents($file);

        if (preg_match('/Instrument Sans|bunny\.net|fonts\.googleapis|gstatic/', $source)) {
            $offenders[] = $file;
        }
    }

    expect($offenders)->toBe([]);

    // The mono family is wired into the Tailwind theme for phone numbers,
    // durations, timestamps, IDs and money.
    expect(file_get_contents(resource_path('css/app.css')))
        ->toContain("--font-mono:")
        ->toContain("'IBM Plex Mono'");
});
