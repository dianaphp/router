<?php

namespace Diana\Router\Tests\Framework;

use Composer\Autoload\ClassLoader;
use Diana\Config\Config;
use Diana\Contracts\Cache\Cache;
use Diana\Contracts\IO\ConsoleRequest as ConsoleRequestContract;
use Diana\Contracts\IO\HttpRequest as HttpRequestContract;
use Diana\Contracts\Router\Router;
use Diana\Contracts\RequestContract;
use Diana\Framework\Core\Application;
use Diana\IO\ConsoleRequest;
use Diana\IO\HttpRequest;
use Diana\IO\Response;
use Diana\Router\Attributes\Command;
use Diana\Router\Attributes\Get;
use Diana\Router\Attributes\Middleware;
use Diana\Router\Framework\RouterModule;
use Diana\Router\FileRouter;
use Diana\Router\FileRouterCached;
use Diana\Router\Route;
use PHPUnit\Framework\TestCase;

class FrameworkIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        IntegrationTrace::$events = [];
    }

    public function testFrameworkRequestsImplementSharedContracts(): void
    {
        self::assertInstanceOf(HttpRequestContract::class, new HttpRequest('/example'));
        $request = new ConsoleRequest('example', ['--verbose']);
        self::assertInstanceOf(ConsoleRequestContract::class, $request);
        self::assertSame(['--verbose'], $request->getArguments());
        self::assertSame($request->args->getAll(), $request->getArguments());
    }

    public function testApplicationBootDiscoversRoutesAndCommandsFromExternalPackage(): void
    {
        $config = new RouterIntegrationConfig();
        $app = new RouterIntegrationApplication('/example', new ClassLoader(), $config);

        $response = $app->generateResponse(new HttpRequest('/hello/Ani'));
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Hello Ani', (string) $response);
        self::assertSame('Hello CLI', (string) $app->runCommand('hello'));
        self::assertSame(0, $app->runCommand('hello')->getStatusCode());
        self::assertSame(404, $app->generateResponse(new HttpRequest('/missing'))->getStatusCode());
        self::assertSame(1, $app->runCommand('missing')->getStatusCode());
    }

    public function testGlobalMiddlewareWrapsResolutionRouteMiddlewareAndController(): void
    {
        $app = new RouterIntegrationApplication('/example', new ClassLoader(), new RouterIntegrationConfig([
            'middleware' => [IntegrationGlobalMiddleware::class],
        ]));
        $request = new HttpRequest('/hello/Ani');
        $response = $app->generateResponse($request);

        self::assertSame('Hello Ani', (string) $response);
        self::assertSame([
            'global.before', 'resolve', 'route.before', 'controller', 'route.after', 'global.after',
        ], IntegrationTrace::$events);
    }

    public function testGlobalAbortSkipsResolutionAndRouteMiddleware(): void
    {
        $blocked = new Response('blocked globally', 403);
        $app = new RouterIntegrationApplication('/example', new ClassLoader(), new RouterIntegrationConfig([
            'middleware' => [static fn () => $blocked],
        ]));

        self::assertSame($blocked, $app->generateResponse(new HttpRequest('/hello/Ani')));
        self::assertSame([], IntegrationTrace::$events);
    }

    public function testRouteAbortSkipsControllerButReturnsThroughGlobalMiddleware(): void
    {
        $app = new RouterIntegrationApplication('/example', new ClassLoader(), new RouterIntegrationConfig([
            'middleware' => [IntegrationGlobalMiddleware::class],
        ]));
        $response = $app->generateResponse(new HttpRequest('/blocked'));

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('blocked on route', (string) $response);
        self::assertSame(['global.before', 'resolve', 'route.block', 'global.after'], IntegrationTrace::$events);
    }

    public function testGlobalMiddlewareAlsoWrapsNotFoundResponses(): void
    {
        $app = new RouterIntegrationApplication('/example', new ClassLoader(), new RouterIntegrationConfig([
            'middleware' => [IntegrationGlobalMiddleware::class],
        ]));
        self::assertSame(404, $app->generateResponse(new HttpRequest('/missing'))->getStatusCode());
        self::assertSame(['global.before', 'resolve', 'global.after'], IntegrationTrace::$events);
    }

    public function testRouterModuleCanBeConfiguredDirectlyWithExplicitPackages(): void
    {
        $app = new RouterIntegrationApplication('/example', new ClassLoader(), new RouterIntegrationConfig([
            'entrypoint' => RouterModule::class,
            'packages' => [RouterModule::class, RouterIntegrationController::class],
        ]));

        self::assertSame('Hello Ani', (string) $app->generateResponse(new HttpRequest('/hello/Ani')));
    }

    public function testRepeatedRequestsUseFreshPipelineState(): void
    {
        $app = new RouterIntegrationApplication('/example', new ClassLoader(), new RouterIntegrationConfig());

        self::assertSame(401, $app->generateResponse(new HttpRequest('/blocked'))->getStatusCode());
        self::assertSame('Hello Ani', (string) $app->generateResponse(new HttpRequest('/hello/Ani')));
        self::assertSame('Hello Diana', (string) $app->generateResponse(new HttpRequest('/hello/Diana')));
        self::assertSame(0, $app->runCommand('hello')->getStatusCode());
    }

    public function testCachedRouterIsInitializedBeforePackageRegistrationAndBoot(): void
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
        $values = ['singleton' => [Router::class => FileRouterCached::class, Cache::class => fn () => $cache]];

        $cold = new RouterIntegrationApplication('/example', new ClassLoader(), new RouterIntegrationConfig($values));
        self::assertIsArray($stored);
        self::assertSame('Hello Ani', (string) $cold->generateResponse(new HttpRequest('/hello/Ani')));

        $warm = new RouterIntegrationApplication('/example', new ClassLoader(), new RouterIntegrationConfig($values));
        self::assertSame('Hello Diana', (string) $warm->generateResponse(new HttpRequest('/hello/Diana')));
        self::assertSame('Hello CLI', (string) $warm->runCommand('hello'));
    }

    public function testModuleSuppliesDefaultBindingsWhenNoRouterIsConfigured(): void
    {
        $config = new RouterIntegrationConfig();
        $config->set('singleton', []);
        $app = new RouterIntegrationApplication('/example', new ClassLoader(), $config);

        self::assertSame('Hello Ani', (string) $app->generateResponse(new HttpRequest('/hello/Ani')));
    }

    public function testAnApplicationModuleCanAlsoProvideControllerActions(): void
    {
        $app = new RouterIntegrationApplication('/example', new ClassLoader(), new RouterIntegrationConfig([
            'singleton' => [RouterIntegrationModule::class => RouterIntegrationModule::class],
        ]));

        self::assertSame('module action', (string) $app->generateResponse(new HttpRequest('/module')));
    }
}

