<?php

namespace Ryra\Core\Log;

use Monolog\Level;
use Ryra\Core\App;
use Ryra\Core\Collection\Arr;
use Ryra\Core\Log\Exception\InvalidConfigException;
use Ryra\Core\Contracts\Config\Repository;
use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\FormattableHandlerInterface;
use Monolog\Handler\HandlerInterface;
use Monolog\Handler\StreamHandler;

class LoggerFactory
{
    protected array $loggers = [];

    public function __construct(protected Repository $config)
    {
    }

    public function make($name = 'vietiso', $group = 'default'): Logger
    {
        $config = $this->config->get('logger');
        if (!isset($config[$group])) {
            throw new InvalidConfigException(\sprintf('Logger config[%s] is not defined.', $group));
        }

        $config = $config[$group];
        $handlers = $this->handlers($config);
        $processors = $this->processors($config);

        return App::make(Logger::class, [
            'name' => $name,
            'handlers' => $handlers,
            'processors' => $processors,
        ]);
    }

    public function get($name = 'vietiso', $group = 'default'): Logger
    {
        if (isset($this->loggers[$group][$name]) && $this->loggers[$group][$name] instanceof Logger) {
            return $this->loggers[$group][$name];
        }

        return $this->loggers[$group][$name] = $this->make($name, $group);
    }

    protected function getDefaultFormatterConfig($config)
    {
        $formatterClass = Arr::get($config, 'formatter.class', LineFormatter::class);
        $formatterConstructor = Arr::get($config, 'formatter.constructor', []);

        return [
            'class' => $formatterClass,
            'constructor' => $formatterConstructor,
        ];
    }

    protected function getDefaultHandlerConfig(array $config): array
    {
        $handlerClass = Arr::get($config, 'handler.class', StreamHandler::class);
        $handlerConstructor = Arr::get($config, 'handler.constructor', [
            'stream' => \storage_path(\join_paths('vietiso', 'logs', 'vietiso.log')),
            'level' => Level::Debug,
        ]);

        return [
            'class' => $handlerClass,
            'constructor' => $handlerConstructor,
        ];
    }

    protected function processors(array $config): array
    {
        $result = [];
        if (!isset($config['processors']) && isset($config['processor'])) {
            $config['processors'] = [$config['processor']];
        }

        foreach ($config['processors'] ?? [] as $value) {
            if (\is_array($value) && isset($value['class'])) {
                $value = App::make($value['class'], $value['constructor'] ?? []);
            }

            $result[] = $value;
        }

        return $result;
    }

    protected function handlers(array $config): array
    {
        $handlerConfigs = $config['handlers'] ?? [[]];
        $handlers = [];
        $defaultHandlerConfig = $this->getDefaultHandlerConfig($config);
        $defaultFormatterConfig = $this->getDefaultFormatterConfig($config);
        foreach ($handlerConfigs as $value) {
            if (\is_string($value)) {
                if (! $this->config->has($group = 'logger.' . $value)) {
                    continue;
                }
                $value = $this->config->get("$group.handler", []);
                if ($this->config->has("$group.formatter")) {
                    $value['formatter'] = $this->config->get("$group.formatter", []);
                }
            }
            $class = $value['class'] ?? $defaultHandlerConfig['class'];
            $constructor = $value['constructor'] ?? $defaultHandlerConfig['constructor'];
            if (isset($value['formatter'])) {
                if (! isset($value['formatter']['constructor'])) {
                    $value['formatter']['constructor'] = $defaultFormatterConfig['constructor'];
                }
            }
            $formatterConfig = $value['formatter'] ?? $defaultFormatterConfig;

            $handlers[] = $this->handler($class, $constructor, $formatterConfig);
        }

        return $handlers;
    }

    /**
     * @param class-string<HandlerInterface> $class
     * @param array $constructor
     * @param array $formatterConfig
     */
    protected function handler(string $class, array $constructor, array $formatterConfig): HandlerInterface
    {
        $handler = App::make($class, $constructor);

        if ($handler instanceof FormattableHandlerInterface) {
            $formatterClass = $formatterConfig['class'];
            $formatterConstructor = $formatterConfig['constructor'];

            /** @var FormatterInterface $formatter */
            $formatter = App::make($formatterClass, $formatterConstructor);

            $handler->setFormatter($formatter);
        }

        return $handler;
    }
}