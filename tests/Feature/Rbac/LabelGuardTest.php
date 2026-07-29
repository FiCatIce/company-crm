<?php

use Illuminate\Support\Facades\File;

/**
 * L3-B static label guard — two layers, complementary:
 *
 * 1. LITERAL TRIPWIRE: no hardcoded role-label WORD in user-facing source.
 *    Forbidden (case-sensitive, standalone): Sales / Maintenance / Manager /
 *    CS, plus the 'mekanik' synonym that once slipped into copy. Copy must go
 *    through TeamRoleLabels (PHP) / useLabels() (Vue). Case-sensitivity keeps
 *    slugs-as-data ('sales' keys, type unions) out of scope. Comments are
 *    stripped first (they may name roles freely).
 *
 * 1b. LOWERCASE PROSE LAYER: the case-sensitive scan misses "hubungi sales
 *    Anda". Closed for QUOTED prose: a case-INSENSITIVE scan that applies only
 *    to string literals CONTAINING A SPACE — a slug never has one, a sentence
 *    always does — with `${…}`/`{$…}` interpolations stripped first so
 *    `${label('sales')}` inside a template string never trips it. Unquoted
 *    Vue template text remains out of reach; still a tripwire, not a proof.
 *
 * 2. RIVAL-RESOLVER GUARD: a literal scan cannot catch a SECOND resolver like
 *    the old RoleName::label() (it hid labels behind an enum, then drifted
 *    from the registry over 'cs'). So: TeamRoleLabels must stay the only
 *    reader of config('hierarchy.role_labels'), and RoleName must not grow a
 *    label() back.
 */

/** Strip block, line, and HTML comments so prose about roles never trips the scan. */
function labelGuardStripComments(string $code): string
{
    $code = (string) preg_replace('#/\*.*?\*/#s', '', $code);
    $code = (string) preg_replace('#<\!--.*?-->#s', '', $code);
    // Full-line and trailing `// …` comments; `https://` is safe (no space before //).
    $code = (string) preg_replace('#^\s*//[^\n]*$#m', '', $code);

    return (string) preg_replace('#\s//\s[^\n]*#', '', $code);
}

/**
 * Offending lines in $code: a standalone forbidden label word outside comments.
 * \b keeps identifiers (topSales, RevenueBySalesCard, $headManager) out.
 *
 * @param  list<string>  $allowed  substrings to excuse (reviewed exceptions)
 * @return list<string>
 */
function labelGuardViolations(string $code, array $allowed = []): array
{
    $code = labelGuardStripComments($code);

    foreach ($allowed as $exception) {
        $code = str_replace($exception, '', $code);
    }

    $violations = [];

    foreach (explode("\n", $code) as $line) {
        if (preg_match('/\b(Sales|Maintenance|Manager|CS)\b|[Mm]ekanik/', $line) === 1) {
            $violations[] = trim($line);
        }
    }

    return $violations;
}

/**
 * PHP variant: copy always lives in QUOTED LITERALS, while enum members and
 * class references (RoleName::Sales, `case Sales`) are identifiers — so only
 * string literal contents are scanned, not whole lines.
 *
 * @return list<string>
 */
function labelGuardPhpStringViolations(string $code): array
{
    $code = labelGuardStripComments($code);

    preg_match_all('/\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*"/', $code, $matches);

    $violations = [];

    foreach ($matches[0] as $literal) {
        if (preg_match('/\b(Sales|Maintenance|Manager|CS)\b|[Mm]ekanik/', $literal) === 1) {
            $violations[] = $literal;
        }
    }

    return $violations;
}

/**
 * Layer 1b — lowercase prose: case-INSENSITIVE scan over string literals that
 * contain a SPACE (slugs never do, sentences always do). Interpolation
 * segments are stripped first so `${label('sales')}` / `{$var}` never trip it.
 * Shared by the Vue/TS and PHP scans.
 *
 * @return list<string>
 */
function labelGuardSpacedLiteralViolations(string $code): array
{
    $code = labelGuardStripComments($code);

    preg_match_all('/\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*"|`(?:[^`\\\\]|\\\\.)*`/', $code, $matches);

    $violations = [];

    foreach ($matches[0] as $literal) {
        $content = substr($literal, 1, -1);
        $content = (string) preg_replace('/\$\{[^}]*\}|\{\$[^}]*\}/', '', $content);

        // A NESTED quote of the other style is code-in-a-string (a Vue
        // expression attribute like "props.kind === 'manager'") — the inner
        // slug is data, so drop it before scanning the remaining prose.
        $content = match ($literal[0]) {
            '"' => (string) preg_replace("/'[^']*'/", '', $content),
            "'" => (string) preg_replace('/"[^"]*"/', '', $content),
            default => (string) preg_replace('/\'[^\']*\'|"[^"]*"/', '', $content),
        };

        if (! str_contains($content, ' ')) {
            continue;
        }

        if (preg_match('/\b(sales|maintenance|manager|cs)\b|mekanik/i', $content) === 1) {
            $violations[] = $literal;
        }
    }

    return $violations;
}

