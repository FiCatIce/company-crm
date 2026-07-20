<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Support\AccountStatus;
use App\Support\UserOffboarding;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();

        // H7e: the same holdings guard the admin delete path enforces. Without it
        // this route was a way around H7c entirely — customers.assigned_to and
        // created_by are both nullOnDelete, and Customer::scopeVisibleTo matches on
        // exactly those two columns, so a rep deleting their own account nulled both
        // on their whole book and made those customers invisible to EVERY role
        // (no shipped preset holds customer.view.all). Unrecoverable through the UI.
        $blocking = UserOffboarding::blockingReason($user);
        if ($blocking !== null) {
            return back()->with('error', $blocking);
        }

        if (AccountStatus::isLastAdmin($user)) {
            return back()->with('error', 'Tidak dapat menghapus administrator terakhir.');
        }

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
