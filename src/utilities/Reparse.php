<?php
/**
 * Preparse plugin for Craft CMS 5.x
 *
 * Renders a Twig template when an element is saved and stores the typed result.
 *
 * @link      https://jalendport.com
 * @copyright Copyright (c) 2026 Jalen Davenport
 */

namespace jalendport\preparse\utilities;

use Craft;
use craft\base\Utility;
use craft\console\Application as ConsoleApplication;
use craft\web\Application as WebApplication;
use jalendport\preparse\fields\PreparseField;
use jalendport\preparse\Preparse;
use Throwable;

/**
 * Control panel utility for reparsing fields and reviewing parse errors.
 *
 * The error table is the half that doesn't exist anywhere else: Preparse 3.x
 * swallowed template errors entirely, so a field could stop rendering and
 * nobody would find out until someone noticed a blank page.
 *
 * @author Jalen Davenport <hello@jalendport.com>
 * @since 4.0.0
 */
class Reparse extends Utility
{
    // Static Methods
    // =========================================================================

    /**
     * Returns the number of stored parse errors, shown as a badge on the utility.
     *
     * @inheritdoc
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    public static function badgeCount(): int
    {
        try {
            return Preparse::$plugin->values->errorCount();
        } catch (Throwable) {
            // A badge is not worth breaking the control panel nav over.
            return 0;
        }
    }

    /**
     * @inheritdoc
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    public static function contentHtml(): string
    {
        /** @var WebApplication|ConsoleApplication $app */
        $app = Craft::$app;
        $fieldsService = $app->getFields();

        /** @var PreparseField[] $fields */
        $fields = $fieldsService->getFieldsByType(PreparseField::class);

        return $app->getView()->renderTemplate('preparse-field/_utility.twig', [
            'errors' => Preparse::$plugin->values->recentErrors(),
            'fields' => $fields,
            'sections' => $app->getEntries()->getAllSections(),
            'sites' => $app->getSites()->getAllSites(),
        ]);
    }

    /**
     * @inheritdoc
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    public static function displayName(): string
    {
        return Craft::t('preparse-field', 'Preparse');
    }

    /**
     * @inheritdoc
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    public static function icon(): ?string
    {
        return 'wand-magic-sparkles';
    }

    /**
     * @inheritdoc
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    public static function id(): string
    {
        return 'preparse';
    }
}