// Reviewed exceptions: file suffix => substrings allowed to stay.
const LABEL_GUARD_ALLOWLIST = [
    // A custom-role NAME example in the role builder, not a label of an
    // existing role — renaming system roles never changes it.
    'pages/Roles/Create.vue' => ['mis. Regional Manager'],
];

it('has no hardcoded role label in vue/ts source', function () {
    $offenders = [];

    foreach (File::allFiles(resource_path('js')) as $file) {
        if (! in_array($file->getExtension(), ['vue', 'ts'], true)) {
            continue;
        }

        // Wayfinder output is generated from routes — not copy, not committed.
        $relative = str_replace('\\', '/', $file->getRelativePathname());
        if (str_starts_with($relative, 'actions/') || str_starts_with($relative, 'routes/') || str_starts_with($relative, 'wayfinder/')) {
            continue;
        }

        $allowed = [];
        foreach (LABEL_GUARD_ALLOWLIST as $suffix => $exceptions) {
            if (str_ends_with($relative, $suffix)) {
                $allowed = $exceptions;
            }
        }

        $source = $file->getContents();

        foreach ($allowed as $exception) {
            $source = str_replace($exception, '', $source);
        }

        $found = [
            ...labelGuardViolations($source),
            ...labelGuardSpacedLiteralViolations($source),
        ];

        if ($found !== []) {
            $offenders[$relative] = $found;
        }
    }

    expect($offenders)->toBe([]);
});

it('has no hardcoded role label in backend strings', function () {
    $offenders = [];

    foreach (File::allFiles(app_path()) as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $source = $file->getContents();

        $found = [
            ...labelGuardPhpStringViolations($source),
            ...labelGuardSpacedLiteralViolations($source),
        ];

        if ($found !== []) {
            $offenders[str_replace('\\', '/', $file->getRelativePathname())] = $found;
        }
    }

    expect($offenders)->toBe([]);
});

it('keeps TeamRoleLabels the only reader of the role_labels config', function () {
    $offenders = [];

    foreach (File::allFiles(app_path()) as $file) {
        if ($file->getExtension() !== 'php' || $file->getFilename() === 'TeamRoleLabels.php') {
            continue;
        }

        if (str_contains($file->getContents(), 'hierarchy.role_labels')) {
            $offenders[] = str_replace('\\', '/', $file->getRelativePathname());
        }
    }

    expect($offenders)->toBe([]);
});

it('keeps RoleName label-free so the rival resolver cannot return', function () {
    $source = File::get(app_path('Enums/RoleName.php'));

    expect(preg_match('/function\s+label\s*\(/', $source))->toBe(0);
});

// Proves the tripwire actually bites (a green guard would be worthless otherwise).
it('flags hardcoded labels but accepts registry reads and comments', function () {
    expect(labelGuardViolations('<h2>Sales Teratas</h2>'))->not->toBe([])
        ->and(labelGuardViolations('label="CS & Maintenance"'))->not->toBe([])
        ->and(labelGuardViolations("'Belum ada CS/mekanik yang Anda assign'"))->not->toBe([])
        ->and(labelGuardViolations("{{ label('sales') }} Teratas"))->toBe([])
        ->and(labelGuardViolations('const topSales = props.topSales; // Sales ranking'))->toBe([])
        ->and(labelGuardViolations("/** the Sales/CS split */ kind: 'sales'"))->toBe([]);

    // PHP variant: identifiers pass, quoted copy trips.
    expect(labelGuardPhpStringViolations("case Sales = 'sales'; RoleName::Sales => self::sales(),"))->toBe([])
        ->and(labelGuardPhpStringViolations("return 'Assign CS/maintenance ke sales';"))->not->toBe([])
        ->and(labelGuardPhpStringViolations("'Lihat customer dari '.TeamRoleLabels::labelLower(RoleName::Sales->value)"))->toBe([]);

    // Layer 1b — lowercase prose in spaced literals trips; slugs and
    // interpolated template strings never do.
    expect(labelGuardSpacedLiteralViolations("'hubungi sales Anda'"))->not->toBe([])
        ->and(labelGuardSpacedLiteralViolations('"belum ada cs di tim"'))->not->toBe([])
        ->and(labelGuardSpacedLiteralViolations("'sales'"))->toBe([])
        // Vue expression attribute: the slug is nested code, not prose.
        ->and(labelGuardSpacedLiteralViolations('v-if="props.kind === \'manager\'"'))->toBe([])
        ->and(labelGuardSpacedLiteralViolations('`akun ${labelLower(\'sales\')} yang aktif`'))->toBe([])
        ->and(labelGuardSpacedLiteralViolations('"Role {$role->name} berhasil dibuat."'))->toBe([])
        ->and(labelGuardSpacedLiteralViolations("'Tiket sudah dipegang teknisi lain.'"))->toBe([]);
});
