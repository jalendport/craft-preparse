<?php
namespace Craft;

class PreparseFieldService extends BaseApplicationComponent
{
    public function parseField($fieldType)
    {
        $fieldTwig = $fieldType->getSettings()->fieldTwig;

        $element = $fieldType->element;
        $elementType = $element->getElementType();
        $elementTemplateName = strtolower($elementType);

        // Set generateTransformsBeforePageLoad = true
        $configService = craft()->config;
        $generateTransformsBeforePageLoad = $configService->get('generateTransformsBeforePageLoad');
        $configService->set('generateTransformsBeforePageLoad', true);

        // save cp template path and set to site templates
        if (craft()->getBuild()<2778) {
            $oldPath = craft()->path->getTemplatesPath();
            craft()->path->setTemplatesPath(craft()->path->getSiteTemplatesPath());
        } else {
            $oldMode = craft()->templates->getTemplateMode();
            craft()->templates->setTemplateMode(TemplateMode::Site);
        }

        // parse data
        $parsedData = craft()->templates->renderString($fieldTwig, array($elementTemplateName => $element));

        // restore cp template paths
        if (craft()->getBuild()<2778) {
            craft()->path->setTemplatesPath($oldPath);
        } else {
            craft()->templates->setTemplateMode($oldMode);
        }

        // Set generateTransformsBeforePageLoad back to whatever it was
        $configService->set('generateTransformsBeforePageLoad', $generateTransformsBeforePageLoad);

        return $parsedData;
    }
}
