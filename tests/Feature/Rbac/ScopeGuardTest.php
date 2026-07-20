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
 * Split PHP source into method bodies, keyed by "methodName". Used by the hierarchy
 * guard, which judges a whole method rather than a single statement: the bound on a
 * hierarchy query is usually established a few lines earlier (a HierarchyResolver
 * call feeding a whereIn), so per-statement matching would be pure noise.
 *
 * @return array<string, string>
 */
function methodBodies(string $code): array
{
    $methods = [];
    $lines = explode("\n", $code);
    $current = null;

    foreach ($lines as $line) {
        if (preg_match('/function\s+(\w+)\s*\(/', $line, $m) === 1) {
            $current = $m[1];
            $methods[$current] = '';
        }

        if ($current !== null) {
            $methods[$current] .= $line."\n";
        }
    }

    return $methods;
}

/**
 * Controller methods that query the HIERARCHY (users as staff, teams, membership,
 * support assignment) without any bound on WHICH people they may reach.
 *
 * Why a second guard: the sensitive-model guard below watches customer/transaction/
 * interaction rows, but L1 added a whole second axis — a staff directory, team
 * rosters, and the assignment pivot. A new endpoint that lists "users" or "team
 * members" unbounded leaks the org chart across teams and, worse, feeds pickers
 * whose ids are ACCESS GRANTS (assigning a customer or a support agent widens who
 * can see a book). That is exactly how the customers.owner quick-change shipped an
 * unbounded staff list and an unbounded assigned_to.
 *
 * A method passes if it establishes a bound (HierarchyResolver / CapabilityResolver
 * / ->visibleTo / an authorize+policy pin), only aggregates, or carries an explicit
 * `unscoped-ok:` justification.
 *
 * @return list<string>
 */
function unboundedHierarchyQueries(string $code): array
{
    // Reads of the people/hierarchy tables — NOT ::class, NOT ::create.
    // NOTE: orderBy/select/pluck are in here deliberately — the real leak that
    // shipped was `User::orderBy('name')->get()`, which an entry-point list built
    // around query/where alone would have sailed straight past.
    $trigger = '/\b(User|Team)::(query|where\w*|find|first\w*|get|all|with|has|latest'
        .'|paginate|role|orderBy|select|pluck)\b'
        .'|DB::table\([\'"](team_user|sales_assignee)[\'"]\)/';

    // Tokens that prove the set of reachable people was bounded before use.
    $bounded = '/HierarchyResolver::|CapabilityResolver::|->visibleTo\(|teamMembersQuery\(|supportCandidateIds\(|eligibleSuccessors\(/';

    // Terminals returning a scalar — no roster leaves the method.
    $aggregate = '/->(count|sum|avg|min|max|exists|doesntExist|value)\(\)/';

    $violations = [];

    foreach (methodBodies($code) as $name => $body) {
        if (preg_match($trigger, $body) !== 1) {
            continue;
        }

        $ok = preg_match($bounded, $body) === 1
            || preg_match($aggregate, $body) === 1
            || str_contains($body, 'unscoped-ok');

        if (! $ok) {
            $violations[] = $name;
        }
    }

    return $violations;
}

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
    // `value(` is deliberately NOT here: value('amount') returns a real money figure
    // off an unscoped query, which is a leak wearing an aggregate's clothes.
    $aggregate = '/->(count|sum|avg|min|max|exists|doesntExist)\(/';

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

it('has no unbounded hierarchy query in any controller', function () {
    $offenders = [];

    foreach (File::allFiles(app_path('Http/Controllers')) as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $found = unboundedHierarchyQueries($file->getContents());

        if ($found !== []) {
            $offenders[$file->getRelativePathname()] = $found;
        }
    }

    // A non-empty map names the file(s) + method(s) that reach the org chart
    // without a bound. Fix by scoping through HierarchyResolver/CapabilityResolver,
    // or add `// unscoped-ok: <why>` if the breadth is genuinely intended.
    expect($offenders)->toBe([]);
});

// Proves the hierarchy guard actually bites, on the exact shapes that shipped bugs.
it('flags an unbounded staff/roster query but accepts bounded or justified ones', function () {
    // The real regression: an org-wide staff picker feeding an access-granting field.
    $leak = '<?php class C { public function userOptions() {
        $users = User::orderBy("name")->get(["id", "name"])->all();
        return $users;
    } }';
    expect(unboundedHierarchyQueries($leak))->toBe(['userOptions']);

    $bounded = '<?php class C { public function userOptions() {
        $ids = HierarchyResolver::teamMemberIds($actor);
        return User::whereIn("id", $ids)->get();
    } }';
    expect(unboundedHierarchyQueries($bounded))->toBe([]);

    $aggregate = '<?php class C { public function m() {
        return User::role("admin")->count();
    } }';
    expect(unboundedHierarchyQueries($aggregate))->toBe([]);

    $justified = '<?php class C { public function index() {
        // unscoped-ok: admin user-management directory
        return User::query()->paginate();
    } }';
    expect(unboundedHierarchyQueries($justified))->toBe([]);

    // Membership/assignment pivots count as hierarchy reads too.
    $pivot = '<?php class C { public function m() {
        return DB::table("sales_assignee")->get();
    } }';
    expect(unboundedHierarchyQueries($pivot))->toBe(['m']);
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
