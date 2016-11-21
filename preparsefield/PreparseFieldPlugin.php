<?php
namespace Craft;

/**
 * Preparse Field plugin
 */

class PreparseFieldPlugin extends BasePlugin
{
    protected $_version = '0.3.6',
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
     * Stores the IDs of elements we already preparsed the fields for.
     *
     * @var array
     */
    public $preparsedElements;

    /**
     * Initializes the plugin
     */
    public function init()
    {
        $this->_initEventListeners();
    }

    /**
     * Make sure requirements are met before installation.
     *
     * @return bool
     * @throws Exception
     */
    public function onBeforeInstall()
    {
        if (version_compare(craft()->getVersion(), $this->getCraftRequiredVersion(), '<')) {
            throw new Exception($this->getName() . ' plugin requires Craft ' . $this->getCraftRequiredVersion() . ' or later.');
        }
    }

    /**
     * Initializes event listeners
     */
    private function _initEventListeners()
    {
        $this->preparsedElements = array(
          'onBeforeSave' => array(),
          'onSave' => array(),
        );

        $obj = &$this; // php 5.3.x fix

        craft()->on('elements.onBeforeSaveElement', function (Event $event) use (&$obj) {
            $element = $event->params['element'];

            if (!in_array($element->id, $obj->preparsedElements['onBeforeSave'])) {
                $obj->preparsedElements['onBeforeSave'][] = $element->id;

                $content = craft()->preparseField->getPreparseFieldsContent($element, 'onBeforeSave');

                if (!empty($content)) {
                    $element->setContentFromPost($content);
                }
            }
        });

        craft()->on('elements.onSaveElement', function (Event $event) use (&$obj) {
            $element = $event->params['element'];

            if (!in_array($element->id, $obj->preparsedElements['onSave'])) {
                $obj->preparsedElements['onSave'][] = $element->id;

                $content = craft()->preparseField->getPreparseFieldsContent($element, 'onSave');

                if (!empty($content)) {
                    $element->setContentFromPost($content);

                    $success = craft()->elements->saveElement($element);

                    // if no success, log error
                    if (!$success) {
                        PreparseFieldPlugin::log('Couldn’t save element with id “' . $element->id . '”.', LogLevel::Error);
                    }
                }
            }
        });
        
        craft()->on('structures.onMoveElement', function (Event $event) use (&$obj) {
            $element = $event->params['element'];
            
            if (craft()->preparseField->shouldParseElementOnMove($element)) {
                $success = craft()->elements->saveElement($element);
    
                // if no success, log error
                if (!$success) {
                    PreparseFieldPlugin::log('Couldn’t save element with id “' . $element->id . '” in structures.onMoveElement.', LogLevel::Error);
                }
            }
        });
    }

}
