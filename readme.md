# pkp-theme-helper

A library of helper functions to build themes for PKP software (OJS, OMP, and OPS).

* Safely register new template functions or override existing functions
* Generate pagination data on common pages
* Select primary, remote, or supplementary galleys

## Usage

> **Your theme must use [composer](https://getcomposer.org/) to use this library.**

Run the following command from the root directory of your custom theme in order to add this library as a dependency.

```
composer require natewr/pkp-theme-helper
```

Initialize the `ThemeHelper` in your theme's `init()` method and add the built-in template helper plugins in your theme.

```php
require __DIR__ . '/vendor/autoload.php';

use NateWr\themehelper\ThemeHelper;

import('lib.pkp.classes.plugins.ThemePlugin');

class ExampleTheme extends ThemePlugin
{
    protected ThemeHelper $themeHelper;

    public function init()
    {
        $templateMgr = TemplateManager::getManager(
            Application::get()->getRequest()
        );
        $this->themeHelper = new ThemeHelper($templateMgr);
        $this->themeHelper->addCommonTemplatePlugins();
    }
}
```

Then use the library's helper functions in your theme's templates.

```html
{th_locales assign="languages"}

{foreach key="code" item="name" from=$languages}
    <a href="{url page="user" op="setLocale" path=$code source=$smarty.server.REQUEST_URI}">
        {$name}
    </a>
{/foreach}
```

## API

### Built-in Functions

#### th_locales

Set the locales supported by the journal or site

*@option* `string` **assign** Variable to assign the result to

```html
{th_locales
    assign="languages"
}
```

#### th_filter_galleys

Filter a list of galleys by genreId and remoteUrl

*@option* `string` **assign** Variable to assign the result to<br>
*@option* `Galley[]` **galleys** List of galleys to filter<br>
*@option* `int[]` **genreIds** List of genres to include in result<br>
*@option* `bool` **remotes** Whether to include galleys with remote urls

```html
{th_filter_galleys
    assign="galleys"
    galleys=$article->getGalleys()
    genreIds=$primaryGenreIds
    remotes=true
}
```

#### th_pagination

Get an array of page numbers for pagination components

The list of page numbers will be truncated so that it is
no more than maxPages. Skipped pages are returned as -1 in
the page list.

Example result:

```
assignCurrent = 23
assignPages = [1, -1, 21, 22, 23, 24, 25, -1, 82]
```

*@option* `string` **assignPages** Variable to assign the list of page numbers<br>
*@option* `string` **assignCurrent** Variable to assign the current page to<br>
*@option* `int` **perPage** Number of items on each page<br>
*@option* `int` **total** Total number of items<br>
*@option* `int` **start** Number of first item on current page, eg - 21 = 21st item in total list of items<br>
*@option* `int` **maxPages** (Optional) Max number of page numbers to show at once

```html
{th_pagination
    assignPages="pages"
    assignCurrent="current"
    perPage="10"
    total="200"
    start="1"
}
```

### Custom Functions

> Use pkp-theme-helper to register custom template functions in order to avoid fatal errors with child themes.

Register a custom template function.

```php
use NateWr\themehelper\TemplatePlugin;

class ExampleTheme extends ThemePlugin
{
    protected ThemeHelper $themeHelper;

    public function init()
    {
        // ...
        $this->themeHelper->addTemplatePlugin(
            new TemplatePlugin(
                type: 'function',
                name: 'example_get_title_size',
                callback: [$this, 'getTitleSize']
            )
        );
    }

    public function getTitleSize(array $params, $smarty): void
    {
        /**
         * Check for required function parameters.
         */
        if (!$this->themeHelper->hasParams($params, ['assign', 'title'], 'example_function')) {
            return;
        }

        if (strlen($params['title']) > 100) {
            return 'large'
        }

        return 'small';
    }
}
```

Then use the function in your templates like this.


```html
{assign var="exampleTitle" value="My example journal title"}
{example_function
    assign="size"
    title=$exampleTitle
}

{if $size === 'large'}
    <span class="text-sm">{$exampleTitle}</span>
{else}
    <span class="text-lg">{$exampleTitle}</span>
{/if}
```

By default, `ThemeHelper` will not override an existing template plugin. If you want to replace an existing template plugin, like functions registered by `PKPTemplateManager`, use the `override` param.

```php
$this->themeHelper->addTemplatePlugin(
    new TemplatePlugin(
        type: 'function',
        name: 'example_get_title_size',
        callback: [$this, 'getTitleSize'],
        override: true
    )
);
