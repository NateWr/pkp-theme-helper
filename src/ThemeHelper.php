<?php

namespace NateWr\themehelper;

use Application;
use Config;
use HookRegistry;
use NateWr\themehelper\TemplatePlugin;
use NateWr\themehelper\exceptions\MissingFunctionParam;
use TemplateManager;

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
     * @var TemplatePlugin[]
     */
    protected array $templatePlugins = [];

    public function __construct(
        protected TemplateManager $templateMgr
    ) {
        $this->templateMgr = $templateMgr;
        HookRegistry::register('TemplateManager::display', [$this, 'registerTemplatePlugins']);
    }

    /**
     * Register common helper functions with the Smarty
     * template manager
     *
     * @param TemplateManager $templateMgr
     */
    public function addCommonTemplatePlugins(): void
    {
        $this->addTemplatePlugin(
            new TemplatePlugin(
                type: 'function',
                name: 'th_locales',
                callback: [$this, 'setLocales']
            )
        );
        $this->addTemplatePlugin(
            new TemplatePlugin(
                type: 'function',
                name: 'th_filter_galleys',
                callback: [$this, 'filterGalleys']
            )
        );
    }

    /**
     * Add a template plugin
     */
    public function addTemplatePlugin(TemplatePlugin $plugin): void
    {
        $this->templatePlugins[] = $plugin;
    }

    /**
     * Register template plugins
     *
     * This method is called with the TemplateManager::display
     * hook in order to ensure that the template plugins are
     * registered after all core plugins have been registered.
     *
     * This allows core plugins to be overridden.
     */
    public function registerTemplatePlugins(string $hookName, array $args): bool
    {
        foreach ($this->templatePlugins as $plugin) {
            $this->safeRegisterTemplatePlugin($plugin);
        }
        return false;

    }

    /**
     * Register a smarty plugin safely
     *
     * This wrapper function prevents a fatal error if a smarty plugin
     * with the same name has already been registered.
     */
    protected function safeRegisterTemplatePlugin(TemplatePlugin $plugin): void
    {
        $registered = isset($this->templateMgr->registered_plugins[$plugin->type][$plugin->name]);
        if ($registered && $plugin->override) {
            $this->templateMgr->unregisterPlugin($plugin->type, $plugin->name);
            $this->templateMgr->registerPlugin($plugin->type, $plugin->name, $plugin->callback);
        } elseif (!$registered) {
            $this->templateMgr->registerPlugin($plugin->type, $plugin->name, $plugin->callback);
        }
    }

    /**
     * Set the locales supported by the journal or site
     *
     * @example {th_locales assign="languages"}
     * @param array $params
     *   @option string assign Variable to assign the result to
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
     * Filter a list of galleys by genreId and remoteUrl
     *
     * @example {th_filter_galleys
     *   assign="galleys"
     *   galleys=$article->getGalleys()
     *   genreIds=$primaryGenreIds
     *   remotes=true
     * }
     * @param array $params
     *   @option string assign Variable to assign the result to
     *   @option Galley[] galleys List of galleys to filter
     *   @option int[] genreIds List of genres to include in result
     *   @option bool remotes Whether to include galleys with remote urls
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