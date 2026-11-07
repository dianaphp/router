<?php

namespace Diana\Router\Tests;

use Diana\Contracts\Core\Container;
use Diana\Router\Attributes\{Command, Get, Head, Options, Post};
use Diana\Router\Exceptions\{DuplicateRouteException, MethodNotAllowedException, OptionsRequestException, RouteNotFoundException};
use Diana\Router\FileRouter;
use Diana\Router\Route;
use Diana\Router\Tests\Fixtures\{ConsoleRequest, HttpRequest};
use PHPUnit\Framework\TestCase;

class HttpSemanticsTest extends TestCase
{
    private function router(): FileRouter
    {
        $container = $this->createStub(Container::class);
        $container->method('make')->willReturnCallback(
            static fn (string $abstract, array $parameters): Route => new Route(...$parameters)
        );
        $router = new FileRouter($container);
        $router->generateRoutesFromPackage(new class {
            #[Get('/users/:id')]
            public function dynamic(): void {}
            #[Get('/users/new')]
            public function fixed(): void {}
            #[Post('/users/:id')]
            public function update(): void {}
            #[Head('/explicit')]
            public function head(): void {}
            #[Get('/explicit')]
            public function get(): void {}
            #[Options('/explicit')]
            public function options(): void {}
        });
        return $router;
    }

    public function testStaticRouteWinsEvenWhenRegisteredAfterDynamicRoute(): void
    {
        $route = $this->router()->resolve(new HttpRequest('/users/new'));
        self::assertSame('fixed', $route->getMethod());
        self::assertSame([], $route->getParameters());
        self::assertSame(['id' => '42'], $this->router()->resolve(new HttpRequest('/users/42'))->getParameters());
    }

    public function testHeadFallsBackToGetAndExplicitHeadAndOptionsWin(): void
    {
        $router = $this->router();
        self::assertSame('fixed', $router->resolve(new HttpRequest('/users/new', 'HEAD'))->getMethod());
        self::assertSame('head', $router->resolve(new HttpRequest('/explicit', 'HEAD'))->getMethod());
        self::assertSame('options', $router->resolve(new HttpRequest('/explicit', 'OPTIONS'))->getMethod());
    }

    public function testMethodMismatchIncludesAllMethodsForTheMatchingPath(): void
    {
        try {
            $this->router()->resolve(new HttpRequest('/users/42', 'DELETE'));
            self::fail('Expected 405');
        } catch (MethodNotAllowedException $exception) {
            self::assertSame(405, $exception->getCode());
            self::assertSame(['GET', 'HEAD', 'OPTIONS', 'POST'], $exception->getAllowedMethods());
        }
    }

    public function testAutomaticOptionsExposesTheSameAllowList(): void
    {
        try {
            $this->router()->resolve(new HttpRequest('/users/42', 'OPTIONS'));
            self::fail('Expected automatic OPTIONS');
        } catch (OptionsRequestException $exception) {
            self::assertSame(204, $exception->getCode());
            self::assertSame(['GET', 'HEAD', 'OPTIONS', 'POST'], $exception->getAllowedMethods());
        }
    }

    public function testOptionsDoesNotMakeUnknownPathsExist(): void
    {
        $this->expectException(RouteNotFoundException::class);
        $this->router()->resolve(new HttpRequest('/missing', 'OPTIONS'));
    }

    public function testDuplicateCommandsAcrossPackagesAreRejectedAndOriginalIsRetained(): void
    {
        $router = $this->router();
        $router->generateRoutesFromPackage(new class {
            #[Command('demo')]
            public function original(): void {}
        });
        try {
            $router->generateRoutesFromPackage(new class {
                #[Command('demo')]
                public function replacement(): void {}
            });
            self::fail('Expected duplicate command rejection');
        } catch (DuplicateRouteException) {
            self::assertSame('original', $router->resolve(new ConsoleRequest('demo'))->getMethod());
        }
    }
}
