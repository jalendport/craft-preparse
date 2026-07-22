<?php
/**
 * Preparse plugin for Craft CMS 5.x
 *
 * Renders a Twig template when an element is saved and stores the typed result.
 *
 * @link      https://jalendport.com
 * @copyright Copyright (c) 2026 Jalen Davenport
 */

namespace jalendport\preparse;

use jalendport\base\Plugin;

/**
 * Preparse plugin.
 *
 * The main class is deliberately lean: component registration lives in the
 * static {@see config()} method, and {@see init()} is a table of contents of
 * private `_registerXxx()` methods. Console controllers under
 * `console\controllers` are wired up automatically by Craft, so there is no
 * console `controllerNamespace` bookkeeping here.
 *
 * @author Jalen Davenport <hello@jalendport.com>
 * @since 4.0.0
 */
class Preparse extends Plugin
{
    // Static Properties
    // =========================================================================

    /**
     * @var Preparse the plugin instance
     * @since 4.0.0
     */
    public static Preparse $plugin;

    // Public Properties
    // =========================================================================

    /**
     * @var bool whether the plugin has a settings page in the control panel
     * @since 4.0.0
     */
    public bool $hasCpSettings = false;

    /**
     * @var string the plugin's schema version
     *
     * Carried over from the 3.x line so the 4.0 upgrade migration has a known
     * starting point on existing installs.
     *
     * @since 4.0.0
     */
    public string $schemaVersion = '1.1.0';

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    public function init(): void
    {
        parent::init();
        self::$plugin = $this;
    }
}
