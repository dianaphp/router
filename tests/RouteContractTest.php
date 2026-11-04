<?php

namespace Diana\Router\Tests;

use Diana\Contracts\Cache\Cache;
use Diana\Contracts\Core\Container;
use Diana\Contracts\Router\Route;
use Diana\Router\Attributes\Command;
use Diana\Router\Attributes\CommandErrorHandler;
use Diana\Router\Attributes\Get;
use Diana\Router\Attributes\HttpErrorHandler;
use Diana\Router\Attributes\Middleware;
use Diana\Router\FileRouter;
use Diana\Router\FileRouterCached;
use Diana\Router\Tests\Fixtures\ConsoleRequest;
use Diana\Router\Tests\Fixtures\CustomRoute;
use Diana\Router\Tests\Fixtures\HttpRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RouteContractTest extends TestCase
{
    public static function routerModes(): iterable
    {
        yield 'registered routes' => [false];
        yield 'restored from cache' => [true];
    }

    private function router(bool $cached): FileRouter
    {
        $container = $this->createStub(Container::class);
        $container->method('make')->willReturnCallback(
            static function (string $abstract, array $parameters): Route {
                self::assertSame(Route::class, $abstract);
                return new CustomRoute(...$parameters);
            }
        );
        $stored = null;
        $cache = $this->createStub(Cache::class);
        $cache->method('get')->willReturnCallback(static function () use (&$stored): mixed {
            return $stored;
        });
        $cache->method('set')->willReturnCallback(static function (string $key, mixed $value) use (&$stored): bool {
            $stored = $value;
            return true;
        });
        $router = $cached ? new FileRouterCached($container, $cache) : new FileRouter($container);
        $router->generateRoutesFromPackage(new ContractController());
        if ($cached) {
            $router->generateCache();
            $router = new FileRouterCached($container, $cache);
            self::assertTrue($router->isLoadedFromCache());
        }
        return $router;
    }

    #[DataProvider('routerModes')]
    public function testHttpResolutionAcceptsCustomRoutes(bool $cached): void
    {
        $router = $this->router($cached);
        $first = $router->resolve(new HttpRequest('/custom/42'));
        $second = $router->resolve(new HttpRequest('/custom/99'));

        self::assertInstanceOf(CustomRoute::class, $first);
        self::assertSame(ContractController::class, $first->getController());
        self::assertSame('http', $first->getMethod());
        self::assertSame(['custom.middleware'], $first->getMiddleware());
        self::assertSame(['id' => '42'], $first->getParameters());
        self::assertSame(['id' => '99'], $second->getParameters());
        self::assertNotSame($first, $second);
    }

    #[DataProvider('routerModes')]
    public function testCommandResolutionAcceptsCustomRoutes(bool $cached): void
    {
        $router = $this->router($cached);
        $first = $router->resolve(new ConsoleRequest('custom', ['first', '--verbose']));
        $second = $router->resolve(new ConsoleRequest('custom', ['second']));

        self::assertInstanceOf(CustomRoute::class, $first);
        self::assertSame('command', $first->getMethod());
        self::assertSame(['verbose' => true, 'name' => 'first'], $first->getParameters());
        self::assertSame(['name' => 'second'], $second->getParameters());
        self::assertNotSame($first, $second);
    }

    #[DataProvider('routerModes')]
    public function testErrorHandlersAcceptCustomRoutes(bool $cached): void
    {
        $router = $this->router($cached);
        self::assertInstanceOf(CustomRoute::class, $router->getErrorRoute());
        self::assertSame('httpError', $router->getErrorRoute()->getMethod());
        self::assertInstanceOf(CustomRoute::class, $router->getErrorCommandRoute());
        self::assertSame('commandError', $router->getErrorCommandRoute()->getMethod());
    }
}

class ContractController
{
    #[Get('/custom/:id')]
    #[Middleware('custom.middleware')]
    public function http(): void {}

    #[Command('custom', ['name'])]
    public function command(): void {}

    #[HttpErrorHandler]
    public function httpError(): void {}

    #[CommandErrorHandler]
    public function commandError(): void {}
}
