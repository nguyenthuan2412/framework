<?php

namespace Ryra\Modules\Bench\Controllers;

use Ryra\Core\Database\DB;
use Ryra\Core\Http\JsonResponse;
use Ryra\Core\Http\Response;
use Ryra\Core\Route\Attributes\Get;

class BenchController
{
    private const SQL = 'SELECT id, randomNumber FROM world WHERE id = ?';

    #[Get(uri: 'plaintext')]
    public function plaintext(): Response
    {
        return Response::text('Hello, World!');
    }

    #[Get(uri: 'json')]
    public function json(): JsonResponse
    {
        return Response::json(['message' => 'Hello, World!']);
    }

    #[Get(uri: 'db')]
    public function db(): JsonResponse
    {
        return Response::json(DB::connection()->selectOne(self::SQL, [\mt_rand(1, 10000)]));
    }

    // 20 query chạy tuần tự (Node cũng chạy tuần tự) để so cùng một kiểu truy cập
    #[Get(uri: 'queries')]
    public function queries(): JsonResponse
    {
        $conn = DB::connection();
        $rows = [];
        for ($i = 0; $i < 20; $i++) {
            $rows[] = $conn->selectOne(self::SQL, [\mt_rand(1, 10000)]);
        }
        return Response::json($rows);
    }
}
