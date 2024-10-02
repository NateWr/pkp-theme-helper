<?php

namespace NateWr\themehelper\exceptions;

use Exception;

class MissingFunctionParam extends Exception
{
  public function __construct(string $function, string $missingParam, array $requiredParams = [])
  {
    $exampleParams = array_join(
      " ",
      array_map(fn($param) => "{$param}=\"...\"", $requiredParams)
    );
    parent::__construct(
      "Call to {{$function} without the required `{$missingParam}` parameter. Usage: {{$function} {$exampleParams}"
    )
  }
}