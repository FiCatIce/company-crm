<?php

use App\Enums\PermissionName;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;

it('provisions example sales and maintenance staff with their presets', function () {
    $this->seed(DemoDataSeeder::class);

    $sales = User::where('extension', '1005')->sole();
    $maintenance = User::where('extension', '1006')->sole();

    expect($sales->hasRole('sales'))->toBeTrue()
        ->and($sales->can(PermissionName::CustomerViewOwn->value))->toBeTrue()
        ->and($sales->can(PermissionName::CustomerViewAll->value))->toBeFalse()
        ->and($maintenance->hasRole('maintenance'))->toBeTrue()
        ->and($maintenance->can(PermissionName::CustomerViewAll->value))->toBeTrue()
        ->and($maintenance->can(PermissionName::TransactionViewAll->value))->toBeFalse();
});
