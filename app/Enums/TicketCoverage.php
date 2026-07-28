<?php

namespace App\Enums;

/**
 * Who pays for the repair (DESIGN_MAINTENANCE.md decision D4). Deliberately an
 * enum and NOT an amount: money never enters the ticket. A paid repair is
 * recorded as a Transaction by a role holding the money permissions; the ticket
 * only states which side of the warranty line the work falls on.
 */
enum TicketCoverage: string
{
    case Warranty = 'warranty';
    case Paid = 'paid';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Warranty => 'Garansi',
            self::Paid => 'Berbayar',
            self::Unknown => 'Belum Ditentukan',
        };
    }
}
