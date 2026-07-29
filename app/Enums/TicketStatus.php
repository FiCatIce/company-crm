<?php

namespace App\Enums;

/**
 * Service-ticket lifecycle (DESIGN_MAINTENANCE.md §1.3, decision D2): a deliberate
 * 4-state flow. Richer pipelines (diagnosing, waiting_parts) are string-backed
 * extensions later, not a redesign. `done` is final — a returning unit gets a NEW
 * ticket (D8), so history reads as what actually happened: two visits, two rows.
 */
enum TicketStatus: string
{
    case Received = 'received';
    case InProgress = 'in_progress';
    case Done = 'done';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Diterima',
            self::InProgress => 'Dikerjakan',
            self::Done => 'Selesai',
            self::Cancelled => 'Dibatalkan',
        };
    }

    /**
     * Whether the ticket still needs work — the "active" bucket every list
     * filter and dashboard card shares, so the definition lives in one place.
     */
    public function isActive(): bool
    {
        return $this === self::Received || $this === self::InProgress;
    }

    /**
     * The active bucket as a whereIn-able list. Every "tiket aktif" figure
     * (dashboard cards, /service-tickets stats, the ?status=active filter)
     * counts through this one definition, so the numbers cannot drift apart.
     *
     * @return list<self>
     */
    public static function active(): array
    {
        return array_values(array_filter(self::cases(), fn (self $status): bool => $status->isActive()));
    }
}
