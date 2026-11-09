<?php

namespace Ryra\Core\Grpc\Tests;

use PHPUnit\Framework\TestCase;
use Ryra\Core\Grpc\Exception\MetadataException;
use Ryra\Core\Grpc\Metadata\MetadataFactory;
use Ryra\Core\Grpc\Proto\DtoGenerator;
use Ryra\Core\Grpc\Proto\ProtoParser;
use Ryra\Core\Grpc\ProtoGenerator;
use Ryra\Core\Grpc\Protobuf\Codec;
use Ryra\Core\Grpc\Protobuf\Wire;

/**
 * `grpc:dto`: .proto => DTO #[Message] / enum / interface #[Service].
 */
class ProtoDtoTest extends TestCase
{
    private const NS = 'RyraProtoDtoTest';

    private static string $output;

    private static array $files;

    public static function setUpBeforeClass(): void
    {
        self::$output = \sys_get_temp_dir() . '/ryra_proto_dto_' . \getmypid();
        self::$files = (new DtoGenerator(self::NS))->generate([__DIR__ . '/Fixtures/proto/user.proto']);

        foreach (self::$files as $file => $content) {
            @\mkdir(\dirname(self::$output . "/$file"), 0777, true);
            \file_put_contents(self::$output . "/$file", $content);
        }
        \spl_autoload_register(static function (string $class): void {
            if (\str_starts_with($class, self::NS . '\\')) {
                $file = self::$output . '/' . \str_replace('\\', '/', \substr($class, \strlen(self::NS) + 1)) . '.php';
                if (\is_file($file)) {
                    require $file;
                }
            }
        });
    }

    public function test_generates_classes_for_file_and_imports(): void
    {
        $this->assertSame([
            'Common/V1/Address.php',
            'Common/V1/Status.php',
            'User/V1/GetUserRequest.php',
            'User/V1/User.php',
            'User/V1/UserItem.php',
            'User/V1/UserRole.php',
            'User/V1/UserService.php',
        ], \array_keys(self::$files));

        $user = self::$files['User/V1/User.php'];
        $this->assertStringContainsString("#[Field(3)] public ?string \$email = null", $user);
        $this->assertStringContainsString("#[Field(4, type: Type::Sint32, deprecated: true)] public int \$score = 0", $user);
        $this->assertStringContainsString("#[Field(9, type: Type::Int64, key: Type::String)] public array \$counters = []", $user);
        $this->assertStringContainsString("public \\" . self::NS . "\\Common\\V1\\Status \$status = \\" . self::NS . "\\Common\\V1\\Status::Unspecified", $user);
        $this->assertStringContainsString("#[Field(22, name: 'userID')]", $user);
    }

    public function test_service_metadata_matches_proto(): void
    {
        $service = MetadataFactory::forService(self::NS . '\User\V1\UserService');

        $rpcs = [];
        foreach ($service->methods as $method) {
            $rpcs[$service->path($method)] = [$method->clientStreaming, $method->serverStreaming];
        }
        $this->assertSame([
            '/user.v1.UserService/GetUser' => [false, false],
            '/user.v1.UserService/ListUsers' => [false, true],
            '/user.v1.UserService/ImportUsers' => [true, false],
            '/user.v1.UserService/Chat' => [true, true],
            '/user.v1.UserService/Ping' => [false, false],
            '/user.v1.UserService/lowerName' => [false, false],
        ], $rpcs);

        $proto = (new ProtoGenerator())->generate([$service])['user.v1.proto'];
        $this->assertStringContainsString('rpc Chat(stream User) returns (stream User);', $proto);
        $this->assertStringContainsString('map<int32, UserItem> by_id = 18;', $proto);
    }

    public function test_generated_dto_round_trips_and_matches_wire_layout(): void
    {
        $user = self::NS . '\User\V1\User';
        $item = self::NS . '\User\V1\UserItem';
        $address = self::NS . '\Common\V1\Address';
        $status = self::NS . '\Common\V1\Status';

        $message = new $user(
            id: 7,
            fullName: 'Thuận',
            status: $status::InProgress,
            address: new $address('1 Lê Lợi', 'HCM'),
            primary: new $item('p', 2),
            byId: [5 => new $item('z', -1)],
            createdAt: new \DateTimeImmutable('2026-11-09 10:20:30.123456 UTC'),
        );

        $codec = new Codec();
        $bytes = $codec->encode($message);

        // id=7 (field 1), full_name (field 2), status=2 (field 7)
        $this->assertStringStartsWith("\x08\x07\x12\x07Thuận\x38\x02", $bytes);
        $this->assertStringContainsString(Wire::tag(17, Wire::LEN) . "\x05\x0a\x01p\x10\x02", $bytes);

        $decoded = $codec->decode($bytes, $user);
        $this->assertSame($message->createdAt->format('U.u'), $decoded->createdAt->format('U.u'));
        $decoded->createdAt = $message->createdAt;
        $this->assertEquals($message, $decoded);
    }

    public function test_parser_errors_point_to_location(): void
    {
        $cases = [
            "syntax = \"proto2\";" => 'Only proto3',
            "syntax = \"proto3\";\nmessage A {\n  int32 x = 1\n}" => 'input.proto:4: Expected [;]',
            "syntax = \"proto3\"; message A { extensions 1 to 2; }" => 'not supported',
        ];
        foreach ($cases as $source => $error) {
            try {
                (new ProtoParser())->parse($source);
                $this->fail("Expected error for: $source");
            } catch (MetadataException $e) {
                $this->assertStringContainsString($error, $e->getMessage());
            }
        }
    }

    public function test_unsupported_well_known_type(): void
    {
        $file = \sys_get_temp_dir() . '/ryra_proto_dto_duration.proto';
        \file_put_contents($file, 'syntax = "proto3"; import "google/protobuf/duration.proto"; message A { google.protobuf.Duration d = 1; }');

        $this->expectException(MetadataException::class);
        $this->expectExceptionMessage('google.protobuf.Duration');
        (new DtoGenerator('X'))->generate([$file]);
    }
}
