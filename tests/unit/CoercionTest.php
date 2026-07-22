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
