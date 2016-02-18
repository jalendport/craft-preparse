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
        return $this->_getEnabledPreparseColumns('entry');
    }

    public function defineAdditionalCategoryTableAttributes()
    {
        return $this->_getEnabledPreparseColumns('category');
    }

    public function defineAdditionalAssetTableAttributes()
    {
        return $this->_getEnabledPreparseColumns('asset');
    }

    public function defineAdditionalUserTableAttributes()
    {
        return $this->_getEnabledPreparseColumns('user');
    }

    public function defineAdditionalCommerce_ProductTableAttributes()
    {
        return $this->_getEnabledPreparseColumns('Commerce_Product');
    }

    public function defineAdditionalCommerce_VariantTableAttributes()
    {
        return $this->_getEnabledPreparseColumns('Commerce_Variant');
    }

    public function defineAdditionalCommerce_OrderTableAttributes()
    {
        return $this->_getEnabledPreparseColumns('Commerce_Order');
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
}

