<?php

use App\Support\Phone;

it('normalizes 10-digit input by prefixing +7', function () {
    expect(Phone::normalize('9991234567'))->toBe('+79991234567');
});

it('normalizes 11-digit input starting with 7', function () {
    expect(Phone::normalize('79991234567'))->toBe('+79991234567');
});

it('normalizes 11-digit input starting with 8 to +7', function () {
    expect(Phone::normalize('89991234567'))->toBe('+79991234567');
});

it('normalizes input with formatting characters', function () {
    expect(Phone::normalize('+7 (999) 123-45-67'))->toBe('+79991234567');
    expect(Phone::normalize('8-999-123-45-67'))->toBe('+79991234567');
    expect(Phone::normalize(' +7 999 1234567 '))->toBe('+79991234567');
});

it('returns null for empty or null input', function () {
    expect(Phone::normalize(null))->toBeNull();
    expect(Phone::normalize(''))->toBeNull();
    expect(Phone::normalize('   '))->toBeNull();
});

it('returns null for too short or too long numbers', function () {
    expect(Phone::normalize('12345'))->toBeNull();
    expect(Phone::normalize('123456789012'))->toBeNull();
});

it('returns null for non-Russian country codes', function () {
    expect(Phone::normalize('+1 555 1234567'))->toBeNull();
    expect(Phone::normalize('44 7700 900123'))->toBeNull();
});

it('reports validity via isValid', function () {
    expect(Phone::isValid('+79991234567'))->toBeTrue();
    expect(Phone::isValid('not-a-phone'))->toBeFalse();
    expect(Phone::isValid(null))->toBeFalse();
});
