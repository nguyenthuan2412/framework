<?php

namespace Ryra\Core\Process;

use Symfony\Component\Process\Process as SymfonyProcess;

class Process
{
    private ?int $timeout = 60;
    private ?float $idleTimeout = null;
    private ?string $cwd = null;
    private ?array $env = null;
    private bool $tty = false;
    private bool $pty = false;
    private bool $silent = false;
    private array $options = [];
    private SymfonyProcess $process;

    public function path(string $cwd): static
    {
        $this->cwd = $cwd;
        return $this;
    }

    public function timeout(int $timeout): static
    {
        $this->timeout = $timeout;
        return $this;
    }

    public function idleTimeout(int $idleTimeout): static
    {
        $this->idleTimeout = $idleTimeout;
        return $this;
    }

    public function forever(): static
    {
        $this->timeout = null;
        return $this;
    }

    public function env(array $env): static
    {
        $this->env = $env;
        return $this;
    }

    public function tty(): static
    {
        $this->tty = true;
        return $this;
    }

    public function pty(): static
    {
        $this->pty = true;
        return $this;
    }

    public function quietly(): static
    {
        $this->silent = true;
        return $this;
    }

    public function run(string $command): ProcessResult
    {
        $this->process = $this->buildSymfonyProcess($command);
        $this->process->run();
        return new ProcessResult($this->process);
    }

    public function start(string $command): static
    {
        $this->process = $this->buildSymfonyProcess($command);
        $this->process->start();
        return $this;
    }

    public function options(array $options = []): static
    {
        $this->options = $options;
        return $this;
    }

    public function id(): ?int
    {
        return $this->process->getPid();
    }

    public function wait(): ProcessResult
    {
        $this->process->wait();
        return new ProcessResult($this->process);
    }

    public function running(): bool
    {
        return $this->process->isRunning();
    }

    public function latestOutput(): string
    {
        return $this->process->getIncrementalOutput();
    }

    public function latestErrorOutput(): string
    {
        return $this->process->getIncrementalErrorOutput();
    }

    public function stop(float $timeout = 10, ?int $signal = null): ?int
    {
        return $this->process->stop($timeout, $signal);
    }

    protected function buildSymfonyProcess(string $command): SymfonyProcess
    {
        $process = new SymfonyProcess(
            explode(" ", $command),
            $this->cwd,
            $this->env,
            timeout: $this->timeout
        );

        if ($this->options) {
            $process->setOptions($this->options);
        }

        $process->setTty($this->tty);
        $process->setPty($this->pty);
        if ($this->idleTimeout) {
            $process->setIdleTimeout($this->idleTimeout);
        }

        if ($this->silent) {
            $process->disableOutput();
        }

        return $process;
    }
}