<?php

namespace Ryra\Core\Grpc\Tests\Fixtures;

use Ryra\Core\Grpc\Attributes\Field;
use Ryra\Core\Grpc\Attributes\Message;
use Ryra\Core\Grpc\Protobuf\Type;

#[Message(comment: 'Thông tin người dùng')]
final class User
{
    public function __construct(
        #[Field(1)] public int $id,
        #[Field(2)] public string $name,
        #[Field(3)] public ?string $email = null,
        #[Field(4, type: Type::Sint32)] public int $score = 0,
        #[Field(5)] public float $balance = 0.0,
        #[Field(6)] public bool $verified = false,
        #[Field(7)] public UserStatus $status = UserStatus::Active,
        #[Field(8, type: Type::String)] public array $tags = [],
        #[Field(9, type: Type::Int64, key: Type::String)] public array $counters = [],
        #[Field(10)] public ?Address $address = null,
        #[Field(11, type: Address::class)] public array $history = [],
        #[Field(12)] public ?\DateTimeImmutable $createdAt = null,
        #[Field(13, type: Type::Bytes)] public string $avatar = '',
    ) {}
}
