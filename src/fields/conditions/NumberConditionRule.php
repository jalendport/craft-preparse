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

use craft\base\conditions\BaseNumberConditionRule;
use craft\fields\conditions\FieldConditionRuleInterface;
use craft\fields\conditions\FieldConditionRuleTrait;
use jalendport\preparse\fields\PreparseField;

/**
 * Condition rule for preparse fields storing numbers.
 *
 * Gives editors the greater-than/less-than/between UI that Craft's generated
 * fields can't offer, because the underlying value is a real number rather than
 * a string.
 *
 * @author Jalen Davenport <hello@jalendport.com>
 * @since 4.0.0
 */
class NumberConditionRule extends BaseNumberConditionRule implements FieldConditionRuleInterface
{
    use FieldConditionRuleTrait;

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    protected function elementQueryParam(): string|array|null
    {
        if (!$this->_appliesToField()) {
            return null;
        }

        return $this->paramValue();
    }

    /**
     * @inheritdoc
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    protected function matchFieldValue($value): bool
    {
        if (!$this->_appliesToField()) {
            return true;
        }

        /** @var int|float|null $value */
        return $this->matchValue($value);
    }

    // Private Methods
    // =========================================================================

    /**
     * Returns whether the rule's field is still configured for this value type.
     *
     * A field's value type can be changed after a condition has been saved, at
     * which point the stored rule no longer describes anything meaningful.
     *
     * @return bool whether the rule applies
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    private function _appliesToField(): bool
    {
        $field = $this->field();

        return $field instanceof PreparseField && $field->valueType === PreparseField::VALUE_TYPE_NUMBER;
    }
}
