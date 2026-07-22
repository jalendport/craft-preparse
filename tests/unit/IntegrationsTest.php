<?php
/**
 * Preparse plugin for Craft CMS 5.x
 *
 * @link      https://github.com/jalendport
 * @copyright Copyright (c) 2026 Jalen Davenport
 */

use GraphQL\Type\Definition\Type;
use jalendport\preparse\fields\conditions\BooleanConditionRule;
use jalendport\preparse\fields\conditions\DateConditionRule;
use jalendport\preparse\fields\conditions\NumberConditionRule;
use jalendport\preparse\fields\conditions\TextConditionRule;
use jalendport\preparse\fields\PreparseField;

/**
 * Unit coverage for the settings-driven mapping behind the integrations:
 * which condition rule, GraphQL type, and column type each value type picks.
 *
 * Only the app-free branches are covered. Sorting, previews, and the non-scalar
 * GraphQL types all reach a booted Craft application — a database connection,
 * the formatter, or `GqlEntityRegistry`, which reads the type prefix off the
 * general config — so they belong to the smoke checklist. `Type::string()` and
 * `Type::boolean()` are plain graphql-php singletons and need nothing.
 */

it('picks a condition rule per value type', function(string $valueType, string $expected) {
    expect(preparseField(['valueType' => $valueType])->getElementConditionRuleType())->toBe($expected);
})->with([
    [PreparseField::VALUE_TYPE_TEXT, TextConditionRule::class],
    [PreparseField::VALUE_TYPE_NUMBER, NumberConditionRule::class],
    [PreparseField::VALUE_TYPE_BOOLEAN, BooleanConditionRule::class],
    [PreparseField::VALUE_TYPE_DATE, DateConditionRule::class],
]);

it('advertises a string GraphQL type for text', function() {
    expect(preparseField(['valueType' => PreparseField::VALUE_TYPE_TEXT])->getContentGqlType())
        ->toBe(Type::string());
});

it('advertises a boolean GraphQL type for booleans', function() {
    expect(preparseField(['valueType' => PreparseField::VALUE_TYPE_BOOLEAN])->getContentGqlType())
        ->toBe(Type::boolean());
});

it('narrows the GraphQL query argument to a boolean only for booleans', function() {
    $boolean = preparseField(['valueType' => PreparseField::VALUE_TYPE_BOOLEAN, 'handle' => 'flag']);

    expect($boolean->getContentGqlQueryArgumentType())
        ->toBe(['name' => 'flag', 'type' => Type::boolean()]);
});

it('casts to a real column type so sorting is typed, not lexicographic', function(string $valueType, int $decimals, string $expected) {
    expect(preparseField(['valueType' => $valueType, 'decimals' => $decimals])->valueDbType())->toBe($expected);
})->with([
    'text' => [PreparseField::VALUE_TYPE_TEXT, 0, 'text'],
    'boolean' => [PreparseField::VALUE_TYPE_BOOLEAN, 0, 'boolean'],
    'date' => [PreparseField::VALUE_TYPE_DATE, 0, 'datetime'],
    'whole numbers' => [PreparseField::VALUE_TYPE_NUMBER, 0, 'integer'],
]);

it('exposes the value key first, so Craft treats it as primary', function() {
    expect(array_key_first(PreparseField::dbType()))->toBe('value');
});
