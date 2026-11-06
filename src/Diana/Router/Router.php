<?php

namespace Diana\Router;

use Diana\Contracts\IO\ConsoleRequest;
use Diana\Contracts\IO\HttpRequest;
use Diana\Contracts\RequestContract;
use Diana\Contracts\Router\Route;
use Diana\Contracts\Router\Router as RouterContract;
use Diana\Router\Attributes\CommandErrorHandler;
use Diana\Router\Attributes\HttpErrorHandler;
use Diana\Router\Exceptions\CommandNotFoundException;
use Diana\Router\Exceptions\RouteNotFoundException;
use Diana\Router\Exceptions\UnsupportedRequestTypeException;
use Diana\Support\Helpers\Str;
use Diana\Router\Exceptions\MethodNotAllowedException;
use Diana\Router\Exceptions\OptionsRequestException;

abstract class Router implements RouterContract
{
    /**
     * @var Route[][] The routes
     */
    protected array $routes = [];

    /**
     * @var Route[] The commands
     */
    protected array $commands = [];

    /**
     * @throws UnsupportedRequestTypeException
     * @throws MethodNotAllowedException
     * @throws OptionsRequestException
     * @throws RouteNotFoundException
     * @throws CommandNotFoundException
     */
    public function resolve(RequestContract $request): Route
    {
        if ($request instanceof HttpRequest) {
            return $this->resolveRoute($request->getRoute(), $request->getMethod());
        } elseif ($request instanceof ConsoleRequest) {
            return $this->resolveCommand($request->getCommand(), $request->getArguments());
        } else {
            throw new UnsupportedRequestTypeException(
                "The provided request type is not being supported by the router."
            );
        }
    }

    /**
     * @throws MethodNotAllowedException
     * @throws OptionsRequestException
     * @throws RouteNotFoundException
     */
    protected function resolveRoute(string $path, string $method): Route
    {
        $segments = explode('/', trim($path, '/'));
        $matches = [];

        foreach ($this->routes as $registeredMethod => $routes) {
            if ($registeredMethod === '*') {
                continue;
            }
            $best = null;
            $bestScore = -1;
            foreach ($routes as $route) {
                $params = [];
                $score = 0;
                $routeSegments = $route->getSegments();
                if (count($segments) !== count($routeSegments)) {
                    continue;
                }
                foreach ($routeSegments as $i => $segment) {
                    if (str_starts_with($segment, ':')) {
                        if ($segments[$i] === '') {
                            continue 2;
                        }
                        $params[substr($segment, 1)] = $segments[$i];
                    } elseif ($segment !== $segments[$i]) {
                        continue 2;
                    } else {
                        $score++;
                    }
                }
                // More literal segments win; equal specificity retains registration order.
                if ($score > $bestScore) {
                    $best = clone $route;
                    $best->setParameters($params);
                    $bestScore = $score;
                }
            }
            if ($best !== null) {
                $matches[$registeredMethod] = $best;
            }
        }

        if (isset($matches[$method])) {
            return $matches[$method];
        }
        if ($method === 'HEAD' && isset($matches['GET'])) {
            return $matches['GET'];
        }
        if ($matches !== []) {
            $allowed = array_keys($matches);
            if (isset($matches['GET'])) {
                $allowed[] = 'HEAD';
            }
            $allowed[] = 'OPTIONS';
            $allowed = array_values(array_unique($allowed));
            sort($allowed);
            if ($method === 'OPTIONS') {
                throw new OptionsRequestException($allowed);
            }
            throw new MethodNotAllowedException($allowed);
        }

        throw new RouteNotFoundException("The route [{$path}] using the method [{$method}] could not have been found.");
    }

    /**
     * @throws CommandNotFoundException
     */
    protected function resolveCommand(string $command, array $args = []): Route
    {
        if (isset($this->commands[$command])) {
            $route = $this->commands[$command];
            $argumentNames = $route->getParameters();

            $params = [];

            $size = sizeof($args);
            for ($i = 0; $i < $size; $i++) {
                if (Str::startsWith($args[$i], '--')) {
                    if (isset($args[$i + 1]) && !Str::startsWith($args[$i + 1], '--')) {
                        $params[substr($args[$i], 2)] = $args[$i + 1];
                        unset($args[$i]);
                        unset($args[$i + 1]);
                        $i++;
                    } else {
                        $params[substr($args[$i], 2)] = true;
                        unset($args[$i]);
                    }
                }
            }

            $args = array_values($args);

            $size = sizeof($args);
            for ($i = 0; $i < $size; $i++) {
                if (!isset($argumentNames[$i])) {
                    continue;
                }

                $name = ltrim($argumentNames[$i], '?');
                $params[$name] = $args[$i];
            }

            $route = clone $route;
            $route->setParameters($params);

            return $route;
        }

        throw new CommandNotFoundException("The command [{$command}] could not have been found.");
    }

    public function getErrorRoute(): ?Route
    {
        return $this->routes['*'][HttpErrorHandler::class] ?? null;
    }

    public function getErrorCommandRoute(): ?Route
    {
        return $this->commands[CommandErrorHandler::class] ?? null;
    }
}
