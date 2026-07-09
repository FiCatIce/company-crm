<?php

namespace App\Http\Controllers;

use App\Enums\InteractionSource;
use App\Http\Requests\StoreInteractionRequest;
use App\Http\Requests\UpdateInteractionRequest;
use App\Models\Customer;
use App\Models\Interaction;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;

class InteractionController extends Controller
{
    use AuthorizesRequests;

    public function store(StoreInteractionRequest $request, Customer $customer): RedirectResponse
    {
        $data = $request->validated();

        $customer->interactions()->create([
            ...$data,
            'user_id' => $request->user()->id,
            'source' => InteractionSource::Manual,
            'occurred_at' => $data['occurred_at'] ?? now(),
        ]);

        return back()->with('success', 'Interaksi berhasil dicatat.');
    }

    public function update(UpdateInteractionRequest $request, Interaction $interaction): RedirectResponse
    {
        $data = $request->validated();

        $interaction->update([
            ...$data,
            'occurred_at' => $data['occurred_at'] ?? $interaction->occurred_at,
        ]);

        return back()->with('success', 'Interaksi berhasil diperbarui.');
    }

    public function destroy(Interaction $interaction): RedirectResponse
    {
        $this->authorize('delete', $interaction);

        $interaction->delete();

        return back()->with('success', 'Interaksi berhasil dihapus.');
    }
}
