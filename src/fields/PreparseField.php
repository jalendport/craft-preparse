<?php
/**
 * Preparse plugin for Craft CMS 5.x
 *
 * Renders a Twig template when an element is saved and stores the typed result.
 *
 * @link      https://jalendport.com
 * @copyright Copyright (c) 2026 Jalen Davenport
 */

namespace jalendport\preparse\fields;

use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\base\PreviewableFieldInterface;
use craft\base\SortableFieldInterface;
use craft\console\Application as ConsoleApplication;
use craft\elements\Entry;
use craft\gql\types\DateTime as DateTimeType;
use craft\gql\types\Number as NumberType;
use craft\helpers\Db;
use craft\helpers\Html;
use craft\helpers\UrlHelper;
use craft\i18n\Locale;
use craft\web\Application as WebApplication;
use DateTime;
use GraphQL\Type\Definition\Type;
use jalendport\preparse\errors\ParseException;
use jalendport\preparse\fields\conditions\BooleanConditionRule;
use jalendport\preparse\fields\conditions\DateConditionRule;
use jalendport\preparse\fields\conditions\NumberConditionRule;
use jalendport\preparse\fields\conditions\TextConditionRule;
use jalendport\preparse\models\ParseResult;
use jalendport\preparse\Preparse;
use nystudio107\codeeditor\CodeEditor;
use yii\db\ExpressionInterface;
use yii\db\Schema;

/**
 * A field whose value is rendered from a Twig template when the element saves.
 *
 * Values live in the `elements_sites.content` JSON as a three-key envelope
 * (see {@see ParseResult}) rather than a bare scalar, and the field advertises
 * a *typed* SQL expression for the value key — so a number field sorts
 * numerically and a date field compares as a date, instead of lexicographically
 * like Craft's generated fields (#60, #74, #93, #104).
 *
 * The field itself never renders anything. Depending on “Parse timing”, that's
 * either the after-propagate listener wired up in {@see Preparse} or, for
 * inline mode, {@see serializeValueForDb()}.
 *
 * @author Jalen Davenport <hello@jalendport.com>
 * @since 4.0.0
 */
class PreparseField extends Field implements PreviewableFieldInterface, SortableFieldInterface
{
    // Const Properties
    // =========================================================================

    /**
     * @var string Keep the field out of the element edit page entirely.
     * @since 4.0.0
     */
    public const DISPLAY_HIDDEN = 'hidden';

    /**
     * @var string Show the stored value on the element edit page, read-only.
     * @since 4.0.0
     */
    public const DISPLAY_VALUE = 'value';

    /**
     * @var string Fail the save when the template can't be rendered.
     * @since 4.0.0
     */
    public const ON_ERROR_BLOCK_SAVE = 'blockSave';

    /**
     * @var string Store the fallback value when the template can't be rendered.
     * @since 4.0.0
     */
    public const ON_ERROR_FALLBACK = 'fallback';

    /**
     * @var string Leave the previous value in place when the template can't be rendered.
     * @since 4.0.0
     */
    public const ON_ERROR_KEEP_PREVIOUS = 'keepPrevious';

    /**
     * @var string Render once the element and everything it owns has been saved.
     * @since 4.0.0
     */
    public const PARSE_TIMING_AFTER_PROPAGATE = 'afterPropagate';

    /**
     * @var string Render during the save itself, so the value is final before other plugins see it.
     * @since 4.0.0
     */
    public const PARSE_TIMING_INLINE = 'inline';

    /**
     * @var string Render a template file from the site template root.
     * @since 4.0.0
     */
    public const TEMPLATE_MODE_FILE = 'file';

    /**
     * @var string Render a Twig snippet stored in the field's settings.
     * @since 4.0.0
     */
    public const TEMPLATE_MODE_INLINE = 'inline';

    /**
     * @var string Store the value as a boolean.
     * @since 4.0.0
     */
    public const VALUE_TYPE_BOOLEAN = 'boolean';

    /**
     * @var string Store the value as a date.
     * @since 4.0.0
     */
    public const VALUE_TYPE_DATE = 'date';

    /**
     * @var string Store the value as a number.
     * @since 4.0.0
     */
    public const VALUE_TYPE_NUMBER = 'number';

    /**
     * @var string Store the value as text.
     * @since 4.0.0
     */
    public const VALUE_TYPE_TEXT = 'text';

    /**
     * @var string Re-render on every save.
     * @since 4.0.0
     */
    public const WHEN_TO_PARSE_ALWAYS = 'always';

