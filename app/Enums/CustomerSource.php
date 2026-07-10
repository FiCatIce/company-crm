<?php

namespace App\Enums;

enum CustomerSource: string
{
    case Referral = 'referral';
    case WalkIn = 'walk_in';
    case Online = 'online';
    case Reseller = 'reseller';
    case Event = 'event';
    case Cti = 'cti';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Referral => 'Referral',
            self::WalkIn => 'Datang Langsung',
            self::Online => 'Online',
            self::Reseller => 'Reseller',
            self::Event => 'Event',
            self::Cti => 'Telepon Masuk',
            self::Other => 'Lainnya',
        };
    }
}
