<?php

namespace App\Actions\Cti;

use App\Enums\CustomerSource;
use App\Enums\CustomerStatus;
use App\Enums\InteractionDirection;
use App\Enums\InteractionOutcome;
use App\Enums\InteractionSource;
use App\Enums\InteractionType;
use App\Models\Customer;
use App\Models\Interaction;
use App\Models\User;
use App\Support\PhoneNormalizer;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Orchestrates a single CTI call ingest: idempotency, caller-ID matching (with
 * guarded auto-lead for unknown callers), agent resolution, and interaction
 * persistence — the write path wrapped in one transaction.
 */
class IngestCall
{
    /**
     * @param  array<string, mixed>  $data  validated StoreCtiCallRequest payload
     * @return array{code: int, payload: array<string, mixed>}
     */
    public function __invoke(array $data): array
    {
        $externalRef = (string) $data['external_call_id'];

        // 3. Idempotent replay — the external_ref was already ingested.
        $existing = Interaction::query()->where('external_ref', $externalRef)->first();
        if ($existing !== null) {
            return $this->idempotent($existing);
        }

        // 4. Normalize the customer-side number (caller on inbound, callee on outbound).
        $rawNumber = $data['direction'] === 'in'
            ? (string) $data['from_number']
            : (string) $data['to_number'];
        $e164 = PhoneNormalizer::e164($rawNumber);
        if ($e164 === null) {
            return $this->skipped('unparseable_number');
        }

        // 6. Resolve the handling agent (null = unmatched, stored as "system").
        $agentId = $this->resolveAgent(
            isset($data['agent_extension']) ? (string) $data['agent_extension'] : null,
            isset($data['agent_email']) ? (string) $data['agent_email'] : null,
        );

        try {
            return DB::transaction(function () use ($data, $externalRef, $rawNumber, $e164, $agentId): array {
                // 5. Match an existing customer, or auto-create a guarded lead.
                $customer = $this->resolveCustomer($e164);
                $customerCreated = false;

                if ($customer === null) {
                    if (! $this->passesLeadGuard($data)) {
                        return $this->skipped('unmatched_unanswered');
                    }

                    $customer = Customer::firstOrCreate(
                        ['phone_normalized' => $e164],
                        [
                            'name' => 'Penelepon '.$e164,
                            'phone' => $rawNumber,
                            'status' => CustomerStatus::Lead,
                            'source' => CustomerSource::Cti,
                            'reseller_id' => null,
                            'assigned_to' => $agentId,
                        ],
                    );
                    $customerCreated = $customer->wasRecentlyCreated;
                }

                // 7. Persist the call.
                $interaction = Interaction::create([
                    'customer_id' => $customer->id,
                    'user_id' => $agentId,
                    'type' => InteractionType::Call,
                    'direction' => $data['direction'] === 'in'
                        ? InteractionDirection::In
                        : InteractionDirection::Out,
                    'outcome' => isset($data['outcome'])
                        ? InteractionOutcome::from((string) $data['outcome'])
                        : null,
                    'duration_sec' => isset($data['duration_sec']) ? (int) $data['duration_sec'] : null,
                    'occurred_at' => $data['started_at'] ?? $data['ended_at'] ?? now(),
                    'source' => InteractionSource::Cti,
                    'external_ref' => $externalRef,
                    'meta' => $this->buildMeta($data),
                ]);

                return [
                    'code' => 201,
                    'payload' => [
                        'created' => true,
                        'interaction_id' => $interaction->id,
                        'customer_id' => $customer->id,
                        'customer_created' => $customerCreated,
                    ],
                ];
            });
        } catch (QueryException $e) {
            // A concurrent request inserted the same external_ref between our
            // idempotency check and this insert — treat the race as idempotent.
            $raced = Interaction::query()->where('external_ref', $externalRef)->first();
            if ($raced !== null) {
                return $this->idempotent($raced);
            }

            throw $e;
        }
    }

    /**
     * Resolve the handling agent from PBX extension (preferred) or email.
     */
    protected function resolveAgent(?string $extension, ?string $email): ?int
    {
        if ($extension !== null && $extension !== '') {
            $id = User::query()->where('extension', $extension)->value('id');
            if ($id !== null) {
                return (int) $id;
            }
        }

        if ($email !== null && $email !== '') {
            $id = User::query()->where('email', $email)->value('id');
            if ($id !== null) {
                return (int) $id;
            }
        }

        return null;
    }

    /**
     * Find the customer for a normalized number. When several share it (e.g. a
     * shared office line), prefer the most-recently-contacted, then smallest id.
     */
    protected function resolveCustomer(string $e164): ?Customer
    {
        $candidates = Customer::query()
            ->where('phone_normalized', $e164)
            ->withMax('interactions', 'occurred_at')
            ->get();

        if ($candidates->isEmpty()) {
            return null;
        }

        if ($candidates->count() === 1) {
            return $candidates->first();
        }

        return $candidates->sort(function (Customer $a, Customer $b): int {
            $am = $a->getAttribute('interactions_max_occurred_at');
            $bm = $b->getAttribute('interactions_max_occurred_at');

            if ($am === $bm) {
                return $a->id <=> $b->id;
            }
            if ($am === null) {
                return 1;
            }
            if ($bm === null) {
                return -1;
            }

            return strcmp((string) $bm, (string) $am);
        })->first();
    }

    /**
     * Guard against robocall/misdial pollution: only a real, answered
     * conversation of sufficient length becomes a lead.
     *
     * @param  array<string, mixed>  $data
     */
    protected function passesLeadGuard(array $data): bool
    {
        $answered = (bool) $data['answered'];
        $duration = isset($data['duration_sec']) ? (int) $data['duration_sec'] : 0;
        $outcome = isset($data['outcome']) ? InteractionOutcome::from((string) $data['outcome']) : null;
        $min = (int) config('cti.lead_min_duration_sec', 10);

        return $answered
            && $duration >= $min
            && $outcome !== InteractionOutcome::WrongNumber;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    protected function buildMeta(array $data): ?array
    {
        $meta = [];

        if (! empty($data['recording_url'])) {
            $meta['recording_url'] = (string) $data['recording_url'];
        }

        return $meta === [] ? null : $meta;
    }

    /**
     * @return array{code: int, payload: array<string, mixed>}
     */
    protected function idempotent(Interaction $interaction): array
    {
        return [
            'code' => 200,
            'payload' => ['idempotent' => true, 'interaction_id' => $interaction->id],
        ];
    }

    /**
     * @return array{code: int, payload: array<string, mixed>}
     */
    protected function skipped(string $reason): array
    {
        return [
            'code' => 200,
            'payload' => ['skipped' => true, 'reason' => $reason],
        ];
    }
}
