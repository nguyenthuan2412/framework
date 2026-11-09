<?php

namespace Ryra\Core\Grpc\Tests;

use Google\Protobuf\Timestamp;
use PHPUnit\Framework\TestCase;
use Ryra\Core\Grpc\Attributes\Field;
use Ryra\Core\Grpc\Exception\MetadataException;
use Ryra\Core\Grpc\Metadata\MetadataFactory;
use Ryra\Core\Grpc\Protobuf\Codec;
use Ryra\Core\Grpc\Protobuf\Frame;
use Ryra\Core\Grpc\Server\GrpcKernel;
use Ryra\Core\Grpc\Server\ServerContext;
use Ryra\Core\Grpc\Tests\Fixtures\UserService;

class FrameAndMetadataTest extends TestCase
{
    public function test_frame_pack_and_unpack(): void
    {
        $body = Frame::pack('abc') . Frame::pack('', false) . Frame::pack('zip me', true);
        $this->assertSame("\x00\x00\x00\x00\x03abc", substr($body, 0, 8));
        $this->assertSame(['abc', '', 'zip me'], Frame::unpackAll($body, 'gzip'));
    }

    public function test_service_metadata(): void
    {
        $service = MetadataFactory::forService(UserService::class);

        $this->assertSame('user.v1.UserService', $service->fullName());
        $this->assertSame('/user.v1.UserService/GetUser', $service->path($service->methods['GetUser']));
        $this->assertSame(1, $service->methods['GetUser']->contextPosition);
        $this->assertTrue($service->methods['ListUsers']->serverStreaming);
        $this->assertTrue($service->methods['ImportUsers']->clientStreaming);
        $this->assertTrue($service->methods['Ping']->returnsVoid);
    }

    public function test_array_property_requires_element_type(): void
    {
        $class = new class { public array $items = []; };
        $this->expectException(MetadataException::class);
        $this->expectExceptionMessage('needs #[Field(type: ...)]');
        MetadataFactory::forMessage($class::class);
    }

    public function test_duplicate_field_number(): void
    {
        $class = new class {
            #[Field(1)] public string $a = '';
            #[Field(1)] public string $b = '';
        };
        $this->expectException(MetadataException::class);
        $this->expectExceptionMessage('Duplicate field number 1');
        MetadataFactory::forMessage($class::class);
    }

    public function test_native_protoc_classes_pass_through(): void
    {
        $codec = new Codec();
        $timestamp = new Timestamp(['seconds' => 1700000000, 'nanos' => 5]);

        $decoded = $codec->decode($codec->encode($timestamp), Timestamp::class);
        $this->assertSame(1700000000, (int) $decoded->getSeconds());
        $this->assertSame(5, $decoded->getNanos());
    }

    public function test_generator_skips_services_built_from_proto_files(): void
    {
        $service = new #[\Ryra\Core\Grpc\Attributes\Service(package: 'ext.v1', name: 'Clock')] class {
            public function now(\Google\Protobuf\GPBEmpty $request): Timestamp
            {
                return new Timestamp();
            }
        };

        $this->assertSame([], (new \Ryra\Core\Grpc\ProtoGenerator())->generate([MetadataFactory::forService($service::class)]));
    }

    public function test_camel_case_matches_protoc(): void
    {
        $this->assertSame('CreatedAt', Codec::camel('created_at'));
        $this->assertSame('Address2Line', Codec::camel('address2_line'));
        $this->assertSame('Id', Codec::camel('id'));
    }

    public function test_grpc_message_and_timeout_encoding(): void
    {
        $this->assertSame('Kh%C3%B4ng t%C3%ACm th%E1%BA%A5y 100%25', GrpcKernel::encodeMessage('Không tìm thấy 100%'));
        $this->assertEqualsWithDelta(microtime(true) + 1.5, ServerContext::parseTimeout('1500m'), 0.05);
        $this->assertNull(ServerContext::parseTimeout('abc'));
    }
}
