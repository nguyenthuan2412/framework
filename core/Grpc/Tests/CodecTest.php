<?php

namespace Ryra\Core\Grpc\Tests;

use PHPUnit\Framework\TestCase;
use Ryra\Core\Grpc\Metadata\MetadataFactory;
use Ryra\Core\Grpc\ProtoGenerator;
use Ryra\Core\Grpc\Protobuf\Codec;
use Ryra\Core\Grpc\Tests\Fixtures\Address;
use Ryra\Core\Grpc\Tests\Fixtures\GetUserRequest;
use Ryra\Core\Grpc\Tests\Fixtures\User;
use Ryra\Core\Grpc\Tests\Fixtures\UserService;
use Ryra\Core\Grpc\Tests\Fixtures\UserStatus;

class CodecTest extends TestCase
{
    private static Codec $codec;

    public static function setUpBeforeClass(): void
    {
        self::$codec = new Codec();
    }

    public function test_generates_proto_files(): void
    {
        $files = (new ProtoGenerator())->generate([MetadataFactory::forService(UserService::class)]);

        $this->assertSame(['common.v1.proto', 'user.v1.proto'], array_keys($files));
        $proto = $files['user.v1.proto'];
        $this->assertStringContainsString('package user.v1;', $proto);
        $this->assertStringContainsString('import "common.v1.proto";', $proto);
        $this->assertStringContainsString('import "google/protobuf/empty.proto";', $proto);
        $this->assertStringContainsString('import "google/protobuf/timestamp.proto";', $proto);
        $this->assertStringContainsString('rpc GetUser(GetUserRequest) returns (User);', $proto);
        $this->assertStringContainsString('rpc ListUsers(GetUserRequest) returns (stream User);', $proto);
        $this->assertStringContainsString('rpc ImportUsers(stream User) returns (GetUserRequest);', $proto);
        $this->assertStringContainsString('rpc Ping(google.protobuf.Empty) returns (google.protobuf.Empty);', $proto);
        $this->assertStringContainsString('optional string email = 3;', $proto);
        $this->assertStringContainsString('sint32 score = 4;', $proto);
        $this->assertStringContainsString('map<string, int64> counters = 9;', $proto);
        $this->assertStringContainsString('repeated common.v1.Address history = 11;', $proto);
        $this->assertStringContainsString('google.protobuf.Timestamp created_at = 12;', $proto);
        $this->assertStringContainsString("enum UserStatus {\n  USER_STATUS_UNSPECIFIED = 0;\n  USER_STATUS_ACTIVE = 1;", $proto);
    }

    public function test_round_trip_full_message(): void
    {
        $user = new User(
            id: 9007199254740993,
            name: 'Thuận',
            email: 'a@b.c',
            score: -42,
            balance: 12.5,
            verified: true,
            status: UserStatus::Banned,
            tags: ['php', 'grpc'],
            counters: ['login' => 3, 'post' => -1],
            address: new Address('1 Lê Lợi', 'HCM'),
            history: [new Address('A', 'B'), new Address('C', 'D')],
            createdAt: new \DateTimeImmutable('2026-11-09 10:20:30.123456 UTC'),
            avatar: "\x00\xFF\x10",
        );

        $decoded = self::$codec->decode(self::$codec->encode($user), User::class);

        $this->assertEquals($user->createdAt->format('U.u'), $decoded->createdAt->format('U.u'));
        $decoded->createdAt = $user->createdAt;
        $this->assertEquals($user, $decoded);
    }

    public function test_defaults_and_optional_presence(): void
    {
        $decoded = self::$codec->decode(self::$codec->encode(new User(id: 0, name: '')), User::class);

        $this->assertNull($decoded->email);
        $this->assertNull($decoded->address);
        $this->assertNull($decoded->createdAt);
        $this->assertSame([], $decoded->tags);
        $this->assertSame(UserStatus::Active, $decoded->status);

        $withEmpty = self::$codec->decode(self::$codec->encode(new User(id: 1, name: 'x', email: '')), User::class);
        $this->assertSame('', $withEmpty->email);
    }

    public function test_known_wire_bytes(): void
    {
        // Ví dụ trong tài liệu protobuf: field 1 = 150 => 08 96 01
        $this->assertSame('089601', bin2hex(self::$codec->encode(new GetUserRequest(150))));
        $this->assertSame(150, self::$codec->decode(hex2bin('089601'), GetUserRequest::class)->id);
    }

    public function test_readonly_dto_and_array_helpers(): void
    {
        $user = self::$codec->fromArray(['id' => 5, 'name' => 'A', 'address' => ['city' => 'HN']], User::class);
        $this->assertSame('HN', $user->address->city);
        $this->assertSame(5, self::$codec->toArray($user)['id']);

        $request = self::$codec->decode(self::$codec->encode(new GetUserRequest(7)), GetUserRequest::class);
        $this->assertSame(7, $request->id);
    }
}