    /**
     * @var string Render only while the stored value is empty.
     * @since 4.0.0
     */
    public const WHEN_TO_PARSE_WHEN_EMPTY = 'whenEmpty';

    // Public Properties
    // =========================================================================

    /**
     * @var int The number of decimal places to keep, for the number value type. `0` means integer semantics.
     * @since 4.0.0
     */
    public int $decimals = 0;

    /**
     * @var string How the field appears on element edit pages: `hidden` or `value`
     * @since 4.0.0
     */
    public string $display = self::DISPLAY_HIDDEN;

    /**
     * @var string The value to store when a render fails and `onError` is `fallback`
     * @since 4.0.0
     */
    public string $fallbackValue = '';

    /**
     * @var string What to do when a render fails: `keepPrevious`, `fallback`, or `blockSave`
     * @since 4.0.0
     */
    public string $onError = self::ON_ERROR_KEEP_PREVIOUS;

    /**
     * @var bool Whether to re-render when the element is moved within a structure
     * @since 4.0.0
     */
    public bool $parseOnMove = false;

    /**
     * @var string When to render: `afterPropagate` or `inline`
     * @since 4.0.0
     */
    public string $parseTiming = self::PARSE_TIMING_AFTER_PROPAGATE;

    /**
     * @var string The Twig snippet, or the site template path, depending on `templateMode`
     * @since 4.0.0
     */
    public string $template = '';

    /**
     * @var string Where the template comes from: `inline` or `file`
     * @since 4.0.0
     */
    public string $templateMode = self::TEMPLATE_MODE_INLINE;

    /**
     * @var string The value type to coerce to: `text`, `number`, `boolean`, or `date`
     * @since 4.0.0
     */
    public string $valueType = self::VALUE_TYPE_TEXT;

    /**
     * @var string Whether to render on every save or only while empty: `always` or `whenEmpty`
     * @since 4.0.0
     */
    public string $whenToParse = self::WHEN_TO_PARSE_ALWAYS;

    // Static Methods
    // =========================================================================

    /**
     * Returns the three keys a preparse value occupies in the content JSON.
     *
     * The value key comes first so Craft treats it as the primary one when
     * building sort and filter expressions.
     *
     * @inheritdoc
     * @return array<string, string>
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    public static function dbType(): array
    {
        return [
            ParseResult::KEY_VALUE => Schema::TYPE_TEXT,
            ParseResult::KEY_ERROR => Schema::TYPE_TEXT,
            ParseResult::KEY_PARSED_AT => Schema::TYPE_DATETIME,
        ];
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
    public static function icon(): string
    {
        return 'wand-magic-sparkles';
    }

    /**
     * @inheritdoc
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    public static function phpType(): string
    {
        return sprintf('string|int|float|bool|\\%s|null', DateTime::class);
    }

    /**
     * Builds a query condition using the parser appropriate to the value type.
     *
     * Craft's generated fields only ever compare as strings, which is why
     * numeric ranges and date queries can't be expressed against them. The
     * value SQL here is already cast to the configured type, so the matching
     * parser produces a genuine typed comparison.
     *
     * @inheritdoc
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    public static function queryCondition(
        array $instances,
        mixed $value,
        array &$params,
    ): array|string|ExpressionInterface|false|null {
        $valueSql = static::valueSql($instances);

        if ($valueSql === null) {
            return false;
        }

        /** @var self $field */
        $field = $instances[0];

