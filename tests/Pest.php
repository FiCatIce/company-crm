<?php

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Customer;
use App\Models\Team;
use App\Models\User;
use App\Support\RolePresets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Create a user provisioned with the given role and its preset permissions
 * (roles + permissions must already be seeded — RoleSeeder does both). Runtime
 * authorization checks permissions, so the preset must be synced, not just the
 * role assigned.
 */
function userWithRole(string $role): User
{
    $user = User::factory()->create();
    RolePresets::assign($user, RoleName::from($role));

    return $user;
}

/**
 * A user who sees the WHOLE org — for tests exercising org-wide mechanics (CRUD
 * lists, dashboard aggregates, stats) rather than hierarchy scoping. Post-H3 no
 * system role sees everything, so we synthesise the pre-H3 global manager by
 * granting the global view permissions on top of the supervisor preset.
 */
function userWithGlobalView(): User
{
    $user = userWithRole('supervisor');
    $user->givePermissionTo([
        PermissionName::CustomerViewAll->value,
        PermissionName::TransactionViewAll->value,
        PermissionName::InteractionViewAll->value,
    ]);

    return $user;
}

/**
 * A CS/maintenance user assigned to a fresh Sales rep who OWNS $customer — so a
 * hierarchy (assignment-scoped) role can actually see the customer under test.
 * Returns [supportUser, salesOwner].
 *
 * @return array{0: User, 1: User}
 */
function supportAssignedToOwnerOf(Customer $customer, string $role = 'cs'): array
{
    $sales = userWithRole('sales');
    $customer->forceFill(['created_by' => $sales->id])->save();

    $support = userWithRole($role);
    $sales->assignees()->attach($support->id);

    return [$support, $sales];
}

/**
 * Parse every custom-property declaration inside the :root block of
 * resources/css/app.css into ['--token' => 'raw value'] (comments stripped).
 * Raw values keep their var() references — resolve with resolveDesignToken().
 */
function designTokenMap(): array
{
    $css = file_get_contents(resource_path('css/app.css'));

    if (! preg_match('/:root\s*\{(.*?)\}/s', $css, $m)) {
        throw new RuntimeException('No :root block found in app.css');
    }

    $block = preg_replace('~/\*.*?\*/~s', '', $m[1]);

    preg_match_all('/(--[\w-]+)\s*:\s*([^;]+);/', $block, $decls, PREG_SET_ORDER);

    $map = [];
    foreach ($decls as $decl) {
        $map[$decl[1]] = trim($decl[2]);
    }

    return $map;
}

/**
 * Follow a token's var() chain through the :root map until it lands on a
 * concrete literal, computing color-mix(in srgb, …) steps the way the
 * browser does (per-channel linear interpolation of the gamma-encoded
 * sRGB components) so pins stay concrete hex. Throws on dangling
 * references or cycles so tests fail loudly when a semantic token stops
 * resolving.
 */
function resolveDesignToken(string $token): string
{
    $map = designTokenMap();
    $value = $map[$token] ?? throw new RuntimeException("Token {$token} is not defined in :root");

    $depth = 0;
    while (preg_match('/^var\((--[\w-]+)\)$/', $value, $m)) {
        if (++$depth > 10) {
            throw new RuntimeException("Token {$token} does not resolve (cycle or chain too deep)");
        }

        $value = $map[$m[1]] ?? throw new RuntimeException("Token {$token} references undefined {$m[1]}");
    }

    if (str_starts_with($value, 'color-mix(')) {
        if (! preg_match('/^color-mix\(in srgb,\s*(.+?)\s+(\d+(?:\.\d+)?)%,\s*(.+?)\)$/', $value, $m)) {
            throw new RuntimeException("Token {$token} carries an unsupported color-mix form: {$value}");
        }

        [$a, $b, $pct] = [designTokenRgb($m[1]), designTokenRgb($m[3]), (float) $m[2]];

        return sprintf('#%02x%02x%02x', ...array_map(
            fn (int $ca, int $cb) => (int) round(($ca * $pct + $cb * (100 - $pct)) / 100),
            $a,
            $b,
        ));
    }

    return $value;
}

/**
 * Resolve a color-mix argument — a var() reference or a #rrggbb literal —
 * to its [r, g, b] channels.
 *
 * @return array{0: int, 1: int, 2: int}
 */
function designTokenRgb(string $color): array
{
    if (preg_match('/^var\((--[\w-]+)\)$/', $color, $m)) {
        $color = resolveDesignToken($m[1]);
    }

    if (! preg_match('/^#([0-9a-fA-F]{6})$/', $color, $m)) {
        throw new RuntimeException("Cannot resolve {$color} to an #rrggbb color");
    }

    return array_map(hexdec(...), str_split($m[1], 2));
}

/**
 * A supervisor (manager) leading a team that contains $sales — so a team roll-up
 * viewer can see that sales rep's customers.
 */
function managerOverTeamOf(User $sales): User
{
    $manager = userWithRole('supervisor');
    $team = Team::factory()->create();
    $team->members()->attach([
        $manager->id => ['role_in_team' => 'manager'],
        $sales->id => ['role_in_team' => 'sales'],
    ]);

    return $manager;
}
