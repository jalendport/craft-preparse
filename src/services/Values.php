<?php
/**
 * Preparse plugin for Craft CMS 5.x
 *
 * Renders a Twig template when an element is saved and stores the typed result.
 *
 * @link      https://jalendport.com
 * @copyright Copyright (c) 2026 Jalen Davenport
 */

namespace jalendport\preparse\services;

use Craft;
use craft\base\Component;
use craft\base\ElementInterface;
use craft\console\Application as ConsoleApplication;
use craft\db\Query;
use craft\db\Table;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\ElementHelper;
use craft\helpers\Json;
use craft\records\Element_SiteSettings as Element_SiteSettingsRecord;
use craft\web\Application as WebApplication;
use DateTime;
use jalendport\preparse\errors\ParseException;
use jalendport\preparse\fields\PreparseField;
use jalendport\preparse\models\ParseResult;
use jalendport\preparse\Preparse;
use Throwable;
use WeakMap;
use yii\db\Expression;

/**
 * Reads, writes, and patches preparse values.
 *
 * The important thing this service does *not* do is call `saveElement()`.
 * Preparse 3.x rendered its fields on after-propagate and then saved the
 * element a second time to persist them, which is the root of most of the
 * plugin's long-standing bugs: resave storms, every field marked dirty (#77),
 * front-end uploads consumed twice (#57, #85), and Matrix blocks going missing
 * on the second pass (#29). Instead, values are written straight into the
 * `elements_sites.content` JSON through the site settings record — the exact
 * mechanism Craft's own generated fields use.
 *
 * @author Jalen Davenport <hello@jalendport.com>
 * @since 4.0.0
 */
class Values extends Component
{
    // Const Properties
    // =========================================================================

    /**
     * @var int how long the control panel badge count is cached, in seconds
     * @since 4.0.0
     */
    private const ERROR_COUNT_CACHE_DURATION = 300;

    /**
     * @var string the control panel badge count cache key
     * @since 4.0.0
     */
    private const ERROR_COUNT_CACHE_KEY = 'preparse-field.error-count';

    /**
     * @var int The most `elements_sites` rows {@see recentErrors()} will pull back in one go.
     *
     * Parse errors are meant to be rare. If an install has more than this many,
     * the utility's job is to say so, not to render thousands of rows.
     *
     * @since 4.0.0
     */
    public const MAX_ERROR_ROWS = 500;

    // Private Properties
    // =========================================================================

    /**
     * @var array<int, class-string<ElementInterface>>|null The element types that have preparse fields
     * @see elementTypesWithFields()
     */
    private ?array $_elementTypes = null;

    /**
     * @var WeakMap<ElementInterface, array<string, ParseResult>> Envelopes seen for each element, keyed by field handle
     * @see getResult()
     */
    private WeakMap $_results;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    public function init(): void
    {
        parent::init();

        // A field's stored envelope carries more than the value the element
        // exposes, so the error and timestamp are parked here when the value is
        // normalized. Weak keys mean a long-running resave doesn't accumulate
        // every element it has touched.
        $this->_results = new WeakMap();
    }

    /**
     * Returns the element types whose field layouts include a given field.
     *
     * @param PreparseField $field the field
     * @return array<int, class-string<ElementInterface>> the element types
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    public function elementTypesForField(PreparseField $field): array
    {
        if (!isset($field->id)) {
            return [];
        }

        /** @var WebApplication|ConsoleApplication $app */
        $app = Craft::$app;
        $types = [];

        foreach ($app->getFields()->getAllLayouts() as $layout) {
            if ($layout->type === null || isset($types[$layout->type])) {
                continue;
            }

            foreach ($layout->getCustomFields() as $instance) {
                if ($instance instanceof PreparseField && $instance->id === $field->id) {
                    $types[$layout->type] = true;
                    break;
                }
            }
        }

        /** @var array<int, class-string<ElementInterface>> $keys */
        $keys = array_keys($types);

