<?php

namespace Ryra\Core\Cache\Exceptions;

use Psr\Cache\CacheException as PsrCacheException;

class CacheException extends \Exception implements PsrCacheException
{
}