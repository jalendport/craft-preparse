<?php
namespace Craft;

class PreparseFieldTwigExtension extends \Twig_Extension
{
    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'Preparse Field';
    }

    /**
     * Returns a list of filters to add to the existing list.
     *
     * @return array An array of filters
     */
    public function getFilters()
    {
        return [
            new \Twig_SimpleFilter('json_decode', array($this, 'jsonDecode')),
        ];
    }

    /**
     * Decodes a JSON string.
     *
     * @param string $value
     * @param bool $assoc
     * @param int $depth
     * @param int $options
     * @return array
     */
    public function jsonDecode($value, $assoc = false, $depth = 512, $options = 0)
    {
        return json_decode(html_entity_decode($value), $assoc, $depth, $options);
    }
}
