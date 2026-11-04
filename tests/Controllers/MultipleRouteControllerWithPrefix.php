<?php

namespace Diana\Router\Tests\Controllers;

use Diana\Router\Attributes\Delete;
use Diana\Router\Attributes\Patch;
use Diana\Router\Attributes\Post;
use Diana\Router\Attributes\Put;

class MultipleRouteControllerWithPrefix
{
    public static string $route = '/prefix';

    #[Post('/post')]
    public function post(): string
    {
        return 'post';
    }

    #[Delete('/delete')]
    public function delete(): string
    {
        return 'delete';
    }

    #[Patch('/patch')]
    public function patch(): string
    {
        return 'patch';
    }

    #[Put('/put')]
    public function put(): string
    {
        return 'put';
    }
}
