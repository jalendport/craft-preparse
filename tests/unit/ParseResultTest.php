<?php
/**
 * Preparse plugin for Craft CMS 5.x
 *
 * @link      https://github.com/jalendport
 * @copyright Copyright (c) 2026 Jalen Davenport
 */

use jalendport\preparse\models\ParseResult;

/**
 * Unit coverage for the value envelope's round trip.
 *
 * Envelopes carrying a `DateTime` are left to the smoke checklist —
 * `Db::prepareDateForDb()` needs a booted Craft application.
 */

it('reads a full envelope back out of storage', function() {
    $result = ParseResult::fromStoredValue([
        ParseResult::KEY_VALUE => 'rendered',
        ParseResult::KEY_ERROR => 'Unknown "foo" filter.',
    ]);

    expect($result->value)->toBe('rendered')
        ->and($result->error)->toBe('Unknown "foo" filter.')
        ->and($result->hasError())->toBeTrue();
});

it('reads a Preparse 3.x bare value', function() {
    $result = ParseResult::fromStoredValue('a plain 3.x string');

    expect($result->value)->toBe('a plain 3.x string')
        ->and($result->error)->toBeNull();
});

it('treats an envelope holding only an error as a real value', function() {
    $result = ParseResult::fromStoredValue([ParseResult::KEY_ERROR => 'boom']);

    expect($result->value)->toBeNull()
        ->and($result->isEmpty())->toBeFalse();
});

it('reads nothing out of an empty row', function(mixed $stored) {
    $result = ParseResult::fromStoredValue($stored);

    expect($result->value)->toBeNull()
        ->and($result->error)->toBeNull()
        ->and($result->isEmpty())->toBeTrue();
})->with([
    [null],
    [''],
]);

it('round-trips a value through storage', function(mixed $value) {
    $result = new ParseResult();
    $result->value = $value;

    expect(ParseResult::fromStoredValue($result->toStoredValue())->value)->toBe($value);
})->with([
    ['some text'],
    [42],
    [3.5],
    [true],
    [false],
]);

it('round-trips an error through storage', function() {
    $result = new ParseResult();
    $result->value = 'kept';
    $result->error = 'Unexpected token.';

    $restored = ParseResult::fromStoredValue($result->toStoredValue());

    expect($restored->value)->toBe('kept')
        ->and($restored->error)->toBe('Unexpected token.');
});

it('stores nothing for an empty result', function() {
    expect((new ParseResult())->toStoredValue())->toBeNull();
});

it('treats a plain array as a value, not an envelope', function() {
    // Only the envelope keys mark an envelope. Anything else is a value that
    // happens to be an array, and mistaking one for the other would silently
    // drop it.
    $result = ParseResult::fromStoredValue(['first', 'second']);

    expect($result->value)->toBe(['first', 'second'])
        ->and($result->error)->toBeNull();
});

it('ignores a blank error', function() {
    $result = ParseResult::fromStoredValue([
        ParseResult::KEY_VALUE => 'fine',
        ParseResult::KEY_ERROR => '',
    ]);

    expect($result->hasError())->toBeFalse()
        ->and($result->toStoredValue())->toBe([ParseResult::KEY_VALUE => 'fine']);
});

it('ignores a blank timestamp', function() {
    $result = ParseResult::fromStoredValue([
        ParseResult::KEY_VALUE => 'fine',
        ParseResult::KEY_PARSED_AT => '',
    ]);

    expect($result->parsedAt)->toBeNull();
});

it('stores a zero, which is not the same as no value', function() {
    $result = new ParseResult();
    $result->value = 0;

    expect($result->isEmpty())->toBeFalse()
        ->and($result->toStoredValue())->toBe([ParseResult::KEY_VALUE => 0]);
});

it('stores a false value, which is not the same as no value', function() {
    $result = new ParseResult();
    $result->value = false;

    expect($result->isEmpty())->toBeFalse()
        ->and($result->toStoredValue())->toBe([ParseResult::KEY_VALUE => false]);
});
