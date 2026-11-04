<?php

namespace Diana\Router\Tests;

use Diana\Contracts\Core\Container;
use Diana\Contracts\RequestContract;
use Diana\Contracts\Router\Route as RouteContract;
use Diana\Router\Exceptions\CommandNotFoundException;
use Diana\Router\Exceptions\DuplicateRouteException;
use Diana\Router\Exceptions\MissingArgumentsException;
use Diana\Router\Exceptions\RouteNotFoundException;
use Diana\Router\Exceptions\UnsupportedRequestTypeException;
use Diana\Router\FileRouter;
use Diana\Router\Route;
use Diana\Router\Tests\Controllers\DuplicateRouteController;
use Diana\Router\Tests\Controllers\MultipleRouteControllerWithPrefix;
use Diana\Router\Tests\Controllers\PlainController;
use Diana\Router\Tests\Controllers\SingleRouteControllerWithoutMiddleware;
use Diana\Router\Tests\Fixtures\HttpRequest;
use PHPUnit\Framework\TestCase;
use ReflectionException;

class FileRouterTest extends TestCase
{
    public FileRouter $router;

    public function setUp(): void
    {
        $container = $this->createStub(Container::class);
        $container->method('make')->willReturnCallback(
            static fn (string $abstract, array $parameters): RouteContract => new Route(...$parameters)
        );
        $this->router = new FileRouter($container);
    }

    public function tearDown(): void
    {
        unset($this->router);
    }

    /**
     * @throws ReflectionException
     * @throws MissingArgumentsException
     * @throws RouteNotFoundException
     * @throws DuplicateRouteException
     * @throws CommandNotFoundException
     * @throws UnsupportedRequestTypeException
     */
    public function testRouteNotFound(): void
    {
        $this->router->generateRoutesFromPackage(new PlainController());

        $this->expectException(RouteNotFoundException::class);
        $this->router->resolve(new HttpRequest('/test', 'GET'));
        $this->router->resolve(new HttpRequest('/test', 'POST'));
        $this->router->resolve(new HttpRequest('/test', 'DELETE'));
        $this->router->resolve(new HttpRequest('/test', 'PATCH'));
        $this->router->resolve(new HttpRequest('/test', 'PUT'));
    }

    /**
     * @throws ReflectionException
     * @throws MissingArgumentsException
     * @throws DuplicateRouteException
     */
    public function testUndefinedErrorRoutes(): void
    {
        $this->router->generateRoutesFromPackage(new PlainController());

        $this->assertNull($this->router->getErrorRoute());
        $this->assertNull($this->router->getErrorCommandRoute());
    }

    /**
     * @throws ReflectionException
     * @throws MissingArgumentsException
     */
    public function testDuplicateRoute(): void
    {
        $this->expectException(DuplicateRouteException::class);
        $this->router->generateRoutesFromPackage(new DuplicateRouteController());
    }

    /**
     * @throws RouteNotFoundException
     * @throws DuplicateRouteException
     * @throws ReflectionException
     * @throws MissingArgumentsException
     * @throws UnsupportedRequestTypeException
     * @throws CommandNotFoundException
     */
    public function testGetRoute(): void
    {
        $controller = new SingleRouteControllerWithoutMiddleware();
        $this->router->generateRoutesFromPackage($controller);

        $route = $this->router->resolve(new HttpRequest('/test', 'GET'));
        $this->assertEquals('test', $route->getMethod());
        $this->assertEquals(get_class($controller), $route->getController());
        $this->assertEquals([], $route->getMiddleware());
        $this->assertEquals([], $route->getParameters());
        $this->assertEquals(['test'], $route->getSegments());

        $this->expectException(RouteNotFoundException::class);
        $this->router->resolve(new HttpRequest('/test', 'POST'));
        $this->router->resolve(new HttpRequest('/test', 'DELETE'));
        $this->router->resolve(new HttpRequest('/test', 'PATCH'));
        $this->router->resolve(new HttpRequest('/test', 'PUT'));
    }

