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

test('the teal signal accent and exact hover are defined', function () {
    expect(resolveDesignToken('--accent'))->toBe('#0f6e8c') // signal teal
        ->and(resolveDesignToken('--accent-hover'))->toBe('#0d5e77') // signal 85% + black
        ->and(resolveDesignToken('--primary'))->toBe('#0f6e8c') // derived from accent
        ->and(resolveDesignToken('--primary-hover'))->toBe('#0d5e77')
        ->and(resolveDesignToken('--ring'))->toBe('#0f6e8c'); // focus ring = accent
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

test('the canvas is field-gray with paper surfaces', function () {
    expect(resolveDesignToken('--background'))->toBe('#f2f4f7') // field canvas
        ->and(resolveDesignToken('--card'))->toBe('#ffffff') // paper surface
        ->and(resolveDesignToken('--sidebar-background'))->toBe('#ffffff');
});

test('neutral ink text and thin borders are defined', function () {
    expect(resolveDesignToken('--foreground'))->toBe('#14181f') // ink primary text
        ->and(resolveDesignToken('--muted-foreground'))->toBe('#5a6472') // slate secondary text
        ->and(resolveDesignToken('--border'))->toBe('#dde1e7'); // line thin borders
});

test('the status vocabulary is defined and never accent-derived', function () {
    $map = designTokenMap();

    expect(resolveDesignToken('--state-ok'))->toBe('#1e9e6a')
        ->and(resolveDesignToken('--state-warn'))->toBe('#d98a0b')
        ->and(resolveDesignToken('--state-busy'))->toBe('#c4402f')
        ->and(resolveDesignToken('--state-idle'))->toBe('#9aa3ae');

    // The derived badge steps are pinned too: soft is the 10-12% tint the
    // badges sit on, strong is the text step that has to clear WCAG AA on
    // that tint (the raw hues don't — that's the whole point of strong).
    expect(resolveDesignToken('--state-ok-soft'))->toBe('#e9f5f0')
        ->and(resolveDesignToken('--state-ok-strong'))->toBe('#156f4a')
        ->and(resolveDesignToken('--state-warn-soft'))->toBe('#fbf3e7')
        ->and(resolveDesignToken('--state-warn-strong'))->toBe('#986108')
        ->and(resolveDesignToken('--state-busy-soft'))->toBe('#f9ecea')
        ->and(resolveDesignToken('--state-busy-strong'))->toBe('#892d21')
        ->and(resolveDesignToken('--state-idle-soft'))->toBe('#f3f4f5')
        ->and(resolveDesignToken('--state-idle-strong'))->toBe('#363c44');

    // Status colors are product vocabulary, not branding: none of them may
    // reference the themeable accent group.
    foreach ($map as $token => $value) {
        if (str_starts_with($token, '--state-') || $token === '--destructive') {
            expect($value)->not->toContain('--accent');
        }
    }
});

test('a 4px radius and soft shadow tokens are defined', function () {
    expect(resolveDesignToken('--radius'))->toBe('0.25rem'); // 4px

    expect(appCss())
        ->toContain('--shadow-sm:')
        ->toContain('rgb(15 23 42 /'); // slate-tinted soft shadows
});

test('active states use a signal tint in the sidebar', function () {
    expect(resolveDesignToken('--sidebar-accent'))->toBe('#e7f1f4') // signal-soft active/hover
        ->and(resolveDesignToken('--sidebar-accent-foreground'))->toBe('#0d5e77'); // signal-strong
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
