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

/**
 * @return array<string, mixed>
 */
function composerJson(): array
{
    return json_decode(file_get_contents(__DIR__ . '/../composer.json'), true);
}
