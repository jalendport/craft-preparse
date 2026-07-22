# Preparse — manual smoke checklist

Pest covers pure mapping, coercion, envelope, and migration logic. The Spark Craft Lab page covers the repeatable Craft-coupled baseline, while the remaining control panel and lifecycle scenarios below should be exercised before tagging a release.

## Spark Craft Lab baseline

- [ ] Run `spark lab up` from the plugin repository and open the reported `/lab-test` URL.
- [ ] The page reports `ALL PASS` and contains no `FAIL` markers.
- [ ] The untranslatable text field has the same English locale-sensitive value on the English and German sites.
- [ ] The site-translatable text field has independently rendered English and German values.
- [ ] Numeric values sort as `2,10,100` through `orderBy('labPreparseNumber asc')`, not lexicographically.
- [ ] Date and boolean templates normalize to typed values end to end.
- [ ] A new multisite entry with a Matrix block receives both top-level values and the nested Matrix-entry Preparse value.
- [ ] Clear the seeded Preparse envelopes, run `php craft resave/entries --section=labArticles`, and confirm `/lab-test` returns to `ALL PASS`. This verifies resaves trigger parsing (#40).

## Save lifecycle and search

- [ ] Import entries through a normal Craft import path and confirm Preparse values are generated.
- [ ] Configure a broken template with `onError = blockSave`; confirm the element save rolls back and no partial content remains.
- [ ] Configure `onError = keepPrevious`; save a valid value, break the template, and confirm the prior value remains while the new error is recorded.
- [ ] Configure `onError = fallback`; break the template and confirm the typed fallback value and render error are both stored.
- [ ] Configure an inline-timed field, change only a different field, and confirm the inline field is marked dirty and re-renders.
- [ ] Document the inline-timing limitation with a brand-new element whose template reads Matrix content: nested elements are not yet saved, so the first inline value is stale by design.
- [ ] Make a searchable text Preparse field visible in search results and confirm its generated keywords are indexed after an after-propagate patch.
- [ ] Configure `parseOnMove`, reorder an entry in a structure, and confirm its value changes.
- [ ] Reorder more than 25 structured entries and confirm reparsing queues instead of looping synchronously.
- [ ] Create and save a draft and a provisional draft and confirm both parse; create a revision and confirm its frozen content is not reparsed.

## Settings UX

- [ ] Enter invalid inline Twig and confirm validation rejects it with the correct template line number.
- [ ] Confirm a valid generated-field `{shorthand}` template passes validation.
- [ ] Add a genuine Twig error beside shorthand and confirm validation still rejects it, proving shorthand normalization does not mask errors.
- [ ] In file mode, confirm a nonexistent path is rejected, a valid template file is accepted, and a file containing broken Twig is rejected with its template error.
- [ ] Confirm Monaco loads in the field settings screen, edits persist after saving, and the saved template reopens unchanged.
- [ ] Disable the code-editor module and confirm the settings screen falls back to a working textarea.
- [ ] Confirm the stored-values warning is absent on a brand-new field and appears after at least one value has been parsed.
- [ ] Use the Test button with a valid snippet and confirm its rendered and coerced value.
- [ ] Use the Test button with broken Twig and confirm the render error is shown without saving the field.
- [ ] Test a number field whose snippet returns `1,234.50` and confirm the result says `No value` rather than coercing to zero.
- [ ] Open field settings from a field-layout designer slideout and confirm the Test button works with its namespaced inputs.

## Storage compatibility and display

- [ ] Put a pre-4.0 bare scalar at the field layout-element UID and confirm the field reads and sorts it through the `COALESCE` fallback before any reparse.
- [ ] Reparse that element and confirm the bare scalar is rewritten as a `{value, error?, parsedAt}` envelope.
- [ ] Set `display = value` and confirm the typed stored value renders read-only on the element edit page.
- [ ] Hide a field after a render error and confirm its error remains discoverable through the error banner/utility even though the field input is hidden.

## Typed queries and previews

- [ ] Run ascending and descending number sorts on both MySQL and PostgreSQL and confirm numeric ordering (#104).
- [ ] Run a text sort on MySQL and confirm the multi-key envelope's value is cast correctly.
- [ ] Add numeric-range and date-range field conditions to an element index and confirm their filtered results.
- [ ] Query number and date fields through GraphQL and confirm the number is numeric and the date resolves as `DateTime`.
- [ ] Save a valid value, break its template, and confirm the element-index preview shows both the stale value and its error indicator.

## Reparse tooling

- [ ] Run `php craft preparse-field/reparse` with no arguments on a multisite install and confirm each element is parsed once rather than once per site.
- [ ] Run with `--site=<handle>` against a translatable field and confirm only that site's stored value changes.
- [ ] Set a field to `whenEmpty`, then confirm `--force` overwrites its existing value.
- [ ] Run with `--full-save` and confirm another plugin's element-save hook fires.
- [ ] Trigger the element-index bulk action below and above 25 elements and confirm the synchronous/queued threshold.
- [ ] Break a template and confirm the reparse utility's error table populates; repair it, reparse, and confirm the row clears.

## Generated-field converter

- [ ] Run `php craft preparse-field/convert <handle> --dry-run` and confirm it reports every affected layout without changing fields, layouts, project config, or queue state.
- [ ] Run the converter without `--dry-run`, decline confirmation, and confirm it remains a no-op.
- [ ] Accept confirmation and confirm the generated field is removed, a Preparse field occupies its card-view position and layout, and the copied template is unchanged.
- [ ] Confirm the created Preparse field starts with `valueType = text` and `templateMode = inline`.
- [ ] Confirm affected elements are queued for a forced reparse restricted to the converted field handle.
