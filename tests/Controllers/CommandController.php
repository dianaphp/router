<?php

namespace Diana\Router\Tests\Controllers;

use Diana\Router\Attributes\Command;
use Diana\Router\Attributes\Middleware;
use Diana\Router\Tests\Middleware\MockMiddleware;

class CommandController
{
    #[Command('test')]
    #[Middleware(MockMiddleware::class)]
    public function test()
    {
        return 'noarg';
    }

    #[Command('testarg', 'opt')]
    public function testArg($opt)
    {
        return 'arg';
    }
}