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
    'Keep previous value' => 'Keep previous value',
    'Number' => 'Number',
    'On error' => 'On error',
    'Only when empty' => 'Only when empty',
    'Parse on move' => 'Parse on move',
    'Parse timing' => 'Parse timing',
    'Re-render when the element is moved within a structure. Turn this on if the template uses `parent`, `level`, or anything else that depends on the element’s position.' => 'Re-render when the element is moved within a structure. Turn this on if the template uses `parent`, `level`, or anything else that depends on the element’s position.',
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
    'What to do when the template fails to render. The error is recorded either way and shown on the element’s edit page.' => 'What to do when the template fails to render. The error is recorded either way and shown on the element’s edit page.',
    'Whether the stored value is shown, read-only, on element edit pages. Errors are shown either way.' => 'Whether the stored value is shown, read-only, on element edit pages. Errors are shown either way.',
    'Whether the Twig lives in this field’s settings or in a template file.' => 'Whether the Twig lives in this field’s settings or in a template file.',
    '“After propagate” waits until the element and everything it owns has saved — that’s what makes nested Matrix content visible to the template. “Inline” renders during the save itself, so the value is final before other plugins see it, but nested elements may not have been written yet.' => '“After propagate” waits until the element and everything it owns has saved — that’s what makes nested Matrix content visible to the template. “Inline” renders during the save itself, so the value is final before other plugins see it, but nested elements may not have been written yet.',
    '“Only when empty” renders once and then leaves the value alone.' => '“Only when empty” renders once and then leaves the value alone.',

    // Settings test render
    'Couldn’t find an element to test against. Pick one above.' => 'Couldn’t find an element to test against. Pick one above.',
    'Couldn’t run the test.' => 'Couldn’t run the test.',
    'Optional. Leave empty to test against the most recent entry.' => 'Optional. Leave empty to test against the most recent entry.',
    'Test' => 'Test',
    'Test against' => 'Test against',
    'There’s no template to test yet.' => 'There’s no template to test yet.',

    // Template validation
    'Couldn’t read the template at “{path}”.' => 'Couldn’t read the template at “{path}”.',
    'No template exists at “{path}”.' => 'No template exists at “{path}”.',
    'Twig error on line {line}: {message}' => 'Twig error on line {line}: {message}',

    // Generated field converter
    'A custom field with the handle “{handle}” already exists.' => 'A custom field with the handle “{handle}” already exists.',
    'Aborted.' => 'Aborted.',
    'Convert generated field “{handle}”' => 'Convert generated field “{handle}”',
    'Convert “{handle}” in {count, plural, =1{1 field layout} other{# field layouts}}? This removes the generated field configuration.' => 'Convert “{handle}” in {count, plural, =1{1 field layout} other{# field layouts}}? This removes the generated field configuration.',
    'Converting generated field “{handle}”' => 'Converting generated field “{handle}”',
    'Created Preparse field “{handle}” and queued reparsing.' => 'Created Preparse field “{handle}” and queued reparsing.',
    'Created Preparse field “{handle}”. No affected elements needed reparsing.' => 'Created Preparse field “{handle}”. No affected elements needed reparsing.',
    'Dry run complete. No changes were made.' => 'Dry run complete. No changes were made.',
    'Generated fields with the handle “{handle}” use different templates across layouts.' => 'Generated fields with the handle “{handle}” use different templates across layouts.',
    'Handle' => 'Handle',
    'Layout UID' => 'Layout UID',
    'Name' => 'Name',
    'No generated field with the handle “{handle}” exists.' => 'No generated field with the handle “{handle}” exists.',
    'The field layout “{uid}” could not be saved.' => 'The field layout “{uid}” could not be saved.',
    'The Preparse field could not be saved.' => 'The Preparse field could not be saved.',

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
