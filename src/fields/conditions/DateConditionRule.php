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

use craft\base\conditions\BaseDateRangeConditionRule;
use craft\fields\conditions\FieldConditionRuleInterface;
use craft\fields\conditions\FieldConditionRuleTrait;
use DateTime;
use jalendport\preparse\fields\PreparseField;
use yii\base\InvalidConfigException;

/**
 * Condition rule for preparse fields storing dates.
 *
 * @author Jalen Davenport <hello@jalendport.com>
 * @since 4.0.0
 */
class DateConditionRule extends BaseDateRangeConditionRule implements FieldConditionRuleInterface
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
    protected function elementQueryParam(): array|string|null
    {
        if (!$this->_appliesToField()) {
            return null;
        }

        return $this->queryParamValue();
    }

    /**
     * @inheritdoc
     * @throws InvalidConfigException if the rule's field no longer stores dates
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    protected function inputHtml(): string
    {
        if (!$this->_appliesToField()) {
            throw new InvalidConfigException('This condition rule is only valid for preparse fields storing dates.');
        }

        return parent::inputHtml();
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

        /** @var DateTime|null $value */
        return $this->matchValue($value);
    }

    // Private Methods
    // =========================================================================

    /**
     * Returns whether the rule's field is still configured for this value type.
     *
     * @return bool whether the rule applies
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    private function _appliesToField(): bool
    {
        $field = $this->field();

        return $field instanceof PreparseField && $field->valueType === PreparseField::VALUE_TYPE_DATE;
    }
}