    /**
     * @throws RouteNotFoundException
     * @throws DuplicateRouteException
     * @throws ReflectionException
     * @throws MissingArgumentsException
     * @throws UnsupportedRequestTypeException
     * @throws CommandNotFoundException
     */
    public function testPostRoute(): void
    {
        $controller = new MultipleRouteControllerWithPrefix();
        $this->router->generateRoutesFromPackage($controller);

        $route = $this->router->resolve(new HttpRequest('/prefix/post', 'POST'));
        $this->assertEquals('post', $route->getMethod());
        $this->assertEquals(get_class($controller), $route->getController());
        $this->assertEquals([], $route->getMiddleware());
        $this->assertEquals([], $route->getParameters());
        $this->assertEquals(['prefix', 'post'], $route->getSegments());

        $this->expectException(RouteNotFoundException::class);
        $this->router->resolve(new HttpRequest('/prefix/post', 'GET'));
        $this->router->resolve(new HttpRequest('/prefix/post', 'DELETE'));
        $this->router->resolve(new HttpRequest('/prefix/post', 'PATCH'));
        $this->router->resolve(new HttpRequest('/prefix/post', 'PUT'));
    }

    /**
     * @throws RouteNotFoundException
     * @throws DuplicateRouteException
     * @throws ReflectionException
     * @throws MissingArgumentsException
     * @throws UnsupportedRequestTypeException
     * @throws CommandNotFoundException
     */
    public function testDeleteRoute(): void
    {
        $controller = new MultipleRouteControllerWithPrefix();
        $this->router->generateRoutesFromPackage($controller);

        $route = $this->router->resolve(new HttpRequest('/prefix/delete', 'DELETE'));
        $this->assertEquals('delete', $route->getMethod());
        $this->assertEquals(get_class($controller), $route->getController());
        $this->assertEquals([], $route->getMiddleware());
        $this->assertEquals([], $route->getParameters());
        $this->assertEquals(['prefix', 'delete'], $route->getSegments());

        $this->expectException(RouteNotFoundException::class);
        $this->router->resolve(new HttpRequest('/prefix/delete', 'POST'));
        $this->router->resolve(new HttpRequest('/prefix/delete', 'GET'));
        $this->router->resolve(new HttpRequest('/prefix/delete', 'PATCH'));
        $this->router->resolve(new HttpRequest('/prefix/delete', 'PUT'));
    }

    /**
     * @throws RouteNotFoundException
     * @throws DuplicateRouteException
     * @throws ReflectionException
     * @throws MissingArgumentsException
     * @throws UnsupportedRequestTypeException
     * @throws CommandNotFoundException
     */
    public function testPatchRoute(): void
    {
        $controller = new MultipleRouteControllerWithPrefix();
        $this->router->generateRoutesFromPackage($controller);

        $route = $this->router->resolve(new HttpRequest('/prefix/patch', 'PATCH'));
        $this->assertEquals('patch', $route->getMethod());
        $this->assertEquals(get_class($controller), $route->getController());
        $this->assertEquals([], $route->getMiddleware());
        $this->assertEquals([], $route->getParameters());
        $this->assertEquals(['prefix', 'patch'], $route->getSegments());

        $this->expectException(RouteNotFoundException::class);
        $this->router->resolve(new HttpRequest('/prefix/patch', 'POST'));
        $this->router->resolve(new HttpRequest('/prefix/patch', 'GET'));
        $this->router->resolve(new HttpRequest('/prefix/patch', 'DELETE'));
        $this->router->resolve(new HttpRequest('/prefix/patch', 'PUT'));
    }

    /**
     * @throws RouteNotFoundException
     * @throws DuplicateRouteException
     * @throws ReflectionException
     * @throws MissingArgumentsException
     * @throws UnsupportedRequestTypeException
     * @throws CommandNotFoundException
     */
    public function testPutRoute(): void
    {
        $controller = new MultipleRouteControllerWithPrefix();
        $this->router->generateRoutesFromPackage($controller);

        $route = $this->router->resolve(new HttpRequest('/prefix/put', 'PUT'));
        $this->assertEquals('put', $route->getMethod());
        $this->assertEquals(get_class($controller), $route->getController());
        $this->assertEquals([], $route->getMiddleware());
        $this->assertEquals([], $route->getParameters());
        $this->assertEquals(['prefix', 'put'], $route->getSegments());

        $this->expectException(RouteNotFoundException::class);
        $this->router->resolve(new HttpRequest('/prefix/put', 'POST'));
        $this->router->resolve(new HttpRequest('/prefix/put', 'GET'));
        $this->router->resolve(new HttpRequest('/prefix/put', 'DELETE'));
        $this->router->resolve(new HttpRequest('/prefix/put', 'PATCH'));
    }

