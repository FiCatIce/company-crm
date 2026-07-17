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
