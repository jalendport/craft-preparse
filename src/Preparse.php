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
use craft\services\Fields;
use craft\services\Structures;
use craft\web\Application as WebApplication;
use jalendport\base\Plugin;
use jalendport\preparse\fields\PreparseField;
use jalendport\preparse\services\Parser;
use jalendport\preparse\services\Values;
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
     * @var string the plugin's schema version
     *
     * Carried over from the 3.x line so the 4.0 upgrade migration has a known
     * starting point on existing installs.
     *
     * @since 4.0.0
     */
    public string $schemaVersion = '1.1.0';

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
        $this->_registerElementEvents();
        $this->_registerStructureEvents();
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
                foreach ($event->query->all() as $element) {
                    $this->_parseMovedElement($element);
                }
            },
        );
    }
}
