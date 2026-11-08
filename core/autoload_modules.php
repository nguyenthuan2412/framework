<?php

use Ryra\Core\App;
use Ryra\Core\Support\Str;

\spl_autoload_register(function ($class) {
    if (\strpos($class, 'Ryra\Modules') === 0) {
        $segments = \array_slice(\explode("\\", $class), 2);
        if (!empty($segments)) {
            $segments[0] = DS . \join_paths('modules', Str::snake($segments[0]), 'src');
            $file = App::root() . \join_paths(...$segments) . '.php';
            if (\file_exists($file)) {
                require $file;
            } else {
                throw new \Error("Class \"$class\" not found (expected file: $file)");
            }
        }
    }
});