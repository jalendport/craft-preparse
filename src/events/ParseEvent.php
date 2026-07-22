<?php
/**
 * Preparse plugin for Craft CMS 5.x
 *
 * Renders a Twig template when an element is saved and stores the typed result.
 *
 * @link      https://jalendport.com
 * @copyright Copyright (c) 2026 Jalen Davenport
 */

namespace jalendport\preparse\events;

use craft\base\ElementInterface;
use jalendport\preparse\fields\PreparseField;
use jalendport\preparse\models\ParseResult;
use yii\base\Event;

/**
 * Event fired after a preparse field's template has been rendered.
 *
 * This is the instrumentation seam that automatic dependency tracking will hang
 * off in 4.1: a listener can inspect what the render touched and record cache
 * tags against the element, so dependents can be requeued when they change.
 * Nothing in 4.0 listens to it, but the render path fires it from day one so
 * the contract is stable.
 *
 * @author Jalen Davenport <hello@jalendport.com>
 * @since 4.0.0
 */
class ParseEvent extends Event
{
    // Public Properties
    // =========================================================================

    /**
     * @var ElementInterface The element the template was rendered for, in the site it was rendered for
     * @since 4.0.0
     */
    public ElementInterface $element;

    /**
     * @var PreparseField The field that was rendered
     * @since 4.0.0
     */
    public PreparseField $field;

    /**
     * @var ParseResult The render result, including any captured error
     * @since 4.0.0
     */
    public ParseResult $result;
}
