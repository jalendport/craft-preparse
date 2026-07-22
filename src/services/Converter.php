<?php
/**
 * Preparse plugin for Craft CMS 5.x
 *
 * Converts Craft generated fields to Preparse fields.
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
use craft\fieldlayoutelements\CustomField;
use craft\helpers\Queue;
use craft\models\FieldLayout;
use craft\web\Application as WebApplication;
use InvalidArgumentException;
use jalendport\preparse\fields\PreparseField;
use jalendport\preparse\jobs\ReparseElements;
use RuntimeException;
use Throwable;

/**
 * Converts field-layout generated fields to Preparse fields.
 *
 * @author Jalen Davenport <hello@jalendport.com>
 * @since 4.0.0
 */
class Converter extends Component
{
    // Const Properties
    // =========================================================================

    /**
     * @var int the most element IDs included in one queued reparse job
     * @since 4.0.0
     */
    private const QUEUE_ID_CHUNK_SIZE = 5000;

    // Public Methods
    // =========================================================================

    /**
     * Converts every compatible generated-field usage with the given handle.
     *
     * @param string $handle the generated field handle
     * @return array{field: PreparseField, layoutCount: int, queued: bool} the conversion result
     * @throws InvalidArgumentException if the generated field can't be converted unambiguously
     * @throws Throwable if the field or layouts couldn't be saved
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    public function convert(string $handle): array
    {
        $plan = $this->plan($handle);

        /** @var WebApplication|ConsoleApplication $app */
        $app = Craft::$app;
        $fieldsService = $app->getFields();
        $generatedField = $plan['generatedField'];
        $field = new PreparseField([
            'name' => (string)($generatedField['name'] ?? $handle),
            'handle' => $handle,
            ...self::settingsFor($generatedField),
        ]);

        if (!$fieldsService->saveField($field)) {
            $errors = $field->getErrorSummary(true);
            $message = $errors !== []
                ? implode(' ', $errors)
                : Craft::t('preparse-field', 'The Preparse field could not be saved.');

            throw new RuntimeException($message);
        }

        $this->_replaceGeneratedFields($plan['usages'], $field);
        $queued = $this->_queueReparse($plan['usages'], $field);

