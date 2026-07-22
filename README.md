<p align="center"><img src="src/icon.svg" alt="Preparse" width="80" height="80"></p>

<h1 align="center">Preparse</h1>

Preparse is a Craft CMS plugin that renders a Twig template when an element is saved and stores the result as a real field value. Values are stored as text, numbers, booleans, or dates, so they sort, filter, and resolve in GraphQL as the type they actually are. When a template changes, values can be regenerated on demand from the console, an element index, or a control panel utility.

## Features

- **Typed storage.** A value is stored as text, a number, a boolean, or a date — so numbers sort 2, 10, 100 rather than "10", "100", "2", and dates compare as dates.
- **Twig you keep where you like.** Write an inline snippet with syntax highlighting in the control panel, or point the field at a template file in your site's template folder.
- **Searchable.** Preparse values feed Craft's search index through the standard search keywords toggle.
- **Deliberate regeneration.** Reparse from the console, from an element index, or from the Preparse utility — in the background, and without resaving every element.
- **Resilient.** A template that throws doesn't have to take the save down. Keep the previous value, drop in a fallback, or block the save — the error is recorded either way and shown in the control panel.
- **Typed queries.** Element queries, condition rules, index sorting, and GraphQL all understand the field's real type, including numeric and date ranges.
- **Multi-site aware.** Values follow the field's translation method, and templates render in each site's own language.
- **A migration path off generated fields.** One command converts a Craft generated field into a Preparse field, layouts and all.

## Installation

### Requirements

This plugin requires **Craft CMS 5.7.0 or later** and **PHP 8.2 or later**.

