<?php

namespace NateWr\themehelper\interfaces;

interface TemplateManager
{
    function register_function(string $name, callable $callback);
}