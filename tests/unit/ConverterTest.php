<?php
/**
 * Preparse plugin for Craft CMS 5.x
 *
 * Tests generated-field conversion mapping.
 *
 * @link      https://jalendport.com
 * @copyright Copyright (c) 2026 Jalen Davenport
 */

use jalendport\preparse\services\Converter;

it('maps a generated field to text and inline template settings', function() {
    $template = "{{ object.title }}\n{author.fullName}";

    expect(Converter::settingsFor([
        'uid' => '3ad14fcc-e4a8-471a-9941-7601534a4db8',
        'name' => 'Byline',
        'handle' => 'byline',
        'template' => $template,
    ]))->toBe([
        'valueType' => 'text',
        'templateMode' => 'inline',
        'template' => $template,
    ]);
});

it('uses an empty inline template when the generated config has none', function() {
    expect(Converter::settingsFor([
        'name' => 'Byline',
        'handle' => 'byline',
    ]))->toBe([
        'valueType' => 'text',
        'templateMode' => 'inline',
        'template' => '',
    ]);
});
