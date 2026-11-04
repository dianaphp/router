<?php

namespace Diana\Router\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Command
{
    public function __construct(protected string $command, protected array $args = [])
    {
    }
}
