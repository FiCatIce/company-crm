<?php

/*
 * Guards the modern-minimal corporate design system encoded as Tailwind 4
 * CSS variables in resources/css/app.css. These are static assertions on the
 * token layer (the project has no JS/CSS test runner) so the palette can't
 * silently drift back to the grayscale starter-kit look.
 */

function appCss(): string
{
    return file_get_contents(resource_path('css/app.css'));
}

test('the corporate blue accent and exact hover are defined', function () {
    expect(appCss())
        ->toContain('--primary: #2563eb') // blue-600
        ->toContain('--primary-hover: #1d4ed8') // blue-700
        ->toContain('--ring: #2563eb'); // focus ring = accent
});

test('the canvas is white with off-white surfaces', function () {
    expect(appCss())
        ->toContain('--background: #ffffff')
        ->toContain('--card: #f8fafc') // off-white surface
        ->toContain('--sidebar-background: #f8fafc');
});

test('neutral slate text and thin borders are defined', function () {
    expect(appCss())
        ->toContain('--foreground: #0f172a') // slate-900 primary text
        ->toContain('--muted-foreground: #64748b') // slate-500 secondary text
        ->toContain('--border: #e2e8f0'); // slate-200 thin borders
});

test('an 8px radius and soft shadow tokens are defined', function () {
    expect(appCss())
        ->toContain('--radius: 0.5rem') // 8px
        ->toContain('--shadow-sm:')
        ->toContain('rgb(15 23 42 /'); // slate-tinted soft shadows
});

test('active states use a blue tint in the sidebar', function () {
    expect(appCss())
        ->toContain('--sidebar-accent: #eff6ff') // blue-50 active/hover
        ->toContain('--sidebar-accent-foreground: #1d4ed8'); // blue-700
});

test('the dark theme keeps the corporate blue accent', function () {
    $css = appCss();

    expect($css)->toContain('.dark {');
    // The dark block brightens the accent to blue-500 for legibility.
    expect($css)->toContain('--primary: #3b82f6');
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
