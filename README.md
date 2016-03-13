Preparse Field for Craft
===

A fieldtype that parses Twig when an element is saved, and saves the result as plain text.  
**All in the name of performance.**

*Special thanks to [Mats Mikkel](https://github.com/boboldehampsink) and [Bob Olde Hampsink](https://github.com/boboldehampsink) for invaluable help on Slack :)* 

Installation
---
1. Download the zip from this repository, unzip, and put the preparsefield folder in your Craft plugin folder.
2. Enable the plugin in Craft (Settings > Plugins)

The Preparse fieldtype is now available when you create a new field. 


Usage
---
When creating a new Preparse field, you can add the Twig that you want to run to the fields settings. When the entry is 
saved, the element that is saved will be passed to the code, with the same name as it's element type (in lower case). So, if the field 
is attached to an entry, `entry` will be available. If it is attached to a category, user or global set, `category`, `user` and `globalset` will be available.  If attached to Commerce elements, it's `commerce_product`, `commerce_variant` or `commerce_order`.


**Usage in Matrix**  
When a Preparse field is added to a Matrix block, that block will be available to the Twig code as the variable `matrixblock`. The element (entry, category, global set etc) the Matrix field belongs to will be available under `matrixblock.owner`.  
  
### Examples  
  
If you have a category field in your entry named `entryCategory`, you can save the category title to the
preparse field by adding the following Twig to the field settings:

    {{ entry.entryCategory | length ? entry.entryCategory.first().title }}
 
This is useful for saving preparsed values to a field for use with sorting, searching or similar things.
 
You can also do more advanced stuff, for instance for performance optimizing. Let's say you have three different asset 
fields that may or may not be populated. having to check these in the template may require a bunch of queries since you 
can't check if a field has a relation in Craft, without actually querying for it. You could do something like this to 
get the id of the asset to use:

    {% if entry.smallListImage | length %}
        {{ entry.smallListImage.first().id }}
    {% elseif entry.largeListImage | length %}
        {{ entry.largeListImage.first().id }}
    {% elseif entry.mainImage | length %}
        {{ entry.mainImage.first().id }}
    {% endif  %}
 
You'd probably want to wrap that in `{% spaceless %} ... {% endspaceless %}` to make it a bit more useful.

Or you could just use it to do some bulk work when saving, like pregenerating a bunch of image transforms with 
[Imager](https://github.com/aelvan/Imager-Craft) (shameless plug, I know):

    {% if entry.image | length %}
        {% set transformedImages = craft.imager.transformImage(entry.image.first(), [
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
	
The template path is set to your site template path, so you can even include whole templates if you want to do more 
advanced stuff and/or want to keep your fields Twig in version control:
 	
    {% include '_fields/myFieldInclude' %}
	
Make sure that you always write solid Twig, taking into account that fields may not be populated yet. If an error occurs
in your Twig, the element will not be saved. 
 
Cache gotchas
---
The preparse field is only updated when an element is saved. If you grab data from a related element (like in the category 
title example above), and then update the related element, the preparsed value will not automatically be updated. 

 
Locales gotchas
---
The preparse field is made to work with localized sites, but there is one gotcha. It is important that the fields that
you process, and the preparse field itself, has the same locales setup. If the target field is set up to be localized to 
two different languages, and your preparse field is not, the value will be updated to the target field value in the 
language that was saved last. If your target field is not localized, but you localize your preparse field, you will need
to save the entry in both languages if you change the target field.   


Price, license and support
---
The plugin is released under the MIT license, meaning you can do what ever you want with it as long as you don't blame 
me. **It's free**, which means there is absolutely no support included, but you might get it anyway. Just post an issue 
here on github if you have one, and I'll see what I can do. :)


Changelog
---
### Version 0.1
 - Initial Public Release.

