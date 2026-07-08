<?php

namespace App\Enums;

enum CustomerStatus: string
{
    case Lead = 'lead';
    case Active = 'active';
    case Inactive = 'inactive';
    case Churned = 'churned';

    public function label(): string
    {
        return match ($this) {
            self::Lead => 'Prospek',
            self::Active => 'Aktif',
            self::Inactive => 'Tidak Aktif',
            self::Churned => 'Berhenti',
        };
    }
}
