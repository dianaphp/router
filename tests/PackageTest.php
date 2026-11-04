<?php

namespace Diana\Router\Tests;

use Diana\Contracts\Cache\Cache;
use Diana\Contracts\Core\Container;
use Diana\Contracts\RequestContract;
use Diana\Contracts\Router\Route as RouteContract;
use Diana\Router\Attributes\Command;
use Diana\Router\Attributes\Get;
use Diana\Router\Attributes\Middleware;
use Diana\Router\Exceptions\CommandNotFoundException;
use Diana\Router\FileRouter;
use Diana\Router\FileRouterCached;
use Diana\Router\NullRouter;
use Diana\Router\Route;
use Diana\Router\Tests\Fixtures\ConsoleRequest;
use Diana\Router\Tests\Fixtures\HttpRequest;
use PHPUnit\Framework\TestCase;

class PackageTest extends TestCase
{
    private function container(): Container
    {
        $container = $this->createStub(Container::class);
        $container->method('make')->willReturnCallback(
            static fn (string $abstract, array $parameters): RouteContract => new Route(...$parameters)
        );
        return $container;
    }

    public function testRoutesAndCommandsCanBeRegisteredWithoutAFrameworkOrDispatcher(): void
    {
        $router = new FileRouter($this->container());
        $router->generateRoutesFromPackage(new PackageController());

        $route = $router->resolve(new HttpRequest('/hello/42'));
        self::assertSame(PackageController::class, $route->getController());
        self::assertSame('hello', $route->getMethod());
        self::assertSame(['id' => '42'], $route->getParameters());
        self::assertSame(['example.middleware'], $route->getMiddleware());

        $command = $router->resolve(new ConsoleRequest('hello', ['--verbose']));
        self::assertSame('command', $command->getMethod());
        self::assertSame(['verbose' => true], $command->getParameters());
    }

    public function testMissingCommandStillThrowsCommandNotFound(): void
    {
        $router = new FileRouter($this->container());
        $this->expectException(CommandNotFoundException::class);
        $router->resolve(new ConsoleRequest('missing'));
    }

    public function testCacheCanBeWrittenAndRestoredWithoutFrameworkLifecycleEvents(): void
    {
        $stored = null;
        $cache = $this->createMock(Cache::class);
        $cache->method('get')->willReturnCallback(static function () use (&$stored): mixed {
            return $stored;
        });
        $cache->expects(self::once())->method('set')->with('router', self::isType('array'))
            ->willReturnCallback(static function (string $key, mixed $value) use (&$stored): bool {
                $stored = $value;
                return true;
            });

        $cold = new FileRouterCached($this->container(), $cache);
        self::assertFalse($cold->isLoadedFromCache());
        $cold->generateRoutesFromPackage(new PackageController());
        $cold->generateCache();
        self::assertIsArray($stored);

        $warm = new FileRouterCached($this->container(), $cache);
        self::assertTrue($warm->isLoadedFromCache());

        self::assertEquals($cold->resolve(new HttpRequest('/hello/42')), $warm->resolve(new HttpRequest('/hello/42')));
        self::assertEquals($cold->resolve(new ConsoleRequest('hello')), $warm->resolve(new ConsoleRequest('hello')));
    }

    public function testNullRouterAcceptsAnyRequestContract(): void
    {
        $router = new NullRouter($this->container());
        $route = $router->resolve($this->createStub(RequestContract::class));
        self::assertSame(NullRouter::class, $route->getController());
        self::assertSame('shadow', $route->getMethod());
        self::assertNull($router->getErrorRoute());
        self::assertNull($router->getErrorCommandRoute());
    }
}

class PackageController
{
    #[Get('/hello/:id')]
    #[Middleware('example.middleware')]
    public function hello(): void
    {
    }

    #[Command('hello')]
    public function command(): void
    {
    }
}
