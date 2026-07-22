<?php
/**
 * Preparse plugin for Craft CMS 5.x
 *
 * Renders a Twig template when an element is saved and stores the typed result.
 *
 * @link      https://jalendport.com
 * @copyright Copyright (c) 2026 Jalen Davenport
 */

namespace jalendport\preparse\elements\actions;

use Craft;
use craft\base\ElementAction;
use craft\base\ElementInterface;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\Queue;
use jalendport\preparse\jobs\ReparseElements;
use jalendport\preparse\Preparse;
use Throwable;

/**
 * Element index bulk action that re-renders preparse fields.
 *
 * Small selections are handled on the spot so the editor gets an answer
 * immediately; anything larger goes to the queue, because “select all” on an
 * element index can mean tens of thousands of elements and a web request is the
 * wrong place to find that out.
 *
 * @author Jalen Davenport <hello@jalendport.com>
 * @since 4.0.0
 */
class Reparse extends ElementAction
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    public function getTriggerLabel(): string
    {
        return Craft::t('preparse-field', 'Reparse');
    }

    /**
     * @inheritdoc
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    public function performAction(ElementQueryInterface $query): bool
    {
        $ids = $query->ids();
        $count = count($ids);

        if ($count === 0) {
            $this->setMessage(Craft::t('preparse-field', 'Nothing to reparse.'));

            return true;
        }

        if ($count > ReparseElements::SYNC_THRESHOLD) {
            /** @var class-string<ElementInterface> $elementType */
            $elementType = $query->elementType;

            Queue::push(new ReparseElements([
                'criteria' => ['id' => $ids],
                'elementType' => $elementType,
            ]));

            $this->setMessage(Craft::t('preparse-field', 'Reparsing {count} elements in the background.', [
                'count' => $count,
            ]));

            return true;
        }

        foreach ($query->all() as $element) {
            try {
                Preparse::$plugin->values->parseElement($element, invalidateCaches: true);
            } catch (Throwable $e) {
                Preparse::error("Couldn’t reparse element {$element->id}: {$e->getMessage()}");
            }
        }

        $this->setMessage(Craft::t('preparse-field', 'Elements reparsed.'));

        return true;
    }
}
