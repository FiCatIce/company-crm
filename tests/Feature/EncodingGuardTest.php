<?php

/*
 * Encoding tripwire — debt from the R-B incident: a PowerShell edit that read
 * UTF-8 (no BOM) sources as ANSI double-encoded every non-ASCII character in
 * 18 pages (— became â€", ≤ became â‰¤), and EVERY gate stayed green — tests,
 * eslint, prettier, vue-tsc and the Vite build are all mojibake-blind; only a
 * browser screenshot caught it. This scan closes that hole: frontend sources
 * must be valid UTF-8 and free of the classic double-encoding byte sequences.
 */

/** Frontend source files: resources/js + resources/css (+ blade views). */
function encodingGuardFiles(): array
{
    $found = [];

    foreach (['js', 'css', 'views'] as $dir) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                resource_path($dir),
                FilesystemIterator::SKIP_DOTS,
            ),
        );

        foreach ($files as $file) {
            if (in_array($file->getExtension(), ['vue', 'ts', 'js', 'css', 'php'], true)) {
                $found[] = $file->getPathname();
            }
        }
    }

    return $found;
}

test('every frontend source file is valid utf-8', function () {
    $offenders = [];

    foreach (encodingGuardFiles() as $file) {
        if (! mb_check_encoding(file_get_contents($file), 'UTF-8')) {
            $offenders[] = $file;
        }
    }

    expect($offenders)->toBe([]);
});

test('no frontend source carries mojibake byte sequences', function () {
    // The UTF-8-read-as-cp1252-then-re-encoded signatures: â€ covers the
    // whole punctuation block (— – " " … ≈ â€"/â€"/â€œ/â€¦), Ã opens the
    // accented-letter block (é → Ã©, ü → Ã¼), â‰ the math operators
    // (≤ → â‰¤), Â the stray-nbsp artifact, and EF BF BD is U+FFFD.
    $signatures = ['â€', 'â‰', 'Ã©', 'Ã¼', 'Ã¨', 'Ã«', 'Â ', "\u{FFFD}"];

    $offenders = [];

    foreach (encodingGuardFiles() as $file) {
        $source = file_get_contents($file);

        foreach ($signatures as $signature) {
            if (str_contains($source, $signature)) {
                $offenders[$file][] = bin2hex($signature);
            }
        }
    }

    expect($offenders)->toBe([]);
});
