<?php
/**
 * Preparse plugin for Craft CMS 5.x
 *
 * Renders a Twig template when an element is saved and stores the typed result.
 *
 * @link      https://jalendport.com
 * @copyright Copyright (c) 2026 Jalen Davenport
 */

namespace jalendport\preparse\models;

use craft\base\Model;
use craft\helpers\DateTimeHelper;
use craft\helpers\Db;
use DateTime;
use jalendport\preparse\fields\PreparseField;

/**
 * The value envelope stored in `elements_sites.content` for a preparse field.
 *
 * A preparse value is more than the rendered result: a failed render still has
 * to leave a trace so the control panel can explain itself, and reparse tooling
 * needs to know when a value was last produced. So the field stores three keys
 * rather than a bare scalar, and this model is the round-trip between that
 * stored shape and the typed runtime value.
 *
 * Values written by Preparse 3.x are bare scalars sitting at the layout
 * element's UID. {@see fromStoredValue()} reads those too, which is what lets
 * the 4.0 upgrade leave existing rows untouched until their first reparse.
 *
 * @author Jalen Davenport <hello@jalendport.com>
 * @since 4.0.0
 */
class ParseResult extends Model
{
    // Const Properties
    // =========================================================================

    /**
     * @var string The envelope key holding the render error, if the last render failed.
     * @since 4.0.0
     */
    public const KEY_ERROR = 'error';

    /**
     * @var string The envelope key holding the timestamp of the last render attempt.
     * @since 4.0.0
     */
    public const KEY_PARSED_AT = 'parsedAt';

    /**
     * @var string The envelope key holding the typed value.
     * @since 4.0.0
     */
    public const KEY_VALUE = 'value';

    // Public Properties
    // =========================================================================

    /**
     * @var string|null The error message from the last render attempt, or `null` if it succeeded
     * @since 4.0.0
     */
    public ?string $error = null;

    /**
     * @var DateTime|null When the value was last rendered
     * @since 4.0.0
     */
    public ?DateTime $parsedAt = null;

    /**
     * @var mixed The typed value: `string`, `int`, `float`, `bool`, `DateTime`, or `null`
     * @since 4.0.0
     */
    public mixed $value = null;

    // Static Methods
    // =========================================================================

