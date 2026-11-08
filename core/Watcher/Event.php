<?php

namespace Ryra\Core\Watcher;

enum Event: string
{
    case ADD = 'add';
    case CHANGE = 'change';
    case UNLINK = 'unlink';
    case ADD_DIR = 'add_dir';
    case UNLINK_DIR = 'unlink_dir';
    case ERROR = 'error';
    case READY = 'ready';
    case ALL = 'all';
}