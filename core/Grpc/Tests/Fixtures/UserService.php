<?php

namespace Ryra\Core\Grpc\Tests\Fixtures;

use Ryra\Core\Grpc\Attributes\Rpc;
use Ryra\Core\Grpc\Attributes\Service;
use Ryra\Core\Grpc\Exception\GrpcException;
use Ryra\Core\Grpc\Server\ServerContext;

#[Service(package: 'user.v1', comment: 'Quản lý người dùng')]
final class UserService
{
    public function getUser(GetUserRequest $request, ServerContext $context): User
    {
        if ($request->id === 404) {
            throw GrpcException::notFound('User not found');
        }
        $context->setTrailer('x-handled-by', 'ryra');
        return new User(id: $request->id, name: 'User ' . $request->id, tags: ['a', 'b']);
    }

    #[Rpc(response: User::class)]
    public function listUsers(GetUserRequest $request): \Generator
    {
        for ($i = 1; $i <= $request->id; $i++) {
            yield new User(id: $i, name: "User $i");
        }
    }

    #[Rpc(request: User::class)]
    public function importUsers(iterable $users): GetUserRequest
    {
        $count = 0;
        foreach ($users as $user) {
            $count++;
        }
        return new GetUserRequest($count);
    }

    public function ping(): void
    {
    }
}
