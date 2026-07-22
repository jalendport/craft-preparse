<?php
/**
 * Preparse plugin for Craft CMS 5.x
 *
 * @link      https://github.com/jalendport
 * @copyright Copyright (c) 2026 Jalen Davenport
 */

use jalendport\base\Plugin as BasePlugin;
use jalendport\preparse\Preparse;

/**
 * Unit coverage for the plugin's packaging identity.
 *
 * Craft resolves the plugin through `extra.class` in composer.json, so a
 * rename that misses one side leaves an installable package that can't boot.
 * These checks are app-free: nothing here instantiates the plugin.
 */

it('points extra.class at the plugin class', function() {
    expect(composerJson()['extra']['class'])->toBe(Preparse::class);
});

it('uses the preparse-field handle', function() {
    expect(composerJson()['extra']['handle'])->toBe('preparse-field');
});

it('ships an English translation file for the handle', function() {
    expect(__DIR__ . '/../../src/translations/en/preparse-field.php')->toBeFile();
});

it('extends the shared craft-base plugin class', function() {
    expect(is_subclass_of(Preparse::class, BasePlugin::class))->toBeTrue();
});
