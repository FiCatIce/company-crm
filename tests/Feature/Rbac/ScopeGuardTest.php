<?php

use Illuminate\Support\Facades\File;

/**
 * Static scope guard (DESIGN_RBAC.md §4.2c/§7.5) — the safety net the whole
 * redesign leans on. Every READ of a scope-sensitive model (Customer /
 * Transaction / Interaction) inside a controller must be one of:
 *   - run through ->visibleTo($user)                       (row-scoped), or
 *   - a pure aggregate — count/sum/avg/…                   (returns a scalar), or
 *   - carry an explicit `unscoped-ok:` justification       (reviewed exception).
 * Anything else makes this test RED, so a new endpoint that forgets to scope its
 * query cannot merge green.
 *
 * Scope: app/Http/Controllers only. Integration/console code that runs without an
 * authenticated user (e.g. the CTI IngestCall action, seeders) is intentionally
 * unscoped and lives outside this tree — see DESIGN_RBAC.md §4.2.
 *
 * How to add a new query: prefer ->visibleTo($request->user()). If the query must
 * be org-wide (an aggregate, or a manager-gated feed), put a one-line
 * `// unscoped-ok: <why>` comment on/above it so the exception is explicit.
 */

/**
 * Return the offending source lines in $code (empty = everything scoped/justified).
 *
 * @return list<string>
 */
function unscopedSensitiveQueries(string $code): array
{
    $lines = explode("\n", $code);
    // Eloquent read entry points on a sensitive model — NOT ::class, NOT ::create.
    $trigger = '/\b(Customer|Transaction|Interaction)::(query|where\w*|find|first\w*|get|all|with|has|latest|paginate)\b/';
    // Terminals that return a scalar (never rows) — safe to leave unscoped.
    $aggregate = '/->(count|sum|avg|min|max|exists|doesntExist|value)\(/';

    $violations = [];

    foreach ($lines as $i => $line) {
        if (preg_match($trigger, $line) !== 1) {
            continue;
        }

        // Accumulate the whole statement (up to its terminating ';').
        $statement = '';
        for ($j = $i, $n = count($lines); $j < $n && $j < $i + 12; $j++) {
            $statement .= $lines[$j]."\n";
            if (str_contains($lines[$j], ';')) {
                break;
            }
        }

        // The justification may sit inside the statement or on the two lines above.
        $context = ($lines[$i - 1] ?? '')."\n".($lines[$i - 2] ?? '')."\n".$statement;

        $ok = str_contains($statement, '->visibleTo(')
            || preg_match($aggregate, $statement) === 1
            || str_contains($context, 'unscoped-ok');

        if (! $ok) {
            $violations[] = trim($line);
        }
    }

    return $violations;
}

it('has no unscoped sensitive-model query in any controller', function () {
    $offenders = [];

    foreach (File::allFiles(app_path('Http/Controllers')) as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $found = unscopedSensitiveQueries($file->getContents());

        if ($found !== []) {
            $offenders[$file->getRelativePathname()] = $found;
        }
    }

    // A non-empty map names the file(s) + line(s) that forgot to scope.
    expect($offenders)->toBe([]);
});

// Proves the guard actually bites — a green suite would be worthless otherwise.
it('flags an unscoped read but accepts scoped, aggregate, or justified ones', function () {
    $leak = '<?php $rows = Customer::query()->where("x", 1)->get();';
    expect(unscopedSensitiveQueries($leak))->not->toBe([]);

    $scoped = '<?php $rows = Customer::query()->visibleTo($user)->get();';
    expect(unscopedSensitiveQueries($scoped))->toBe([]);

    $aggregate = '<?php $n = Transaction::query()->where("x", 1)->count();';
    expect(unscopedSensitiveQueries($aggregate))->toBe([]);

    $justified = "<?php\n// unscoped-ok: aggregate feed\n\$rows = Interaction::query()->get();";
    expect(unscopedSensitiveQueries($justified))->toBe([]);
});
