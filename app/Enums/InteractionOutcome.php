<?php

namespace App\Enums;

enum InteractionOutcome: string
{
    case Answered = 'answered';
    case Missed = 'missed';
    case Voicemail = 'voicemail';
    case Busy = 'busy';
    case NoAnswer = 'no_answer';
    case WrongNumber = 'wrong_number';

    public function label(): string
    {
        return match ($this) {
            self::Answered => 'Terjawab',
            self::Missed => 'Tak Terjawab',
            self::Voicemail => 'Voicemail',
            self::Busy => 'Sibuk',
            self::NoAnswer => 'Tidak Diangkat',
            self::WrongNumber => 'Salah Sambung',
        };
    }
}
