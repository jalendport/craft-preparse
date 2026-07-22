<?php
/**
 * Preparse plugin for Craft CMS 5.x
 *
 * Renders a Twig template when an element is saved and stores the typed result.
 *
 * @link      https://jalendport.com
 * @copyright Copyright (c) 2026 Jalen Davenport
 */

namespace jalendport\preparse\console\controllers;

use Craft;
use craft\base\ElementInterface;
use craft\console\Application as ConsoleApplication;
use craft\console\Controller;
use craft\elements\db\ElementQueryInterface;
use craft\elements\Entry;
use craft\helpers\Console;
use craft\helpers\Queue;
use jalendport\preparse\fields\PreparseField;
use jalendport\preparse\jobs\ReparseElements;
use jalendport\preparse\Preparse;
use Throwable;
use yii\console\ExitCode;

/**
 * Re-renders preparse fields.
 *
 * With no arguments this sweeps every element type that has a preparse field
 * somewhere in a field layout:
 *
 * ```
 * php craft preparse-field/reparse
 * php craft preparse-field/reparse --fields=readingTime --section=blog --queue
 * ```
 *
 * @author Jalen Davenport <hello@jalendport.com>
 * @since 4.0.0
 */
class ReparseController extends Controller
{
    // Public Properties
    // =========================================================================

    /**
     * @var int The number of elements to process per batch when queueing
     * @since 4.0.0
     */
    public int $batchSize = 100;

    /**
     * @var string|null The element type to reparse. Defaults to every type that has a preparse field.
     * @since 4.0.0
     */
    public ?string $elementType = null;

    /**
     * @var string|null Comma-separated preparse field handles. Defaults to all of them.
     * @since 4.0.0
     */
    public ?string $fields = null;

    /**
     * @var bool Whether to parse fields that are set to only parse when empty
     * @since 4.0.0
     */
    public bool $force = false;

    /**
     * @var bool Whether to run real resaves instead of patching content directly
     * @since 4.0.0
     */
    public bool $fullSave = false;

    /**
     * @var bool Whether to queue the work instead of running it now
     * @since 4.0.0
     */
    public bool $queue = false;

    /**
     * @var string|null Comma-separated section handles. Entries only.
     * @since 4.0.0
     */
    public ?string $section = null;

    /**
     * @var string|null Comma-separated site handles. Defaults to every site.
     * @since 4.0.0
     */
    public ?string $site = null;

    // Public Methods
    // =========================================================================

    /**
     * Re-renders preparse fields.
     *
     * @return int the exit code
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    public function actionIndex(): int
    {
        $handles = $this->_fieldHandles();

        if ($handles === false) {
            return ExitCode::USAGE;
        }

        $siteIds = $this->_siteIds();

        if ($siteIds === false) {
            return ExitCode::USAGE;
        }

        $elementTypes = $this->_elementTypes();

        if (empty($elementTypes)) {
            $this->stdout("No element types have a preparse field.\n", Console::FG_YELLOW);

            return ExitCode::OK;
        }

        foreach ($elementTypes as $elementType) {
            $this->_reparse($elementType, $handles, $siteIds);
        }

        return ExitCode::OK;
    }

    /**
     * @inheritdoc
     * @return array<int, string>
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    public function options($actionID): array
    {
        return [
            ...parent::options($actionID),
            'batchSize',
            'elementType',
            'fields',
            'force',
            'fullSave',
            'queue',
            'section',
            'site',
        ];
    }

    // Private Methods
    // =========================================================================

    /**
     * Returns the criteria selecting which elements to reparse.
     *
     * @param class-string<ElementInterface> $elementType the element type
     * @return array<string, mixed> the criteria
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    private function _criteria(string $elementType): array
    {
        $criteria = [];

        if ($this->section !== null && is_a($elementType, Entry::class, true)) {
            $criteria['section'] = $this->_split($this->section);
        }

        return $criteria;
    }

    /**
     * Returns the element types to sweep.
     *
     * @return array<int, class-string<ElementInterface>> the element types
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    private function _elementTypes(): array
    {
        if ($this->elementType === null) {
            return Preparse::$plugin->values->elementTypesWithFields();
        }

        /** @var class-string<ElementInterface> $elementType */
        $elementType = $this->elementType;

