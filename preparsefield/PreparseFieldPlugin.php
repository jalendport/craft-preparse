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