> Using Craft 4? Use the [2.x](https://github.com/jalendport/craft-preparse/tree/craft4) line. Using Craft 3? Use the [1.x](https://github.com/jalendport/craft-preparse/tree/craft3) line.

### Plugin Store

Log into your control panel and click on "Plugin Store". Search for "Preparse", then click "Install".

### Composer

Open your terminal, go to your Craft project, and run:

```bash
composer require jalendport/craft-preparse && php craft plugin/install preparse-field
```

## Usage

Create a **Preparse** field in Settings → Fields, write the Twig you want rendered, and add the field to a field layout. From then on, every time an element in that layout is saved, the template is rendered and the result is stored on the element.

Preparse fields are read-only by design: the value comes from your template, never from an author.

### The template context

Templates are rendered as Craft **object templates**, in your site's template mode. Three ways to reach the element are available, and they all refer to the same thing:

```twig
{{ object.title }}   {# `object` — the same variable Craft's generated fields use #}
{{ element.title }}  {# `element` — an alias, if you prefer the explicit name #}
{title}              {# shorthand — expands to {{ object.title }} #}
```

Because the context matches Craft's generated fields, a generated field template pastes into a Preparse field unchanged.

Everything else you'd expect in a site template is available too — `craft.entries`, your Twig extensions, filters, and functions:

```twig
{{ object.title|upper }} — {{ object.author.fullName }}
{{ craft.entries().section('news').relatedTo(object).count() }} related stories
```

Templates render in the language of the **site the element belongs to**, not the language of whoever triggered the save. A `|date` filter on a German site produces German month names even when an English-speaking author hits Save.

### Value types

Every field has a value type, and whatever your template outputs is coerced into it. Output that can't be represented in the target type is stored as **no value**, rather than a misleading zero or epoch date.

| Type | Stores | Coercion |
| --- | --- | --- |
| **Text** | `string` | Trimmed. Empty output is stored as no value. |
| **Number** | `int` or `float` | Only genuinely numeric output is accepted. With `decimals` at `0` the value is rounded to a whole number; above `0` it's rounded to that many places. |
| **Boolean** | `bool` | `false`, `no`, `off`, `n`, and `0` are false; empty output is no value; anything else is true. |
| **Date** | `DateTime` | Anything Craft can parse as a date. Unparseable output is no value. |

#### Number output must be unformatted

The number type accepts `1234.5`. It does **not** accept `1,234.50` — a thousands separator in one locale is a decimal separator in another, and guessing wrong is worse than storing nothing. Formatted output is stored as no value.

Output the raw number, and format it where you display it:

```twig
{# In the field template — no formatting #}
{{ object.price * 1.2 }}

{# In your front-end template — format on output #}
{{ entry.priceWithTax|number(2) }}
```

### Parse timing

| Timing | When it runs |
| --- | --- |
| **After propagate** (default) | After the element and everything it owns has been saved. Nested Matrix content is visible to the template, and the value is written straight to the element's content — no second save. |
| **Inline** | During the save itself, so the value is final before other plugins see it. |

Inline mode has one real limitation: on a **brand-new** element, nested elements haven't been written yet when it runs, so a template that reads a Matrix field will see nothing on the first save. Use inline mode for self-contained snippets, and after propagate for anything that reaches into nested content.

### When to parse

| Mode | Behaviour |
| --- | --- |
| **Always** (default) | The template is rendered on every save. |
| **Only when empty** | The template is rendered while the value is empty, then left alone. Useful for a value that should be captured once and then stay put. |

Fields set to "Only when empty" are skipped by the reparse tools too, unless you pass `--force`.

### Error handling

A template that throws produces an error, and the "On error" setting decides what happens to the value:

| Mode | Behaviour |
| --- | --- |
| **Keep previous value** (default) | The stored value is left as it was. |
| **Use fallback value** | The configured fallback is stored, coerced through the same rules as a rendered result. |
| **Block the save** | The save is rolled back and the exception surfaces. |

All three record the error and the time it happened. Errors show up in three places: on the element's edit page, next to the value in element index tables, and in the Preparse utility's error table. An error clears itself the moment the field parses cleanly again.

Note that "Block the save" throws rather than adding a validation error. By the time an after-propagate template runs, the element has already been written and propagated, so rolling the transaction back is the only way to genuinely stop the save.

### Displaying the value

| Display | Behaviour |
| --- | --- |
| **Hidden** (default) | The field doesn't appear on element edit pages. |
| **Value** | The stored value is shown, read-only and formatted for its type. |

The error banner appears on the edit page whenever an error is stored, regardless of this setting.

### Querying, sorting, and filtering

Preparse fields behave like any other typed field in an element query:

```twig
{# Text #}
{% set entries = craft.entries().mySummary('*deadline*').all() %}

{# Number — ranges work, because the value is a real number #}
{% set entries = craft.entries().wordCount('>= 500').all() %}
{% set entries = craft.entries().wordCount(['and', '>= 500', '< 2000']).all() %}

{# Date #}
{% set entries = craft.entries().lastReviewed('>= ' ~ now|date_modify('-1 month')|atom).all() %}

{# Boolean #}
{% set entries = craft.entries().isFeatured(true).all() %}

{# Sorting orders by the real type #}
{% set entries = craft.entries().orderBy('wordCount desc').all() %}
```

Element index sort options and condition rules are wired up automatically, with numeric and date range rules where the type supports them.

### GraphQL

Each value type resolves as its matching GraphQL type — `String`, `Number`, `Boolean`, or `DateTime`:

```graphql
{
  entries(section: "blog", wordCount: ">= 500") {
    title
    wordCount
    lastReviewed
  }
}
```

### Reparsing

Values are generated when an element is saved. Change a template and existing elements keep what they last stored, until you reparse them.

Reparsing renders and writes the new value directly, without running a full save — so reparsing a large site doesn't fire a save lifecycle for every element and wake every other plugin on the install.

#### Console command

```bash
# Everything, across every element type that has a preparse field
php craft preparse-field/reparse

# One field, one section, in the background
php craft preparse-field/reparse --fields=wordCount --section=blog --queue

# One site's values of a translatable field
php craft preparse-field/reparse --site=de

# Include fields set to only parse when empty
php craft preparse-field/reparse --force

# Run real resaves, so other plugins react to the new values
php craft preparse-field/reparse --full-save
```

| Option | Description |
| --- | --- |
| `--fields` | Comma-separated preparse field handles. Defaults to all of them. |
| `--section` | Comma-separated section handles. Entries only. |
| `--site` | Comma-separated site handles. Only these sites' values are written. Defaults to every site. |
| `--element-type` | A specific element type class. Defaults to every type that has a preparse field. |
| `--queue` | Queue the work instead of running it now. |
| `--force` | Parse fields set to only parse when empty. |
| `--full-save` | Run real resaves instead of writing content directly. Much slower; use it when another plugin needs to react. |
| `--batch-size` | Elements per batch when queueing. Defaults to `100`. |

#### Bulk action

Element indexes get a **Reparse** action wherever a preparse field is in play. Small selections run immediately; larger ones are queued.

#### Utility

Utilities → **Preparse** has a scope picker for queueing a reparse across fields, sections, and sites, and a table of every stored parse error with a link to the element it belongs to. The utility's badge shows how many errors are currently stored.

## Configuration

Preparse has no plugin-level settings. Everything is configured per field, in Settings → Fields.

| Setting | Values | Default | Description |
| --- | --- | --- | --- |
| Value type | `text`, `number`, `boolean`, `date` | `text` | The type the rendered result is stored as. |
| Decimals | `int` | `0` | Number type only. `0` stores whole numbers. |
| Template mode | `inline`, `file` | `inline` | Whether the Twig lives in the field settings or in a template file. |
| Template | Twig, or a template path | _(empty)_ | The snippet, or the path to a template in your site's template folder. |
| Parse timing | `afterPropagate`, `inline` | `afterPropagate` | When the template renders. |
| When to parse | `always`, `whenEmpty` | `always` | Whether to re-render on every save. |
| On error | `keepPrevious`, `fallback`, `blockSave` | `keepPrevious` | What happens when the template throws. |
| Fallback value | `string` | _(empty)_ | Stored when a render fails and On error is `fallback`. |
| Parse on move | `bool` | `false` | Re-render when the element moves within a structure. Turn this on for templates that use `parent`, `level`, or anything else positional. |
| Display | `hidden`, `value` | `hidden` | Whether the value appears on element edit pages. |

Search keywords are controlled by Craft's standard search keywords setting. Date values are deliberately excluded from search keywords — a formatted date is noise in a search index.

### Validating and testing a template

Field settings are validated on save: the template is tokenized and parsed without being rendered, and a syntax error is reported with its line number. A missing template file is reported with the path that was resolved.

The **Test** button renders the current settings against a sample element and shows the result — including the coercion, so a template returning `1,234.50` on a number field visibly produces no value. Pick an element, or leave the picker empty to test against the most recent entry.

## Preparse vs. generated fields

Craft 5.8 added generated fields, which cover the same basic idea: render a template on save, store the result. Preparse is a superset.

| | Generated fields | Preparse |
| --- | --- | --- |
| **Storage type** | String only | Text, number, boolean, or date |
| **Sorting** | Lexicographic — 10, 100, 2 | Typed — 2, 10, 100 |
| **Query params** | String comparison | Typed, including numeric and date ranges |
| **Condition rules** | Text only | Text, number range, date range, boolean |
| **GraphQL** | `String` | `String`, `Number`, `Boolean`, `DateTime` |
| **Search index** | Not searchable | Standard search keywords toggle |
| **Template source** | Inline only | Inline snippet or a template file |
| **Template errors** | The save fails | Keep previous, use a fallback, or block the save — with the error recorded and surfaced |
| **Regeneration** | Resave the elements | Console command, bulk action, utility, or structure moves |
| **Parse once** | Not available | "Only when empty" |
| **Timing** | After propagate | After propagate or inline |

If you don't need anything in the right-hand column, generated fields are built in and perfectly good. Preparse exists for when you do.

### Converting a generated field

`preparse-field/convert` creates a matching Preparse field, swaps it into every field layout that used the generated field, removes the generated field configuration, and queues a reparse of the affected elements.

Inspect the change first:

```bash
php craft preparse-field/convert myGeneratedField --dry-run
```

That prints every field layout the generated field appears in, and makes no changes. When it looks right, run it for real:

```bash
php craft preparse-field/convert myGeneratedField
```

The command lists the affected layouts again and asks for confirmation before touching anything. Because the template context is the same, the template carries over unchanged. The new field starts as a Text field, so set its value type afterwards if it should be a number, boolean, or date, then reparse.

## Upgrading from 3.x

Preparse 4 is a ground-up rebuild. Install it, let the migration run, and read the two breaking changes below.

Upgrading directly requires **Preparse 1.5.1 or later**. On anything older, update to a recent 3.x release first.

### Your stored values survive

The migration doesn't rewrite your content. Existing values keep resolving in templates, queries, and sorts exactly as before, and are rewritten into the 4.0 storage format the first time each element is parsed — on its next save, or whenever you reparse it.

### Settings are mapped for you

| 3.x | 4.0 |
| --- | --- |
| `fieldTwig` | Template, in inline mode |
| `columnType` TEXT / MEDIUMTEXT | Value type: Text |
| `columnType` INTEGER | Value type: Number, decimals `0` |
| `columnType` DECIMAL / FLOAT | Value type: Number, decimals carried over |
| `columnType` DATETIME | Value type: Date |
| `parseBeforeSave` on | Parse timing: Inline |
| `parseBeforeSave` off | Parse timing: After propagate |
| `parseOnMove` | Parse on move (unchanged) |
| `displayType` hidden | Display: Hidden |
| `displayType` textinput / textarea | Display: Value |
| `allowSelect`, `textareaRows`, `showField` | Removed |

### Breaking: the element alias is gone

3.x exposed the element under its reference handle — `entry`, `asset`, `category`, and so on. That alias no longer exists. Use `object`, `element`, or shorthand:

```diff
- {{ entry.title }}
+ {{ object.title }}

- {% if entry.author %}
+ {% if object.author %}
```

A find/replace across your preparse templates for `entry.` → `object.` (and the equivalent for whichever element types you use) is usually the whole job. The **Test** button on the field settings will tell you immediately if you missed one.

### Breaking: the old namespaces are gone

The `aelvan\preparsefield\` and `besteadfast\preparsefield\` class aliases have been removed. The migration rewrites stale references in the database and project config, but if your own modules reference those classes directly, update them to `jalendport\preparse\`.

## FAQ

### Why does my template see the wrong Matrix or Neo blocks?

Element queries in Craft are **mutable objects**. Calling `.limit()`, `.status()`, or similar on `object.myMatrixField` changes that query for everything downstream — including code that runs after your template. Because preparse templates run in the middle of a save, a mutated query can produce wrong results elsewhere in the same request.

Clone the query, or resolve it immediately:

```twig
{# Fine — resolved straight away #}
{% set blocks = object.myMatrixField.all() %}

{# Fine — cloned before being narrowed #}
{% set featured = clone(object.myMatrixField).limit(3).all() %}

{# Risky — mutates the query the element itself is holding #}
{% set featured = object.myMatrixField.limit(3).all() %}
```

This isn't specific to Preparse — it's how element queries work everywhere — but a preparse template is an unusually good place to get caught by it.

### Why doesn't `{% cache %}` do anything in my template?

Craft's non-global template caches are keyed by the **request path**. A preparse template renders during a save, so its cache entry is written under a control panel path and can never be hit from the front end. On console and queue requests, non-global template caching is switched off entirely.

Use `{% cache global %}`, which drops the path from the key and works in every context:

```twig
{% cache global %}
    {# expensive work #}
{% endcache %}
```

See [#83](https://github.com/jalendport/craft-preparse/issues/83) for the background.

### Can I edit a preparse value by hand?

No. The value comes from the template, and any manual edit would be overwritten on the next save. Set Display to "Value" if you want authors to see it.

### Does it parse drafts and revisions?

Drafts and provisional drafts are parsed like any other save. Revisions are never parsed — they're a record of what an element used to be, and re-rendering them would rewrite history.

## Support

Found a bug or have a question? [Open an issue](https://github.com/jalendport/craft-preparse/issues).

---

<p align="center">Made by <a href="https://jalendport.com">Jalen Davenport</a></p>