        return [$elementType];
    }

    /**
     * Resolves the `--fields` option to preparse field handles.
     *
     * @return string[]|false the handles, or `false` if one of them isn't a preparse field
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    private function _fieldHandles(): array|false
    {
        if ($this->fields === null) {
            return [];
        }

        /** @var ConsoleApplication $app */
        $app = Craft::$app;
        $handles = $this->_split($this->fields);

        foreach ($handles as $handle) {
            $field = $app->getFields()->getFieldByHandle($handle);

            if (!$field instanceof PreparseField) {
                $this->stderr("“{$handle}” isn’t a preparse field.\n", Console::FG_RED);

                return false;
            }
        }

        return $handles;
    }

    /**
     * Reparses one element type, either now or on the queue.
     *
     * @param class-string<ElementInterface> $elementType the element type
     * @param string[] $handles the field handles to reparse
     * @param int[]|null $siteIds the sites to write values for
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    private function _reparse(string $elementType, array $handles, ?array $siteIds): void
    {
        $criteria = $this->_criteria($elementType);
        $label = $elementType::pluralLowerDisplayName();

        if ($this->queue) {
            $this->do("Queueing a reparse of $label", function() use ($elementType, $criteria, $handles, $siteIds): void {
                Queue::push(new ReparseElements([
                    'batchSize' => $this->batchSize,
                    'criteria' => $criteria,
                    'elementType' => $elementType,
                    'fieldHandles' => $handles,
                    'force' => $this->force,
                    'fullSave' => $this->fullSave,
                    'siteIds' => $siteIds,
                ]));
            });

            return;
        }

        $query = $this->_query($elementType, $criteria);
        $total = (int)$query->count();

        if ($total === 0) {
            $this->stdout("No $label match that criteria.\n", Console::FG_YELLOW);

            return;
        }

        $this->stdout("Reparsing $total $label …\n", Console::FG_YELLOW);
        Console::startProgress(0, $total);

        $done = 0;
        $failed = 0;
        $filter = $this->_filter($handles);

        foreach ($query->each() as $element) {
            try {
                Preparse::$plugin->values->parseElement(
                    $element,
                    $filter,
                    invalidateCaches: true,
                    force: $this->force,
                    siteIds: $siteIds,
                );
            } catch (Throwable $e) {
                $failed++;
                Preparse::error("Couldn’t reparse element {$element->id}: {$e->getMessage()}");
            }

            Console::updateProgress(++$done, $total);
        }

        Console::endProgress();

        $this->table(['Element type', 'Reparsed', 'Failed'], [
            [$elementType::displayName(), $done - $failed, $failed],
        ]);

        if ($failed > 0) {
            $this->stdout("Failures are stored against the elements — see the Preparse utility.\n", Console::FG_YELLOW);
        }
    }

    /**
     * Returns the field filter for a set of handles.
     *
     * @param string[] $handles the field handles
     * @return callable(PreparseField): bool|null the filter
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    private function _filter(array $handles): ?callable
    {
        if (empty($handles)) {
            return null;
        }

        return static fn(PreparseField $field) => in_array($field->handle, $handles, true);
    }

    /**
     * Builds the element query for a reparse.
     *
     * @param class-string<ElementInterface> $elementType the element type
     * @param array<string, mixed> $criteria the criteria
     * @return ElementQueryInterface the query
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    private function _query(string $elementType, array $criteria): ElementQueryInterface
    {
        // One row per element, not per site: parseElement() fans out across an
        // element's sites itself, so iterating site rows would parse everything
        // once for every site it exists in.
        $query = $elementType::find()
            ->siteId('*')
            ->unique()
            ->status(null)
            ->orderBy(['elements.id' => SORT_ASC]);

        if (!empty($criteria)) {
            Craft::configure($query, $criteria);
        }

        return $query;
    }

    /**
     * Resolves the `--site` option to site IDs.
     *
     * @return int[]|false|null the site IDs, `null` for every site, or `false` if a handle is unknown
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    private function _siteIds(): array|false|null
    {
        if ($this->site === null) {
            return null;
        }

        /** @var ConsoleApplication $app */
        $app = Craft::$app;
        $siteIds = [];

        foreach ($this->_split($this->site) as $handle) {
            $site = $app->getSites()->getSiteByHandle($handle);

            if ($site === null) {
                $this->stderr("“{$handle}” isn’t a site handle.\n", Console::FG_RED);

                return false;
            }

            $siteIds[] = $site->id;
        }

        return $siteIds;
    }

    /**
     * Splits a comma-separated option value.
     *
     * @param string $value the option value
     * @return string[] the parts
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    private function _split(string $value): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }
}
