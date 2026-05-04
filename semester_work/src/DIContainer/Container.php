<?php

namespace MyApp\DIContainer;

use ReflectionClass;
use ReflectionParameter;
use ReflectionNamedType;
use InvalidArgumentException;
use RuntimeException;

class Container
{
    private array $definitions = [];
    private array $singletons = [];
    private array $aliases = [];

    public function set(string $id, callable $factory): self
    {
        $this->definitions[$id] = $factory;
        return $this;
    }

    public function setClass(string $id, ?string $class = null): self
    {
        $class = $class ?? $id;
        $this->definitions[$id] = function (Container $c) use ($class) {
            return $c->resolve($class);
        };
        return $this;
    }

    public function singleton(string $id, callable|string|null $factory = null): self
    {
        if ($factory === null) {
            $factory = $id;
        }

        $this->definitions[$id] = function (Container $c) use ($id, $factory) {
            if (!isset($this->singletons[$id])) {
                if (is_string($factory)) {
                    if (interface_exists($factory)) {
                        throw new InvalidArgumentException(
                            "Cannot bind interface '{$factory}' to singleton. 
                            Use singleton('interface', ConcreteClass::class) or provide a factory.",
                        );
                    }

                    if (!class_exists($factory)) {
                        throw new InvalidArgumentException("Class '{$factory}' not found");
                    }

                    $this->singletons[$id] = $c->resolve($factory);
                } else {
                    $this->singletons[$id] = $factory($c);
                }
            }
            return $this->singletons[$id];
        };

        return $this;
    }

    public function bind(string $interface, string $implementation): self
    {
        if (!interface_exists($interface) && !class_exists($interface)) {
            throw new InvalidArgumentException("Interface or class '{$interface}' not found");
        }

        if (!class_exists($implementation)) {
            throw new InvalidArgumentException("Implementation class '{$implementation}' not found");
        }

        if (interface_exists($interface) && !is_subclass_of($implementation, $interface)) {
            throw new InvalidArgumentException("Class '{$implementation}' must implement interface '{$interface}'");
        }

        $this->definitions[$interface] = function (Container $c) use ($implementation) {
            return $c->resolve($implementation);
        };
        return $this;
    }

    public function bindSingleton(string $interface, string $implementation): self
    {
        if (!interface_exists($interface) && !class_exists($interface)) {
            throw new InvalidArgumentException("Interface or class '{$interface}' not found");
        }

        if (!class_exists($implementation)) {
            throw new InvalidArgumentException("Implementation class '{$implementation}' not found");
        }

        if (interface_exists($interface) && !is_subclass_of($implementation, $interface)) {
            throw new InvalidArgumentException("Class '{$implementation}' must implement interface '{$interface}'");
        }
        $this->singleton($interface, function (Container $c) use ($implementation) {
            return $c->resolve($implementation);
        });
        return $this;
    }

    public function instance(string $id, object $instance): self
    {
        $this->singletons[$id] = $instance;
        $this->definitions[$id] = function () use ($instance) {
            return $instance;
        };
        return $this;
    }

    public function alias(string $alias, string $service): self
    {
        $this->aliases[$alias] = $service;
        return $this;
    }

    public function get(string $id): object
    {
        $id = $this->resolveAlias($id);

        if ($id === self::class || $id === Container::class) {
            return $this;
        }

        if (isset($this->singletons[$id])) {
            return $this->singletons[$id];
        }

        if (isset($this->definitions[$id])) {
            $definition = $this->definitions[$id];

            if ($definition instanceof \Closure) {
                return $definition($this);
            }

            if (is_string($definition) && class_exists($definition)) {
                return $this->resolve($definition);
            }

            if (is_object($definition)) {
                return $definition;
            }
        }

        if (class_exists($id)) {
            return $this->resolve($id);
        }

        if (interface_exists($id)) {
            throw new InvalidArgumentException(
                "Interface '{$id}' not bound to any implementation. Use bind() or bindSingleton() first.",
            );
        }
        throw new InvalidArgumentException("Service '{$id}' not found in container");
    }

    public function has(string $id): bool
    {
        $id = $this->resolveAlias($id);
        return isset($this->definitions[$id]) || isset($this->singletons[$id]) || class_exists($id);
    }

    public function resolve(string $class): object
    {
        $reflection = new ReflectionClass($class);

        if ($reflection->isInterface()) {
            throw new RuntimeException("Cannot instantiate interface '{$class}'. Use bind() or bindSingleton() first.");
        }

        if (!$reflection->isInstantiable()) {
            throw new RuntimeException("Class {$class} is not instantiable");
        }

        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return $reflection->newInstance();
        }

        $parameters = $constructor->getParameters();
        $dependencies = $this->resolveParameters($parameters);

        return $reflection->newInstanceArgs($dependencies);
    }

    private function resolveParameters(array $parameters): array
    {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $dependencies[] = $this->resolveParameter($parameter);
        }
        return $dependencies;
    }

    private function resolveParameter(ReflectionParameter $parameter): mixed
    {
        $type = $parameter->getType();

        if ($type === null || $type->isBuiltin()) {
            if ($parameter->isDefaultValueAvailable()) {
                return $parameter->getDefaultValue();
            }

            throw new RuntimeException("Cannot resolve parameter '{$parameter->getName()}'");
        }

        if (!$type instanceof ReflectionNamedType) {
            throw new RuntimeException("Union or intersection types are not 
            supported for parameter '{$parameter->getName()}'");
        }

        $typeName = $type->getName();

        if ($type->allowsNull() && !$parameter->isDefaultValueAvailable()) {
            return null;
        }

        if ($this->has($typeName)) {
            return $this->get($typeName);
        }

        if (class_exists($typeName)) {
            $reflection = new ReflectionClass($typeName);
            if (!$reflection->isInterface() && !$reflection->isAbstract()) {
                return $this->resolve($typeName);
            }
        }

        if (interface_exists($typeName) || class_exists($typeName)) {
            throw new RuntimeException(
                "Cannot resolve dependency '{$typeName}' for 
                parameter '{$parameter->getName()}'. Use bind() 
                or bindSingleton() first.",
            );
        }

        throw new RuntimeException("Cannot resolve dependency '{$typeName}' for parameter '{$parameter->getName()}'");
    }

    public function call(callable|array $callable, array $additionalParams = []): mixed
    {
        if (is_array($callable)) {
            $reflection = new \ReflectionMethod($callable[0], $callable[1]);
        } else {
            $reflection = new \ReflectionFunction($callable);
        }

        $parameters = $reflection->getParameters();
        $args = [];

        foreach ($parameters as $parameter) {
            $paramName = $parameter->getName();

            if (isset($additionalParams[$paramName])) {
                $args[] = $additionalParams[$paramName];
                continue;
            }

            $type = $parameter->getType();
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $typeName = $type->getName();
                if ($this->has($typeName)) {
                    $args[] = $this->get($typeName);
                    continue;
                }
            }

            if ($parameter->isDefaultValueAvailable()) {
                $args[] = $parameter->getDefaultValue();
                continue;
            }

            throw new RuntimeException("Cannot resolve parameter '{$paramName}'");
        }

        return $reflection->invokeArgs($args);
    }

    private function resolveAlias(string $id): string
    {
        return $this->aliases[$id] ?? $id;
    }

    public function getDefinitions(): array
    {
        return array_keys($this->definitions);
    }

    public function clear(): void
    {
        $this->definitions = [];
        $this->singletons = [];
        $this->aliases = [];
    }
}
