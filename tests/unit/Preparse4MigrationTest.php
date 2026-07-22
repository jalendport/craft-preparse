<?php
/**
 * Preparse plugin for Craft CMS 5.x
 *
 * Tests the Preparse 3.x to 4.0 settings migration.
 *
 * @link      https://jalendport.com
 * @copyright Copyright (c) 2026 Jalen Davenport
 */

use jalendport\preparse\migrations\m260722_000000_upgrade_preparse4;

it('maps text field settings', function() {
    expect(m260722_000000_upgrade_preparse4::mapSettings([
        'allowSelect' => true,
        'columnType' => 'TEXT',
        'displayType' => 'hidden',
        'fieldTwig' => '{{ object.title }}',
        'parseBeforeSave' => false,
        'parseOnMove' => true,
        'showField' => true,
        'textareaRows' => 8,
    ]))->toBe([
        'valueType' => 'text',
        'templateMode' => 'inline',
        'template' => '{{ object.title }}',
        'parseTiming' => 'afterPropagate',
        'parseOnMove' => true,
        'display' => 'hidden',
    ]);
});

it('maps column types and decimals', function(string $columnType, string $valueType, ?int $decimals) {
    $settings = m260722_000000_upgrade_preparse4::mapSettings([
        'columnType' => $columnType,
        'decimals' => 4,
    ]);

    expect($settings['valueType'])->toBe($valueType);

    if ($decimals === null) {
        expect($settings)->not->toHaveKey('decimals');

        return;
    }

    expect($settings['decimals'])->toBe($decimals);
})->with([
    'text' => ['TEXT', 'text', null],
    'medium text' => ['MEDIUMTEXT', 'text', null],
    'integer' => ['INTEGER', 'number', 0],
    'decimal' => ['DECIMAL', 'number', 4],
    'float' => ['FLOAT', 'number', 4],
    'date' => ['DATETIME', 'date', null],
]);

it('maps timing and value displays', function(string $displayType) {
    expect(m260722_000000_upgrade_preparse4::mapSettings([
        'displayType' => $displayType,
        'parseBeforeSave' => true,
    ]))->toMatchArray([
        'display' => 'value',
        'parseTiming' => 'inline',
    ]);
})->with(['textinput', 'textarea']);

it('uses safe defaults for incomplete settings', function() {
    expect(m260722_000000_upgrade_preparse4::mapSettings([]))->toBe([
        'valueType' => 'text',
        'templateMode' => 'inline',
        'template' => '',
        'parseTiming' => 'afterPropagate',
        'parseOnMove' => false,
        'display' => 'hidden',
    ]);
});
