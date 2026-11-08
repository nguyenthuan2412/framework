<?php

namespace Ryra\Core\Pipeline;

use Ryra\Core\App;
use SplStack;

class Pipeline
{
    protected mixed $passable;

    protected SplStack $pipes;

    protected $destination;

    protected string $method = 'handle';

    public function __construct()
    {
        $this->pipes = new SplStack();
    }

    /**
     * Set the object being sent through the pipeline.
     */
    public function send(mixed $passable): static
    {
        $this->passable = $passable;
        return $this;
    }

    /**
     * Set the array of pipes.
     */
    public function through(array $pipes): static
    {
        $this->pipes = new SplStack();
        return $this->pipe($pipes);
    }

    /**
     * Push additional pipes onto the pipeline.
     */
    public function pipe(array $pipes): static
    {
        foreach ($pipes as $pipe) {
            $this->pipes->push($pipe);
        }
        return $this;
    }

    /**
     * Set the method to call on the pipes.
     */
    public function via(string $method): static
    {
        $this->method = $method;
        return $this;
    }

    /**
     * Run the pipeline with a final destination callback.
     */
    public function then(callable $destination): mixed
    {
        $next = $destination;

        foreach ($this->pipes as $pipe) {
            if (\is_string($pipe)) {
                $pipe = App::make($pipe);
            }

            $method = $this->method;
            $next = static fn($passable) => $pipe->{$method}($passable, $next);
        }

        return $next($this->passable);
    }

    /**
     * Run the pipeline and return the result.
     */
    public function thenReturn(): mixed
    {
        return $this->then(fn($passable) => $passable);
    }
}