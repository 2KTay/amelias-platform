<?php

declare(strict_types=1);

namespace Amelias\Http;

/**
 * Path → controller@method router.
 *
 * Routes are registered as (METHOD, pattern, handler). Patterns support named
 * params: "/order/{id}" captures id. Handlers are "Class@method" strings
 * (resolved from the Amelias\Controllers namespace) or callables.
 */
final class Router
{
    /** @var array<int,array{method:string,regex:string,params:string[],handler:mixed,name:?string}> */
    private array $routes = [];

    /** @var callable|null */
    private $notFound = null;

    /** @var callable|null */
    private $errorHandler = null;

    public function add(string $method, string $pattern, mixed $handler, ?string $name = null): void
    {
        [$regex, $params] = $this->compile($pattern);
        $this->routes[] = [
            'method'  => strtoupper($method),
            'regex'   => $regex,
            'params'  => $params,
            'handler' => $handler,
            'name'    => $name,
        ];
    }

    public function get(string $pattern, mixed $handler, ?string $name = null): void
    {
        $this->add('GET', $pattern, $handler, $name);
    }

    public function post(string $pattern, mixed $handler, ?string $name = null): void
    {
        $this->add('POST', $pattern, $handler, $name);
    }

    public function map(array $methods, string $pattern, mixed $handler, ?string $name = null): void
    {
        foreach ($methods as $method) {
            $this->add($method, $pattern, $handler, $name);
        }
    }

    public function setNotFound(callable $handler): void
    {
        $this->notFound = $handler;
    }

    public function setErrorHandler(callable $handler): void
    {
        $this->errorHandler = $handler;
    }

    public function dispatch(Request $request): void
    {
        $matchedPathButNotMethod = false;

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $request->path, $matches)) {
                continue;
            }
            if ($route['method'] !== $request->method) {
                $matchedPathButNotMethod = true;
                continue;
            }

            $params = [];
            foreach ($route['params'] as $name) {
                $params[$name] = $matches[$name] ?? '';
            }
            $request->setRouteParams($params);

            try {
                $this->invoke($route['handler'], $request, $params);
            } catch (\Throwable $e) {
                $this->handleError($e, $request);
            }
            return;
        }

        $this->handleNotFound($request, $matchedPathButNotMethod ? 405 : 404);
    }

    private function invoke(mixed $handler, Request $request, array $params): void
    {
        if (is_callable($handler)) {
            $handler($request, $params);
            return;
        }

        if (is_string($handler) && str_contains($handler, '@')) {
            [$class, $method] = explode('@', $handler, 2);
            $fqcn = str_starts_with($class, 'Amelias\\') ? $class : "Amelias\\Controllers\\{$class}";
            if (!class_exists($fqcn)) {
                throw new \RuntimeException("Controller not found: {$fqcn}");
            }
            $controller = new $fqcn();
            if (!method_exists($controller, $method)) {
                throw new \RuntimeException("Method not found: {$fqcn}::{$method}");
            }
            $controller->{$method}($request, $params);
            return;
        }

        throw new \RuntimeException('Invalid route handler.');
    }

    private function handleNotFound(Request $request, int $status): void
    {
        if ($this->notFound !== null) {
            ($this->notFound)($request, $status);
            return;
        }
        http_response_code($status);
        echo $status === 405 ? 'Method Not Allowed' : 'Not Found';
    }

    private function handleError(\Throwable $e, Request $request): void
    {
        error_log('[router] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        if ($this->errorHandler !== null) {
            ($this->errorHandler)($e, $request);
            return;
        }
        http_response_code(500);
        echo 'Server Error';
    }

    /**
     * Compile "/order/{id}" into a regex with a named capture group.
     *
     * @return array{0:string,1:string[]}
     */
    private function compile(string $pattern): array
    {
        $params = [];
        $regex = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
            static function (array $m) use (&$params): string {
                $params[] = $m[1];
                return '(?P<' . $m[1] . '>[^/]+)';
            },
            $pattern
        );
        $regex = '#^' . $regex . '$#';
        return [$regex, $params];
    }
}
