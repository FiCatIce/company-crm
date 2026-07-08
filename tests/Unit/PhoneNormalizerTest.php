<?php

use App\Support\PhoneNormalizer;

it('normalizes a local Indonesian mobile number to E.164', function () {
    expect(PhoneNormalizer::e164('081234567890'))->toBe('+6281234567890');
});

it('keeps an already-normalized E.164 number', function () {
    expect(PhoneNormalizer::e164('+6281234567890'))->toBe('+6281234567890');
});

it('handles locally formatted numbers with separators and spaces', function () {
    expect(PhoneNormalizer::e164('0812-3456-7890'))->toBe('+6281234567890')
        ->and(PhoneNormalizer::e164('0812 3456 7890'))->toBe('+6281234567890');
});

it('returns null for blank or unparseable input', function () {
    expect(PhoneNormalizer::e164(null))->toBeNull()
        ->and(PhoneNormalizer::e164(''))->toBeNull()
        ->and(PhoneNormalizer::e164('   '))->toBeNull()
        ->and(PhoneNormalizer::e164('not-a-number'))->toBeNull();
});
