<?php
namespace jalendport\Preparse;

use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\base\PreviewableFieldInterface;
use craft\base\SortableFieldInterface;
use craft\db\mysql\Schema;
use craft\elements\db\ElementQuery;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\DateTimeHelper;
use craft\gql\types\DateTime as GqlDateTimeType;
use craft\helpers\Db;
use GraphQL\Type\Definition\Type;

class PreparseField extends Field implements PreviewableFieldInterface, SortableFieldInterface
{

	public string $columnType = Schema::TYPE_TEXT;
	public int $decimals = 0;
	public string $fieldTwig = '';
	public bool $hideField = false;
	public bool $parseOnMove = false;

	/**
	 * @deprecated
	 */
	public bool $allowSelect = false;

	/**
	 * @deprecated
	 */
	public string $displayType = 'hidden';

	/**
	 * @deprecated
	 */
	public bool $parseBeforeSave = false;

	/**
	 * @deprecated
	 */
	public bool $showField = false;

	/**
	 * @deprecated
	 */
	public int $textareaRows = 5;


	// TODO
	public function getContentColumnType(): array|string
	{
		if ($this->columnType === Schema::TYPE_DECIMAL) {
			return Db::getNumericalColumnType(null, null, $this->decimals);
		}

		return $this->columnType;
	}

	// TODO
	public function getContentGqlType(): Type|array
	{
		return match ($this->columnType) {
			Schema::TYPE_DATETIME => GqlDateTimeType::getType(),
			default => parent::getContentGqlType(),
		};
	}

	// TODO
	public function getInputHtml(mixed $value, ?ElementInterface $element = null): string
	{
		// Get our id and namespace
		$id = Craft::$app->getView()->formatInputId($this->handle);
		$namespacedId = Craft::$app->getView()->namespaceInputId($id);

		// Render the input template
		$displayType = $this->displayType;
		if ($displayType !== 'hidden' && $this->columnType === Schema::TYPE_DATETIME) {
			$displayType = 'date';
		}
		return Craft::$app->getView()->renderTemplate(
			'preparse-field/_components/field/_input',
			[
				'name' => $this->handle,
				'value' => $value,
				'field' => $this,
				'id' => $id,
				'namespacedId' => $namespacedId,
				'displayType' => $displayType,
			]
		);
	}

	// TODO
	public function getSearchKeywords(mixed $value, ElementInterface $element): string
	{
		if ($this->columnType === Schema::TYPE_DATETIME) {
			return '';
		}
		return parent::getSearchKeywords($value, $element);
	}

	// TODO
	public function getSettingsHtml(): string
	{
		$columns = [
			Schema::TYPE_TEXT => "text (~64KB)",
			Schema::TYPE_MEDIUMTEXT => "mediumtext (~16MB)",
			Schema::TYPE_STRING => "string (255B)",
			Schema::TYPE_INTEGER => "integer",
			Schema::TYPE_DECIMAL => "decimal",
			Schema::TYPE_FLOAT => "float",
			Schema::TYPE_DATETIME => "datetime",
		];

		// TODO
		$displayTypes = [
			'hidden' => 'Hidden',
			'textinput' => 'Text input',
			'textarea' => 'Textarea',
		];

		// TODO
		return Craft::$app->getView()->renderTemplate(
			'preparse-field/_components/field/_settings',
			[
				'field' => $this,
				'columns' => $columns,
				'displayTypes' => $displayTypes,
				'existing' => $this->id !== null,
			]
		);
	}

	// TODO
	public function getTableAttributeHtml(mixed $value, ElementInterface $element): string
	{
		if (!$value) {
			return '';
		}

		if ($this->columnType === Schema::TYPE_DATETIME) {
			return Craft::$app->getFormatter()->asDatetime($value, Locale::LENGTH_SHORT);
		}

		return parent::getTableAttributeHtml($value, $element);
	}

	// TODO
	public function modifyElementsQuery(ElementQueryInterface $query, mixed $value): void
	{
		if ($this->columnType === Schema::TYPE_DATETIME) {
			if ($value !== null) {
				/** @var ElementQuery $query */
				$query->subQuery->andWhere(Db::parseDateParam('content.' . Craft::$app->getContent()->fieldColumnPrefix . $this->handle, $value));
			}
		}
		parent::modifyElementsQuery($query, $value);
	}

	// TODO
	public function normalizeValue(mixed $value, ?ElementInterface $element = null): mixed
	{
		if ($this->columnType === Schema::TYPE_DATETIME) {
			if ($value && ($date = DateTimeHelper::toDateTime($value)) !== false) {
				return $date;
			}
			return null;
		}
		return parent::normalizeValue($value, $element);
	}

	// TODO
	public function rules(): array
	{

		$rules = parent::rules();

		return $rules + [
				['fieldTwig', 'string'],
				['columnType', 'string'],
				['decimals', 'number'],
				['parseOnMove', 'boolean'],
			];

	}

	/*
	 * Statics
	 */

	public static function displayName(): string
	{
		return "Preparse";
	}

	public static function hasContentColumn(): bool
	{
		return false;
	}

}
