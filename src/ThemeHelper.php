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
     * @var TemplateManager
     */
    protected $templateMgr;

    public function __construct($templateMgr)
    {
        $this->templateMgr = $templateMgr;
    }

    /**
     * Register helper functions with the Smarty
     * template manager
     *
     * @param TemplateManager $templateMgr
     */
    public function registerDefaultPlugins(): void
    {
        $this->safeRegisterPlugin('function', 'th_locales', [$this, 'setLocales']);
    }

    /**
     * Register a smarty plugin safely
     *
     * This wrapper function prevents a fatal error if a smarty plugin
     * with the same name has already been registered.
     */
    public function safeRegisterPlugin(string $type, string $name, callable $callback, bool $override = false): void
    {
        $registered = isset($this->templateMgr->registered_plugins[$type][$name]);
        if ($registered && $override) {
            $this->templateMgr->unregisterPlugin($type, $name);
            $this->templateMgr->registerPlugin($type, $name, $callback);
        } elseif (!$registered) {
            $this->templateMgr->registerPlugin($type, $name, $callback);
        }
    }

    /**
     * Set the locales supported by the journal or site
     */
    public function setLocales(array $params, $smarty): void
    {
        if (!$this->hasParams($params, ['assign'], 'th_locales')) {
            return;
        }

        $request = \Application::get()->getRequest();
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
        foreach ($requiredParams as $requiredParam) {
            if (empty($params[$requiredParam])) {
                throw new MissingFunctionParam($function, $requiredParam, $requiredParams);
            }
        }
        return true;
    }
}