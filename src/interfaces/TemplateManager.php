<?php

namespace NateWr\themehelper\interfaces;

use PKPRequest;

interface TemplateManager
{
    public array $registered_plugins;
    static function &getManager(PKPRequest $request = null): self;
    function registerPlugin(string $type, string $name, callable $callback);
    function unregisterPlugin(string $type, string $name);
    public function assign(string|array $variable, ?array $data = null): void;
    public function getTemplateVars(?string $variable): mixed;
}