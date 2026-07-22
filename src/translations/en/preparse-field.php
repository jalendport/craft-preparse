<?php
/**
 * Preparse plugin for Craft CMS 5.x
 *
 * English translation messages.
 *
 * @link      https://jalendport.com
 * @copyright Copyright (c) 2026 Jalen Davenport
 */

return [
    // Plugin
    'Preparse' => 'Preparse',

    // Field settings
    'After propagate' => 'After propagate',
    'Always' => 'Always',
    'Block the save' => 'Block the save',
    'Boolean' => 'Boolean',
    'Changing the value type also changes how the field sorts, filters, and resolves in GraphQL, so saved condition rules and queries built against the old type may stop matching.' => 'Changing the value type also changes how the field sorts, filters, and resolves in GraphQL, so saved condition rules and queries built against the old type may stop matching.',
    'Date' => 'Date',
    'Decimals' => 'Decimals',
    'Display' => 'Display',
    'Existing values are not re-rendered when this changes.' => 'Existing values are not re-rendered when this changes.',
    'Fallback value' => 'Fallback value',
    'Hidden' => 'Hidden',
    'How many decimal places to keep. Leave at 0 for whole numbers.' => 'How many decimal places to keep. Leave at 0 for whole numbers.',
    'Inline' => 'Inline',
    'Inline snippet' => 'Inline snippet',
    'Number' => 'Number',
    'On error' => 'On error',
    'Only when empty' => 'Only when empty',
    'Parse on move' => 'Parse on move',
    'Parse timing' => 'Parse timing',
    'Reparse existing values →' => 'Reparse existing values →',
    'Stored when a render fails. It’s coerced to the value type just like a rendered result.' => 'Stored when a render fails. It’s coerced to the value type just like a rendered result.',
    'Template file' => 'Template file',
    'Template mode' => 'Template mode',
    'Template path' => 'Template path',
    'Text' => 'Text',
    'The path to a template in your site’s template folder. The element is available as `object` and as `element`.' => 'The path to a template in your site’s template folder. The element is available as `object` and as `element`.',
    'The Twig to render. The element is available as `object` and as `element`, and `{shorthand}` syntax works the same way it does in a generated field.' => 'The Twig to render. The element is available as `object` and as `element`, and `{shorthand}` syntax works the same way it does in a generated field.',
    'The type the rendered result is stored as. Number, boolean, and date values sort and filter as their real type, unlike Craft’s generated fields.' => 'The type the rendered result is stored as. Number, boolean, and date values sort and filter as their real type, unlike Craft’s generated fields.',
    'This field already has stored values. Changing the value type or the template does not re-render them — existing elements keep whatever was last parsed until they’re saved again or reparsed.' => 'This field already has stored values. Changing the value type or the template does not re-render them — existing elements keep whatever was last parsed until they’re saved again or reparsed.',
    'Twig snippet' => 'Twig snippet',
    'Use fallback value' => 'Use fallback value',
    'Value' => 'Value',
    'Value type' => 'Value type',
    'When to parse' => 'When to parse',
    'Whether the stored value is shown, read-only, on element edit pages. Errors are shown either way.' => 'Whether the stored value is shown, read-only, on element edit pages. Errors are shown either way.',
    'Whether the Twig lives in this field’s settings or in a template file.' => 'Whether the Twig lives in this field’s settings or in a template file.',

    // Template validation
    'Couldn’t read the template at “{path}”.' => 'Couldn’t read the template at “{path}”.',
    'No template exists at “{path}”.' => 'No template exists at “{path}”.',
    'Twig error on line {line}: {message}' => 'Twig error on line {line}: {message}',

    // Field values
    'No' => 'No',
    'No value' => 'No value',
    'Parsed value' => 'Parsed value',
    'Yes' => 'Yes',
    'Last parsed {date}' => 'Last parsed {date}',
    'This field couldn’t be parsed.' => 'This field couldn’t be parsed.',
    'This field couldn’t be parsed:' => 'This field couldn’t be parsed:',

    // Reparse action
    'Elements reparsed.' => 'Elements reparsed.',
    'Nothing to reparse.' => 'Nothing to reparse.',
    'Reparse' => 'Reparse',
    'Reparsing {count} elements in the background.' => 'Reparsing {count} elements in the background.',
    'Reparsing {type}' => 'Reparsing {type}',

    // Utility
    'Element' => 'Element',
    'Entries only. Other element types are always reparsed in full.' => 'Entries only. Other element types are always reparsed in full.',
    'Error' => 'Error',
    'Field' => 'Field',
    'Fields' => 'Fields',
    'Last parsed' => 'Last parsed',
    'Much slower, but fires the whole save lifecycle so other plugins react to the new values. Leave this off unless you need it.' => 'Much slower, but fires the whole save lifecycle so other plugins react to the new values. Leave this off unless you need it.',
    'No element types have a preparse field.' => 'No element types have a preparse field.',
    'No preparse fields exist yet.' => 'No preparse fields exist yet.',
    'Parse errors' => 'Parse errors',
    'Parse fields set to only parse when empty' => 'Parse fields set to only parse when empty',
    'Queue reparse' => 'Queue reparse',
    'Re-renders preparse fields and writes the results straight to the elements’ content. Leave a scope empty to include everything.' => 'Re-renders preparse fields and writes the results straight to the elements’ content. Leave a scope empty to include everything.',
    'Reparse queued.' => 'Reparse queued.',
    'Run full saves' => 'Run full saves',
    'Sections' => 'Sections',
    'Sites' => 'Sites',
    'Those fields are normally left alone once they have a value.' => 'Those fields are normally left alone once they have a value.',
    '{count} stored {count, plural, one{value} other{values}} failed to render. The previous value is still in place unless the field was set to use a fallback.' => '{count} stored {count, plural, one{value} other{values}} failed to render. The previous value is still in place unless the field was set to use a fallback.',
];
