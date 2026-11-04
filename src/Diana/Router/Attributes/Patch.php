<?php

namespace Diana\Router\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Patch extends Route
{
    public const string METHOD = 'PATCH';
}
