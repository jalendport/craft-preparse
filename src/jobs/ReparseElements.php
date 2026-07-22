<?php
/**
 * Preparse plugin for Craft CMS 5.x
 *
 * Renders a Twig template when an element is saved and stores the typed result.
 *
 * @link      https://jalendport.com
 * @copyright Copyright (c) 2026 Jalen Davenport
 */

namespace jalendport\preparse\jobs;

use Craft;
use craft\base\Batchable;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\console\Application as ConsoleApplication;
use craft\db\QueryBatcher;
use craft\elements\Entry;
use craft\i18n\Translation;
use craft\queue\BaseBatchedElementJob;
use craft\web\Application as WebApplication;
use jalendport\preparse\fields\PreparseField;
use jalendport\preparse\Preparse;
use Throwable;

/**
 * Re-renders preparse fields across a set of elements.
 *
 * Two modes. By default the job is *lightweight*: it renders, patches the
 * content JSON, and updates the search index, without going anywhere near
 * `saveElement()`. That's the whole point — a reparse of 50,000 entries
 * shouldn't fire 50,000 save lifecycles and wake every other plugin on the
 * install.
 *
 * `$fullSave` opts into real resaves for the cases where that's the actual
 * requirement — usually “another plugin needs to react to the new value”.
 *
 * @author Jalen Davenport <hello@jalendport.com>
 * @since 4.0.0
 */
class ReparseElements extends BaseBatchedElementJob
{
    // Const Properties
    // =========================================================================

    /**
     * @var int The number of elements worth handling inline rather than queueing.
     *
     * Below this, spinning up a queue job costs more than just doing the work —
     * and the person who triggered it gets their answer immediately instead of
     * being told to go watch the queue.
     *
     * @since 4.0.0
     */
    public const SYNC_THRESHOLD = 25;

    // Public Properties
    // =========================================================================

    /**
     * @var array<string, mixed>|null The element criteria selecting what to reparse
     * @since 4.0.0
     */
    public ?array $criteria = null;

    /**
     * @var class-string<ElementInterface> The element type to reparse
     * @since 4.0.0
     */
    public string $elementType = Entry::class;

    /**
     * @var string[] Only reparse these field handles. Empty means every preparse field.
     * @since 4.0.0
     */
    public array $fieldHandles = [];

    /**
     * @var bool Whether to parse fields set to only parse when empty
     * @since 4.0.0
     */
    public bool $force = false;

    /**
     * @var bool Whether to run real resaves instead of patching content directly
     * @since 4.0.0
     */
    public bool $fullSave = false;

    /**
     * @var int[]|null Only write values for these sites, or `null` for all of them
     * @since 4.0.0
     */
    public ?array $siteIds = null;

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    protected function defaultDescription(): ?string
    {
        return Translation::prep('preparse-field', 'Reparsing {type}', [
            'type' => $this->elementType::pluralLowerDisplayName(),
        ]);
    }

    /**
     * @inheritdoc
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    protected function loadData(): Batchable
    {
        // One row per element, not per site: parseElement() fans out across an
        // element's sites itself, so iterating site rows would parse everything
        // once for every site it exists in.
        $query = $this->elementType::find()
            ->siteId('*')
            ->unique()
            ->status(null)
            ->orderBy(['elements.id' => SORT_ASC]);

        if (!empty($this->criteria)) {
            Craft::configure($query, $this->criteria);
        }

        return new QueryBatcher($query);
    }

    /**
     * @inheritdoc
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    protected function processItem(mixed $item): void
    {
        if (!$item instanceof ElementInterface) {
            return;
        }

        try {
            if ($this->fullSave) {
                $this->_resave($item);
                return;
            }

            Preparse::$plugin->values->parseElement(
                $item,
                $this->_filter(),
                invalidateCaches: true,
                force: $this->force,
                siteIds: $this->siteIds,
            );
        } catch (Throwable $e) {
            // One bad template shouldn't abandon the rest of the batch. The
            // error is already stored on the element's envelope, so it surfaces
            // in the utility either way.
            Preparse::error("Couldn’t reparse element {$item->id}: {$e->getMessage()}");
        }
    }

    // Private Methods
    // =========================================================================

    /**
     * Returns the field filter for this job.
     *
     * @return callable(PreparseField): bool|null the filter
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    private function _filter(): ?callable
    {
        if (empty($this->fieldHandles)) {
            return null;
        }

        $handles = $this->fieldHandles;

        return static fn(PreparseField $field) => in_array($field->handle, $handles, true);
    }

    /**
     * Runs a real resave, for when other plugins need to see the save happen.
     *
     * @param ElementInterface $element the element to resave
     * @throws Throwable if the save fails
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    private function _resave(ElementInterface $element): void
    {
        /** @var WebApplication|ConsoleApplication $app */
        $app = Craft::$app;

        $element->setScenario(Element::SCENARIO_ESSENTIALS);
        $element->resaving = true;

        // saveContent forces every field through serializeValueForDb(), which is
        // what inline-timed preparse fields need in order to re-render at all.
        $app->getElements()->saveElement($element, updateSearchIndex: true, saveContent: true);
    }
}
