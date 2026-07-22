<?php

use App\Enums\PermissionName as P;
use App\Models\Role;
use App\Models\User;
use App\Support\CapabilityResolver;
use App\Support\DelegatedUserCreator;
use App\Support\SupportAssignments;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Sweep findings #5 + #3 verified against the REAL dev database (pgsql), replaying
 * the actual attack chain. Everything runs inside a rolled-back transaction.
 *
 * Run explicitly:  vendor/bin/pest tests/Smoke
 */
uses(TestCase::class);

beforeEach(function () {
    config([
        'database.default' => 'pgsql',
        'database.connections.pgsql.database' => 'company_crm',
        'database.connections.pgsql.username' => env('DB_USERNAME', 'postgres'),
        'database.connections.pgsql.password' => env('DB_PASSWORD', ''),
    ]);
    DB::purge('pgsql');
    $this->withoutVite();
});

it('stops the real manager minting an org-wide reader', function () {
    DB::beginTransaction();

    try {
        $manager = User::where('email', 'manager@crm.test')->firstOrFail();

        // Replay the chain exactly: an admin creates a custom role that reads the
        // whole org and wires it into the manager's whitelist.
        $wide = new Role;
        $wide->forceFill(['name' => 'smoke-wide-reader', 'guard_name' => 'web'])->save();
        $wide->syncPermissions([P::CustomerViewOwn->value, P::CustomerViewAll->value]);

        $supervisorRole = Role::where('name', 'supervisor')->firstOrFail();
        $before = $supervisorRole->assignable_types;
        $supervisorRole->forceFill([
            'assignable_types' => [...($before ?? ['sales', 'cs', 'maintenance']), 'smoke-wide-reader'],
        ])->save();

        // Even so configured, the manager cannot mint one.
        expect($manager->canCreateUserType('smoke-wide-reader'))->toBeFalse()
            ->and(CapabilityResolver::creatableTypes($manager))->not->toContain('smoke-wide-reader');

        expect(fn () => DelegatedUserCreator::create($manager, 'smoke-wide-reader', [
            'name' => 'Wide', 'email' => 'smoke-wide@x.test', 'password' => 'password-123',
        ]))->toThrow(AuthorizationException::class);

        expect(User::where('email', 'smoke-wide@x.test')->exists())->toBeFalse();

        // …while the ordinary team roles still work on real data.
        $normal = DelegatedUserCreator::create($manager, 'cs', [
            'name' => 'Smoke CS', 'email' => 'smoke-cs@x.test', 'password' => 'password-123',
        ]);

        dump([
            'manager' => $manager->email,
            'blocked_type' => 'smoke-wide-reader',
            'creatable_types' => CapabilityResolver::creatableTypes($manager),
            'normal_create_ok' => $normal->email,
        ]);
    } finally {
        DB::rollBack();
    }
});

it('refuses a real cross-team assignment at the service layer', function () {
    DB::beginTransaction();

    try {
        // Finding #3: call the service DIRECTLY, bypassing the request whose
        // Rule::in used to be the only thing enforcing the team bound.
        $sales = User::role('sales')->whereHas('teams')->firstOrFail();
        $salesTeamIds = $sales->teams()->pluck('teams.id')->all();

        $outsider = User::role('cs')
            ->whereDoesntHave('teams', fn ($q) => $q->whereIn('teams.id', $salesTeamIds))
            ->first();

        if ($outsider === null) {
            $this->markTestSkipped('no out-of-team CS in dev data');
        }

        expect(fn () => SupportAssignments::assign($sales, $outsider))
            ->toThrow(AuthorizationException::class);

        expect($sales->fresh()->assignees()->pluck('users.id')->all())
            ->not->toContain($outsider->id);

        dump([
            'sales' => $sales->email,
            'outsider_refused' => $outsider->email,
        ]);
    } finally {
        DB::rollBack();
    }
});
