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


    /* one for each supported element type: */
    public function defineAdditionalEntryTableAttributes()
    {
        if (!$this->_disableElementTables()) {
            return $this->_getEnabledPreparseColumns('entry');
        }
        return array();
    }

    public function defineAdditionalCategoryTableAttributes()
    {
        if (!$this->_disableElementTables()) {
            return $this->_getEnabledPreparseColumns('category');
        }
        return array();
    }

    public function defineAdditionalAssetTableAttributes()
    {
        if (!$this->_disableElementTables()) {
            return $this->_getEnabledPreparseColumns('asset');
        }
        return array();
    }

    public function defineAdditionalUserTableAttributes()
    {
        if (!$this->_disableElementTables()) {
            return $this->_getEnabledPreparseColumns('user');
        }
        return array();
    }

    public function defineAdditionalCommerce_ProductTableAttributes()
    {
        if (!$this->_disableElementTables()) {
            return $this->_getEnabledPreparseColumns('Commerce_Product');
        }
        return array();
    }

    public function defineAdditionalCommerce_VariantTableAttributes()
    {
        if (!$this->_disableElementTables()) {
            return $this->_getEnabledPreparseColumns('Commerce_Variant');
        }
        return array();
    }

    public function defineAdditionalCommerce_OrderTableAttributes()
    {
        if (!$this->_disableElementTables()) {
            return $this->_getEnabledPreparseColumns('Commerce_Order');
        }
        return array();
    }

    /* one for each supported element type: */
    public function getEntryTableAttributeHtml($element, $attribute)
    {
        if (array_key_exists($attribute, $this->defineAdditionalEntryTableAttributes())) {
            return $element[$attribute];
        }
    }

    public function getCategoryTableAttributeHtml($element, $attribute)
    {
        if (array_key_exists($attribute, $this->defineAdditionalCategoryTableAttributes())) {
            return $element[$attribute];
        }
    }

    public function getAssetTableAttributeHtml($element, $attribute)
    {
        if (array_key_exists($attribute, $this->defineAdditionalAssetTableAttributes())) {
            return $element[$attribute];
        }
    }

    public function getUserTableAttributeHtml($element, $attribute)
    {
        if (array_key_exists($attribute, $this->defineAdditionalUserTableAttributes())) {
            return $element[$attribute];
        }
    }

    public function getCommerce_ProductTableAttributeHtml($element, $attribute)
    {
        if (array_key_exists($attribute, $this->defineAdditionalCommerce_ProductTableAttributes())) {
            return $element[$attribute];
        }
    }

    public function getCommerce_VariantTableAttributeHtml($element, $attribute)
    {
        if (array_key_exists($attribute, $this->defineAdditionalCommerce_VariantTableAttributes())) {
            return $element[$attribute];
        }
    }

    public function getCommerce_OrderTableAttributeHtml($element, $attribute)
    {
        if (array_key_exists($attribute, $this->defineAdditionalOrderTableAttributes())) {
            return $element[$attribute];
        }
    }

    protected function defineSettings()
    {
        return array(
            'disableElementTables' => array(
                AttributeType::Bool,
                'default' => false
            )
        );
    }

    public function getSettingsHtml()
    {
       return craft()->templates->render('preparsefield/global.twig', array(
           'settings' => $this->getSettings()
       ));
    }


    private function _getEnabledPreparseColumns($elementTypeClass)
    {
        $fields = craft()->fields->getFieldsByElementType($elementTypeClass);
        $attributes = array();

        foreach ($fields as $field) {
            $fieldType = $field->getFieldType();

            if ($fieldType && $fieldType->getClassHandle() === 'PreparseField_Preparse') {
                if ($fieldType->getSettings()->showColumn) {
                    $attributes[$field->handle] = array('label' => Craft::t($field->name));
                }
            }
        }

        return $attributes;
    }

    private function _disableElementTables()
    {
        return craft()->plugins->getPlugin('preparsefield')->getSettings()->disableElementTables;
    }
}
