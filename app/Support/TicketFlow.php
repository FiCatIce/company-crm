<?php

namespace App\Support;

use App\Enums\PermissionName as P;
use App\Enums\TicketStatus;
use App\Models\AuditLog;
use App\Models\ServiceTicket;
use App\Models\User;

/**
 * The service-ticket state machine (DESIGN_MAINTENANCE.md §1.3, batch M2).
 *
 * Every status move goes through here and NOWHERE else: status, assigned_to and
 * the lifecycle stamps are not mass-assignable, so an update request can never
 * smuggle a transition in. Each method guards the source state, stamps the
 * matching timestamp itself, audits, and returns a human-readable refusal (or
 * null on success) — the blockingReason idiom, so controllers just flash it.
 *
 * AUTHORIZATION IS NOT DONE HERE. Callers authorize via ServiceTicketPolicy
 * first; this class only enforces the state machine.
 */
final class TicketFlow
{
    /**
     * Pool → mine: self-assign an unclaimed ticket.
     */
    public static function claim(User $actor, ServiceTicket $ticket): ?string
    {
        if ($ticket->status !== TicketStatus::Received) {
            return 'Tiket hanya bisa diklaim saat berstatus Diterima.';
        }

        if ($ticket->assigned_to !== null) {
            return 'Tiket sudah dipegang teknisi lain.';
        }

        $ticket->forceFill(['assigned_to' => $actor->id])->save();

        self::audit($actor, $ticket, 'ticket.claimed');

        return null;
    }

    /**
     * received → in_progress. Requires a holder — work never starts ownerless,
     * or "who is repairing this" has no answer.
     */
    public static function start(User $actor, ServiceTicket $ticket): ?string
    {
        if ($ticket->status !== TicketStatus::Received) {
            return 'Tiket hanya bisa mulai dikerjakan dari status Diterima.';
        }

        if ($ticket->assigned_to === null) {
            return 'Klaim atau tugaskan tiket ini dulu sebelum mulai dikerjakan.';
        }

        $ticket->forceFill([
            'status' => TicketStatus::InProgress,
            'started_at' => now(),
        ])->save();

        self::audit($actor, $ticket, 'ticket.started');

        return null;
    }

    /**
     * in_progress → done. The resolution is the deliverable — the controller
     * validates it present before calling.
     */
    public static function complete(User $actor, ServiceTicket $ticket, string $resolution): ?string
    {
        if ($ticket->status !== TicketStatus::InProgress) {
            return 'Tiket hanya bisa diselesaikan saat sedang dikerjakan.';
        }

        $ticket->forceFill([
            'status' => TicketStatus::Done,
            'finished_at' => now(),
            'resolution' => $resolution,
        ])->save();

        self::audit($actor, $ticket, 'ticket.completed');

        return null;
    }

    /**
     * Any ACTIVE state → cancelled. The reason lands in work_notes so the row
     * explains itself; done/cancelled are final (D8 — no reopen).
     */
    public static function cancel(User $actor, ServiceTicket $ticket, string $reason): ?string
    {
        if (! $ticket->status->isActive()) {
            return 'Tiket yang sudah selesai atau batal tidak bisa dibatalkan lagi.';
        }

        $notes = trim(($ticket->work_notes !== null ? $ticket->work_notes."\n" : '')."Dibatalkan: {$reason}");

        $ticket->forceFill([
            'status' => TicketStatus::Cancelled,
            'cancelled_at' => now(),
            'work_notes' => $notes,
        ])->save();

        self::audit($actor, $ticket, 'ticket.cancelled', ['reason' => $reason]);

        return null;
    }

    /**
     * Route (or re-route) the ticket to a named technician. The target must be
     * able to actually WORK it: active and holding ticket.update.
     */
    public static function assignTo(User $actor, ServiceTicket $ticket, User $technician): ?string
    {
        if (! $ticket->status->isActive()) {
            return 'Tiket yang sudah selesai atau batal tidak bisa ditugaskan.';
        }

        if (! $technician->is_active || ! $technician->can(P::TicketUpdate->value)) {
            return 'Teknisi tidak memenuhi syarat (harus aktif dan punya izin mengerjakan tiket).';
        }

        $ticket->forceFill(['assigned_to' => $technician->id])->save();

        self::audit($actor, $ticket, 'ticket.assigned', [
            'technician' => ['id' => $technician->id, 'name' => $technician->name],
        ]);

        return null;
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private static function audit(User $actor, ServiceTicket $ticket, string $action, array $extra = []): void
    {
        AuditLog::record($actor, null, $action, array_merge([
            'ticket' => ['id' => $ticket->id, 'customer_id' => $ticket->customer_id],
            'status' => $ticket->status->value,
        ], $extra));
    }
}
