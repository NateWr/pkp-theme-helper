<?php

namespace NateWr\themehelper\interfaces;

interface TemplateManager
{
    public array $registered_plugins;
    function registerPlugin(string $type, string $name, callable $callback);
    function unregisterPlugin(string $type, string $name);
}