        return [
            'field' => $field,
            'layoutCount' => count($plan['usages']),
            'queued' => $queued,
        ];
    }

    /**
     * Builds and validates a conversion plan without changing anything.
     *
     * One global Preparse field can be placed in multiple layouts, but its
     * template setting cannot vary per layout. Identical handle/template
     * usages are converted together; conflicting templates must be resolved
     * before conversion.
     *
     * @param string $handle the generated field handle
     * @return array{generatedField: array<string, mixed>, usages: array<int, array{layout: FieldLayout, generatedField: array<string, mixed>}>} the conversion plan
     * @throws InvalidArgumentException if the generated field can't be converted unambiguously
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    public function plan(string $handle): array
    {
        $usages = $this->_findUsages($handle);

        if ($usages === []) {
            throw new InvalidArgumentException(Craft::t('preparse-field', 'No generated field with the handle “{handle}” exists.', [
                'handle' => $handle,
            ]));
        }

        /** @var WebApplication|ConsoleApplication $app */
        $app = Craft::$app;

        if ($app->getFields()->getFieldByHandle($handle) !== null) {
            throw new InvalidArgumentException(Craft::t('preparse-field', 'A custom field with the handle “{handle}” already exists.', [
                'handle' => $handle,
            ]));
        }

        $templates = array_unique(array_map(
            static fn(array $usage): string => (string)($usage['generatedField']['template'] ?? ''),
            $usages,
        ));

        if (count($templates) > 1) {
            throw new InvalidArgumentException(Craft::t('preparse-field', 'Generated fields with the handle “{handle}” use different templates across layouts.', [
                'handle' => $handle,
            ]));
        }

        return [
            'generatedField' => $usages[0]['generatedField'],
            'usages' => $usages,
        ];
    }

    /**
     * Maps a generated-field config to matching Preparse field settings.
     *
     * @param array<string, mixed> $generatedField the generated-field config
     * @return array{valueType: string, templateMode: string, template: string} the Preparse settings
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    public static function settingsFor(array $generatedField): array
    {
        return [
            'valueType' => PreparseField::VALUE_TYPE_TEXT,
            'templateMode' => PreparseField::TEMPLATE_MODE_INLINE,
            'template' => (string)($generatedField['template'] ?? ''),
        ];
    }

    // Private Methods
    // =========================================================================

    /**
     * Finds every saved field-layout usage of a generated-field handle.
     *
     * @param string $handle the generated field handle
     * @return array<int, array{layout: FieldLayout, generatedField: array<string, mixed>}> the usages
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    private function _findUsages(string $handle): array
    {
        /** @var WebApplication|ConsoleApplication $app */
        $app = Craft::$app;
        $usages = [];

        foreach ($app->getFields()->getAllLayouts() as $layout) {
            foreach ($layout->getGeneratedFields() as $generatedField) {
                if (($generatedField['handle'] ?? null) !== $handle) {
                    continue;
                }

                $usages[] = [
                    'layout' => $layout,
                    'generatedField' => $generatedField,
                ];
            }
        }

        return $usages;
    }

    /**
     * Queues reparsing for elements that use the converted layouts.
     *
     * @param array<int, array{layout: FieldLayout, generatedField: array<string, mixed>}> $usages the converted usages
     * @param PreparseField $field the new Preparse field
     * @return bool whether a reparse job was queued
     * @throws Throwable if a reparse job couldn't be queued
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    private function _queueReparse(array $usages, PreparseField $field): bool
    {
        $layoutIdsByType = [];
        $queued = false;

        foreach ($usages as $usage) {
            $layout = $usage['layout'];

            if ($layout->id === null || $layout->type === null) {
                continue;
            }

            if (!is_subclass_of($layout->type, ElementInterface::class)) {
                continue;
            }

            $layoutIdsByType[$layout->type][] = $layout->id;
        }

        foreach ($layoutIdsByType as $elementType => $layoutIds) {
            $elementIds = array_map(
                static fn(mixed $id): int => (int)$id,
                (new Query())
                    ->select(['id'])
                    ->from(Table::ELEMENTS)
                    ->where(['fieldLayoutId' => array_unique($layoutIds)])
                    ->column(),
            );

            foreach (array_chunk($elementIds, self::QUEUE_ID_CHUNK_SIZE) as $elementIdChunk) {
                Queue::push(new ReparseElements([
                    'criteria' => ['id' => $elementIdChunk],
                    'elementType' => $elementType,
                    'fieldHandles' => [$field->handle],
                    'force' => true,
                ]));

                $queued = true;
            }
        }

        return $queued;
    }

    /**
     * Replaces each generated-field usage and persists its layout in the
     * database and project config.
     *
     * @param array<int, array{layout: FieldLayout, generatedField: array<string, mixed>}> $usages the generated-field usages
     * @param PreparseField $field the new Preparse field
     * @throws Throwable if a layout or project-config occurrence couldn't be saved
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    private function _replaceGeneratedFields(array $usages, PreparseField $field): void
    {
        /** @var WebApplication|ConsoleApplication $app */
        $app = Craft::$app;
        $fieldsService = $app->getFields();
        $projectConfig = $app->getProjectConfig();
        $muteEvents = $projectConfig->muteEvents;
        $projectConfig->muteEvents = true;

        try {
            foreach ($usages as $usage) {
                $layout = $usage['layout'];
                $generatedField = $usage['generatedField'];
                $generatedFieldUid = (string)($generatedField['uid'] ?? '');
                $layoutElementConfig = [];

                if (($generatedField['name'] ?? null) !== $field->name) {
                    $layoutElementConfig['label'] = (string)($generatedField['name'] ?? $field->name);
                }

                $layoutElement = new CustomField($field, $layoutElementConfig);
                $layout->prependElements([$layoutElement]);
                $layout->setGeneratedFields(array_values(array_filter(
                    $layout->getGeneratedFields(),
                    static fn(array $item): bool => ($item['uid'] ?? null) !== $generatedFieldUid,
                )));

                $generatedFieldKey = "generatedField:$generatedFieldUid";
                $layoutElementKey = "layoutElement:$layoutElement->uid";
                $layout->setCardView(array_map(
                    static fn(string $key): string => $key === $generatedFieldKey ? $layoutElementKey : $key,
                    $layout->getCardView(),
                ));

                if (!$fieldsService->saveLayout($layout)) {
                    throw new RuntimeException(Craft::t('preparse-field', 'The field layout “{uid}” could not be saved.', [
                        'uid' => $layout->uid,
                    ]));
                }

                $occurrences = $projectConfig->find(
                    static fn(array $item): bool => isset($item[$layout->uid]),
                );

                foreach ($occurrences as $path => $item) {
                    $projectConfig->set(
                        "$path.$layout->uid",
                        $layout->getConfig(),
                        Craft::t('preparse-field', 'Convert generated field “{handle}”', ['handle' => $field->handle]),
                    );
                }
            }
        } finally {
            $projectConfig->muteEvents = $muteEvents;
        }
    }
}