        return $keys;
    }

    /**
     * Returns the element types that have a preparse field somewhere in a field layout.
     *
     * Used to decide which element indexes get the reparse bulk action, and
     * which element types a bare `reparse` command should sweep.
     *
     * @return array<int, class-string<ElementInterface>> the element types
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    public function elementTypesWithFields(): array
    {
        if (isset($this->_elementTypes)) {
            return $this->_elementTypes;
        }

        /** @var WebApplication|ConsoleApplication $app */
        $app = Craft::$app;
        $types = [];

        foreach ($app->getFields()->getAllLayouts() as $layout) {
            if ($layout->type === null || isset($types[$layout->type])) {
                continue;
            }

            foreach ($layout->getCustomFields() as $field) {
                if ($field instanceof PreparseField) {
                    $types[$layout->type] = true;
                    break;
                }
            }
        }

        /** @var array<int, class-string<ElementInterface>> $keys */
        $keys = array_keys($types);
        $this->_elementTypes = $keys;

        return $this->_elementTypes;
    }

    /**
     * Returns the cached number of stored parse errors.
     *
     * @return int the stored parse error count
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    public function errorCount(): int
    {
        /** @var WebApplication|ConsoleApplication $app */
        $app = Craft::$app;

        return (int)$app->getCache()->getOrSet(
            self::ERROR_COUNT_CACHE_KEY,
            fn(): int => count($this->recentErrors(self::MAX_ERROR_ROWS)),
            self::ERROR_COUNT_CACHE_DURATION,
        );
    }

    /**
     * Returns the preparse fields in an element's field layout.
     *
     * @param ElementInterface $element the element
     * @param callable(PreparseField): bool|null $filter an optional filter
     * @return array<string, PreparseField> the fields, indexed by handle
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    public function fieldsFor(ElementInterface $element, ?callable $filter = null): array
    {
        $fields = [];

        foreach ($element->getFieldLayout()?->getCustomFields() ?? [] as $field) {
            if (!$field instanceof PreparseField) {
                continue;
            }

            if ($filter !== null && !$filter($field)) {
                continue;
            }

            $fields[$field->handle] = $field;
        }

        return $fields;
    }

    /**
     * Returns the envelope last seen for an element's field, if any.
     *
     * @param ElementInterface $element the element
     * @param PreparseField $field the field
     * @return ParseResult|null the envelope
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    public function getResult(ElementInterface $element, PreparseField $field): ?ParseResult
    {
        return ($this->_results[$element] ?? [])[$field->handle] ?? null;
    }

    /**
     * Returns whether a field has any values stored anywhere.
     *
     * The settings page uses this to decide whether changing the value type or
     * the template is actually risky. On a field nobody has used yet, a warning
     * about existing values would be noise.
     *
     * @param PreparseField $field the field
     * @return bool whether any element has a value for it
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    public function hasStoredValues(PreparseField $field): bool
    {
        if (!isset($field->id)) {
            return false;
        }

        /** @var WebApplication|ConsoleApplication $app */
        $app = Craft::$app;
        $uids = [];

        foreach ($app->getFields()->getAllLayouts() as $layout) {
            foreach ($layout->getCustomFields() as $instance) {
                if ($instance instanceof PreparseField && $instance->id === $field->id) {
                    $uid = $instance->layoutElement->uid ?? null;

                    if ($uid !== null) {
                        $uids[$uid] = true;
                    }
                }
            }
        }

        if (empty($uids)) {
            return false;
        }

        $db = $app->getDb();
        $qb = $db->getQueryBuilder();
        $condition = ['or'];

        foreach (array_keys($uids) as $uid) {
            $condition[] = new Expression(sprintf(
                '%s IS NOT NULL',
                $qb->jsonExtract('content', [$uid, ParseResult::KEY_VALUE]),
            ));
        }

        return (new Query())
            ->from([Table::ELEMENTS_SITES])
            ->where($condition)
            ->exists($db);
    }

    /**
     * Renders a field and applies its “On error” policy.
     *
     * @param PreparseField $field the field to parse
     * @param ElementInterface $element the element to parse it for
     * @param ParseResult $previous the envelope currently stored
     * @return ParseResult the envelope to store
     * @throws ParseException if the render failed and the field blocks saves on error
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    public function parse(PreparseField $field, ElementInterface $element, ParseResult $previous): ParseResult
    {
        $result = Preparse::$plugin->parser->render($field, $element);

        if (!$result->hasError()) {
            return $result;
        }

        if ($field->onError === PreparseField::ON_ERROR_BLOCK_SAVE) {
            throw new ParseException("Couldn’t render preparse field “{$field->handle}”: {$result->error}");
        }

        // Both surviving policies still persist the error and the timestamp, so
        // the control panel can say what went wrong even though the save stood.
        $result->value = $field->onError === PreparseField::ON_ERROR_FALLBACK
            ? ParseResult::coerce($field->fallbackValue, $field->valueType, $field->decimals)
            : $previous->value;

        return $result;
    }

    /**
     * Parses an element's preparse fields across every site it supports, and
     * patches the results into the content JSON.
     *
     * @param ElementInterface $element the element that was saved or moved
     * @param callable(PreparseField): bool|null $filter which fields to parse
     * @param bool $invalidateCaches whether to invalidate the element's caches afterwards
     * @param bool $force whether to parse even fields set to only parse when empty
     * @param int[]|null $siteIds only write values for these sites, or `null` for all of them
     * @throws ParseException if a render failed and the field blocks saves on error
     * @throws Throwable if the content couldn't be written
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    public function parseElement(
        ElementInterface $element,
        ?callable $filter = null,
        bool $invalidateCaches = false,
        bool $force = false,
        ?array $siteIds = null,
    ): void {
        // Revisions are frozen history; re-rendering them would rewrite the past (#103).
        if ($element->getIsRevision() || ElementHelper::isRevision($element)) {
            return;
        }

        $fields = $this->fieldsFor($element, $filter);

        if (empty($fields)) {
            return;
        }

        $siteElements = $this->_siteElements($element);
        $results = [];

        foreach ($fields as $field) {
            foreach ($this->_translationGroups($field, $siteElements) as $group) {
                // One render per translation group: an untranslatable field
                // renders once and the value is copied to every site (#96),
                // while a translatable one lands in a group per site and gets
                // rendered against that site's element in that site's language.
                $source = reset($group);
                $previous = $this->_previousResult($source, $field);

                if (!$force && !$this->shouldParse($field, $previous)) {
                    continue;
                }

                $result = $this->parse($field, $source, $previous);

                foreach (array_keys($group) as $siteId) {
                    $results[$siteId][$field->handle] = $result;
                }
            }
        }

        foreach ($results as $siteId => $siteResults) {
            // Grouping needs every site — a translation group is only meaningful
            // whole — but writing can be narrowed, which is what lets a reparse
            // refresh one site of a translatable field without touching the rest.
            if ($siteIds !== null && !in_array($siteId, $siteIds, true)) {
                continue;
            }

            $this->patch($siteElements[$siteId], $fields, $siteResults, $invalidateCaches);
        }
    }

    /**
     * Writes envelopes into an element's `elements_sites.content` JSON.
     *
     * @param ElementInterface $element the element, in the site being written
     * @param array<string, PreparseField> $fields the element's preparse fields, indexed by handle
     * @param array<string, ParseResult> $results the envelopes to write, indexed by field handle
     * @param bool $invalidateCaches whether to invalidate the element's caches afterwards
     * @return bool whether anything was written
     * @throws Throwable if the record couldn't be saved
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    public function patch(
        ElementInterface $element,
        array $fields,
        array $results,
        bool $invalidateCaches = false,
    ): bool {
        $record = Element_SiteSettingsRecord::findOne([
            'elementId' => $element->id,
            'siteId' => $element->siteId,
        ]);

        if ($record === null) {
            return false;
        }

        $content = $this->_content($record);
        $updated = false;

        foreach ($results as $handle => $result) {
            $uid = $fields[$handle]->layoutElement->uid ?? null;

            if ($uid === null) {
                continue;
            }

            $stored = $result->toStoredValue();

            if (!$this->_hasChanged($content[$uid] ?? null, $stored)) {
                continue;
            }

            $updated = true;

            if ($stored === null) {
                unset($content[$uid]);
                continue;
            }

            $content[$uid] = $stored;
        }

        if (!$updated) {
            return false;
        }

        $record->content = $content ?: null;
        $record->save(false, ['content']);

        $this->_invalidateErrorCountCache();

        $this->_refreshElement($element, $fields, $results);

        if ($invalidateCaches) {
            /** @var WebApplication|ConsoleApplication $app */
            $app = Craft::$app;
            $app->getElements()->invalidateCachesForElement($element);
        }

        return true;
    }

    /**
     * Returns the stored parse errors across every element, newest first.
     *
     * Errors aren't kept in a table of their own — they live in the same
     * content JSON as the values they belong to, which means they can never
     * drift out of sync with what's actually stored, and they disappear on
     * their own the moment a field parses cleanly again.
     *
     * @param int $limit the maximum number of errors to return
     * @return array<int, array{elementId: int, siteId: int, handle: string, name: string, error: string, parsedAt: DateTime|null}>
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    public function recentErrors(int $limit = 50): array
    {
        $placements = $this->_fieldPlacements();

        if (empty($placements)) {
            return [];
        }

        /** @var WebApplication|ConsoleApplication $app */
        $app = Craft::$app;
        $db = $app->getDb();
        $qb = $db->getQueryBuilder();

        $condition = ['or'];

        foreach (array_keys($placements) as $uid) {
            // jsonExtract() quotes the path itself, so the UID can't escape it.
            $condition[] = new Expression(sprintf(
                '%s IS NOT NULL',
                $qb->jsonExtract('content', [$uid, ParseResult::KEY_ERROR]),
            ));
        }

        $rows = (new Query())
            ->select(['elementId', 'siteId', 'content'])
            ->from([Table::ELEMENTS_SITES])
            ->where($condition)
            ->limit(self::MAX_ERROR_ROWS)
            ->all($db);

        $errors = [];

        foreach ($rows as $row) {
            $content = $row['content'] ?? null;

            if (is_string($content)) {
                $content = $content !== '' ? Json::decode($content) : [];
            }

            if (!is_array($content)) {
                continue;
            }

            foreach ($placements as $uid => $placement) {
                $result = ParseResult::fromStoredValue($content[$uid] ?? null);

                if (!$result->hasError()) {
                    continue;
                }

                $errors[] = [
                    'elementId' => (int)$row['elementId'],
                    'siteId' => (int)$row['siteId'],
                    'handle' => $placement['handle'],
                    'name' => $placement['name'],
                    'error' => (string)$result->error,
                    'parsedAt' => $result->parsedAt,
                ];
            }
        }

        // The rows can't be ordered by timestamp in SQL, because the timestamp
        // sits inside the JSON at a different path per field placement.
        usort($errors, static fn(array $a, array $b) => ($b['parsedAt']?->getTimestamp() ?? 0) <=> ($a['parsedAt']?->getTimestamp() ?? 0));

        return array_slice($errors, 0, $limit);
    }

    /**
     * Records the envelope seen for an element's field.
     *
     * @param ElementInterface $element the element
     * @param PreparseField $field the field
     * @param ParseResult $result the envelope
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    public function rememberResult(ElementInterface $element, PreparseField $field, ParseResult $result): void
    {
        $results = $this->_results[$element] ?? [];
        $previous = $results[$field->handle] ?? null;

        if ($previous instanceof ParseResult && $previous->error !== $result->error) {
            $this->_invalidateErrorCountCache();
        }

        $results[$field->handle] = $result;
        $this->_results[$element] = $results;
    }

    /**
     * Returns whether a field should be re-rendered given what's already stored.
     *
     * @param PreparseField $field the field
     * @param ParseResult $previous the envelope currently stored
     * @return bool whether to parse
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    public function shouldParse(PreparseField $field, ParseResult $previous): bool
    {
        if ($field->whenToParse === PreparseField::WHEN_TO_PARSE_ALWAYS) {
            return true;
        }

        return $previous->value === null || $previous->value === '';
    }

    // Private Methods
    // =========================================================================

    /**
     * Returns a site settings record's content as an array.
     *
     * @param Element_SiteSettingsRecord $record the record
     * @return array<string, mixed> the content
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    private function _content(Element_SiteSettingsRecord $record): array
    {
        $content = $record->content ?? [];

        if (is_string($content)) {
            return $content !== '' ? Json::decode($content) : [];
        }

        return $content;
    }

    /**
     * Returns every preparse field placement across all field layouts.
     *
     * Keyed by layout element UID, because that's the key a value is stored
     * under in the content JSON — the same field placed twice in one layout has
     * two entries, and two different values.
     *
     * @return array<string, array{handle: string, name: string}> the placements
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    private function _fieldPlacements(): array
    {
        /** @var WebApplication|ConsoleApplication $app */
        $app = Craft::$app;
        $placements = [];

        foreach ($app->getFields()->getAllLayouts() as $layout) {
            foreach ($layout->getCustomFields() as $field) {
                if (!$field instanceof PreparseField) {
                    continue;
                }

                $uid = $field->layoutElement->uid ?? null;

                if ($uid === null) {
                    continue;
                }

                $placements[$uid] = [
                    'handle' => $field->handle,
                    'name' => $field->getUiLabel(),
                ];
            }
        }

        return $placements;
    }

    /**
     * Returns whether a new envelope differs from what's already stored.
     *
     * Only the value and the error take part in the comparison. `parsedAt`
     * changes on every render, so including it would mean a write on every
     * save and defeat the whole point of the check — and since an unchanged
     * value *was* produced by the earlier render, keeping its original
     * timestamp is the truthful answer anyway.
     *
     * Anything stored in the pre-4.0 bare-scalar shape always counts as
     * changed, so the first parse upgrades it to the envelope shape.
     *
     * @param mixed $existing the stored value
     * @param array<string, mixed>|null $stored the new envelope
     * @return bool whether it changed
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    private function _hasChanged(mixed $existing, ?array $stored): bool
    {
        if ($existing === null || $stored === null) {
            return $existing !== $stored;
        }

        if (!is_array($existing)) {
            return true;
        }

        $keys = [ParseResult::KEY_VALUE, ParseResult::KEY_ERROR];

        foreach ($keys as $key) {
            if (($existing[$key] ?? null) != ($stored[$key] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Clears the cached control panel badge count.
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    private function _invalidateErrorCountCache(): void
    {
        /** @var WebApplication|ConsoleApplication $app */
        $app = Craft::$app;
        $app->getCache()->delete(self::ERROR_COUNT_CACHE_KEY);
    }

    /**
     * Returns an element query for the same element in its other sites.
     *
     * Mirrors the query Craft builds when it propagates an element, so drafts
     * resolve to drafts and revisions to revisions rather than silently
     * collapsing onto the canonical element.
     *
     * @param ElementInterface $element the element
     * @return ElementQueryInterface the query
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    private function _localizedQuery(ElementInterface $element): ElementQueryInterface
    {
        $query = $element->getLocalized();

        if ($query instanceof ElementQueryInterface) {
            return $query;
        }

        return $element::find()
            ->id($element->id ?: false)
            ->structureId($element->structureId)
            ->drafts($element->getIsDraft())
            ->provisionalDrafts($element->isProvisionalDraft)
            ->revisions($element->getIsRevision());
    }

    /**
     * Returns the envelope currently stored for an element's field.
     *
     * Reading the field value is what forces Craft to normalize it, and
     * normalizing is what parks the envelope where {@see getResult()} can find
     * it — site elements pulled fresh from the database haven't been through
     * that yet.
     *
     * @param ElementInterface $element the element
     * @param PreparseField $field the field
     * @return ParseResult the stored envelope
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    private function _previousResult(ElementInterface $element, PreparseField $field): ParseResult
    {
        try {
            $element->getFieldValue($field->handle);
        } catch (Throwable) {
            // The field isn't readable on this element; treat it as unset.
        }

        return $this->getResult($element, $field) ?? new ParseResult();
    }

    /**
     * Pushes freshly patched values back onto the in-memory element and
     * reindexes them for search.
     *
     * Without this, code reading the field straight after `saveElement()` would
     * see the pre-save value. Craft's own search-index step has already worked
     * out which fields to reindex by the time the content is patched, so
     * anything searchable has to be handed to the search service directly.
     *
     * @param ElementInterface $element the element
     * @param array<string, PreparseField> $fields the element's preparse fields, indexed by handle
     * @param array<string, ParseResult> $results the envelopes that were written
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    private function _refreshElement(ElementInterface $element, array $fields, array $results): void
    {
        $searchable = [];

        foreach ($results as $handle => $result) {
            // Handing the envelope itself to the element means normalizeValue()
            // re-remembers the error alongside the value.
            $element->setFieldValue($handle, $result);
            $element->getFieldValue($handle);

            if ($fields[$handle]->searchable) {
                $searchable[] = $handle;
            }
        }

        if (empty($searchable)) {
            return;
        }

        /** @var WebApplication|ConsoleApplication $app */
        $app = Craft::$app;
        $app->getSearch()->indexElementAttributes($element, $searchable);
    }

    /**
     * Returns the element across every site it supports, indexed by site ID.
     *
     * The element passed in is always first, so an untranslatable field renders
     * against the site the editor was actually working in.
     *
     * @param ElementInterface $element the element
     * @return array<int, ElementInterface> the site elements
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    private function _siteElements(ElementInterface $element): array
    {
        $siteElements = [$element->siteId => $element];

        try {
            $supportedSiteIds = array_column(ElementHelper::supportedSitesForElement($element), 'siteId');
        } catch (Throwable) {
            return $siteElements;
        }

        $otherSiteIds = array_values(array_diff($supportedSiteIds, [$element->siteId]));

        if (empty($otherSiteIds)) {
            return $siteElements;
        }

        $found = $this->_localizedQuery($element)
            ->siteId($otherSiteIds)
            ->status(null)
            ->indexBy('siteId')
            ->all();

        foreach ($found as $siteId => $siteElement) {
            // A brand-new element is still mid-propagation here, and core
            // crashed assuming every supported site already had a row to render
            // against (craftcms#18393). Anything missing is skipped rather than
            // rendered against nothing; it gets picked up on the next save.
            if (!$siteElement instanceof ElementInterface) {
                continue;
            }

            $siteElements[$siteId] = $siteElement;
        }

        return $siteElements;
    }

    /**
     * Groups site elements by the field's translation key.
     *
     * Craft's translation methods already encode “which sites share one value”,
     * so grouping on the key handles every method — none, site, site group,
     * language, and custom — without the plugin second-guessing any of them.
     *
     * @param PreparseField $field the field
     * @param array<int, ElementInterface> $siteElements the site elements
     * @return array<string, array<int, ElementInterface>> the groups
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    private function _translationGroups(PreparseField $field, array $siteElements): array
    {
        $groups = [];

        foreach ($siteElements as $siteId => $siteElement) {
            $groups[$field->getTranslationKey($siteElement)][$siteId] = $siteElement;
        }

        return $groups;
    }
}
