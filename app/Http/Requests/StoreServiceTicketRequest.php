<?php

namespace App\Http\Requests;

use App\Enums\TicketCoverage;
use App\Enums\TicketPriority;
use App\Models\Customer;
use App\Models\ServiceTicket;
use App\Models\Transaction;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Ticket intake (DESIGN_MAINTENANCE.md M2). Two scope rules live here as
 * VALIDATION (friendly 422s, the OffboardUserRequest idiom) and are re-pinned in
 * the controller as the backstop:
 *
 *  1. The customer must be inside the CREATOR's visibility — otherwise intake
 *     becomes a write onto a stranger's book (the same write-IDOR shape
 *     StoreInteractionRequest closes for call logs).
 *  2. A transaction anchor must belong to THAT customer — no cross-wiring a
 *     ticket to someone else's purchase.
 */
class StoreServiceTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ServiceTicket::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'customer_id' => [
                'required', 'integer', Rule::exists('customers', 'id'),
                function (string $attribute, mixed $value, Closure $fail): void {
                    $user = $this->user();

                    if ($user === null || ! Customer::query()->visibleTo($user)->whereKey($value)->exists()) {
                        $fail('Customer ini di luar jangkauan Anda.');
                    }
                },
            ],
            'transaction_id' => [
                'nullable', 'integer', 'required_without_all:product_id,item_description',
                Rule::exists('transactions', 'id'),
                function (string $attribute, mixed $value, Closure $fail): void {
                    $transaction = Transaction::query()->whereKey($value)->first();

                    if ($transaction === null || (int) $transaction->customer_id !== (int) $this->input('customer_id')) {
                        $fail('Transaksi ini bukan milik customer yang dipilih.');
                    }
                },
            ],
            'product_id' => ['nullable', 'integer', Rule::exists('products', 'id')],
            'item_description' => ['nullable', 'string', 'max:255'],
            'serial_no' => ['nullable', 'string', 'max:64'],
            'complaint' => ['required', 'string', 'max:5000'],
            'coverage' => ['nullable', Rule::enum(TicketCoverage::class)],
            'priority' => ['nullable', Rule::enum(TicketPriority::class)],
            'received_at' => ['nullable', 'date', 'before_or_equal:now'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'transaction_id.required_without_all' => 'Sebutkan unit yang diservis: pilih transaksi, produk, atau isi deskripsi barang.',
        ];
    }
}
