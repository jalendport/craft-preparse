<?php
/**
 * Preparse Field plugin for Craft CMS 3.x
 *
 * @link      https://www.steadfastdesignfirm.com/
 * @copyright Copyright (c) Steadfast Design Firm
 */

namespace besteadfast\preparsefield;

use besteadfast\preparsefield\fields\PreparseFieldType;
use besteadfast\preparsefield\services\PreparseFieldService as PreparseFieldServiceService;

use Craft;
use craft\base\Element;
use craft\base\Plugin;
use craft\elements\Asset;
use craft\events\ElementEvent;
use craft\events\MoveElementEvent;
use craft\helpers\FileHelper;
use craft\services\Elements;
use craft\services\Fields;
use craft\events\RegisterComponentTypesEvent;

use craft\services\Structures;
use craft\web\UploadedFile;
use yii\base\Event;

/**
 * Preparse field plugin
 *
 * @author    Steadfast Design Firm
 * @package   PreparseField
 * @since     1.0.0
 *
 * @property  PreparseFieldServiceService $preparseFieldService
 */
class PreparseField extends Plugin
{
    /**
     * Static property that is an instance of this plugin class so that it can be accessed via
     * PreparseField::$plugin
     *
     * @var PreparseField
     */
    public static $plugin;

    /**
     * Stores the IDs of elements we already preparsed the fields for.
     *
     * @var array
     */
    public $preparsedElements;

    /**
     *  Plugin init method
     */
    public function init()
    {
        parent::init();
        self::$plugin = $this;

        $this->preparsedElements = [
            'onBeforeSave' => [],
            'onSave' => [],
            'onMoveElement' => [],
        ];

        // Register our fields
        Event::on(
            Fields::class,
            Fields::EVENT_REGISTER_FIELD_TYPES,
            static function (RegisterComponentTypesEvent $event) {
                $event->types[] = PreparseFieldType::class;
            }
        );

        // Before save element event handler
        Event::on(
            Elements::class,
            Elements::EVENT_BEFORE_SAVE_ELEMENT,
            function (ElementEvent $event) {
                if ($event->element->getIsRevision()) {
                    return;
                }

                /** @var Element $element */
                $element = $event->element;
                $key = $element->id . '__' . $element->siteId;

                if (!isset($this->preparsedElements['onBeforeSave'][$key])) {
                    $this->preparsedElements['onBeforeSave'][$key] = true;

                    $content = self::$plugin->preparseFieldService->getPreparseFieldsContent($element, 'onBeforeSave');

                    if (!empty($content)) {
                        $this->resetUploads();
                        $element->setFieldValues($content);
                    }

                    unset($this->preparsedElements['onBeforeSave'][$key]);
                }
            }
        );

        // After save element event handler
        Event::on(
            Elements::class,
            Elements::EVENT_AFTER_SAVE_ELEMENT,
            function (ElementEvent $event) {
                if ($event->element->getIsRevision()) {
                    return;
                }

                /** @var Element $element */
                $element = $event->element;
                $key = $element->id . '__' . $element->siteId;

                if (!isset($this->preparsedElements['onSave'][$key])) {
                    $this->preparsedElements['onSave'][$key] = true;

                    // Still pass the event element here to generate the preparse fields content, not the $element fetched above.
                    $content = self::$plugin->preparseFieldService->getPreparseFieldsContent($event->element, 'onSave');

                    if (!empty($content)) {
                        $this->resetUploads();

                        $element->setFieldValues($content);
                        $success = Craft::$app->getContent()->saveContent($element);

                        // if no success, log error
                        if (!$success) {
                            Craft::error('Couldn’t save element with id “' . $element->id . '”', __METHOD__);
                        }
                    }

                    unset($this->preparsedElements['onSave'][$key]);
                }

            }
        );

        // After move element event handler
        Event::on(
            Structures::class,
            Structures::EVENT_AFTER_MOVE_ELEMENT,
            function (MoveElementEvent $event) {
                /** @var Element $element */
                $element = $event->element;
                $key = $element->id . '__' . $element->siteId;

                if (self::$plugin->preparseFieldService->shouldParseElementOnMove($element) && !isset($this->preparsedElements['onMoveElement'][$key])) {
                    $this->preparsedElements['onMoveElement'][$key] = true;

                    if ($element instanceof Asset) {
                        $element->setScenario(Element::SCENARIO_DEFAULT);
                    }

                    $success = Craft::$app->getElements()->saveElement($element, true, false);

                    // if no success, log error
                    if (!$success) {
                        Craft::error('Couldn’t move element with id “' . $element->id . '”', __METHOD__);
                    }

                    unset($this->preparsedElements['onMoveElement'][$key]);
                }
            }
        );
    }

    /**
     * @param $msg
     * @param string $level
     * @param string $file
     */
    public static function log($msg, $level = 'notice', $file = 'Preparse')
    {
        try
        {
            $file = Craft::getAlias('@storage/logs/' . $file . '.log');
            $log = "\n" . date('Y-m-d H:i:s') . " [{$level}]" . "\n" . print_r($msg, true);
            FileHelper::writeToFile($file, $log, ['append' => true]);
        }
        catch(\Exception $e)
        {
            Craft::error($e->getMessage());
        }
    }

    /**
     * @param $msg
     * @param string $level
     * @param string $file
     */
    public static function error($msg, $level = 'error', $file = 'RecurringOrders')
    {
        static::log($msg, $level, $file);
    }

    /**
     * Fix file uploads being processed twice by craft, which causes an error.
     *
     * @see https://github.com/besteadfast/craft-preparse-field/issues/23#issuecomment-284682292
     */
    private function resetUploads()
    {
        $_FILES = [];
        UploadedFile::reset();
    }
}
