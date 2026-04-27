<?php

namespace MyApp\middlewares;

use GuzzleHttp\Middleware;
use MyApp\DIContainer\Container;
use MyApp\entities\Request;

class MiddlewarePipeline
{
    private array $stack = [];

    public function use(callable $inline): self
    {
        $this->stack[] = $inline;
        return $this;
    }


    public function useMiddleware(string $middlewareClass, Container $container): self
    {
        $middleware = $container->get($middlewareClass);

        return $this->use(function(Request $request, callable $next) use ($middleware) {
            return $middleware->handle($request, $next);
        });
    }

    public function executeAsync(Request $request): mixed
    {
        $i = -1;

        $next = function() use (&$i, &$next, $request): mixed {
            $i++;

            if ($i < count($this->stack)) {
                return $this->stack[$i]($request, $next);
            }

            return null;
        };

        return $next();
    }

}