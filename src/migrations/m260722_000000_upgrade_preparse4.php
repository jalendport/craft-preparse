<?php
/**
 * Preparse plugin for Craft CMS 5.x
 *
 * Upgrades Preparse 3.x field types and settings for 4.0.
 *
 * @link      https://jalendport.com
 * @copyright Copyright (c) 2026 Jalen Davenport
 */

namespace jalendport\preparse\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\db\Table;
use craft\helpers\Json;

/**
 * Upgrades Preparse 3.x field types and settings for 4.0.
 *
 * @author Jalen Davenport <hello@jalendport.com>
 * @since 4.0.0
 */
class m260722_000000_upgrade_preparse4 extends Migration
{
    // Const Properties
    // =========================================================================

    /**
     * @var string the Preparse 4.0 field type
     * @since 4.0.0
     */
    private const NEW_FIELD_TYPE = 'jalendport\\preparse\\fields\\PreparseField';

    /**
     * @var string[] the field types used by Preparse 3.x and its prior owners
     * @since 4.0.0
     */
    private const OLD_FIELD_TYPES = [
        'aelvan\\preparsefield\\fields\\PreparseFieldType',
        'besteadfast\\preparsefield\\fields\\PreparseFieldType',
        'jalendport\\preparse\\fields\\PreparseFieldType',
    ];

    // Static Methods
    // =========================================================================

    /**
     * Maps Preparse 3.x field settings to their 4.0 equivalents.
     *
     * @param array<string, mixed> $settings the Preparse 3.x settings
     * @return array<string, mixed> the Preparse 4.0 settings
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    public static function mapSettings(array $settings): array
    {
        $columnType = strtoupper((string)($settings['columnType'] ?? 'TEXT'));

        $mappedSettings = [
            'valueType' => match ($columnType) {
                'INTEGER', 'DECIMAL', 'FLOAT' => 'number',
                'DATETIME' => 'date',
                default => 'text',
            },
            'templateMode' => 'inline',
            'template' => (string)($settings['fieldTwig'] ?? ''),
            'parseTiming' => !empty($settings['parseBeforeSave']) ? 'inline' : 'afterPropagate',
            'parseOnMove' => !empty($settings['parseOnMove']),
            'display' => match ($settings['displayType'] ?? 'hidden') {
                'textinput', 'textarea' => 'value',
                default => 'hidden',
            },
        ];

        if ($columnType === 'INTEGER') {
            $mappedSettings['decimals'] = 0;
        }

        if (in_array($columnType, ['DECIMAL', 'FLOAT'], true)) {
            $mappedSettings['decimals'] = (int)($settings['decimals'] ?? 0);
        }

        return $mappedSettings;
    }

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    public function safeDown(): bool
    {
        echo "m260722_000000_upgrade_preparse4 cannot be reverted.\n";

        return false;
    }

    /**
     * @inheritdoc
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    public function safeUp(): bool
    {
        $this->_updateProjectConfig();
        $this->_updateDatabase();

        return true;
    }

    // Private Methods
    // =========================================================================

    /**
     * Rewrites matching field records and maps their settings.
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    private function _updateDatabase(): void
    {
        $fields = (new Query())
            ->select(['id', 'settings'])
            ->from(Table::FIELDS)
            ->where(['type' => self::OLD_FIELD_TYPES])
            ->all($this->db);

        foreach ($fields as $field) {
            $settings = Json::decodeIfJson($field['settings'] ?? '');

            if (!is_array($settings)) {
                $settings = [];
            }

            $this->update(
                Table::FIELDS,
                [
                    'type' => self::NEW_FIELD_TYPE,
                    'settings' => Json::encode(self::mapSettings($settings)),
                ],
                ['id' => $field['id']],
                updateTimestamp: false,
            );
        }
    }

    /**
     * Recursively rewrites matching field configs and maps their settings.
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    private function _updateProjectConfig(): void
    {
        $projectConfig = Craft::$app->getProjectConfig();
        $fieldConfigs = $projectConfig->find(
            static fn(array $config): bool => in_array($config['type'] ?? null, self::OLD_FIELD_TYPES, true),
        );

        $muteEvents = $projectConfig->muteEvents;
        $projectConfig->muteEvents = true;

        try {
            foreach ($fieldConfigs as $path => $fieldConfig) {
                $settings = $fieldConfig['settings'] ?? [];

                if (!is_array($settings)) {
                    $settings = [];
                }

                $fieldConfig['type'] = self::NEW_FIELD_TYPE;
                $fieldConfig['settings'] = self::mapSettings($settings);

                $projectConfig->set($path, $fieldConfig);
            }
        } finally {
            $projectConfig->muteEvents = $muteEvents;
        }
    }
}
