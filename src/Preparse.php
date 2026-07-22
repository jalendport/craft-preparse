<?php
/**
 * Preparse plugin for Craft CMS 5.x
 *
 * Renders a Twig template when an element is saved and stores the typed result.
 *
 * @link      https://jalendport.com
 * @copyright Copyright (c) 2026 Jalen Davenport
 */

namespace jalendport\preparse;

use Craft;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\console\Application as ConsoleApplication;
use craft\events\BulkOpEvent;
use craft\events\ModelEvent;
use craft\events\MoveElementEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterElementActionsEvent;
use craft\helpers\Queue;
use craft\services\Fields;
use craft\services\Structures;
use craft\services\Utilities;
use craft\web\Application as WebApplication;
use jalendport\base\Plugin;
use jalendport\preparse\elements\actions\Reparse;
use jalendport\preparse\fields\PreparseField;
use jalendport\preparse\jobs\ReparseElements;
use jalendport\preparse\services\Converter;
use jalendport\preparse\services\Parser;
use jalendport\preparse\services\Values;
use jalendport\preparse\utilities\Reparse as ReparseUtility;
use Throwable;
use yii\base\Event;

/**
 * Preparse plugin.
 *
 * The main class is deliberately lean: component registration lives in the
 * static {@see config()} method, and {@see init()} is a table of contents of
 * private `_registerXxx()` methods.
 *
 * @author Jalen Davenport <hello@jalendport.com>
 * @since 4.0.0
 *
 * @property-read Converter $converter
 * @property-read Parser $parser
 * @property-read Values $values
 */
class Preparse extends Plugin
{
    // Static Properties
    // =========================================================================

    /**
     * @var Preparse the plugin instance
     * @since 4.0.0
     */
    public static Preparse $plugin;

    // Public Properties
    // =========================================================================

    /**
     * @var bool whether the plugin has a settings page in the control panel
     * @since 4.0.0
     */
    public bool $hasCpSettings = false;

    /**
     * @var string the minimum 3.x version that can upgrade directly to 4.0
     * @since 4.0.0
     */
    public string $minVersionRequired = '1.5.1';

    /**
     * @var string the plugin's schema version
     * @since 4.0.0
     */
    public string $schemaVersion = '2.0.0';

    // Static Methods
    // =========================================================================

    /**
     * Registers the plugin's components per the Craft 5 plugin spec.
     *
     * @return array the component configuration
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    public static function config(): array
    {
        return [
            'components' => [
                'converter' => Converter::class,
                'parser' => Parser::class,
                'values' => Values::class,
            ],
        ];
    }

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    public function init(): void
    {
        parent::init();
        self::$plugin = $this;

        $this->_registerFieldTypes();
        $this->_registerElementActions();
        $this->_registerElementEvents();
        $this->_registerStructureEvents();
        $this->_registerUtilities();
    }

    // Private Methods
    // =========================================================================

    /**
     * Parses an element after a structure move, for fields that opted in.
     *
     * A failure here is logged rather than raised: a structure reorder is a
     * drag-and-drop gesture, and taking the whole move down because one
     * template misbehaved would be a worse outcome than a stale value.
     *
     * @param ElementInterface $element the element that moved
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    private function _parseMovedElement(ElementInterface $element): void
    {
        try {
            self::$plugin->values->parseElement(
                $element,
                static fn(PreparseField $field) => $field->parseOnMove,
                invalidateCaches: true,
            );
        } catch (Throwable $e) {
            self::error("Couldn’t reparse moved element {$element->id}: {$e->getMessage()}");
        }
    }

    /**
     * Wires up the save lifecycle.
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    private function _registerElementEvents(): void
    {
        Event::on(
            Element::class,
            Element::EVENT_BEFORE_SAVE,
            static function(ModelEvent $event): void {
                /** @var ElementInterface $element */
                $element = $event->sender;

                $handles = array_keys(self::$plugin->values->fieldsFor(
                    $element,
                    static fn(PreparseField $field) => $field->parseTiming === PreparseField::PARSE_TIMING_INLINE,
                ));

                if (empty($handles)) {
                    return;
                }

