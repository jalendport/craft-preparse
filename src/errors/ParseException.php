<?php
/**
 * Preparse plugin for Craft CMS 5.x
 *
 * Renders a Twig template when an element is saved and stores the typed result.
 *
 * @link      https://jalendport.com
 * @copyright Copyright (c) 2026 Jalen Davenport
 */

namespace jalendport\preparse\errors;

use yii\base\Exception;

/**
 * Thrown when a preparse field fails to render and its “On error” setting is
 * “Block save”.
 *
 * Raising this from inside the save lifecycle rolls the element's transaction
 * back, which is the only way to genuinely block a save once the element has
 * already been written and propagated.
 *
 * @author Jalen Davenport <hello@jalendport.com>
 * @since 4.0.0
 */
class ParseException extends Exception
{
    /**
     * @inheritdoc
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    public function getName(): string
    {
        return 'Preparse Error';
    }
}
