<?php

namespace Diana\Router\Framework;

use Closure;
use Diana\Contracts\Core\Container;
use Diana\Contracts\Core\Pipeline;
use Diana\Contracts\Event\Dispatcher;
use Diana\Contracts\RequestContract;
use Diana\Contracts\Router\Route as RouteContract;
use Diana\Contracts\Router\Router as RouterContract;
use Diana\Events\BootEvent;
use Diana\Events\RegisterPackageEvent;
use Diana\IO\Response;
use Diana\Router\FileRouter;
use Diana\Router\FileRouterCached;
use Diana\Router\Route;
use Diana\Router\Exceptions\CommandNotFoundException;
use Diana\Router\Exceptions\RouteNotFoundException;

class RouterModule
{
    protected RouterContract $router;

    public function __construct(protected Container $container, Dispatcher $dispatcher)
    {
        if (!$container->has(RouteContract::class)) {
            $container->singleton(RouteContract::class, Route::class);
        }
        if (!$container->has(RouterContract::class)) {
            $container->singleton(RouterContract::class, FileRouter::class);
        }
        $this->router = $container->get(RouterContract::class);

        if ($this->router instanceof FileRouterCached) {
            if ($this->router->isLoadedFromCache()) {
                return;
            }
            $dispatcher->addSingleEventListener(BootEvent::class, [$this->router, 'generateCache']);
        }
        if ($this->router instanceof FileRouter) {
            $dispatcher->addEventListener(RegisterPackageEvent::class, [$this, 'registerRoutes'], ['*']);
        }
    }

    public function registerRoutes(RegisterPackageEvent $event): void
    {
        if ($this->router instanceof FileRouter) {
            $this->router->generateRoutesFromPackage($event->getPackage());
        }
    }

    public function __invoke(RequestContract $request): Response
    {
        $route = null;
        $statusCode = $request->getDefaultStatusCode();

        return $this->pipeline($request, [
            function (RequestContract $request, Closure $next) use (&$route, &$statusCode): Response {
                try {
                    $route = $this->router->resolve($request);
                } catch (RouteNotFoundException) {
                    $statusCode = 404;
                    $route = $this->router->getErrorRoute();
                    if (!$route) {
                        return new Response('HTTP Error 404 - This page could not be found.', $statusCode);
                    }
                } catch (CommandNotFoundException) {
                    $statusCode = 1;
                    $route = $this->router->getErrorCommandRoute();
                    if (!$route) {
                        return new Response('This command could not be found.', $statusCode);
                    }
                }

                $this->container->instance(RouteContract::class, $route);
                return $next();
            },
            function (RequestContract $request) use (&$route, &$statusCode): Response {
                // Route middleware is known only after resolution and wraps the controller in one chain.
                return $this->pipeline($request, [
                    ...$route->getMiddleware(),
                    function (RequestContract $request) use (&$route, &$statusCode): Response {
                        $this->container->instance(RequestContract::class, $request);
                        $response = $this->container->call(
                            $route->getController() . '@' . $route->getMethod(),
                            ['statusCode' => $statusCode, ...$route->getParameters()]
                        );
                        return $response instanceof Response ? $response : new Response($response, $statusCode);
                    },
                ]);
            },
        ]);
    }

    protected function pipeline(RequestContract $request, array $stages): Response
    {
        return $this->container->make(Pipeline::class)
            ->send($request)
            ->pipe($stages)
            ->expect(Response::class);
    }
}
