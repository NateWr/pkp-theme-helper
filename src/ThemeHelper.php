<?php

namespace NateWr\themehelper;

use Application;
use Config;
use HookRegistry;
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
        $this->safeRegisterPlugin('function', 'th_filter_galleys', [$this, 'filterGalleys']);
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
     * Filter a list of galleys with their URL and label based on the passed params
     *
     * Excludes galleys that have no file or remote URL.
     */
    public function filterGalleys(array $params, $smarty): void
    {
        if (!$this->hasParams($params, ['assign', 'galleys'], 'th_filter_galleys')) {
            return;
        }

        $galleys = (array) $params['galleys'];
        $genreIds = isset($params['genreIds']) ? (array) $params['genreIds'] : [];
        $remotes = isset($params['remotes']) ? (bool) $params['remotes'] : false;

        $filteredGalleys = collect([]);

        foreach ($galleys as $galley) {
            if ($galley->getRemoteUrl() && $remotes) {
                $filteredGalleys->push($galley);
                continue;
            }
            $file = $galley->getFile();
            if (!$file) {
                continue;
            }
            if (!count($genreIds) || in_array($file->getGenreId(), $genreIds)) {
                $filteredGalleys->push($galley);
                continue;
            }
        }

        $smarty->assign($params['assign'], $filteredGalleys);
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

    /**
     * Add {$currentPage} and {$lastPage} variables to templates
     * that have paginated data.
     *
     * @param array $templates (Optional) Pass a custom list of templates
     *   that the pagination data should be added to. These templates must
     *   have the `total` and `showingStart` variables assigned to them.
     *   If not passed, it will assign them to all supported core templates.
     */
    public function addPaginationData(array $templates = []): void
    {
        if (!count($templates)) {
            $templates = [
                'frontend/pages/issueArchive.tpl',
                'frontend/pages/catalog.tpl',
                'frontend/pages/catalogSeries.tpl',
                'frontend/pages/catalogCategory.tpl',
            ];
        }
        HookRegistry::register('TemplateManager::display', function(string $hookName, array $args) use ($templates) {
            $context = Application::get()->getRequest()->getContext();
            $total = $this->templateMgr->getTemplateVars('total');
            $showingStart = $this->templateMgr->getTemplateVars('showingStart');
            $perPage = $context?->getData('itemsPerPage')
                ? $context->getData('itemsPerPage')
                : Config::getVar('interface', 'items_per_page');
            $currentPage = (int) ceil($showingStart / $perPage);

            $this->templateMgr->assign([
                'currentPage' => $currentPage,
                'lastPage' => ceil($total / $perPage),
            ]);
        });
    }
}