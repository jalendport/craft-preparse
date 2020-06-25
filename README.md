# Preparse Field for Craft

A fieldtype that parses Twig when an element is saved and saves the result as plain text.

## Requirements

This plugin requires Craft CMS 3.2.0 or later.

_The Craft 2 version can be found in the [craft2](https://github.com/besteadfast/craft-preparse-field/tree/craft2) branch_

## Installation

To install the plugin, follow these instructions.

1.  Open your terminal and go to your Craft project:

        cd /path/to/project

2.  Then tell Composer to load the plugin:

        composer require besteadfast/craft-preparse-field

3.  In the Control Panel, go to Settings → Plugins and click the “Install” button for Preparse Field.

## Usage

When creating a new Preparse field, you can add the Twig that you want to run to the fields settings. When an element with a preparse field is saved, the code will be parsed. The element itself is available as `element` in twig.

**Usage in Matrix**  
When a Preparse field is added to a Matrix block, that block will be available to the Twig code as the variable `element`. The element that the Matrix field belongs to will be available under `element.owner`.

### Examples

If you have a category field in your entry named `entryCategory`, you can save the category title to the preparse field by adding the following Twig to the field settings:

    {{ element.entryCategory | length ? element.entryCategory.first().title }}

This is useful for saving preparsed values to a field for use with sorting, searching or similar things.

You can also do more advanced stuff, for instance for performance optimizing. Let's say you have three different asset fields that may or may not be populated. having to check these in the template may require a bunch of queries since you can't check if a field has a relation in Craft, without actually querying for it. You could do something like this to get the id of the asset to use:

    {% if element.smallListImage | length %}
        {{ element.smallListImage.first().id }}
    {% elseif element.largeListImage | length %}
        {{ element.largeListImage.first().id }}
    {% elseif element.mainImage | length %}
        {{ element.mainImage.first().id }}
    {% endif  %}

You'd probably want to wrap that in `{% spaceless %} ... {% endspaceless %}` to make it more useful.

Or you could just use it to do some bulk work when saving, like pre-generating a bunch of image transforms with [Imager](https://github.com/aelvan/Imager-Craft):

    {% if element.image | length %}
        {% set transformedImages = craft.imager.transformImage(element.image.first(), [
    	    { width: 1000 },
    	    { width: 900 },
    	    { width: 800 },
    	    { width: 700 },
    	    { width: 600 },
    	    { width: 500 },
    	    { width: 400 },
    	    { width: 300 },
    	    { width: 200 },
    	    { width: 100 }
        ]) %}
    {% endif %}

The template path is set to your site template path, so you can even include whole templates if you want to do more advanced stuff and/or want to keep your fields Twig in version control:
{% include '_fields/myFieldInclude' %}
Make sure that you always write solid Twig, taking into account that fields may not be populated yet. If an error occurs in your Twig, the element will not be saved.

## Cache gotchas

The preparse field is only updated when an element is saved. If you grab data from a related element (like in the category title example above), and then update the related element, the preparsed value will not automatically be updated.

## Locales gotchas

The preparse field is made to work with localized sites, but there is one gotcha. It is important that the fields that you process, and the preparse field itself, has the same locales setup. If the target field is set up to be localized to two different languages, and your preparse field is not, the value will be updated to the target field value in the language that was saved last. If your target field is not localized, but you localize your preparse field, you will need to save the entry in both languages if you change the target field.

## Price, license and support

The plugin is released under the MIT license, meaning you can do whatever you want with it as long as you don't blame us. **It's free**, which means there is absolutely no support included, but you might get it anyway. Just post an issue here on github if you have one, and we'll see what we can do. :)

## Changelog

See the [changelog file](https://github.com/besteadfast/craft-preparse-field/blob/craft3/CHANGELOG.md).
