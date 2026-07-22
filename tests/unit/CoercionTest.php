<?php
/**
 * Preparse plugin for Craft CMS 5.x
 *
 * @link      https://github.com/jalendport
 * @copyright Copyright (c) 2026 Jalen Davenport
 */

use jalendport\preparse\fields\PreparseField;
use jalendport\preparse\models\ParseResult;

/**
 * Unit coverage for {@see ParseResult::coerce()}.
 *
 * The date type is exercised in the smoke checklist rather than here:
 * `DateTimeHelper::toDateTime()` reads the system time zone off a booted Craft
 * application, and these tests are deliberately app-free.
 */

it('trims text and treats blank output as no value', function(string $rendered, ?string $expected) {
    expect(ParseResult::coerce($rendered, PreparseField::VALUE_TYPE_TEXT))->toBe($expected);
})->with([
    ['hello', 'hello'],
    ["  padded \n", 'padded'],
    ['', null],
    ['   ', null],
]);

it('coerces numbers to integers when no decimals are configured', function(mixed $rendered, ?int $expected) {
    expect(ParseResult::coerce($rendered, PreparseField::VALUE_TYPE_NUMBER))->toBe($expected);
})->with([
    ['42', 42],
    ['42.6', 43],
    [7, 7],
    ['-3', -3],
    ['', null],
]);

it('rounds numbers to the configured decimals', function() {
    expect(ParseResult::coerce('3.14159', PreparseField::VALUE_TYPE_NUMBER, 2))->toBe(3.14);
});

it('rejects non-numeric output rather than storing zero', function(string $rendered) {
    expect(ParseResult::coerce($rendered, PreparseField::VALUE_TYPE_NUMBER))->toBeNull();
})->with([
    ['not a number'],
    ['1,234.50'],
    ['12 apples'],
]);

it('reads the usual truthy and falsy words as booleans', function(mixed $rendered, ?bool $expected) {
    expect(ParseResult::coerce($rendered, PreparseField::VALUE_TYPE_BOOLEAN))->toBe($expected);
})->with([
    ['true', true],
    ['yes', true],
    ['1', true],
    ['anything else', true],
    ['false', false],
    ['no', false],
    ['off', false],
    ['0', false],
    ['', null],
]);

it('leaves already-coerced values alone', function() {
    expect(ParseResult::coerce(12, PreparseField::VALUE_TYPE_NUMBER))->toBe(12)
        ->and(ParseResult::coerce(true, PreparseField::VALUE_TYPE_BOOLEAN))->toBeTrue()
        ->and(ParseResult::coerce(false, PreparseField::VALUE_TYPE_BOOLEAN))->toBeFalse()
        ->and(ParseResult::coerce('done', PreparseField::VALUE_TYPE_TEXT))->toBe('done');
});

/*
| Coercion runs more than once over a value's life — a patched value is pushed
| back onto the element and normalized again on the next read — so coercing an
| already-coerced value has to be a no-op. This pins that invariant rather than
| trusting it.
*/

it('is idempotent', function(string $valueType, mixed $rendered, int $decimals) {
    $once = ParseResult::coerce($rendered, $valueType, $decimals);

    expect(ParseResult::coerce($once, $valueType, $decimals))->toBe($once);
})->with([
    'text' => [PreparseField::VALUE_TYPE_TEXT, '  spaced  ', 0],
    'empty text' => [PreparseField::VALUE_TYPE_TEXT, '   ', 0],
    'whole number' => [PreparseField::VALUE_TYPE_NUMBER, '42.6', 0],
    'decimal number' => [PreparseField::VALUE_TYPE_NUMBER, '3.14159', 2],
    'unparseable number' => [PreparseField::VALUE_TYPE_NUMBER, 'nope', 0],
    'true' => [PreparseField::VALUE_TYPE_BOOLEAN, 'yes', 0],
    'false' => [PreparseField::VALUE_TYPE_BOOLEAN, 'off', 0],
    'empty boolean' => [PreparseField::VALUE_TYPE_BOOLEAN, '', 0],
]);

it('accepts scientific notation as a number', function() {
    expect(ParseResult::coerce('1e3', PreparseField::VALUE_TYPE_NUMBER))->toBe(1000);
});

it('rounds rather than truncates', function() {
    expect(ParseResult::coerce('2.5', PreparseField::VALUE_TYPE_NUMBER))->toBe(3)
        ->and(ParseResult::coerce('-2.5', PreparseField::VALUE_TYPE_NUMBER))->toBe(-3);
});

it('refuses to make text out of structured output', function(mixed $rendered) {
    expect(ParseResult::coerce($rendered, PreparseField::VALUE_TYPE_TEXT))->toBeNull();
})->with([
    'array' => [['a', 'b']],
    'object' => [new stdClass()],
]);

it('reads a boolean out of numeric output', function(mixed $rendered, bool $expected) {
    expect(ParseResult::coerce($rendered, PreparseField::VALUE_TYPE_BOOLEAN))->toBe($expected);
})->with([
    [1, true],
    [0, false],
    ['0.0', false],
    ['-1', true],
]);

it('never makes a date out of a boolean', function() {
    expect(ParseResult::coerce(true, PreparseField::VALUE_TYPE_DATE))->toBeNull()
        ->and(ParseResult::coerce(false, PreparseField::VALUE_TYPE_DATE))->toBeNull();
});
