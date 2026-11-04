<?php

namespace Diana\Router\Tests;

use Diana\Contracts\Cache\Cache;
use Diana\Contracts\Core\Container;
use Diana\Router\Attributes\Command;
use Diana\Router\Attributes\Get;
use Diana\Router\Exceptions\MissingArgumentsException;
use Diana\Router\FileRouter;
use Diana\Router\FileRouterCached;
use Diana\Router\Route;
use Diana\Router\Tests\Fixtures\ConsoleRequest;
use Diana\Router\Tests\Fixtures\HttpRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ParameterResolutionTest extends TestCase
{
    private function container(): Container
    {
        $container = $this->createStub(Container::class);
        $container->method('make')->willReturnCallback(
            static fn (string $abstract, array $parameters): Route => new Route(...$parameters)
        );
        return $container;
    }

    private function router(): FileRouter
    {
        $router = new FileRouter($this->container());
        $router->generateRoutesFromPackage(new ParameterController());
        return $router;
    }

    public function testRejectedRoutesDoNotLeakParametersIntoTheMatch(): void
    {
        $router = $this->router();
        $first = $router->resolve(new HttpRequest('/fixed/42'));
        self::assertSame(['id' => '42'], $first->getParameters());
        $second = $router->resolve(new HttpRequest('/fixed/99'));
        self::assertSame(['id' => '99'], $second->getParameters());
        self::assertSame(['id' => '42'], $first->getParameters());
    }

    public function testNamedHttpAttributeArgumentsRespectControllerPrefixes(): void
    {
        $router = $this->router();
        $router->generateRoutesFromPackage(new class {
            public static string $route = '/prefix';

            #[Get(path: '/named')]
            public function named(): void {}
        });
        $route = $router->resolve(new HttpRequest('/prefix/named'));
        self::assertSame('named', $route->getMethod());
        self::assertSame([], $route->getParameters());
    }

    public function testMissingAttributeArgumentsThrowTheRouterException(): void
    {
        $router = $this->router();
        $this->expectException(MissingArgumentsException::class);
        $router->generateRoutesFromPackage(new class {
            #[Get]
            public function missing(): void {}
        });
    }

    public static function commandArguments(): iterable
    {
        yield 'positional arguments' => [['one', 'two', 'three'], ['first' => 'one', 'second' => 'two', 'third' => 'three']];
        yield 'options mixed with positions' => [
            ['--verbose', '--format', 'json', 'one', 'two', '--dry-run'],
            ['verbose' => true, 'format' => 'json', 'dry-run' => true, 'first' => 'one', 'second' => 'two'],
        ];
        yield 'optional argument omitted' => [['one', 'two'], ['first' => 'one', 'second' => 'two']];
        yield 'no arguments' => [[], []];
        yield 'only options' => [['--verbose'], ['verbose' => true]];
    }

    #[DataProvider('commandArguments')]
    public function testCommandArgumentsAndOptionsAreResolvedTogether(array $arguments, array $expected): void
    {
        self::assertSame($expected, $this->router()->resolve(new ConsoleRequest('demo', $arguments))->getParameters());
    }

    public function testNamedCommandAttributeArgumentsCanBePassedInAnyOrder(): void
    {
        $router = $this->router();
        $router->generateRoutesFromPackage(new class {
            #[Command(args: ['first', '?second'], command: 'named')]
            public function named(): void {}
        });
        self::assertSame(['first' => 'one', 'second' => 'two'],
            $router->resolve(new ConsoleRequest('named', ['one', 'two']))->getParameters());
    }

    public function testLegacyCommandArgumentSyntaxRemainsSupported(): void
    {
        $router = $this->router();
        $router->generateRoutesFromPackage(new class {
            #[Command('legacy', 'first', '?second')]
            public function legacy(): void {}
        });
        self::assertSame(['first' => 'one', 'second' => 'two'],
            $router->resolve(new ConsoleRequest('legacy', ['one', 'two']))->getParameters());
    }

    public function testRepeatedCommandResolutionKeepsDefinitionsAndPreviousResultsIntact(): void
    {
        $router = $this->router();
        $first = $router->resolve(new ConsoleRequest('demo', ['one', 'two', 'three', '--verbose']));
        self::assertSame(['first' => 'new'], $router->resolve(new ConsoleRequest('demo', ['new']))->getParameters());
        self::assertSame([], $router->resolve(new ConsoleRequest('demo'))->getParameters());
        self::assertSame(['verbose' => true, 'first' => 'one', 'second' => 'two', 'third' => 'three'], $first->getParameters());
    }

    public function testCacheRoundtripRetainsDefinitionsAfterRequestsHaveBeenResolved(): void
    {
        $stored = null;
        $cache = $this->createMock(Cache::class);
        $cache->method('get')->willReturnCallback(static function () use (&$stored): mixed {
            return $stored;
        });
        $cache->expects(self::once())->method('set')->willReturnCallback(
            static function (string $key, mixed $value) use (&$stored): bool {
                $stored = $value;
                return true;
            }
        );
        $cold = new FileRouterCached($this->container(), $cache);
        $cold->generateRoutesFromPackage(new ParameterController());
        $cold->resolve(new ConsoleRequest('demo', ['old', 'values', '--verbose']));
        $cold->resolve(new HttpRequest('/fixed/42'));
        $cold->generateCache();

        self::assertSame(['first', 'second', '?third'], $stored['commands']['demo']['params']);
        self::assertSame([], $stored['routes']['GET']['/fixed/:id']['params']);
        $warm = new FileRouterCached($this->container(), $cache);
        self::assertTrue($warm->isLoadedFromCache());
        self::assertSame(['first' => 'new', 'second' => 'values'],
            $warm->resolve(new ConsoleRequest('demo', ['new', 'values']))->getParameters());
        self::assertSame([], $warm->resolve(new ConsoleRequest('demo'))->getParameters());
        self::assertSame(['id' => '99'], $warm->resolve(new HttpRequest('/fixed/99'))->getParameters());
    }
}

class ParameterController
{
    #[Get('/:leak/wrong')]
    public function rejected(): void {}

    #[Get('/fixed/:id')]
    public function matched(): void {}

    #[Command('demo', ['first', 'second', '?third'])]
    public function command(): void {}
}
