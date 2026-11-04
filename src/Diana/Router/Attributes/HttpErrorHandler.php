<?php

namespace Diana\Router\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class HttpErrorHandler
{
    public function __construct()
    {
    }
}
