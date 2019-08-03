<?php
/**
 * Preparse Field plugin for Craft CMS 3.x
 *
 * @link      https://www.vaersaagod.no
 * @copyright Copyright (c) 2017 André Elvan
 */

namespace aelvan\preparsefield;

use aelvan\preparsefield\fields\PreparseFieldType;
use aelvan\preparsefield\services\PreparseFieldService as PreparseFieldServiceService;

use Craft;
use craft\base\Element;
use craft\base\Plugin;
use craft\elements\Asset;
use craft\events\ElementEvent;
use craft\events\MoveElementEvent;
use craft\services\Elements;
use craft\services\Fields;
use craft\events\RegisterComponentTypesEvent;

use craft\services\Structures;
use craft\web\UploadedFile;
use yii\base\Event;

/**
 * Preparse field plugin
 *
 * @author    André Elvan
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
        Event::on(Fields::class, Fields::EVENT_REGISTER_FIELD_TYPES,
            function (RegisterComponentTypesEvent $event) {
                $event->types[] = PreparseFieldType::class;
            }
        );

        // Before save element event handler
        Event::on(Elements::class, Elements::EVENT_BEFORE_SAVE_ELEMENT,
            function (ElementEvent $event) {
                /** @var Element $element */
                $element = $event->element;
                $key = $element->id . '__' . $element->siteId;

                if (!\in_array($key, $this->preparsedElements['onBeforeSave'], true)) {
                    $this->preparsedElements['onBeforeSave'][] = $key;

                    $content = self::$plugin->preparseFieldService->getPreparseFieldsContent($element, 'onBeforeSave');

                    if (!empty($content)) {
                        $this->resetUploads();
                        $element->setFieldValues($content);
                    }
                }
            }
        );

        // After save element event handler
        Event::on(Elements::class, Elements::EVENT_AFTER_SAVE_ELEMENT,
            function (ElementEvent $event) {
                /** @var Element $element */
                $element = $event->element;
                $key = $element->id . '__' . $element->siteId;

                if (!\in_array($key, $this->preparsedElements['onSave'], true)) {
                    $this->preparsedElements['onSave'][] = $key;

                    $content = self::$plugin->preparseFieldService->getPreparseFieldsContent($element, 'onSave');
                    
                    if (!empty($content)) {
                        $this->resetUploads();
                        
                        if ($element instanceof Asset) {
                            $element->setScenario(Element::SCENARIO_DEFAULT);
                        }

                        $element->setFieldValues($content);
                        $success = Craft::$app->getElements()->saveElement($element, true, false);

                        // if no success, log error
                        if (!$success) {
                            Craft::error('Couldn’t save element with id “' . $element->id . '”', __METHOD__);
                        }
                    }
                }
            }
        );

        // After move element event handler
        Event::on(Structures::class, Structures::EVENT_AFTER_MOVE_ELEMENT,
            function (MoveElementEvent $event) {
                /** @var Element $element */
                $element = $event->element;
                $key = $element->id . '__' . $element->siteId;

                if (self::$plugin->preparseFieldService->shouldParseElementOnMove($element) && !\in_array($key, $this->preparsedElements['onMoveElement'], true)) {
                    $this->preparsedElements['onMoveElement'][] = $key;

                    if ($element instanceof Asset) {
                        $element->setScenario(Element::SCENARIO_DEFAULT);
                    }

                    $success = Craft::$app->getElements()->saveElement($element, true, false);

                    // if no success, log error
                    if (!$success) {
                        Craft::error('Couldn’t move element with id “' . $element->id . '”', __METHOD__);
                    }
                }
            }
        );
    }

    /**
     * Fix file uploads being processed twice by craft, which causes an error.
     *
     * @see https://github.com/aelvan/Preparse-Field-Craft/issues/23#issuecomment-284682292
     */
    private function resetUploads()
    {
        $_FILES = [];
        UploadedFile::reset();
    }
}
