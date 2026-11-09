<?php

namespace Ryra\Core\Grpc\Messages;

use Ryra\Core\Grpc\Attributes\Message;

/**
 * google.protobuf.Empty - dùng cho rpc không nhận tham số hoặc trả về void.
 */
#[Message(name: 'Empty', package: 'google.protobuf')]
final class EmptyMessage {}
