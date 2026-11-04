<?php

namespace Diana\Router\Tests\Controllers;

use Diana\Router\Attributes\Get;

class DuplicateRouteController
{
    #[Get('/test')]
    public function test(): string
    {
        return 'get';
    }

    #[Get('/test')]
    public function test2(): string
    {
        return 'get';
    }
}