class RouterIntegrationApplication extends Application
{
    protected array $modules = [];
}

class RouterIntegrationConfig extends Config
{
    public function __construct(array $values = [])
    {
        parent::__construct('framework');
        $this->config = [
            'entrypoint' => RouterIntegrationModule::class,
            'singleton' => [Router::class => IntegrationRouter::class],
        ];
        $this->config = $this->arrayMergeRecursiveDistinct($this->config, $values);
    }

    public function addDefault(array $default): self
    {
        parent::addDefault($default);
        $this->config = $this->arrayMergeRecursiveDistinct($default, $this->config);
        return $this;
    }
}

class RouterIntegrationController
{
    #[Get('/hello/:name')]
    #[Middleware(IntegrationRouteMiddleware::class)]
    public function hello(string $name): string
    {
        IntegrationTrace::$events[] = 'controller';
        return 'Hello ' . $name;
    }

    #[Command('hello')]
    public function command(): string
    {
        return 'Hello CLI';
    }

    #[Get('/blocked')]
    #[Middleware(IntegrationBlockingMiddleware::class)]
    public function blocked(): string
    {
        throw new \LogicException('The blocked controller must never run.');
    }
}

class RouterIntegrationModule
{
    private RouterModule $router;

    public function __construct(Application $app)
    {
        $this->router = $app->registerPackage(RouterModule::class);
        $app->registerPackage(RouterIntegrationController::class);
    }

    public function __invoke(RequestContract $request): Response
    {
        return ($this->router)($request);
    }

    #[Get('/module')]
    public function action(): string
    {
        return 'module action';
    }
}

class IntegrationTrace
{
    public static array $events = [];
}

class IntegrationRouter extends FileRouter
{
    public function resolve(RequestContract $request): Route
    {
        IntegrationTrace::$events[] = 'resolve';
        return parent::resolve($request);
    }
}

class IntegrationGlobalMiddleware
{
    public function run(RequestContract $request, \Closure $next): Response
    {
        IntegrationTrace::$events[] = 'global.before';
        $response = $next();
        IntegrationTrace::$events[] = 'global.after';
        return $response;
    }
}

class IntegrationRouteMiddleware
{
    public function run(RequestContract $request, \Closure $next): Response
    {
        IntegrationTrace::$events[] = 'route.before';
        $response = $next();
        IntegrationTrace::$events[] = 'route.after';
        return $response;
    }
}

class IntegrationBlockingMiddleware
{
    public function run(RequestContract $request): Response
    {
        IntegrationTrace::$events[] = 'route.block';
        return new Response('blocked on route', 401);
    }
}
