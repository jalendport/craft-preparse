# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## Unreleased

> Preparse 4 is a ground-up rebuild. Stored values carry over untouched and field settings are migrated automatically, but templates that used the reference-handle alias need a find/replace. See [Upgrading from 3.x](https://github.com/jalendport/craft-preparse/blob/master/README.md#upgrading-from-3x).

### Added
- Added typed value storage — text, number, boolean, and date values are stored and queried as their real type ([#60](https://github.com/jalendport/craft-preparse/issues/60), [#74](https://github.com/jalendport/craft-preparse/issues/74), [#93](https://github.com/jalendport/craft-preparse/issues/93))
- Added element index sort options that order by the field’s real type ([#104](https://github.com/jalendport/craft-preparse/issues/104))
- Added typed condition rules, including numeric and date ranges
- Added typed GraphQL support, so a number field resolves as a number and a date field as a `DateTime`
- Added an “On error” setting, for keeping the previous value, storing a fallback, or blocking the save
- Added a “When to parse” setting, for values that should be rendered once and then left alone
- Added a “Template mode” setting, for keeping the Twig in a site template file instead of the field settings
- Added stored parse errors, surfaced on element edit pages, in element indexes, and in the Preparse utility
- Added a `preparse-field/reparse` console command, with `--fields`, `--section`, `--site`, `--queue`, `--force`, and `--full-save` options
- Added a “Reparse” bulk action to element indexes
- Added a Preparse utility, for reparsing on demand and reviewing stored parse errors
- Added a `preparse-field/convert` console command, for converting a Craft generated field into a Preparse field
- Added preparse values to element index tables and cards, with an indicator when a stored value is out of date
- Added Twig syntax validation when field settings are saved ([#78](https://github.com/jalendport/craft-preparse/issues/78))
- Added a Monaco editor for the Twig snippet, with Twig highlighting and line numbers
- Added a “Test” button to the field settings, for rendering the current template against a sample element
- Added a warning when changing the value type or template of a field that already holds values

### Changed
- Changed parsing to write the element’s content directly instead of saving the element a second time ([#29](https://github.com/jalendport/craft-preparse/issues/29), [#77](https://github.com/jalendport/craft-preparse/issues/77))
- Changed multi-site parsing to follow the field’s translation method ([#96](https://github.com/jalendport/craft-preparse/issues/96))
- Changed templates to render in the language of the element’s site rather than the current user’s ([#45](https://github.com/jalendport/craft-preparse/issues/45))
- Changed number coercion to reject formatted output such as `1,234.50`, which is now stored as no value
- Changed `parseBeforeSave` to the “Parse timing” setting, which renders during the save rather than before it
- Changed the minimum requirement to Craft CMS 5.7
- Changed 3.x field settings to be mapped to their 4.0 equivalents automatically on update

### Removed
- Removed the reference-handle template alias (`entry`, `asset`, and so on); use `object`, `element`, or `{shorthand}`
- Removed the `aelvan` and `besteadfast` namespace aliases
- Removed the `displayType`, `allowSelect`, `textareaRows`, and `showField` settings, replaced by the “Display” setting

### Fixed
- Fixed resaves and imports not regenerating preparse values ([#40](https://github.com/jalendport/craft-preparse/issues/40))
- Fixed front-end file uploads being lost when an element had a preparse field ([#57](https://github.com/jalendport/craft-preparse/issues/57), [#85](https://github.com/jalendport/craft-preparse/issues/85))
- Fixed an `UnsupportedSiteException` when saving elements in some multi-site setups ([#67](https://github.com/jalendport/craft-preparse/issues/67))
- Fixed every field on an element being marked as changed when a preparse field was parsed ([#77](https://github.com/jalendport/craft-preparse/issues/77))
- Fixed revisions being parsed ([#103](https://github.com/jalendport/craft-preparse/issues/103))

## 3.0.0-alpha.2 - 2024-07-15
### Fixed
- Fixed reference to renamed method that was preventing preparse fields from rendering in the table view in certain cases ([#101](https://github.com/jalendport/craft-preparse/issues/101))

## 3.0.0-alpha.1 - 2024-07-12
### Added
- Initial Craft 5 release

## 2.1.2 - 2024-07-12
### Fixed
 - Added namespace aliasing to prevent integrations with other plugins/modules from breaking

## 2.1.1 - 2024-07-12
### Fixed
 - Fixed a bug where the `preparseFieldService` could not be found([#100](https://github.com/jalendport/craft-preparse/issues/100))

## 2.1.0 - 2024-07-11
### Changed
- Migrated to `jalendport/craft-preparse`

## 2.0.2 - 2022-12-05
### Fixed
- Updated reference to Twigfield

## 2.0.1 - 2022-12-02
### Changed
- Updated to use craft-code-editor instead of craft-twigfield ([#87](https://github.com/jalendport/craft-preparse/pull/87) - thanks @khalwat)

## 2.0.0 - 2022-08-08
### Added
- Initial Craft 4 release

## 1.4.1 - 2022-12-02
### Changed
- Updated to use craft-code-editor instead of craft-twigfield ([#86](https://github.com/jalendport/craft-preparse/pull/86) - thanks @khalwat)

## 1.4.0 - 2022-08-08
### Added
- Added support for craft-twigfield ([#81](https://github.com/jalendport/craft-preparse/pull/81) - thanks @khalwat)

## 1.3.0 - 2022-08-06
### Added
- Added datetime column type option ([#63](https://github.com/jalendport/craft-preparse/pull/63) - thanks @mmikkel)

## 1.2.5 - 2021-07-02
### Fixed
- Reverted [#66](https://github.com/jalendport/craft-preparse/pull/66) due to bug where sometimes the element couldn't be re-fetched from the database ([#70](https://github.com/jalendport/craft-preparse/issues/70), [#71](https://github.com/jalendport/craft-preparse/issues/71), [#72](https://github.com/jalendport/craft-preparse/issues/72), [#73](https://github.com/jalendport/craft-preparse/issues/73))
- Fixed a bug causing missing Matrix blocks on elements in certain cases ([#69](https://github.com/jalendport/craft-preparse/issues/69))

## 1.2.4 - 2021-02-24
### Fixed
- Fixed a bug preventing elements from saving successfully in certain multisite setups ([#70](https://github.com/jalendport/craft-preparse/pull/70))

## 1.2.3 - 2021-02-23
### Fixed
- Fixed a bug causing missing Matrix blocks on new elements ([#66](https://github.com/jalendport/craft-preparse/pull/66) - thanks @monachilada)

## 1.2.2 - 2020-11-30
### Fixed
- Fixed a bug causing missing Matrix blocks on revisions ([#65](https://github.com/jalendport/craft-preparse/pull/65) - thanks @brandonkelly)

## 1.2.1 - 2020-06-25
### Fixed
- Fixed incorrect branch names in README and composer.json

## 1.2.0 - 2020-06-25
Transfer of ownership...

### Added
- Added a class alias so sites with Preparse currently installed will continue to function smoothly after the namespace change

## 1.1.0 - 2019-08-03
### Fixed
- Fixes compability issues with Craft 3.2 (Thanks, @brandonkelly).

### Added
- Added `SortableFieldInterface` to field type.

### Changed
- Changed composer requirement for `craftcms/cms` to `^3.2.0`.

## 1.0.7 - 2019-08-03
### Changed
- Replaced `unset()` on `$_FILES` with setting it to an empty array (fixes #52).

## 1.0.6 - 2019-03-21
### Fixed
- Fixed a bug where warnings weren’t showing up when editing an existing preparse field’s column type.

## 1.0.5.1 - 2019-02-27
### Fixed
- Fixed an error that occurred when updating to preparse 1.0.5 on Craft 3.0.x

## 1.0.5 - 2019-02-27
### Added
- Adds Craft 3 migrations. (thanks @carlcs). 

## 1.0.4 - 2018-12-16
### Added
- Adds support for showing preparse fields in element indexes (#33) (thanks @benface). 

## 1.0.3 - 2018-10-24
### Fixed
- Fixed an issue (#45) that would occure when uploading files through a front-end form for elements with a preparse field (thanks @aaronwaldon and @ademers). 

## 1.0.2 - 2018-08-01
### Fixed
- Fixed a bug that would keep preparse fields on assets from parsing on first save/upload (#37). 
- Fixes a bug where preparse fields could not be hidden in asset element modals and matrixblocks.

## 1.0.1 - 2018-07-30
### Added
- Added support for DECIMAL column types.

### Fixed
- Fixed an issue that would result in a duplicate key exception in multisite installations. 

## 1.0.0 - 2017-12-02
### Added
- Initial Craft 3 release.
