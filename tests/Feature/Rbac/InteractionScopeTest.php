<?php

use App\Models\Customer;
use App\Models\Interaction;
use Database\Seeders\RoleSeeder;

/**
 * B7 sweep — logging an interaction is scope-checked, closing a write IDOR: a Sales
 * user must not be able to POST a call onto another rep's customer by id
 * (StoreInteractionRequest now requires customer.view scope, DESIGN_RBAC.md §6).
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->withoutVite();
});

it('forbids a sales user from logging an interaction on another rep\'s customer', function () {
    $salesA = userWithRole('sales');
    $salesB = userWithRole('sales');
    $theirs = Customer::factory()->createdBy($salesB)->create();

    $this->actingAs($salesA)
        ->post(route('interactions.store', $theirs), [
            'type' => 'note',
            'subject' => 'sneaky',
            'body' => 'should be blocked',
        ])
        ->assertForbidden();

    expect($theirs->interactions()->count())->toBe(0);
});

it('lets a sales user log an interaction on their own customer', function () {
    $sales = userWithRole('sales');
    $mine = Customer::factory()->createdBy($sales)->create();

    $this->actingAs($sales)
        ->post(route('interactions.store', $mine), [
            'type' => 'note',
            'subject' => 'follow-up',
            'body' => 'called back',
        ])
        ->assertRedirect();

    expect($mine->interactions()->count())->toBe(1)
        ->and(Interaction::first()->user_id)->toBe($sales->id);
});
