<?php
/**
 * Preparse plugin for Craft CMS 5.x
 *
 * Renders a Twig template when an element is saved and stores the typed result.
 *
 * @link      https://jalendport.com
 * @copyright Copyright (c) 2026 Jalen Davenport
 */

namespace jalendport\preparse\fields\conditions;

use craft\base\conditions\BaseTextConditionRule;
use craft\fields\conditions\FieldConditionRuleInterface;
use craft\fields\conditions\FieldConditionRuleTrait;

/**
 * Condition rule for preparse fields storing text.
 *
 * @author Jalen Davenport <hello@jalendport.com>
 * @since 4.0.0
 */
class TextConditionRule extends BaseTextConditionRule implements FieldConditionRuleInterface
{
    use FieldConditionRuleTrait;

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     * @return array<string, mixed>|null
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    protected function elementQueryParam(): ?array
    {
        $value = $this->paramValue();

        if ($value === null) {
            return null;
        }

        return [
            'value' => $value,
            'caseInsensitive' => true,
        ];
    }

    /**
     * @inheritdoc
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    protected function matchFieldValue($value): bool
    {
        return $this->matchValue($value !== null ? (string)$value : null);
    }
}
