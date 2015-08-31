<?php
namespace Craft;

/**
 * Preparse Field plugin
 */

class PreparseFieldPlugin extends BasePlugin
{
    public function getName()
    {
        return 'Preparse Field';
    }

    public function getVersion()
    {
        return '0.1';
    }

    public function getDeveloper()
    {
        return 'André Elvan';
    }

    public function getDeveloperUrl()
    {
        return 'http://vaersaagod.no';
    }

    public function init()
    {
        craft()->on('elements.beforeSaveElement', [$this, 'onBeforeSaveElement']);
    }

    /**
     * beforeSaveElement event listener
     */
    public function onBeforeSaveElement(Event $e)
    {
        $element = $e->params['element'];
        $elementFields = $element->fieldLayout->fields;

        foreach ($elementFields as $fieldModel) {
            if ($fieldModel->field->fieldType->classHandle === 'PreparseField_Preparse') {

                // Render the twigness
                $elementType = strtolower($element->getElementType());
                $fieldTwig = $fieldModel->field->fieldType->getSettings()->fieldTwig;
                $parsedData = craft()->templates->renderString($fieldTwig, array($elementType => $element));

                // Save compiled value to element model
                $fieldHandle = $fieldModel->field->handle;
                $element->getContent()->$fieldHandle = $parsedData;

            }
        }

    }

}
