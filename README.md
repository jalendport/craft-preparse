# Preparse

_a plugin for Craft CMS_

Created by [André Elvan](https://www.vaersaagod.no).
Updated and maintained by [Jalen Davenport](https://jalendport.com) & [Michael Rog](https://michaelrog.com).

* * *

## tl:dr

The Preparse field renders a Twig template on element save and stashes the result in your field content.

This way, you can precompute complex derivative values, making them available when querying, filtering, sorting, or rendering your elements.


## Requirements

Preparse 4.x requires Craft CMS 4.0+ and PHP 8.0+.

## Installation

1. From your project directory, use Composer to require the plugin package:

   ```
   composer require jalendport/craft-preparse-field
   ```

2. In the Control Panel, go to Settings → Plugins and click the “Install” button for Preparse.

3. There is no Step 3.

_Preparse is also available for installation via the Craft CMS Plugin Store._


## Usage

When creating a new Preparse field, you add the Twig that you want run to the field's settings. When an element with that Preparse field is saved, the code will be parsed and the resulting value saved as plain text.

It's worth noting that the Preparse field is only updated when the element the field is on is saved. If you grab data from a related element (like in the category title example below), and then update the related element, the preparsed value will not automatically be updated.

In the Twig, the element that the Preparse field is added to is available as a variable named `element`. It's best to use this variable (as opposed to something like `entry` or `asset`) because it's possible you add the same Preparse field to multiple element types. This also means that when a Preparse field is added to a Matrix, SuperTable, or Neo block, that block will be what is available as `element`, so if you want to access the element that the Matrix/SuperTable/Neo field belongs to, you will want to use `element.owner`.

### Examples

If you have a category field on your element named `relatedCategory`, you can save the category title to the Preparse field by adding the following Twig to the field settings:

    {{ element.relatedCategory.one().title ?? '' }}

This is useful for saving preparsed values to a field for use with sorting, searching, or similar things.

You can also do more advanced stuff, for instance performance optimizing. Let's say you have three different asset fields that may or may not be populated. Having to check these in the template may require a bunch of queries since you can't check if a field has a relation in Craft without actually querying for it. You could do something like this to get the id of the asset to use:

    {% if element.smallListImage | length %}
        {{ element.smallListImage.one().id }}
    {% elseif element.largeListImage | length %}
        {{ element.largeListImage.one().id }}
    {% elseif element.mainImage | length %}
        {{ element.mainImage.one().id }}
    {% endif %}

_You'd probably want to wrap that in `{% apply spaceless %} ... {% endapply %}` to make it more useful..._

Or you could just use it to do some bulk work when saving, like pre-generating a bunch of image transforms with [Imager X](https://plugins.craftcms.com/imager-x?craft4):

    {% if element.mainImage | length %}
        {% set transformedImages = craft.imager.transformImage(element.mainImage.one(), [
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

Preparse also has access to your site's template root, so you can even include local templates if you want to do more advanced stuff and/or want to keep your field's Twig in version control:

    {% include '_partials/customPreparseFieldStuff' %}

Make sure that you always write solid Twig, taking into account that fields may not be populated yet. If an error occurs in your Twig, the element will not be saved. [Code defensively!](https://nystudio107.com/blog/handling-errors-gracefully-in-craft-cms#defensive-coding-in-twig)

## Price, License, and Support

The plugin is released under the MIT license, meaning you can do whatever you want with it as long as you don't blame us. **It's free**, which means there is absolutely no support included, but you might get it anyway. Just post an issue here on GitHub if you have one, and we'll see what we can do. :)

## Changelog

See the [changelog file](https://github.com/besteadfast/craft-preparse-field/blob/master/CHANGELOG.md).
