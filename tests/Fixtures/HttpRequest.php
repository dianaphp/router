<?php

namespace Diana\Router\Tests\Fixtures;

use Diana\Contracts\IO\HttpRequest as HttpRequestContract;

class HttpRequest implements HttpRequestContract
{
    public function __construct(private string $route, private string $method = 'GET')
    {
    }

    public function getRoute(): string
    {
        return $this->route;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getDefaultStatusCode(): int
    {
        return 200;
    }
}