        return match ($field->valueType) {
            self::VALUE_TYPE_BOOLEAN => Db::parseBooleanParam($valueSql, $value, null, Schema::TYPE_JSON),
            self::VALUE_TYPE_DATE => Db::parseDateParam($valueSql, $value),
            self::VALUE_TYPE_NUMBER => Db::parseNumericParam($valueSql, $value, columnType: $field->valueDbType()),
            default => Db::parseParam($valueSql, $value, columnType: Schema::TYPE_JSON),
        };
    }

    // Public Methods
    // =========================================================================

    /**
     * Returns the GraphQL type accepted when querying against this field.
     *
     * Only booleans get a narrowed type. The other three keep Craft's flexible
     * `QueryArgument` list, because their useful queries are operator strings —
     * `'>= 5'`, `'>= 2026-01-01'`, `'not foo'` — and a narrower scalar would
     * reject exactly the queries typed storage exists to enable.
     *
     * @inheritdoc
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    public function getContentGqlQueryArgumentType(): Type|array
    {
        if ($this->valueType !== self::VALUE_TYPE_BOOLEAN) {
            return parent::getContentGqlQueryArgumentType();
        }

        return [
            'name' => $this->handle,
            'type' => Type::boolean(),
        ];
    }

    /**
     * Returns the GraphQL type this field resolves to.
     *
     * Craft's generated fields are `String` and nothing else, so a consumer has
     * to parse numbers and dates back out on the client. A preparse field
     * advertises the type it actually stores.
     *
     * @inheritdoc
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    public function getContentGqlType(): Type|array
    {
        return match ($this->valueType) {
            self::VALUE_TYPE_BOOLEAN => Type::boolean(),
            self::VALUE_TYPE_DATE => DateTimeType::getType(),
            // `Number` covers both integer and float configurations, and passes
            // null through rather than coercing it to 0.
            self::VALUE_TYPE_NUMBER => NumberType::getType(),
            default => Type::string(),
        };
    }

    /**
     * @inheritdoc
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    public function getElementConditionRuleType(): array|string|null
    {
        return match ($this->valueType) {
            self::VALUE_TYPE_BOOLEAN => BooleanConditionRule::class,
            self::VALUE_TYPE_DATE => DateConditionRule::class,
            self::VALUE_TYPE_NUMBER => NumberConditionRule::class,
            default => TextConditionRule::class,
        };
    }

    /**
     * Renders the value for element index tables and cards.
     *
     * @inheritdoc
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    public function getPreviewHtml(mixed $value, ElementInterface $element): string
    {
        $error = Preparse::$plugin->values->getResult($element, $this)?->error;

        if ($error !== null && $error !== '') {
            return $this->_errorIndicatorHtml($error) . $this->_formatValue($value);
        }

        return $this->_formatValue($value);
    }

    /**
     * @inheritdoc
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    public function getReadOnlySettingsHtml(): ?string
    {
        return $this->_settingsHtml(true);
    }

    /**
     * @inheritdoc
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    public function getSettingsHtml(): ?string
    {
        return $this->_settingsHtml(false);
    }

    /**
     * Returns the sort option, ordering by the typed value expression.
     *
     * This is where typed storage pays off. `getValueSql()` already wraps the
     * extracted value in a `CAST` derived from {@see dbTypeForValueSql()}, so a
     * number field sorts 2, 10, 100 rather than “10”, “100”, “2” — the Postgres
     * integer-sort complaint in #104, and the same class of bug people hit with
     * generated fields.
     *
     * @inheritdoc
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    public function getSortOption(): array
    {
        $option = parent::getSortOption();

        /** @var WebApplication|ConsoleApplication $app */
        $app = Craft::$app;

        // Core applies the MySQL text-to-CHAR cast only when dbType() is a plain
        // string (craftcms#15609); ours is always an array, so the text case has
        // to be handled here or text sorting stays broken on MySQL.
        if ($this->valueType === self::VALUE_TYPE_TEXT && $app->getDb()->getIsMysql()) {
            $option['orderBy'] = "CAST({$option['orderBy']} AS CHAR(255))";
        }

        return $option;
    }

    /**
     * @inheritdoc
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    public function isValueEmpty(mixed $value, ElementInterface $element): bool
    {
        // A stored error is worth keeping even when the render produced nothing,
        // otherwise a failure on a brand-new site would be silently dropped.
        if (Preparse::$plugin->values->getResult($element, $this)?->hasError()) {
            return false;
        }

        return parent::isValueEmpty($value, $element);
    }

    /**
     * Turns the stored envelope into the typed value the element exposes.
     *
     * Elements hand back the plain value — a string, number, boolean, or
     * `DateTime` — so templates keep working the way they always have. The
     * error and timestamp are parked on the values service, which is where the
     * control panel picks them up for the error banner.
     *
     * @inheritdoc
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    public function normalizeValue(mixed $value, ?ElementInterface $element): mixed
    {
        $result = $value instanceof ParseResult ? $value : ParseResult::fromStoredValue($value);
        $result->value = ParseResult::coerce($result->value, $this->valueType, $this->decimals);

        if ($element !== null) {
            Preparse::$plugin->values->rememberResult($element, $this, $result);
        }

        return $result->value;
    }

    /**
     * Renders the value for the card view designer, where there's no element.
     *
     * @inheritdoc
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    public function previewPlaceholderHtml(mixed $value, ?ElementInterface $element): string
    {
        if ($value === null && $element !== null) {
            $value = $element->getFieldValue($this->handle);
        }

        if ($value !== null) {
            return $this->_formatValue($value);
        }

        // Nothing to show a real value for, so stand in with something shaped
        // like one rather than leaving the card preview blank.
        return match ($this->valueType) {
            self::VALUE_TYPE_BOOLEAN => Craft::t('preparse-field', 'Yes'),
            self::VALUE_TYPE_DATE => $this->_formatValue(new DateTime()),
            self::VALUE_TYPE_NUMBER => $this->_formatValue($this->decimals > 0 ? 1.5 : 42),
            default => Craft::t('preparse-field', 'Parsed value'),
        };
    }

    /**
     * Serializes the envelope, rendering it first in inline mode.
     *
     * Craft calls this after the element has an ID but before it propagates,
     * which is the whole appeal of inline mode: the value is final by the time
     * anything else in the save sees it. The trade-off is that nested elements
     * haven't been written yet, so inline mode is for self-contained snippets.
     *
     * @inheritdoc
     * @throws ParseException if the render failed and the field blocks saves on error
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    public function serializeValueForDb(mixed $value, ElementInterface $element): mixed
    {
        $values = Preparse::$plugin->values;
        $result = $values->getResult($element, $this) ?? ParseResult::fromStoredValue($value);

        if (
            $this->parseTiming === self::PARSE_TIMING_INLINE &&
            !$element->getIsRevision() &&
            $values->shouldParse($this, $result)
        ) {
            $result = $values->parse($this, $element, $result);
            $values->rememberResult($element, $this, $result);
        }

        return $result->toStoredValue();
    }

    /**
     * Validates the field's template.
     *
     * Catching a typo here means the editor finds out while they're looking at
     * the template, rather than an author finding out days later when a save
     * quietly stores an error against their entry (#78).
     *
     * Public and un-prefixed because Yii resolves validators by name.
     *
     * @param string $attribute the attribute being validated
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    public function validateTemplate(string $attribute): void
    {
        $error = Preparse::$plugin->parser->validateTemplate($this->template, $this->templateMode);

        if ($error === null) {
            return;
        }

        $this->addError($attribute, $error);
    }

    /**
     * Returns the DB type the value key should be compared and sorted as.
     *
     * @return string the column type
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    public function valueDbType(): string
    {
        return match ($this->valueType) {
            self::VALUE_TYPE_BOOLEAN => Schema::TYPE_BOOLEAN,
            self::VALUE_TYPE_DATE => Schema::TYPE_DATETIME,
            self::VALUE_TYPE_NUMBER => $this->_numberDbType(),
            default => Schema::TYPE_TEXT,
        };
    }

    // Protected Methods
    // =========================================================================

    /**
     * Overrides the value key's declared type with the field's configured one.
     *
     * `dbType()` is static and can't see the instance, so this is where the
     * typed CAST that makes sorting and range queries work actually comes from.
     *
     * @inheritdoc
     * @return array<string, string>
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    protected function dbTypeForValueSql(): array
    {
        $dbType = static::dbType();
        $dbType[ParseResult::KEY_VALUE] = $this->valueDbType();

        return $dbType;
    }

    /**
     * @inheritdoc
     * @return array<mixed>
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    protected function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['valueType'], 'in', 'range' => [
            self::VALUE_TYPE_TEXT,
            self::VALUE_TYPE_NUMBER,
            self::VALUE_TYPE_BOOLEAN,
            self::VALUE_TYPE_DATE,
        ]];
        $rules[] = [['templateMode'], 'in', 'range' => [
            self::TEMPLATE_MODE_INLINE,
            self::TEMPLATE_MODE_FILE,
        ]];
        $rules[] = [['parseTiming'], 'in', 'range' => [
            self::PARSE_TIMING_AFTER_PROPAGATE,
            self::PARSE_TIMING_INLINE,
        ]];
        $rules[] = [['whenToParse'], 'in', 'range' => [
            self::WHEN_TO_PARSE_ALWAYS,
            self::WHEN_TO_PARSE_WHEN_EMPTY,
        ]];
        $rules[] = [['onError'], 'in', 'range' => [
            self::ON_ERROR_KEEP_PREVIOUS,
            self::ON_ERROR_FALLBACK,
            self::ON_ERROR_BLOCK_SAVE,
        ]];
        $rules[] = [['display'], 'in', 'range' => [
            self::DISPLAY_HIDDEN,
            self::DISPLAY_VALUE,
        ]];
        $rules[] = [['decimals'], 'integer', 'min' => 0, 'max' => 8];
        $rules[] = [['template'], 'required'];
        $rules[] = [['template'], 'validateTemplate'];

        return $rules;
    }

    /**
     * @inheritdoc
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    protected function inputHtml(mixed $value, ?ElementInterface $element, bool $inline): string
    {
        /** @var WebApplication|ConsoleApplication $app */
        $app = Craft::$app;
        $view = $app->getView();
        $result = $element !== null ? Preparse::$plugin->values->getResult($element, $this) : null;

        return $view->renderTemplate('preparse-field/_input.twig', [
            'field' => $this,
            'namespacedId' => $view->namespaceInputId($this->getInputId()),
            'result' => $result,
            'value' => $value,
        ]);
    }

    /**
     * Skips date values, whose formatted keywords are noise in a search index.
     *
     * @inheritdoc
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    protected function searchKeywords(mixed $value, ElementInterface $element): string
    {
        if ($value === null || $this->valueType === self::VALUE_TYPE_DATE) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '';
        }

        return (string)$value;
    }

    // Private Methods
    // =========================================================================

    /**
     * Returns a small alert marker for a value whose last render failed.
     *
     * The stale value is still shown alongside it — an editor scanning an index
     * needs to know the number is out of date, not to lose it.
     *
     * @param string $error the stored error message
     * @return string the indicator HTML
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    private function _errorIndicatorHtml(string $error): string
    {
        return Html::tag('span', '', [
            'class' => ['error'],
            'data' => ['icon' => 'alert'],
            'title' => $error,
            'aria' => ['label' => Craft::t('preparse-field', 'This field couldn’t be parsed.')],
        ]);
    }

    /**
     * Formats a value for display, according to the value type.
     *
     * @param mixed $value the value
     * @return string the formatted, HTML-safe value
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    private function _formatValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        /** @var WebApplication|ConsoleApplication $app */
        $app = Craft::$app;
        $formatter = $app->getFormatter();

        return match (true) {
            $this->valueType === self::VALUE_TYPE_BOOLEAN => $value
                ? Craft::t('preparse-field', 'Yes')
                : Craft::t('preparse-field', 'No'),
            $this->valueType === self::VALUE_TYPE_DATE && $value instanceof DateTime =>
                $formatter->asDatetime($value, Locale::LENGTH_SHORT),
            $this->valueType === self::VALUE_TYPE_NUMBER && is_numeric($value) =>
                $formatter->asDecimal($value, $this->decimals),
            default => Html::encode((string)$value),
        };
    }

    /**
     * Returns the DB type for the number value type.
     *
     * @return string the column type
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    private function _numberDbType(): string
    {
        if ($this->decimals <= 0) {
            return Schema::TYPE_INTEGER;
        }

        /** @var WebApplication|ConsoleApplication $app */
        $app = Craft::$app;

        if ($app->getDb()->getIsMysql()) {
            return sprintf('%s(65,%s)', Schema::TYPE_DECIMAL, $this->decimals);
        }

        return Schema::TYPE_DECIMAL;
    }

    /**
     * Renders the field's settings.
     *
     * @param bool $readOnly whether the settings are read-only
     * @return string the rendered settings
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    private function _settingsHtml(bool $readOnly): string
    {
        /** @var WebApplication|ConsoleApplication $app */
        $app = Craft::$app;

        return $app->getView()->renderTemplate('preparse-field/_settings.twig', [
            'field' => $this,
            // The code editor is a bootstrapped module, and only for web
            // requests — so the template has to be able to do without it.
            'hasCodeEditor' => CodeEditor::getInstance() !== null,
            'hasStoredValues' => Preparse::$plugin->values->hasStoredValues($this),
            'readOnly' => $readOnly,
            'testElementType' => $this->_testElementType(),
            'utilityUrl' => UrlHelper::cpUrl('utilities/preparse'),
        ]);
    }    /**
     * Returns the element type the settings page's test picker should offer.
     *
     * A field that's already in a layout gets that layout's element type; a
     * brand-new one has no layouts yet, so entries are the sensible default.
     *
     * @return class-string<ElementInterface> the element type
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    private function _testElementType(): string
    {
        $types = Preparse::$plugin->values->elementTypesForField($this);

        return $types[0] ?? Entry::class;
    }
}
