<?php

use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InteractionController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SupportAssignmentController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TeamMemberController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
});

Route::middleware(['auth'])->group(function () {
    Route::resource('customers', CustomerController::class);
    // Lightweight quick-changes from the Customer 360 header (single field each).
    Route::patch('customers/{customer}/status', [CustomerController::class, 'updateStatus'])->name('customers.status');
    Route::patch('customers/{customer}/owner', [CustomerController::class, 'updateOwner'])->name('customers.owner');
    Route::resource('products', ProductController::class)->except(['show']);
    Route::resource('transactions', TransactionController::class)->except(['show']);

    // Interactions — shallow nested: create under a customer, edit/delete standalone.
    Route::post('customers/{customer}/interactions', [InteractionController::class, 'store'])->name('interactions.store');
    Route::put('interactions/{interaction}', [InteractionController::class, 'update'])->name('interactions.update');
    Route::delete('interactions/{interaction}', [InteractionController::class, 'destroy'])->name('interactions.destroy');

    // Admin user management (RBAC B5) — gated per-action by UserPolicy.
    Route::resource('users', UserController::class)->except(['show']);
    // Account lifecycle (hierarchy H7b) — access on/off, data untouched.
    Route::put('users/{user}/status', [UserController::class, 'updateStatus'])->name('users.status');
    // Offboarding (hierarchy H7c) — transfer everything to a successor, then off.
    Route::get('users/{user}/offboard', [UserController::class, 'showOffboard'])->name('users.offboard.form');
    Route::post('users/{user}/offboard', [UserController::class, 'offboard'])->name('users.offboard');

    // "Tim Saya" (hierarchy H6) — read-only hierarchy overview, shape per viewer.
    Route::get('team', [TeamController::class, 'index'])->name('team.index');

    // Delegated team-member management (hierarchy H4) — a manager's scoped area,
    // gated by UserPolicy::manageTeamMembers/manageTeamMember. Distinct from the
    // admin /users UI above: create whitelisted members + reset their passwords only.
    Route::get('team/members', [TeamMemberController::class, 'index'])->name('team.members.index');
    Route::get('team/members/create', [TeamMemberController::class, 'create'])->name('team.members.create');
    Route::post('team/members', [TeamMemberController::class, 'store'])->name('team.members.store');
    Route::put('team/members/{member}/password', [TeamMemberController::class, 'resetPassword'])->name('team.members.password');
    Route::put('team/members/{member}/status', [TeamMemberController::class, 'updateStatus'])->name('team.members.status');
    Route::get('team/members/{member}/offboard', [TeamMemberController::class, 'showOffboard'])->name('team.members.offboard.form');
    Route::post('team/members/{member}/offboard', [TeamMemberController::class, 'offboard'])->name('team.members.offboard');

    // Support assignment (hierarchy H5) — a sales user wires CS/maintenance to
    // their own book. Always self-scoped: no route names another sales.
    Route::get('team/assignments', [SupportAssignmentController::class, 'index'])->name('team.assignments.index');
    Route::post('team/assignments', [SupportAssignmentController::class, 'store'])->name('team.assignments.store');
    Route::delete('team/assignments/{assignee}', [SupportAssignmentController::class, 'destroy'])->name('team.assignments.destroy');

    // Admin role builder — create/edit/delete custom roles + their permission
    // templates. Gated by role.manage inside the controller; system roles locked.
    Route::resource('roles', RoleController::class)->except(['show']);
});

require __DIR__.'/settings.php';
