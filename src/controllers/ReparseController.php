<?php
/**
 * Preparse plugin for Craft CMS 5.x
 *
 * Renders a Twig template when an element is saved and stores the typed result.
 *
 * @link      https://jalendport.com
 * @copyright Copyright (c) 2026 Jalen Davenport
 */

namespace jalendport\preparse\controllers;

use Craft;
use craft\base\ElementInterface;
use craft\elements\Entry;
use craft\helpers\Queue;
use craft\web\Application as WebApplication;
use craft\web\Controller;
use jalendport\preparse\jobs\ReparseElements;
use jalendport\preparse\Preparse;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

/**
 * Handles reparse requests from the control panel utility.
 *
 * @author Jalen Davenport <hello@jalendport.com>
 * @since 4.0.0
 */
class ReparseController extends Controller
{
    // Const Properties
    // =========================================================================

    /**
     * @var string The permission required to queue a reparse.
     *
     * Craft registers a `utility:<id>` permission for every utility, so this is
     * the same gate that decides whether the utility is visible at all. The ID
     * comes from {@see \jalendport\preparse\utilities\Reparse::id()}.
     *
     * @since 4.0.0
     */
    public const PERMISSION_UTILITY = 'utility:preparse';

    // Public Methods
    // =========================================================================

    /**
     * Queues a reparse for the scope chosen in the utility.
     *
     * @return Response the response
     * @throws ForbiddenHttpException if the user can't use the utility
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    public function actionIndex(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission(self::PERMISSION_UTILITY);

        /** @var WebApplication $app */
        $app = Craft::$app;
        $request = $app->getRequest();

        $fieldHandles = array_values(array_filter((array)$request->getBodyParam('fields', [])));
        $sections = array_values(array_filter((array)$request->getBodyParam('sections', [])));
        $siteIds = array_map('intval', array_values(array_filter((array)$request->getBodyParam('siteIds', []))));
        $force = (bool)$request->getBodyParam('force');
        $fullSave = (bool)$request->getBodyParam('fullSave');

        $elementTypes = Preparse::$plugin->values->elementTypesWithFields();
        $queued = 0;

        foreach ($elementTypes as $elementType) {
            Queue::push(new ReparseElements([
                'criteria' => $this->_criteria($elementType, $sections),
                'elementType' => $elementType,
                'fieldHandles' => $fieldHandles,
                'force' => $force,
                'fullSave' => $fullSave,
                'siteIds' => $siteIds ?: null,
            ]));

            $queued++;
        }

        if ($queued === 0) {
            $this->setFailFlash(Craft::t('preparse-field', 'No element types have a preparse field.'));

            return $this->redirectToPostedUrl();
        }

        $this->setSuccessFlash(Craft::t('preparse-field', 'Reparse queued.'));

        return $this->redirectToPostedUrl();
    }

    // Private Methods
    // =========================================================================

    /**
     * Builds the element criteria for a queued reparse.
     *
     * @param class-string<ElementInterface> $elementType the element type
     * @param string[] $sections the selected section handles
     * @return array<string, mixed> the criteria
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    private function _criteria(string $elementType, array $sections): array
    {
        if (empty($sections) || !is_a($elementType, Entry::class, true)) {
            return [];
        }

        return ['section' => $sections];
    }
}
