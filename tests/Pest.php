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
use jalendport\preparse\jobs\ReparseElements;

/**
 * @return array<string, mixed>
 */
function composerJson(): array
{
    return json_decode(file_get_contents(__DIR__ . '/../composer.json'), true);
}

/*
| Constructing a field or a queue job normally runs Yii's `init()` chain, which
| reaches for a booted application — `BaseBatchedJob::init()` reads the queue's
| TTR, for one. Reflection skips the constructor; declared property defaults
| still apply, so the configuration these objects expose is real.
*/

/**
 * @param array<string, mixed> $config
 */
function configured(string $class, array $config = []): object
{
    $object = (new ReflectionClass($class))->newInstanceWithoutConstructor();

    foreach ($config as $name => $value) {
        $object->$name = $value;
    }

    return $object;
}

/**
 * @param array<string, mixed> $settings
 */
function preparseField(array $settings = []): PreparseField
{
    /** @var PreparseField */
    return configured(PreparseField::class, $settings);
}

/**
 * @param array<string, mixed> $config
 */
function reparseJob(array $config = []): ReparseElements
{
    /** @var ReparseElements */
    return configured(ReparseElements::class, $config);
}