    /**
     * Coerces a rendered template result into the field's configured value type.
     *
     * Coercion is idempotent: passing an already-coerced value back through
     * returns it unchanged, which matters because a value can be re-normalized
     * several times over one request.
     *
     * Anything that can't be represented in the target type becomes `null`
     * rather than a bogus zero or epoch date — an unparseable number is missing
     * data, not the number zero.
     *
     * @param mixed $rendered the raw render output
     * @param string $valueType one of the `PreparseField::VALUE_TYPE_*` constants
     * @param int $decimals the number of decimal places, for the number type
     * @return mixed the coerced value
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    public static function coerce(mixed $rendered, string $valueType, int $decimals = 0): mixed
    {
        return match ($valueType) {
            PreparseField::VALUE_TYPE_BOOLEAN => self::_coerceBoolean($rendered),
            PreparseField::VALUE_TYPE_DATE => self::_coerceDate($rendered),
            PreparseField::VALUE_TYPE_NUMBER => self::_coerceNumber($rendered, $decimals),
            default => self::_coerceText($rendered),
        };
    }

    /**
     * Builds a result from a raw `elements_sites.content` value.
     *
     * @param mixed $stored the stored value: a 4.0 envelope, a 3.x bare scalar, or `null`
     * @return self the result
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    public static function fromStoredValue(mixed $stored): self
    {
        $result = new self();

        if ($stored === null || $stored === '') {
            return $result;
        }

        if (!is_array($stored) || !self::_isEnvelope($stored)) {
            // A Preparse 3.x value, or a value someone assigned directly.
            $result->value = $stored;

            return $result;
        }

        $result->value = $stored[self::KEY_VALUE] ?? null;
        $result->error = $stored[self::KEY_ERROR] ?? null;

        $parsedAt = $stored[self::KEY_PARSED_AT] ?? null;

        if ($parsedAt !== null && $parsedAt !== '') {
            $result->parsedAt = DateTimeHelper::toDateTime($parsedAt) ?: null;
        }

        return $result;
    }

    // Public Methods
    // =========================================================================

    /**
     * Returns whether the last render attempt failed.
     *
     * @return bool whether an error is stored
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    public function hasError(): bool
    {
        return $this->error !== null && $this->error !== '';
    }

    /**
     * Returns whether there is nothing worth storing.
     *
     * A stored `false` or `0` is a real value, so only a null value with no
     * error counts as empty.
     *
     * @return bool whether the envelope is empty
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    public function isEmpty(): bool
    {
        return $this->value === null && !$this->hasError();
    }

    /**
     * Returns the envelope to store in `elements_sites.content`.
     *
     * @return array<string, mixed>|null the envelope, or `null` if there's nothing to store
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    public function toStoredValue(): ?array
    {
        if ($this->isEmpty()) {
            return null;
        }

        $stored = [
            self::KEY_VALUE => $this->value instanceof DateTime
                ? Db::prepareDateForDb($this->value)
                : $this->value,
        ];

        if ($this->hasError()) {
            $stored[self::KEY_ERROR] = $this->error;
        }

        if ($this->parsedAt !== null) {
            $stored[self::KEY_PARSED_AT] = Db::prepareDateForDb($this->parsedAt);
        }

        return $stored;
    }

    // Private Methods
    // =========================================================================

    /**
     * Coerces a rendered value to a boolean.
     *
     * @param mixed $rendered the raw render output
     * @return bool|null the boolean value
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    private static function _coerceBoolean(mixed $rendered): ?bool
    {
        if (is_bool($rendered)) {
            return $rendered;
        }

        if ($rendered === null) {
            return null;
        }

        if (is_numeric($rendered)) {
            return (float)$rendered !== 0.0;
        }

        if (!is_string($rendered)) {
            return null;
        }

        $normalized = strtolower(trim($rendered));

        if ($normalized === '') {
            return null;
        }

        return match ($normalized) {
            'false', 'no', 'off', 'n' => false,
            default => true,
        };
    }

    /**
     * Coerces a rendered value to a date.
     *
     * @param mixed $rendered the raw render output
     * @return DateTime|null the date value
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    private static function _coerceDate(mixed $rendered): ?DateTime
    {
        if ($rendered instanceof DateTime) {
            return $rendered;
        }

        if ($rendered === null || $rendered === '' || is_bool($rendered)) {
            return null;
        }

        return DateTimeHelper::toDateTime($rendered) ?: null;
    }

    /**
     * Coerces a rendered value to a number.
     *
     * Only genuinely numeric output is accepted. Formatted output such as
     * `1,234.50` is deliberately rejected rather than guessed at, because the
     * separator's meaning depends on the locale — 3.x ran everything through
     * `number_format()` and quietly turned unparseable strings into `0`.
     *
     * @param mixed $rendered the raw render output
     * @param int $decimals the number of decimal places; `0` means integer semantics
     * @return int|float|null the numeric value
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    private static function _coerceNumber(mixed $rendered, int $decimals): int|float|null
    {
        if (is_bool($rendered) || $rendered === null) {
            return null;
        }

        $value = is_string($rendered) ? trim($rendered) : $rendered;

        if (!is_numeric($value)) {
            return null;
        }

        if ($decimals <= 0) {
            return (int)round((float)$value);
        }

        return round((float)$value, $decimals);
    }

    /**
     * Coerces a rendered value to text.
     *
     * @param mixed $rendered the raw render output
     * @return string|null the text value
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    private static function _coerceText(mixed $rendered): ?string
    {
        if ($rendered === null) {
            return null;
        }

        if ($rendered instanceof DateTime) {
            return $rendered->format(DateTime::ATOM);
        }

        if (is_bool($rendered)) {
            return $rendered ? '1' : '';
        }

        if (is_array($rendered) || is_object($rendered)) {
            return null;
        }

        $value = trim((string)$rendered);

        return $value !== '' ? $value : null;
    }

    /**
     * Returns whether a stored array is a 4.0 envelope rather than a raw array value.
     *
     * @param array<mixed> $stored the stored array
     * @return bool whether it's an envelope
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    private static function _isEnvelope(array $stored): bool
    {
        return array_key_exists(self::KEY_VALUE, $stored)
            || array_key_exists(self::KEY_ERROR, $stored)
            || array_key_exists(self::KEY_PARSED_AT, $stored);
    }
}
