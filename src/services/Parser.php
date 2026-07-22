<?php
/**
 * Preparse plugin for Craft CMS 5.x
 *
 * Renders a Twig template when an element is saved and stores the typed result.
 *
 * @link      https://jalendport.com
 * @copyright Copyright (c) 2026 Jalen Davenport
 */

namespace jalendport\preparse\services;

use Craft;
use craft\base\Component;
use craft\base\ElementInterface;
use craft\console\Application as ConsoleApplication;
use craft\helpers\DateTimeHelper;
use craft\i18n\Locale;
use craft\web\Application as WebApplication;
use craft\web\View;
use jalendport\preparse\events\ParseEvent;
use jalendport\preparse\fields\PreparseField;
use jalendport\preparse\models\ParseResult;
use jalendport\preparse\Preparse;
use Throwable;

/**
 * Renders preparse templates.
 *
 * This service owns exactly one dangerous operation: running arbitrary Twig in
 * the middle of somebody else's request. Everything here is in service of doing
 * that without leaking state — the template mode, the app language, the locale
 * pair, and the transform-generation flag are all swapped in, then restored in
 * a `finally` block that runs even when the template throws a `TypeError`.
 * Preparse 3.x caught only `Exception` and had no `finally`, so a fatal in a
 * template left the whole request rendering in the wrong template mode.
 *
 * @author Jalen Davenport <hello@jalendport.com>
 * @since 4.0.0
 */
class Parser extends Component
{
    // Const Properties
    // =========================================================================

    /**
     * @event ParseEvent The event that is triggered after a field's template has been rendered.
     * @since 4.0.0
     */
    public const EVENT_AFTER_RENDER = 'afterRender';

    // Public Methods
    // =========================================================================

    /**
     * Renders a field's template for an element and coerces the result.
     *
     * Never throws: a broken template produces a result carrying the error
     * message, and it's the caller's job to decide what that means (see the
     * field's “On error” setting). That's deliberate — a Twig typo in one field
     * shouldn't take down an editor's save.
     *
     * @param PreparseField $field the field to render
     * @param ElementInterface $element the element to render it for, in the site it should be rendered for
     * @return ParseResult the render result
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    public function render(PreparseField $field, ElementInterface $element): ParseResult
    {
        $result = new ParseResult();
        $result->parsedAt = DateTimeHelper::now();

        if (trim($field->template) === '') {
            return $result;
        }

        try {
            $rendered = $this->_withSiteContext($element, fn() => $this->_render($field, $element));
            $result->value = ParseResult::coerce($rendered, $field->valueType, $field->decimals);
        } catch (Throwable $e) {
            $result->error = $e->getMessage();

            Preparse::error(
                "Couldn’t render preparse field “{$field->handle}” for element {$element->id} in site {$element->siteId}: {$e->getMessage()}",
            );
        }

        if ($this->hasEventHandlers(self::EVENT_AFTER_RENDER)) {
            $this->trigger(self::EVENT_AFTER_RENDER, new ParseEvent([
                'element' => $element,
                'field' => $field,
                'result' => $result,
            ]));
        }

        return $result;
    }

    // Private Methods
    // =========================================================================

    /**
     * Renders the field's template in site template mode.
     *
     * Inline snippets go through Craft's object-template pipeline, so `{foo}`
     * shorthand and a bare `object` variable work exactly as they do in a
     * generated field — generated-field templates paste in unchanged. `element`
     * is injected as an alias for people who prefer the explicit name.
     *
     * @param PreparseField $field the field to render
     * @param ElementInterface $element the element to render it for
     * @return string the rendered output
     * @throws Throwable if the template can't be rendered
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    private function _render(PreparseField $field, ElementInterface $element): string
    {
        /** @var WebApplication|ConsoleApplication $app */
        $app = Craft::$app;
        $view = $app->getView();
        $variables = ['element' => $element];

        if ($field->templateMode === PreparseField::TEMPLATE_MODE_FILE) {
            $variables['object'] = $element;

            // Sandboxing landed in Craft 5.9; on older versions the unsandboxed
            // call is the only option, and the setting simply has no effect.
            if (method_exists($view, 'renderSandboxedTemplate')) {
                return $view->renderSandboxedTemplate($field->template, $variables, View::TEMPLATE_MODE_SITE);
            }

            return $view->renderTemplate($field->template, $variables, View::TEMPLATE_MODE_SITE);
        }

        if (method_exists($view, 'renderSandboxedObjectTemplate')) {
            return $view->renderSandboxedObjectTemplate(
                $field->template,
                $element,
                $variables,
                View::TEMPLATE_MODE_SITE,
            );
        }

        return $view->renderObjectTemplate($field->template, $element, $variables, View::TEMPLATE_MODE_SITE);
    }

    /**
     * Runs a callback with the app switched to the element's site.
     *
     * Preparse 3.x rendered in whatever language the *request* was in, so a
     * `|date` filter in a template produced the editor's locale rather than the
     * site's, and a French site ended up with English month names whenever an
     * English-speaking author saved (#45). Swapping `language` alone isn't
     * enough — the `locale` and `formattingLocale` components are what the date
     * and number filters actually read.
     *
     * Transform generation is forced on for the duration: templates that ask
     * for an image transform need a URL now, not a deferred one, because there
     * is no page load coming to generate it.
     *
     * @template T
     * @param ElementInterface $element the element whose site should be used
     * @param callable(): T $callback the callback to run
     * @return T the callback's return value
     * @throws Throwable if the callback throws
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    private function _withSiteContext(ElementInterface $element, callable $callback): mixed
    {
        /** @var WebApplication|ConsoleApplication $app */
        $app = Craft::$app;
        $generalConfig = $app->getConfig()->getGeneral();

        $language = $app->language;
        $locale = $app->getLocale();
        $formattingLocale = $app->getFormattingLocale();
        $generateTransforms = $generalConfig->generateTransformsBeforePageLoad;

        $siteLocale = $this->_siteLocale($element);

        if ($siteLocale !== null) {
            $app->language = $siteLocale->id;
            $app->set('locale', $siteLocale);
            $app->set('formattingLocale', $siteLocale);
        }

        $generalConfig->generateTransformsBeforePageLoad = true;

        try {
            return $callback();
        } finally {
            $app->language = $language;
            $app->set('locale', $locale);
            $app->set('formattingLocale', $formattingLocale);
            $generalConfig->generateTransformsBeforePageLoad = $generateTransforms;
        }
    }

    /**
     * Returns the locale for an element's site.
     *
     * @param ElementInterface $element the element
     * @return Locale|null the locale, or `null` if the site's language has no locale data
     * @author Jalen Davenport <hello@jalendport.com>
     * @since 4.0.0
     */
    private function _siteLocale(ElementInterface $element): ?Locale
    {
        /** @var WebApplication|ConsoleApplication $app */
        $app = Craft::$app;

        try {
            return $app->getI18n()->getLocaleById($element->getSite()->language);
        } catch (Throwable) {
            return null;
        }
    }
}
