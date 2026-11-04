<?php

namespace Diana\Router\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Put extends Route
{
    public const string METHOD = 'PUT';
}
