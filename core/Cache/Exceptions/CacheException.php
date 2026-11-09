<?php

namespace Ryra\Core\Cache\Exceptions;

use Psr\SimpleCache\CacheException as PsrCacheException;

class CacheException extends \Exception implements PsrCacheException
{
}