<?php
namespace Craft;

class PreparseField_PreparseFieldType extends BaseFieldType
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
     * onAfterElementSave hook
     */
    // public function onAfterElementSave()
    // {
    //     $fieldHandle = $this->model->handle;
    //     $fieldTwig = $this->getSettings()->fieldTwig;
    //     $elementId = $this->element->id;
    //     $elementType = $this->element->getElementType();
    //     $elementTemplateName = strtolower($elementType);
    //
    //     $criteria = craft()->elements->getCriteria($elementType);
    //     $criteria->id = $elementId;
    //     $element = $criteria->first();
    //
    //     $parsedData = craft()->templates->renderString($fieldTwig, array($elementTemplateName => $element));
    //
    //     if ($this->element->$fieldHandle != $parsedData) { // only run when data was updated to keep it from looping
    //         $newData = array( $fieldHandle => $parsedData );
    //         $element->setContentFromPost($newData);
    //         $success = craft()->elements->saveElement($element);
    //     }
    // }

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
        );
    }

    /**
     * Render settings html
     *
     * @return mixed
     */
    public function getSettingsHtml()
    {
        return craft()->templates->render('preparsefield/settings', array(
          'settings' => $this->getSettings()
        ));
    }

    /**
     * Define database column
     *
     * @return AttributeType::String
     */
    public function defineContentAttribute()
    {
        return array(AttributeType::String, 'column' => ColumnType::Text);
    }

}
