# Diana Router

`dianaphp/router` provides attribute-based HTTP and console routing. The package
requires PHP 8.3+, `dianaphp/sdk` and `dianaphp/lib`. Router and framework can each
be installed independently.

## Package structure

```text
src/Diana/Router/
  FileRouter.php, FileRouterCached.php, Route.php, ...  standalone routing
  Attributes/, Exceptions/                           standalone metadata
  Framework/
    RouterModule.php                                framework integration
    Controllers/                                    framework commands
```

`dianaphp/framework` is a Composer suggestion for applications using the module,
and a development dependency for integration tests. Consumers installing the
router as a dependency do not pull in the framework.

The SDK supplies container, router, route and cache contracts and request
interfaces. `Diana\Contracts\IO\HttpRequest` exposes `getRoute()` and
`getMethod()`; `ConsoleRequest` exposes `getCommand()` and `getArguments()`.
Both extend `RequestContract`. Custom requests can implement these interfaces.

## Standalone usage

Bind `Diana\Contracts\Router\Route` to `Diana\Router\Route` in your container.
Pass your implementation of the SDK container contract to `FileRouter`, then
register an arbitrary object containing route attributes:

```php
use Diana\Router\Attributes\Command;
use Diana\Router\Attributes\Get;
use Diana\Router\FileRouter;

class ExampleController
{
    #[Get('/hello/:name')]
    public function hello(string $name): string
    {
        return 'Hello ' . $name;
    }

    #[Command('hello')]
    public function command(): string
    {
        return 'Hello CLI';
    }
}

$router = new FileRouter($container);
$router->generateRoutesFromPackage(new ExampleController());
$route = $router->resolve($request);
```

`resolve()` returns route metadata; the caller invokes the controller and runs its
middleware. No framework lifecycle or event dispatcher is needed. For caching,
construct `FileRouterCached($container, $cache)` with an SDK cache implementation.
It restores cached metadata on construction. When `isLoadedFromCache()` is false,
register the controller objects and call `generateCache()` explicitly.

## Framework module

`Diana\Router\Framework\RouterModule` connects the package to the framework.
Its constructor supplies default `Router → FileRouter` and `Route → Route`
bindings when the application has not supplied its own. It subscribes to
`RegisterPackageEvent` to discover controllers; for a cold `FileRouterCached`,
it also subscribes to `BootEvent` to write the cache. A warm cache skips discovery.
These lifecycle subscriptions live entirely in the module.

`RouterModule::__invoke(RequestContract $request): Response` owns framework
routing and controller execution. Its routing pipeline resolves a route or an
error route, registers the resolved route in the container and delegates to a
nested pipeline containing route middleware and controller execution.
Route parameters and `statusCode` are injected into the controller method.
Returning from route middleware without `$next()` skips the controller.
It also skips construction and execution of later route middleware. Use
`return $next()` to keep the request or `return $next($request)` to pass a
replacement to later middleware and controller injection. The resolved route
stays the same. Middleware that called `$next` can process the returned response,
including a response from a later middleware abort.
HTTP 404 and console status 1 fallbacks remain available. A controller's returned
`Response` is preserved; other results are wrapped with the resolved status.

The application's module registers the router module before its controllers
and delegates request processing to it:

```php
use Diana\Contracts\RequestContract;
use Diana\Framework\Core\Application;
use Diana\IO\Response;
use Diana\Router\Framework\RouterModule;

class AppModule
{
    private RouterModule $router;

    public function __construct(Application $app)
    {
        $this->router = $app->registerPackage(RouterModule::class);
        $app->registerPackage(AppController::class);
    }

    public function __invoke(RequestContract $request): Response
    {
        return ($this->router)($request);
    }
}
```

Framework global middleware wraps this entire delegation, including requests
without a matching route, and can stop processing before resolution.
Modules and controllers are ordinary classes registered through the same API.
A module may itself contain attributed controller methods. Only a class used
as the configured application entry point needs `__invoke()`.

Alternatively, configure the router module directly and list the controller
packages explicitly:

```php
return [
    'entrypoint' => \Diana\Router\Framework\RouterModule::class,
    'packages' => [
        \Diana\Router\Framework\RouterModule::class,
        \App\AppController::class,
        \App\RenderingPackage::class,
    ],
];
```

To select `FileRouterCached`, configure the router binding in `singleton` and
bind its cache dependency to `PhpFileCache` through `contextualBindings`.
Register the module before controllers so that discovery is ready during boot.
The existing framework command controllers retain their `Diana\Controllers`
namespaces; their files live in `Framework/Controllers/`.

## Local development

The workspace path repository includes this package as `dev-main`.
Use matching local SDK and framework sources for integration tests.
From the workspace root:

```sh
composer install
composer test:router
php vendor/bin/phpunit router/tests/Framework
```

For a separate checkout with the local Diana path repository configured:

```sh
composer install
composer test
composer test -- --testsuite router
composer lint
```

The package's `router` PHPUnit suite covers standalone routing and caching;
`framework-integration` covers the optional module. Development installation
includes the framework for these integration tests. The standalone suite is also
verified from a consuming Composer project without the framework installed.
