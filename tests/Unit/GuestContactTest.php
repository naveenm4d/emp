<?php

use App\Domains\Guest\DTOs\GuestContact;

it('normalises phone numbers', function (?string $input, ?string $expected) {
    expect(GuestContact::phone($input))->toBe($expected);
})->with([
    ['+1 (555) 010-0200', '+15550100200'],
    ['555.010.0200', '5550100200'],
    ['   ', null],
    [null, null],
]);

it('normalises emails', function () {
    expect(GuestContact::email('  Ada@Example.COM '))->toBe('ada@example.com')
        ->and(GuestContact::email(''))->toBeNull();
});
