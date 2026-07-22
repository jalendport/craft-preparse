<?php
/**
 * Preparse plugin for Craft CMS 5.x
 *
 * Console command for converting a generated field to Preparse.
 *
 * @link      https://jalendport.com
 * @copyright Copyright (c) 2026 Jalen Davenport
 */

namespace jalendport\preparse\console\controllers;

use Craft;
use craft\console\Controller;
use InvalidArgumentException;
use jalendport\base\controllers\ConsoleControllerTrait;
use jalendport\preparse\Preparse;
use jalendport\preparse\services\Converter;
use Throwable;
use yii\base\InvalidConfigException;
use yii\console\ExitCode;

/**
 * Converts Craft generated fields to Preparse fields.
 *
 * @author Jalen Davenport <hello@jalendport.com>
 * @since 4.0.0
 */
class ConvertController extends Controller
{
    use ConsoleControllerTrait;

    // Public Properties
    // =========================================================================

    /**
     * @var bool whether to inspect the conversion without changing anything
     * @since 4.0.0
     */
    public bool $dryRun = false;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    public function options($actionID): array
    {
        return [...parent::options($actionID), 'dryRun'];
    }

    /**
     * Converts the generated field identified by its handle.
     *
     * @param string $generatedFieldHandle the generated field handle
     * @return int the exit code
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    public function actionIndex(string $generatedFieldHandle): int
    {
        try {
            $converter = $this->_converter();
            $plan = $converter->plan($generatedFieldHandle);
        } catch (InvalidArgumentException|InvalidConfigException $e) {
            $this->writeError($e->getMessage());

            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->table(
            [
                Craft::t('preparse-field', 'Layout UID'),
                Craft::t('preparse-field', 'Name'),
                Craft::t('preparse-field', 'Handle'),
            ],
            array_map(
                static fn(array $usage): array => [
                    $usage['layout']->uid,
                    (string)($usage['generatedField']['name'] ?? ''),
                    (string)($usage['generatedField']['handle'] ?? ''),
                ],
                $plan['usages'],
            ),
        );

        if ($this->dryRun) {
            $this->writeSuccess(Craft::t('preparse-field', 'Dry run complete. No changes were made.'));

            return ExitCode::OK;
        }

        if (!$this->confirm(Craft::t('preparse-field', 'Convert “{handle}” in {count, plural, =1{1 field layout} other{# field layouts}}? This removes the generated field configuration.', [
            'handle' => $generatedFieldHandle,
            'count' => count($plan['usages']),
        ]))) {
            $this->writeLine(Craft::t('preparse-field', 'Aborted.'));

            return ExitCode::OK;
        }

        $result = null;

        try {
            $this->do(
                Craft::t('preparse-field', 'Converting generated field “{handle}”', ['handle' => $generatedFieldHandle]),
                static function() use ($converter, $generatedFieldHandle, &$result): void {
                    $result = $converter->convert($generatedFieldHandle);
                },
            );
        } catch (Throwable) {
            return ExitCode::UNSPECIFIED_ERROR;
        }

        if ($result['queued']) {
            $this->writeSuccess(Craft::t('preparse-field', 'Created Preparse field “{handle}” and queued reparsing.', [
                'handle' => $generatedFieldHandle,
            ]));
        } else {
            $this->writeSuccess(Craft::t('preparse-field', 'Created Preparse field “{handle}”. No affected elements needed reparsing.', [
                'handle' => $generatedFieldHandle,
            ]));
        }

        return ExitCode::OK;
    }

    // Private Methods
    // =========================================================================

    /**
     * Returns the registered converter service.
     *
     * @return Converter the converter service
     * @throws InvalidConfigException if the service has not been registered
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    private function _converter(): Converter
    {
        $converter = Preparse::$plugin->get('converter');

        if (!$converter instanceof Converter) {
            throw new InvalidConfigException('The Preparse converter service is not registered.');
        }

        return $converter;
    }
}