                // Craft only serializes fields it believes changed, so an inline
                // field would be skipped whenever the editor touched something
                // else. The existing dirty list is preserved and only these
                // handles are added — 3.x marked *every* field dirty and broke
                // change tracking for everyone else on the element (#77).
                $element->setDirtyFields(array_merge($element->getDirtyFields(), $handles));
            },
        );

        Event::on(
            Element::class,
            Element::EVENT_AFTER_PROPAGATE,
            static function(ModelEvent $event): void {
                /** @var ElementInterface $element */
                $element = $event->sender;

                // Everything the element owns — Matrix blocks and the rest — has
                // been written by now, which is the whole reason this is the
                // default timing. Resaves and imports come through here too, so
                // they pick up new values without any special handling (#40).
                self::$plugin->values->parseElement(
                    $element,
                    static fn(PreparseField $field) =>
                        $field->parseTiming === PreparseField::PARSE_TIMING_AFTER_PROPAGATE,
                );
            },
        );
    }

    /**
     * Registers the field type.
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    private function _registerFieldTypes(): void
    {
        Event::on(
            Fields::class,
            Fields::EVENT_REGISTER_FIELD_TYPES,
            static function(RegisterComponentTypesEvent $event): void {
                $event->types[] = PreparseField::class;
            },
        );
    }

    /**
     * Registers the reparse bulk action on element indexes.
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    private function _registerElementActions(): void
    {
        Event::on(
            Element::class,
            Element::EVENT_REGISTER_ACTIONS,
            static function(RegisterElementActionsEvent $event): void {
                /** @var class-string<ElementInterface> $elementType */
                $elementType = $event->sender ?? Element::class;

                // Only offer the action where it would actually do something.
                if (!in_array($elementType, self::$plugin->values->elementTypesWithFields(), true)) {
                    return;
                }

                $event->actions[] = Reparse::class;
            },
        );
    }

    /**
     * Registers the control panel utility.
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    private function _registerUtilities(): void
    {
        Event::on(
            Utilities::class,
            Utilities::EVENT_REGISTER_UTILITIES,
            static function(RegisterComponentTypesEvent $event): void {
                $event->types[] = ReparseUtility::class;
            },
        );
    }

    /**
     * Returns the handles of every field set to reparse on structure moves.
     *
     * @return string[] the field handles
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    private function _parseOnMoveHandles(): array
    {
        /** @var WebApplication|ConsoleApplication $app */
        $app = Craft::$app;

        /** @var PreparseField[] $fields */
        $fields = $app->getFields()->getFieldsByType(PreparseField::class);

        return array_values(array_map(
            static fn(PreparseField $field) => $field->handle,
            array_filter($fields, static fn(PreparseField $field) => $field->parseOnMove),
        ));
    }

    /**
     * Wires up structure moves, for fields whose templates depend on where the
     * element sits in the hierarchy.
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    private function _registerStructureEvents(): void
    {
        Event::on(
            Structures::class,
            Structures::EVENT_AFTER_MOVE_ELEMENT,
            function(MoveElementEvent $event): void {
                /** @var WebApplication|ConsoleApplication $app */
                $app = Craft::$app;

                // Reordering a structure moves a lot of elements at once; during
                // a bulk operation the deferred handler below sweeps them all in
                // one pass instead of once per move.
                if (!empty($app->getElements()->getBulkOpKeys())) {
                    return;
                }

                $this->_parseMovedElement($event->element);
            },
        );

        BulkOpEvent::defer(
            Structures::class,
            Structures::EVENT_AFTER_MOVE_ELEMENT,
            function(BulkOpEvent $event): void {
                $ids = $event->query->ids();

                if (count($ids) > ReparseElements::SYNC_THRESHOLD) {
                    // A full structure reorder can touch thousands of elements,
                    // and this runs at the tail of somebody's request.
                    /** @var class-string<ElementInterface> $elementType */
                    $elementType = $event->query->elementType;

                    Queue::push(new ReparseElements([
                        'criteria' => ['id' => $ids],
                        'elementType' => $elementType,
                        'fieldHandles' => $this->_parseOnMoveHandles(),
                    ]));

                    return;
                }

                foreach ($event->query->all() as $element) {
                    $this->_parseMovedElement($element);
                }
            },
        );
    }
}
