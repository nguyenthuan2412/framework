<?php

namespace Ryra\Core\Grpc\Tests;

use PHPUnit\Framework\TestCase;
use Ryra\Core\Grpc\Attributes\Field;
use Ryra\Core\Grpc\Attributes\Message;
use Ryra\Core\Grpc\Exception\DecodeException;
use Ryra\Core\Grpc\Protobuf\Codec;
use Ryra\Core\Grpc\Protobuf\Type;
use Ryra\Core\Grpc\Protobuf\Wire;
use Ryra\Core\Grpc\Tests\Fixtures\Address;
use Ryra\Core\Grpc\Tests\Fixtures\User;

#[Message(package: 'wiretest.v1')]
final class WireNode
{
    public function __construct(
        #[Field(1)] public string $name = '',
        #[Field(2)] public ?WireNode $child = null,
    ) {}
}

#[Message(package: 'wiretest.v1')]
final class WireNumbers
{
    public function __construct(
        #[Field(1, type: Type::Uint32)] public int $u32 = 0,
        #[Field(2, type: Type::Int32)] public int $i32 = 0,
        #[Field(3, type: Type::Sint64)] public int $s64 = 0,
        #[Field(4, type: Type::Fixed32)] public int $f32 = 0,
        #[Field(5, type: Type::Sint32)] public array $packed = [],
    ) {}
}

/**
 * Encoder/decoder wire format do WireCompiler sinh ra (không cần class protoc).
 */
class WireCodecTest extends TestCase
{
    public function test_varint_matches_spec(): void
    {
        $this->assertSame('01', \bin2hex(Wire::varint(1)));
        $this->assertSame('9601', \bin2hex(Wire::varint(150)));
        $this->assertSame('ffffffffffffffffff01', \bin2hex(Wire::varint(-1)));
        $this->assertSame('ffffffffffffffff7f', \bin2hex(Wire::varint(\PHP_INT_MAX)));

        foreach ([0, 1, 127, 128, 16383, 16384, -1, \PHP_INT_MIN, \PHP_INT_MAX] as $value) {
            $offset = 0;
            $this->assertSame($value, Wire::readVarint(Wire::varint($value), $offset));
        }
    }

    public function test_numbers_round_trip_without_protoc_classes(): void
    {
        $codec = new Codec();
        $message = new WireNumbers(u32: 4294967295, i32: -2147483648, s64: \PHP_INT_MIN, f32: 3000000000, packed: [0, -1, 1, 2147483647, -2147483648]);

        $this->assertEquals($message, $codec->decode($codec->encode($message), WireNumbers::class));
        // packed sint32 [-1, 1] => tag 0x2a, len 2, zigzag 01 02
        $this->assertSame('2a020102', \bin2hex($codec->encode(new WireNumbers(packed: [-1, 1]))));
    }

    public function test_full_message_round_trip(): void
    {
        $codec = new Codec();
        $user = new User(
            id: 42, name: 'Thuận', email: '', counters: ['a' => -1],
            address: new Address('x', 'y'), history: [new Address(), new Address('c', 'd')],
            createdAt: new \DateTimeImmutable('2026-11-09 10:20:30.123456 UTC'),
        );

        $decoded = $codec->decode($codec->encode($user), User::class);

        $this->assertSame($user->createdAt->format('U.u'), $decoded->createdAt->format('U.u'));
        $decoded->createdAt = $user->createdAt;
        $this->assertEquals($user, $decoded);
    }

    public function test_skips_unknown_fields(): void
    {
        $codec = new Codec();
        // field 99 (varint), field 98 (len "abc"), rồi street = "s"
        $bytes = Wire::tag(99, Wire::VARINT) . Wire::varint(5) . Wire::tag(98, Wire::LEN) . "\x03abc" . "\x0a\x01s";

        $this->assertSame('s', $codec->decode($bytes, Address::class)->street);
    }

    public function test_merges_repeated_occurrences_of_message_field(): void
    {
        $codec = new Codec();
        $bytes = "\x52" . "\x03\x0a\x01a" . "\x52" . "\x03\x12\x01b"; // address{street:a} + address{city:b}

        $address = $codec->decode($bytes, User::class)->address;
        $this->assertSame(['a', 'b'], [$address->street, $address->city]);
    }

    public function test_rejects_truncated_input(): void
    {
        $codec = new Codec();
        foreach (['0a', '0a05616263', '08', '29000000'] as $hex) {
            try {
                $codec->decode(\hex2bin($hex), User::class);
                $this->fail("Expected DecodeException for $hex");
            } catch (DecodeException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_limits_nesting_depth_of_recursive_messages(): void
    {
        $codec = new Codec();
        $tree = new WireNode('a', new WireNode('b', new WireNode('c')));
        $this->assertEquals($tree, $codec->decode($codec->encode($tree), WireNode::class));

        $deep = '';
        for ($i = 0; $i < 1000; $i++) {
            $deep = "\x12" . Wire::varint(\strlen($deep)) . $deep;
        }
        try {
            $codec->decode($deep, WireNode::class);
            $this->fail('Expected DecodeException');
        } catch (DecodeException $e) {
            $this->assertStringContainsString('nesting', $e->getMessage());
        }

        // Bộ đếm độ sâu được trả về đúng sau lỗi.
        $this->assertEquals($tree, $codec->decode($codec->encode($tree), WireNode::class));
    }

    public function test_cache_dir_writes_generated_code_to_files(): void
    {
        $dir = \sys_get_temp_dir() . '/ryra-grpc-test-' . \bin2hex(\random_bytes(4));
        try {
            $user = new User(
                id: 7, name: 'x', email: 'e', status: \Ryra\Core\Grpc\Tests\Fixtures\UserStatus::Banned,
                tags: ['a'], counters: ['k' => 1], address: new Address('s', 'c'), history: [new Address('h')],
                createdAt: new \DateTimeImmutable('@1767323045'),
            );
            $expected = (new Codec())->encode($user);

            $codec = new Codec($dir);
            $bytes = $codec->encode($user);
            $this->assertSame($expected, $bytes);
            $decoded = $codec->decode($bytes, User::class);
            $this->assertSame($user->createdAt->getTimestamp(), $decoded->createdAt->getTimestamp());
            $decoded->createdAt = $user->createdAt;
            $this->assertEquals($user, $decoded);

            // Encoder + decoder cho User và Address.
            $files = \glob("$dir/*.php");
            $this->assertCount(4, $files);

            // Worker khác dùng lại file đã sinh.
            $this->assertEquals($decoded, (new Codec($dir))->decode($bytes, User::class));
            $this->assertCount(4, \glob("$dir/*.php"));
        } finally {
            foreach (\glob("$dir/*") ?: [] as $file) {
                \unlink($file);
            }
            @\rmdir($dir);
        }
    }

    public function test_cache_dir_not_writable_falls_back_to_eval(): void
    {
        $file = \tempnam(\sys_get_temp_dir(), 'ryra-grpc');
        try {
            // Đường dẫn nằm dưới một file thường => không tạo được thư mục.
            $codec = new Codec($file . '/sub');
            $node = new WireNode('a', new WireNode('b'));
            $this->assertEquals($node, $codec->decode($codec->encode($node), WireNode::class));
        } finally {
            \unlink($file);
        }
    }
}
