<?php

namespace Diana\Router\Tests\Fixtures;

use Diana\Contracts\Router\Route;

/** A route implementation independent of the router package's default class. */
class CustomRoute implements Route
{
    public function __construct(
        private string $controller,
        private string $method,
        private array $middleware = [],
        private array $params = [],
        private array $segments = []
    ) {
    }

    public function getController(): string
    {
        return $this->controller;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    public function setParameters(array $params): void
    {
        $this->params = $params;
    }

    public function getParameters(): array
    {
        return $this->params;
    }

    public function setSegments(array $segments): void
    {
        $this->segments = $segments;
    }

    public function getSegments(): array
    {
        return $this->segments;
    }

    public function toArray(): array
    {
        return [
            'controller' => $this->controller,
            'method' => $this->method,
            'middleware' => $this->middleware,
            'params' => $this->params,
            'segments' => $this->segments,
        ];
    }
}
