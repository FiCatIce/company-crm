<?php

namespace App\Enums;

/**
 * Work-queue priority (DESIGN_MAINTENANCE.md decision D7). Three values, default
 * normal — enough to bump an urgent repair without inventing an SLA system.
 */
enum TicketPriority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Rendah',
            self::Normal => 'Normal',
            self::High => 'Tinggi',
        };
    }
}
