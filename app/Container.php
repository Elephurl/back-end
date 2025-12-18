<?php

declare(strict_types=1);

namespace App;

use Closure;
use Exception;

class Container
{
    private array $bindings = [];
    private array $instances = [];

    public function bind(string $abstract, Closure $concrete): void
    {
        $this->bindings[$abstract] = $concrete;
    }

    public function singleton(string $abstract, Closure $concrete): void
    {
        $this->bindings[$abstract] = function ($container) use ($abstract, $concrete) {
            if (!isset($this->instances[$abstract])) {
                $this->instances[$abstract] = $concrete($container);
            }
            return $this->instances[$abstract];
        };
    }

    public function instance(string $abstract, object $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    public function make(string $abstract): mixed
    {
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        if (isset($this->bindings[$abstract])) {
            return $this->bindings[$abstract]($this);
        }

        throw new Exception("Service not found: {$abstract}");
    }

    public function has(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) || isset($this->instances[$abstract]);
    }
}
