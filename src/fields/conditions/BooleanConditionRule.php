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

use craft\base\conditions\BaseLightswitchConditionRule;
use craft\fields\conditions\FieldConditionRuleInterface;
use craft\fields\conditions\FieldConditionRuleTrait;
use jalendport\preparse\fields\PreparseField;
use yii\base\InvalidConfigException;

/**
 * Condition rule for preparse fields storing booleans.
 *
 * @author Jalen Davenport <hello@jalendport.com>
 * @since 4.0.0
 */
class BooleanConditionRule extends BaseLightswitchConditionRule implements FieldConditionRuleInterface
{
    use FieldConditionRuleTrait;

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    protected function elementQueryParam(): ?bool
    {
        if (!$this->_appliesToField()) {
            return null;
        }

        return $this->value;
    }

    /**
     * @inheritdoc
     * @throws InvalidConfigException if the rule's field no longer stores booleans
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    protected function inputHtml(): string
    {
        if (!$this->_appliesToField()) {
            throw new InvalidConfigException('This condition rule is only valid for preparse fields storing booleans.');
        }

        return parent::inputHtml();
    }

    /**
     * @inheritdoc
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    protected function matchFieldValue($value): bool
    {
        if (!$this->_appliesToField()) {
            return true;
        }

        // A field that has never been parsed holds null, which the lightswitch
        // base can't take — treat it as off.
        return $this->matchValue((bool)$value);
    }

    // Private Methods
    // =========================================================================

    /**
     * Returns whether the rule's field is still configured for this value type.
     *
     * @return bool whether the rule applies
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    private function _appliesToField(): bool
    {
        $field = $this->field();

        return $field instanceof PreparseField && $field->valueType === PreparseField::VALUE_TYPE_BOOLEAN;
    }
}
