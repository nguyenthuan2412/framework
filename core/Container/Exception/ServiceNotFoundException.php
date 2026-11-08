<?php

namespace Ryra\Core\Container\Exception;

use Psr\Container\NotFoundExceptionInterface;

class ServiceNotFoundException extends \InvalidArgumentException implements NotFoundExceptionInterface
{
}