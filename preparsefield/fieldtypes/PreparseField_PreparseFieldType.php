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
    public function onAfterElementSave()
    {
        $fieldHandle = $this->model->handle;
        $fieldTwig = $this->getSettings()->fieldTwig;
        $elementType = $this->element->getElementType();
        $elementTemplateName = strtolower($elementType);
        
        $parsedData = craft()->templates->renderString($fieldTwig, array($elementTemplateName => $this->element));
        if ($this->element->getContent()->getAttribute($fieldHandle)!==$parsedData) {
            $this->element->getContent()->setAttribute($fieldHandle, $parsedData);
            $success = craft()->elements->saveElement($this->element);
            
            if (!$success) {
                PreparseFieldPlugin::log('Couldn’t save element with id "' . $element->id . '" and preparse field "' . $fieldHandle . '"',
                  LogLevel::Error);
            }
        }
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
