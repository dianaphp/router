<?php

namespace Diana\Router\Tests\Middleware;

class MockMiddleware
{
    public function handle($request, $next)
    {
        return $next($request);
    }
}