<?php
/**
 * Preparse — Spark Craft Lab seed hook
 *
 * Builds a multisite field surface and deterministic content for the smoke
 * page. Schema creation and content seeding happen in separate processes:
 * Craft filters custom-field values against stale layout state when entries
 * are saved in the same process that created their schema.
 *
 * @link https://github.com/jalendport/spark-craft-lab
 */

use craft\base\Field;
use craft\elements\Entry;
use craft\fieldlayoutelements\CustomField;
use craft\fields\Matrix;
use craft\models\EntryType;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;
use craft\models\Section_SiteSettings;
use craft\models\Site;
use jalendport\preparse\fields\PreparseField;

return function ($controller = null): void {
    $out = static function (string $message) use ($controller): void {
        if ($controller !== null && method_exists($controller, 'stdout')) {
            $controller->stdout("  [preparse] $message\n");
        }
    };

    $fail = static function (string $label, $model): void {
        throw new RuntimeException(sprintf(
            'Could not save %s: %s',
            $label,
            json_encode($model->getErrors()) ?: 'unknown validation error',
        ));
    };

    $root = Craft::getAlias('@root');
    $run = static function (string $command) use ($root, $out): void {
        // Child Craft processes cannot acquire the project-config lock while
        // this process still has modified config data pending.
        Craft::$app->getProjectConfig()->saveModifiedConfigData();

        $out("> $command");
        exec(sprintf('cd %s && %s 2>&1', escapeshellarg($root), $command), $output, $code);

        if ($code !== 0) {
            throw new RuntimeException("Command failed ($code): $command\n" . implode("\n", $output));
        }
    };

    $fields = Craft::$app->getFields();
    $entries = Craft::$app->getEntries();
    $sites = Craft::$app->getSites();
    $schemaCreated = false;
    $layoutReady = getenv('LAB_PREPARSE_LAYOUT_READY') !== false;

    $ensurePreparseField = static function (string $handle, string $name, array $config) use ($fields, $fail, $out, &$schemaCreated): PreparseField {
        $field = $fields->getFieldByHandle($handle);

        if ($field instanceof PreparseField) {
            return $field;
        }

        if ($field !== null) {
            throw new RuntimeException("Field $handle exists with an unexpected type.");
        }

        $schemaCreated = true;
        $out("Creating field $handle");
        $field = new PreparseField([
            'name' => $name,
            'handle' => $handle,
            'searchable' => true,
            'templateMode' => PreparseField::TEMPLATE_MODE_INLINE,
            'parseTiming' => PreparseField::PARSE_TIMING_AFTER_PROPAGATE,
            ...$config,
        ]);

        if (!$fields->saveField($field)) {
            $fail("field $handle", $field);
        }

        return $field;
    };

    $primarySite = $sites->getPrimarySite();
    $germanSite = $sites->getSiteByHandle('labGerman');

    if ($germanSite === null) {
        $schemaCreated = true;
        $out('Creating German lab site');
        $germanSite = new Site([
            'groupId' => $primarySite->groupId,
            'handle' => 'labGerman',
            'primary' => false,
            'hasUrls' => true,
        ]);
        $germanSite->setName('Deutsch');
        $germanSite->setLanguage('de');
        $germanSite->setBaseUrl(rtrim((string)$primarySite->getBaseUrl(), '/') . '/de/');
        $germanSite->setEnabled(true);

        // Spark's Alpine image ships English-only ICU locale data. The site is
        // intentionally German, so bypass only the locale-list validation;
        // Craft still persists it through the normal project-config path.
        if (!$sites->saveSite($germanSite, false)) {
            $fail('German lab site', $germanSite);
        }
    }

    $section = $entries->getSectionByHandle('labArticles');
    $siteSettings = $section->getSiteSettings();

    if (!isset($siteSettings[$germanSite->id])) {
        $schemaCreated = true;
        $out('Enabling labArticles on the German site');
        $siteSettings[$germanSite->id] = new Section_SiteSettings([
            'siteId' => $germanSite->id,
            'enabledByDefault' => true,
            'hasUrls' => true,
            'uriFormat' => 'de/lab/articles/{slug}',
            'template' => '_lab/article',
        ]);
        $section->setSiteSettings($siteSettings);

        if (!$entries->saveSection($section)) {
            $fail('labArticles section', $section);
        }
    }

    // Parser installs the element site's language before rendering. Translation
    // grouping should render this once for the shared field and once per site
    // for the translated field.
    $localeTemplate = '{{ craft.app.language }}';

    $sharedField = $ensurePreparseField('labPreparseShared', 'Lab Preparse Shared', [
        'translationMethod' => Field::TRANSLATION_METHOD_NONE,
        'valueType' => PreparseField::VALUE_TYPE_TEXT,
        'template' => $localeTemplate,
    ]);
    $translatedField = $ensurePreparseField('labPreparseTranslated', 'Lab Preparse Translated', [
        'translationMethod' => Field::TRANSLATION_METHOD_SITE,
        'valueType' => PreparseField::VALUE_TYPE_TEXT,
        'template' => $localeTemplate,
    ]);
    $numberField = $ensurePreparseField('labPreparseNumber', 'Lab Preparse Number', [
        'valueType' => PreparseField::VALUE_TYPE_NUMBER,
        'decimals' => 0,
        'template' => "{{ object.slug|split('-')|last }}",
    ]);
    $dateField = $ensurePreparseField('labPreparseDate', 'Lab Preparse Date', [
        'valueType' => PreparseField::VALUE_TYPE_DATE,
        'template' => '2026-07-22 15:30:00',
    ]);
    $booleanField = $ensurePreparseField('labPreparseBoolean', 'Lab Preparse Boolean', [
        'valueType' => PreparseField::VALUE_TYPE_BOOLEAN,
        'template' => "{{ object.slug == 'preparse-10' ? 'true' : 'false' }}",
    ]);
    $nestedField = $ensurePreparseField('labPreparseNested', 'Lab Preparse Nested', [
        'valueType' => PreparseField::VALUE_TYPE_TEXT,
        'template' => 'nested-{{ object.owner.slug }}',
    ]);

    $matrixEntryType = $entries->getEntryTypeByHandle('labPreparseBlock');

    if ($matrixEntryType === null) {
        $schemaCreated = true;
        $out('Creating labPreparseBlock entry type');
        $matrixEntryType = new EntryType([
            'name' => 'Lab Preparse Block',
            'handle' => 'labPreparseBlock',
            'hasTitleField' => false,
        ]);
    }

    if (!$layoutReady) {
        $matrixLayout = $matrixEntryType->getFieldLayout() ?? new FieldLayout(['type' => Entry::class]);
        $matrixTabs = array_values(array_filter(
            $matrixLayout->getTabs(),
            static fn(FieldLayoutTab $tab): bool => $tab->name !== 'Preparse Lab',
        ));
        $matrixTab = new FieldLayoutTab(['name' => 'Preparse Lab', 'layout' => $matrixLayout]);
        $matrixTab->setElements([new CustomField($nestedField)]);
        $matrixTabs[] = $matrixTab;
        $matrixLayout->setTabs($matrixTabs);
        $matrixEntryType->setFieldLayout($matrixLayout);

        if (!$entries->saveEntryType($matrixEntryType)) {
            $fail('labPreparseBlock entry type', $matrixEntryType);
        }
    }

    $matrixField = $fields->getFieldByHandle('labPreparseMatrix');

    if (!$matrixField instanceof Matrix) {
        if ($matrixField !== null) {
            throw new RuntimeException('Field labPreparseMatrix exists with an unexpected type.');
        }

        $schemaCreated = true;
        $out('Creating field labPreparseMatrix');
        $matrixField = new Matrix([
            'name' => 'Lab Preparse Matrix',
            'handle' => 'labPreparseMatrix',
            'viewMode' => Matrix::VIEW_MODE_BLOCKS,
        ]);
        $matrixField->setEntryTypes([$matrixEntryType]);

        if (!$fields->saveField($matrixField)) {
            $fail('field labPreparseMatrix', $matrixField);
        }
    }

    $articleType = $entries->getEntryTypeByHandle('labArticle');

    if (!$layoutReady) {
        // Rebuild the dedicated tab each run so a reseed repairs its exact
        // field surface without duplicating the tab.
        $articleLayout = $articleType->getFieldLayout();
        $articleTabs = array_values(array_filter(
            $articleLayout->getTabs(),
            static fn(FieldLayoutTab $tab): bool => $tab->name !== 'Preparse Lab',
        ));
        $articleTab = new FieldLayoutTab(['name' => 'Preparse Lab', 'layout' => $articleLayout]);
        $articleTab->setElements(array_map(
            static fn($field): CustomField => new CustomField($field),
            [$sharedField, $translatedField, $numberField, $dateField, $booleanField, $matrixField],
        ));
        $articleTabs[] = $articleTab;
        $articleLayout->setTabs($articleTabs);
        $articleType->setFieldLayout($articleLayout);

        if (!$entries->saveEntryType($articleType)) {
            $fail('labArticle entry type', $articleType);
        }
    }

    // Never save content in the process that created its schema. A fresh
    // process is required even after refreshing Craft's schema caches.
    if ($schemaCreated || !$layoutReady) {
        if ($schemaCreated && getenv('LAB_PREPARSE_SCHEMA_BUILT') !== false) {
            throw new RuntimeException('Lab schema still missing after the schema pass.');
        }

        $out('Schema or layout updated — re-running the seed for the content pass');
        $run('LAB_PREPARSE_SCHEMA_BUILT=1 LAB_PREPARSE_LAYOUT_READY=1 php craft lab/seed');

        return;
    }

    $out('Seeding deterministic Preparse entries');

    foreach ([2, 10, 100] as $number) {
        $slug = "preparse-$number";
        $entry = Entry::find()
            ->section('labArticles')
            ->siteId($primarySite->id)
            ->slug($slug)
            ->status(null)
            ->one() ?? new Entry();

        $entry->sectionId = $section->id;
        $entry->typeId = $articleType->id;
        $entry->siteId = $primarySite->id;
        $entry->enabled = true;
        $entry->title = "Preparse $number";
        $entry->slug = $slug;
        $entry->setFieldValue('labSummary', "Deterministic Preparse value $number");
        $entry->setFieldValue('labBody', "This entry should parse the typed number $number.");
        $entry->setFieldValue('labPreparseMatrix', $number === 10 ? [
            'new1' => [
                'type' => 'labPreparseBlock',
                'enabled' => true,
                'fields' => [],
            ],
        ] : []);

        if (!Craft::$app->getElements()->saveElement($entry)) {
            $fail("entry $slug", $entry);
        }
    }
};
