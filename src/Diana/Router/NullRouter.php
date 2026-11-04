<?php

namespace Diana\Router;

use Diana\Contracts\Core\Container;
use Diana\Contracts\RequestContract;
use Diana\Contracts\Router\Route;
use Diana\Contracts\Router\Router;

class NullRouter implements Router
{
    public function __construct(protected Container $container)
    {
    }

    public function resolve(RequestContract $request): Route
    {
        return $this->container->make(Route::class, [
            'controller' => self::class,
            'method' => 'shadow'
        ]);
    }

    public function shadow(): void
    {
    }

    public function getErrorRoute(): ?Route
    {
        return null;
    }

    public function getErrorCommandRoute(): ?Route
    {
        return null;
    }
}
