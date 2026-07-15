<?php

use App\Models\User;

it('assigns the admin role to the seeded Test User', function () {
    $this->seed();

    $user = User::where('email', 'test@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->hasRole('admin'))->toBeTrue();
});

it('lets the seeded Test User reach the dashboard', function () {
    $this->seed();

    // Mock out Vite so the Inertia root template renders without built assets;
    // this test targets the role gate, not the frontend build. The seeded user is
    // an admin (a system role since B4), so its landing page is the dashboard —
    // it can no longer reach the customer detail area.
    $this->withoutVite();

    $user = User::where('email', 'test@example.com')->first();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});
