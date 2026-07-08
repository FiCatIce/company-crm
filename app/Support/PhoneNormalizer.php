<?php

namespace App\Support;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

class PhoneNormalizer
{
    /**
     * Default region used to interpret local numbers (leading-0 Indonesian mobiles).
     */
    public const DEFAULT_REGION = 'ID';

    /**
     * Normalize a phone number to canonical E.164 (e.g. "+6281234567890"), or null
     * when blank / unparseable / invalid. Backs CTI caller-ID lookup.
     */
    public static function e164(?string $number, string $region = self::DEFAULT_REGION): ?string
    {
        $number = trim((string) $number);

        if ($number === '') {
            return null;
        }

        $util = PhoneNumberUtil::getInstance();

        try {
            $parsed = $util->parse($number, $region);
        } catch (NumberParseException) {
            return null;
        }

        if (! $util->isValidNumber($parsed)) {
            return null;
        }

        return $util->format($parsed, PhoneNumberFormat::E164);
    }
}
