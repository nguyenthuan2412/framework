<?php

namespace Ryra\Modules\Example\Controllers;

use Ryra\Core\Http\JsonResponse;
use Ryra\Core\Http\Response;
use Ryra\Core\Route\Attributes\Get;
use Ryra\Core\Route\Attributes\Group;
use Ryra\Core\Route\Attributes\Post;
use Ryra\Modules\Example\DTOs\GreetDTO;
use Ryra\Modules\Example\Services\GreetingService;

#[Group(prefix: 'example', name: 'example.')]
class ExampleController
{
    public function __construct(
        protected GreetingService $greeting,
    ) {}

    // GET /example — trả về trang HTML từ views/welcome.twig
    #[Get(uri: '/', name: 'index')]
    public function index(): Response
    {
        return Response::view('welcome.twig', ['title' => 'Ryra Example']);
    }

    // GET /example/hello/{name}
    #[Get(uri: 'hello/{name}', name: 'hello')]
    public function hello(string $name): JsonResponse
    {
        return Response::json(['message' => $this->greeting->greet($name)]);
    }

    // POST /example/greet  body: {"name": "Ryra"}
    #[Post(uri: 'greet', name: 'greet')]
    public function greet(GreetDTO $dto): JsonResponse
    {
        return Response::json(['message' => $this->greeting->greet($dto->name)]);
    }
}
