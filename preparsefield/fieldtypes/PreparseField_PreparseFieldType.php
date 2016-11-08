<?php
namespace Craft;

class PreparseField_PreparseFieldType extends BaseFieldType implements IPreviewableFieldType
{
    /**
     * Fieldtype name
     *
     * @return string
     */
    public function getName()
    {
        return Craft::t('Preparse');
    }

    /**
     * Display our fieldtype
     *
     * @param string $name Our fieldtype handle
     * @return string Return our fields input template
     */
    public function getInputHtml($name, $value)
    {
        $inputId = craft()->templates->formatInputId($name);
        $namespaceInputId = craft()->templates->namespaceInputId($inputId);

        return craft()->templates->render('preparsefield/field', array(
          'id' => $namespaceInputId,
          'name' => $name,
          'value' => $value,
          'settings' => $this->getSettings()
        ));
    }

    /**
     * Validates
     *
     * Always returns 'true'
     *
     * @param array $value
     * @return true|string|array
     */
    public function validate($value)
    {
        return true;
    }

    /**
     * Define fieldtype settings
     *
     * @return array
     */
    protected function defineSettings()
    {
        return array(
          'fieldTwig' => array(AttributeType::String, 'default' => ''),
          'showField' => array(AttributeType::Bool, 'default' => false),
          'columnType' => array(AttributeType::String),
          'decimals' => array(AttributeType::Number, 'default' => 0),
          'parseBeforeSave' => array(AttributeType::Bool, 'default' => false),
          'parseOnMove' => array(AttributeType::Bool, 'default' => false),
          'allowSelect' => array(AttributeType::Bool, 'default' => false),
        );
    }

    /**
     * Render settings html
     *
     * @return mixed
     */
    public function getSettingsHtml()
    {
        $columns = array(
          ColumnType::Text => Craft::t('Text (stores about 64K)'),
          ColumnType::MediumText => Craft::t('MediumText (stores about 4GB)'),
          'number' => Craft::t('Number'),
        );

        return craft()->templates->render('preparsefield/settings', array(
          'settings' => $this->getSettings(),
          'columns' => $columns,
          'existing' => !empty($this->model->id),
        ));
    }

    /**
     * Define database column
     *
     * @return mixed
     */
    public function defineContentAttribute()
    {
        $settings = $this->getSettings();

        // It hasn't always been a settings, so default to Text if it's not set.
        if (!$settings->getAttribute('columnType')) {
            return array(AttributeType::String, 'column' => ColumnType::Text);
        }

        if ($settings->columnType === 'number') {
            $attribute = ModelHelper::getNumberAttributeConfig(null, null, $settings->decimals);
            $attribute['default'] = 0;

            return $attribute;
        }

        return array(AttributeType::String, 'column' => $settings->columnType);
    }
}
