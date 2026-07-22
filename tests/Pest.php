<?php
/**
 * Preparse plugin for Craft CMS 5.x
 *
 * @link      https://github.com/jalendport
 * @copyright Copyright (c) 2026 Jalen Davenport
 */

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
|
| These tests never boot a Craft application — Craft-coupled flows are covered
| by the manual smoke checklist instead. Helpers that stub service seams live
| here as the suite grows.
*/

use jalendport\preparse\fields\PreparseField;

/**
 * @return array<string, mixed>
 */
function composerJson(): array
{
    return json_decode(file_get_contents(__DIR__ . '/../composer.json'), true);
}

/*
| Constructing a field normally runs Craft's component `init()` chain, which
| reaches for a booted application. Reflection skips the constructor so the
| settings-driven mapping methods can be exercised on their own.
*/

/**
 * @param array<string, mixed> $settings
 */
function preparseField(array $settings = []): PreparseField
{
    $field = (new ReflectionClass(PreparseField::class))->newInstanceWithoutConstructor();

    foreach ($settings as $name => $value) {
        $field->$name = $value;
    }

    return $field;
}
