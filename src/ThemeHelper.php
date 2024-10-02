<?php

namespace NateWr\themehelper;

use NateWr\themehelper\interfaces\TemplateManager;
use NateWr\themehelper\exceptions\MissingFunctionParam;

/**
 * A helper class for building custom themes for
 * PKP's scholarly publishing software (OJS, OMP,
 * and OPS).
 *
 * Typically used to register helper functions
 * with the TemplateManager, for use in .tpl files.
 *
 * The TemplateManager class is an instance of the
 * Smarty templating library.
 *
 * @see https://www.smarty.net
 */
class ThemeHelper
{
    /**
     * Register helper functions with the Smarty
     * template manager
     *
     * @param TemplateManager $templateMgr
     */
    public function registerTemplateFunctions($templateMgr): void
    {
        $templateMgr->register_function('th_locales', [$this, 'setLocales']);
    }

    /**
     * Set the locales supported by the journal or site to the
     * template variable passed to the function.
     */
    public function setLocales(array $params, $smarty): void
    {
        if (!$this->hasParams($params, ['assign'], 'th_locales')) {
            return;
        }

        $request = Application::get()->getRequest();
        $context = $request->getContext();

        $locales = $context
            ? $context->getSupportedLocaleNames()
            : $request->getSite()->getSupportedLocaleNames();

        $smarty->assign($params['assign'], $locales);
    }

    /**
     * Throw an exception if any of the required parameters
     * are missing from the array.
     *
     * Expects params a [key => value] map that is part
     * of all custom Smarty functions.
     *
     * @param array $params A [key => value] map. Typically c
     * @param string[] $requiredParams List of required param keys.
     * @throws MissingFunctionParam
     */
    public function hasParams(array $params, array $requiredParams, string $function): bool
    {
        $missingParams = array_diff($requiredParams, keys($params));
        foreach ($requiredParams as $requiredParam) {
            if (empty($params[$requiredParam]) {
                throw new MissingFunctionParam($function, $requiredParam, $requiredParams);
            }
        }
    }
}