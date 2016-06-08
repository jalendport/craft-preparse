<?php
namespace Craft;

/**
 * Preparse Field plugin
 */

class PreparseFieldPlugin extends BasePlugin
{

    protected $_version = '0.2.2',
      $_schemaVersion = '1.0.0',
      $_name = 'Preparse Field',
      $_url = 'https://github.com/aelvan/Preparse-Field-Craft',
      $_releaseFeedUrl = 'https://raw.githubusercontent.com/aelvan/Preparse-Field-Craft/master/releases.json',
      $_documentationUrl = 'https://github.com/aelvan/Preparse-Field-Craft/blob/master/README.md',
      $_description = 'A fieldtype that parses Twig when an element is saved, and saves the result as plain text.',
      $_developer = 'André Elvan',
      $_developerUrl = 'http://vaersaagod.no/',
      $_minVersion = '2.5';

    public function getName()
    {
        return Craft::t($this->_name);
    }

    public function getUrl()
    {
        return $this->_url;
    }

    public function getVersion()
    {
        return $this->_version;
    }

    public function getDeveloper()
    {
        return $this->_developer;
    }

    public function getDeveloperUrl()
    {
        return $this->_developerUrl;
    }

    public function getDescription()
    {
        return $this->_description;
    }

    public function getDocumentationUrl()
    {
        return $this->_documentationUrl;
    }

    public function getSchemaVersion()
    {
        return $this->_schemaVersion;
    }

    public function getReleaseFeedUrl()
    {
        return $this->_releaseFeedUrl;
    }

    public function getCraftRequiredVersion()
    {
        return $this->_minVersion;
    }

    /**
     * Make sure requirements are met before installation.
     *
     * @return bool
     * @throws Exception
     */
    public function onBeforeInstall()
    {
        if (version_compare(craft()->getVersion(), $this->getCraftRequiredVersion(), '<=')) {
            throw new Exception($this->getName().' plugin requires Craft '.$this->getCraftRequiredVersion().'+');
        }
    }

    public function init()
    {
        $this->_initEventListeners();
    }

    /**
     * Initializes event listeners
     */
    private function _initEventListeners()
    {
        craft()->on('elements.onSaveElement', function(Event $event) {
            $element = $event->params['element'];
            $fieldLayout = $element->getFieldLayout();

            $elementContent = array();

            if ($fieldLayout) {
                foreach ($fieldLayout->getFields() as $fieldLayoutField) {
                    $field = $fieldLayoutField->getField();

                    if ($field) {
                        $fieldType = $field->getFieldType();

                        if ($fieldType && $fieldType->getClassHandle() === 'PreparseField_Preparse') {
                            $fieldType->element = $element;

                            $fieldValue = craft()->preparseField->parseField($fieldType);
                            $elementContent[$field->handle] = $fieldValue;
                        }
                    }
                }
            }

            $flashId = 'element-' . $element->id;

            if (!craft()->userSession->hasFlash($flashId)) {
                craft()->userSession->setFlash($flashId, "saved");

                $element->setContentFromPost($elementContent);
                $success = craft()->elements->saveElement($element);

                // if no success, log error
                if (!$success) {
                    PreparseFieldPlugin::log('Couldn’t save element with id "' . $this->element->id . '" and preparse field "' . $fieldHandle . '"',
                      LogLevel::Error);
                }
            }
        });
    }
}
