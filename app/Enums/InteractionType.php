<?php

namespace App\Enums;

enum InteractionType: string
{
    case Call = 'call';
    case WhatsApp = 'whatsapp';
    case Email = 'email';
    case Note = 'note';
    case Visit = 'visit';

    public function label(): string
    {
        return match ($this) {
            self::Call => 'Telepon',
            self::WhatsApp => 'WhatsApp',
            self::Email => 'Email',
            self::Note => 'Catatan',
            self::Visit => 'Kunjungan',
        };
    }
}