    /**
     * @throws RouteNotFoundException
     * @throws DuplicateRouteException
     * @throws ReflectionException
     * @throws MissingArgumentsException
     * @throws CommandNotFoundException
     */
    public function testUnsupportedRequestType(): void
    {
        $this->router->generateRoutesFromPackage(new SingleRouteControllerWithoutMiddleware());

        $this->expectException(UnsupportedRequestTypeException::class);
        $this->router->resolve($this->createStub(RequestContract::class));
    }

    /**
     * @throws RouteNotFoundException
     * @throws DuplicateRouteException
     * @throws ReflectionException
     * @throws MissingArgumentsException
     * @throws UnsupportedRequestTypeException
     * @throws CommandNotFoundException
     */
    public function testPrefix(): void
    {
        $controller = new MultipleRouteControllerWithPrefix();
        $this->router->generateRoutesFromPackage($controller);

        $route = $this->router->resolve(new HttpRequest('/prefix/post', 'POST'));
        $this->assertEquals('post', $route->getMethod());
        $this->assertEquals(get_class($controller), $route->getController());
        $this->assertEquals([], $route->getMiddleware());
        $this->assertEquals([], $route->getParameters());
        $this->assertEquals(['prefix', 'post'], $route->getSegments());

        $this->expectException(RouteNotFoundException::class);
        $this->router->resolve(new HttpRequest('/post', 'POST'));
    }

    /* todo:
    public function testParamRouteResolving()
    {
        $this->router->loadControllers([ParamController::class]);
        $routes = $this->router->getRoutes();

        $route = $routes['GET']['/noparam'];
        $route['params'] = [];
        $this->assertEquals($route, $this->router->resolve(new HttpRequest('/noparam', 'GET')));

        $route = $routes['GET']['/param/:param'];
        $route['params'] = ['param' => '123'];
        $this->assertNotEquals($route, $this->router->resolve(new HttpRequest('/param/test', 'GET')));

        $route['params'] = ['param' => 'test'];
        $this->assertEquals($route, $this->router->resolve(new HttpRequest('/param/test', 'GET')));
    }

    public function testInvalidParamRouteResolving()
    {
        $this->router->loadControllers([ParamController::class]);

        $this->expectException(RouteNotFoundException::class);
        $this->router->resolve(new HttpRequest('/param/', 'GET'));
    }

    public function testCommandResolving()
    {
        $this->router->loadControllers([CommandController::class]);

        $command = $this->router->resolve(new ConsoleRequest('test', []));
        $expectedCommand = $this->router->getCommands()['test'];
        $expectedCommand['params'] = [];
        $this->assertEquals($expectedCommand, $command);

        $this->assertEquals(CommandController::class, $command['controller']);
        $this->assertEquals('test', $command['method']);

        $this->assertCount(1, $command['middleware']);
        $this->assertContains(MockMiddleware::class, $command['middleware']);
    }

    public function testInvalidCommandResolving()
    {
        $this->router->loadControllers([CommandController::class]);

        $this->expectException(CommandNotFoundException::class);
        $this->router->resolve(new ConsoleRequest('invalid', []));
    }

    public function testArgCommandResolving()
    {
        $this->router->loadControllers([CommandController::class]);

        $command = $this->router->resolve(new ConsoleRequest('testarg', [1]));
        $expectedCommand = $this->router->getCommands()['testarg'];
        $expectedCommand['params'] = ['opt' => 1];
        $this->assertEquals($expectedCommand, $command);

        $this->assertEquals(CommandController::class, $command['controller']);
        $this->assertEquals('testArg', $command['method']);

        $this->assertEmpty($command['middleware']);
    }

    public function testArgCommandResolvingWithArgNotProvided()
    {
        $this->router->loadControllers([CommandController::class]);

        $command = $this->router->resolve(new ConsoleRequest('testarg', []));

        $expectedCommand = $this->router->getCommands()['testarg'];
        $expectedCommand['params'] = [];
        $this->assertEquals($expectedCommand, $command);

        $this->assertEquals(CommandController::class, $command['controller']);
        $this->assertEquals('testArg', $command['method']);

        $this->assertEmpty($command['middleware']);
    }*/
}
