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
use craft\web\Application as WebApplication;
use craft\web\Controller;
use DateTime;
use jalendport\preparse\fields\PreparseField;
use jalendport\preparse\Preparse;
use Throwable;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

/**
 * Renders a preparse template against a sample element, for the “Test” button
 * on the field settings.
 *
 * The point is to fail here rather than in production: a snippet can be
 * syntactically perfect and still return nothing useful, and finding that out
 * before saving the field beats finding out after every entry has been resaved.
 *
 * @author Jalen Davenport <hello@jalendport.com>
 * @since 4.0.0
 */
class PreviewController extends Controller
{
    // Public Methods
    // =========================================================================

    /**
     * Renders the posted template against a sample element.
     *
     * @return Response the response
     * @throws BadRequestHttpException if the request isn't a valid POST
     * @throws ForbiddenHttpException if the user isn't an admin
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    public function actionRender(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        // Field settings are admin territory. `false` because previewing
        // doesn't write anything, so `allowAdminChanges` isn't relevant.
        $this->requireAdmin(false);

        /** @var WebApplication $app */
        $app = Craft::$app;
        $request = $app->getRequest();

        $field = new PreparseField([
            'decimals' => (int)$request->getBodyParam('decimals', 0),
            'template' => (string)$request->getBodyParam('template', ''),
            'templateMode' => (string)$request->getBodyParam('templateMode', PreparseField::TEMPLATE_MODE_INLINE),
            'valueType' => (string)$request->getBodyParam('valueType', PreparseField::VALUE_TYPE_TEXT),
        ]);

        if (trim($field->template) === '') {
            return $this->asJson([
                'error' => Craft::t('preparse-field', 'There’s no template to test yet.'),
            ]);
        }

        $element = $this->_sampleElement($request->getBodyParam('elementId'));

        if ($element === null) {
            return $this->asJson([
                'error' => Craft::t('preparse-field', 'Couldn’t find an element to test against. Pick one above.'),
            ]);
        }

        $result = Preparse::$plugin->parser->render($field, $element);

        return $this->asJson([
            'element' => (string)$element,
            'error' => $result->error,
            'value' => $this->_display($result->value),
        ]);
    }

    // Private Methods
    // =========================================================================

    /**
     * Formats a parsed value for display, showing the coercion rather than
     * hiding it.
     *
     * @param mixed $value the parsed value
     * @return string the display string
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    private function _display(mixed $value): string
    {
        return match (true) {
            $value === null => Craft::t('preparse-field', 'No value'),
            is_bool($value) => $value ? 'true' : 'false',
            $value instanceof DateTime => $value->format(DateTime::ATOM),
            default => (string)$value,
        };
    }

    /**
     * Returns an element to render against.
     *
     * @param mixed $elementId the posted element ID, if any
     * @return ElementInterface|null the element
     *
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    private function _sampleElement(mixed $elementId): ?ElementInterface
    {
        /** @var WebApplication $app */
        $app = Craft::$app;

        if (is_array($elementId)) {
            $elementId = reset($elementId);
        }

        if ($elementId !== null && $elementId !== '') {
            return $app->getElements()->getElementById((int)$elementId);
        }

        // Nothing picked, so fall back to the most recent entry — enough to
        // prove a snippet runs, even on a field that isn't in a layout yet.
        try {
            return Entry::find()->status(null)->orderBy(['elements.id' => SORT_DESC])->one();
        } catch (Throwable) {
            return null;
        }
    }
}
