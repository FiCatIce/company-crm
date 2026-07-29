<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\TeamRoleLabels;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
                // Effective permission names, so the client can gate nav/buttons.
                // Authorization is still enforced server-side; this is UI only.
                'permissions' => $request->user()
                    ? $request->user()->getAllPermissions()->pluck('name')->values()->all()
                    : [],
                // Derived capabilities that no single permission expresses — a
                // manager (delegable types) vs the admin (none). UI-gating only;
                // the policy of the same name enforces it server-side.
                'can' => [
                    'manageTeamMembers' => $request->user()?->can('manageTeamMembers', User::class) ?? false,
                ],
            ],
            // L3 white-label: resolved role labels (override → default), in Title
            // Case + the sentence-context lower form. Vue copy reads these via
            // useLabels() instead of hardcoding 'Sales'/'CS' — an admin override
            // is then visible on the very next request, everywhere at once.
            'labels' => [
                'roles' => TeamRoleLabels::all(),
                'rolesLower' => TeamRoleLabels::allLower(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
