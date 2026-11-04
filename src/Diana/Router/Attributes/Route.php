<?php

namespace Diana\Router\Attributes;

abstract class Route
{
    public function __construct(protected string $path)
    {
    }
}
