<?php

namespace Diana\Router\Tests\Framework;

use Diana\Contracts\Core\Container;
use Diana\Contracts\Event\Dispatcher;
use Diana\Contracts\Core\Pipeline as PipelineContract;
use Diana\Contracts\Router\Router;
use Diana\Router\Framework\RouterModule;
use Diana\IO\ConsoleRequest;
use Diana\IO\HttpRequest;
use Diana\IO\Pipeline;
use Diana\IO\Response;
use Diana\Router\Exceptions\CommandNotFoundException;
use Diana\Router\Exceptions\RouteNotFoundException;
use Diana\Router\Route;
use Diana\Runtime\IlluminateContainer;
use PHPUnit\Framework\TestCase;

class RouterModuleTest extends TestCase
{
    private function container(): IlluminateContainer
    {
        $container = new IlluminateContainer();
        $container->instance(Container::class, $container);
        $container->bind(PipelineContract::class, Pipeline::class);
        return $container;
    }

    private function module(Router $router, ?IlluminateContainer $container = null): RouterModule
    {
        $container ??= $this->container();
        $container->instance(Router::class, $router);
        return new RouterModule($container, $this->createStub(Dispatcher::class));
    }

    public function testHttpNotFoundFallbackRemainsAvailable(): void
    {
        $router = $this->createMock(Router::class);
        $router->method('resolve')->willThrowException(new RouteNotFoundException());
        $router->expects(self::once())->method('getErrorRoute')->willReturn(null);
        $response = ($this->module($router))(new HttpRequest('/missing'));

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('HTTP Error 404 - This page could not be found.', (string) $response);
    }

    public function testCommandNotFoundFallbackRemainsAvailable(): void
    {
        $router = $this->createMock(Router::class);
        $router->method('resolve')->willThrowException(new CommandNotFoundException());
        $router->expects(self::once())->method('getErrorCommandRoute')->willReturn(null);
        $response = ($this->module($router))(new ConsoleRequest('missing'));

        self::assertSame(1, $response->getStatusCode());
        self::assertSame('This command could not be found.', (string) $response);
    }

    public function testConfiguredErrorRouteReceivesStatusCode(): void
    {
        $router = $this->createMock(Router::class);
        $router->method('resolve')->willThrowException(new RouteNotFoundException());
        $router->method('getErrorRoute')->willReturn(new Route(ProcessorErrorController::class, 'error'));
        $response = ($this->module($router))(new HttpRequest('/missing'));

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('error:404', (string) $response);
    }

    public function testModuleUsesThePipelineContract(): void
    {
        $response = new Response('custom pipeline');
        $pipeline = $this->createMock(PipelineContract::class);
        $pipeline->method('send')->willReturnSelf();
        $pipeline->method('pipe')->willReturnSelf();
        $pipeline->expects(self::once())->method('expect')->with(Response::class)->willReturn($response);

        $container = $this->container();
        $container->bind(PipelineContract::class, fn () => $pipeline);
        $router = $this->createMock(Router::class);
        $router->method('resolve')->willReturn(new Route('UnusedController', 'unused'));

        self::assertSame($response, ($this->module($router, $container))(new HttpRequest('/')));
    }

    public function testCommandErrorRouteReceivesFailureStatusCode(): void
    {
        $router = $this->createMock(Router::class);
        $router->method('resolve')->willThrowException(new CommandNotFoundException());
        $router->method('getErrorCommandRoute')->willReturn(new Route(ProcessorErrorController::class, 'error'));
        $response = ($this->module($router))(new ConsoleRequest('missing'));

        self::assertSame(1, $response->getStatusCode());
        self::assertSame('error:1', (string) $response);
    }

    public function testControllerResponseIsReturnedWithItsOriginalStatus(): void
    {
        $router = $this->createMock(Router::class);
        $router->method('resolve')->willReturn(new Route(ProcessorErrorController::class, 'response'));
        $response = ($this->module($router))(new HttpRequest('/'));

        self::assertSame(ProcessorErrorController::$response, $response);
        self::assertSame(201, $response->getStatusCode());
    }
}

class ProcessorErrorController
{
    public static Response $response;

    public function error(int $statusCode): string { return 'error:' . $statusCode; }

    public function response(): Response { return self::$response = new Response('created', 201); }
}
