<?php

/*
 * Guards the modern-minimal corporate design system encoded as Tailwind 4
 * CSS variables in resources/css/app.css. Since the R-A redesign the token
 * layer is two-tier (--p-* raw palette → semantic tokens), so these
 * assertions resolve var() chains and pin the RESOLVED values: the palette
 * still can't silently drift, however the tokens are spelled.
 * DesignTokenGuardTest guards the layering rules themselves.
 */

function appCss(): string
{
    return file_get_contents(resource_path('css/app.css'));
}

test('the corporate blue accent and exact hover are defined', function () {
    expect(resolveDesignToken('--accent'))->toBe('#2563eb') // blue-600
        ->and(resolveDesignToken('--accent-hover'))->toBe('#1d4ed8') // blue-700
        ->and(resolveDesignToken('--primary'))->toBe('#2563eb') // derived from accent
        ->and(resolveDesignToken('--primary-hover'))->toBe('#1d4ed8')
        ->and(resolveDesignToken('--ring'))->toBe('#2563eb'); // focus ring = accent
});

test('the primary tokens are derived from the themeable accent', function () {
    $map = designTokenMap();

    // A tenant override of the --accent group must reach every primary
    // action surface: the derivation has to stay var()-based, not copied.
    expect($map['--primary'])->toBe('var(--accent)')
        ->and($map['--primary-hover'])->toBe('var(--accent-hover)')
        ->and($map['--ring'])->toBe('var(--accent)')
        ->and($map['--sidebar-primary'])->toBe('var(--accent)')
        ->and($map['--sidebar-accent'])->toBe('var(--accent-soft)');
});

test('the canvas is white with off-white surfaces', function () {
    expect(resolveDesignToken('--background'))->toBe('#ffffff')
        ->and(resolveDesignToken('--card'))->toBe('#f8fafc') // off-white surface
        ->and(resolveDesignToken('--sidebar-background'))->toBe('#f8fafc');
});

test('neutral slate text and thin borders are defined', function () {
    expect(resolveDesignToken('--foreground'))->toBe('#0f172a') // slate-900 primary text
        ->and(resolveDesignToken('--muted-foreground'))->toBe('#64748b') // slate-500 secondary text
        ->and(resolveDesignToken('--border'))->toBe('#e2e8f0'); // slate-200 thin borders
});

test('the status vocabulary is defined and never accent-derived', function () {
    $map = designTokenMap();

    expect(resolveDesignToken('--state-ok'))->toBe('#16a34a') // green-600
        ->and(resolveDesignToken('--state-warn'))->toBe('#d97706') // amber-600
        ->and(resolveDesignToken('--state-busy'))->toBe('#dc2626') // red-600
        ->and(resolveDesignToken('--state-idle'))->toBe('#64748b'); // slate-500

    // Status colors are product vocabulary, not branding: none of them may
    // reference the themeable accent group.
    foreach ($map as $token => $value) {
        if (str_starts_with($token, '--state-') || $token === '--destructive') {
            expect($value)->not->toContain('--accent');
        }
    }
});

test('an 8px radius and soft shadow tokens are defined', function () {
    expect(resolveDesignToken('--radius'))->toBe('0.5rem'); // 8px

    expect(appCss())
        ->toContain('--shadow-sm:')
        ->toContain('rgb(15 23 42 /'); // slate-tinted soft shadows
});

test('active states use a blue tint in the sidebar', function () {
    expect(resolveDesignToken('--sidebar-accent'))->toBe('#eff6ff') // blue-50 active/hover
        ->and(resolveDesignToken('--sidebar-accent-foreground'))->toBe('#1d4ed8'); // blue-700
});

test('the theme is light-only with no dark variant', function () {
    $css = appCss();

    // The app is forced light: the dark token block and the dark custom
    // variant were removed, so no OS/browser theme can flip the palette.
    expect($css)
        ->not->toContain('.dark')
        ->not->toContain('@custom-variant dark')
        ->not->toContain('--primary: #3b82f6'); // the old brighter dark accent
});

test('the grayscale starter-kit palette is gone', function () {
    expect(appCss())
        ->not->toContain('--primary: hsl(0 0% 9%)') // old near-black primary
        ->not->toContain('--ring: hsl(0 0% 3.9%)'); // old near-black ring
});

test('the primary button uses the corporate hover token', function () {
    $button = file_get_contents(resource_path('js/components/ui/button/index.ts'));

    expect($button)
        ->toContain('hover:bg-primary-hover')
        ->not->toContain('hover:bg-primary/90');
});
