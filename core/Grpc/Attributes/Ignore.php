<?php

namespace Ryra\Core\Grpc\Attributes;

use Attribute;

/**
 * Bỏ qua property (không serialize) hoặc method (không phải rpc).
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD)]
final class Ignore {}
