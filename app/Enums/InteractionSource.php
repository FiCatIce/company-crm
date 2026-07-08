<?php

namespace App\Enums;

enum InteractionSource: string
{
    case Manual = 'manual';
    case Cti = 'cti';
    case Import = 'import';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::Cti => 'Sistem Telepon',
            self::Import => 'Impor',
        };
    }
}
