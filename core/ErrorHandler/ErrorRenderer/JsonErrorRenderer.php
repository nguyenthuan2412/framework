<?php

namespace Ryra\Core\ErrorHandler\ErrorRenderer;

use Ryra\Core\Coroutine\Exception\BatchException;
use Ryra\Core\Http\Response;
use Throwable;

class JsonErrorRenderer
{
    public static function render(Throwable $exception): Response
    {
        return Response::json(static::format($exception))
            ->setStatusCode(500)
            ->setEncodingOptions(JSON_PRETTY_PRINT);
    }

    private static function format(Throwable $exception): array
    {
        $data = [
            'message'   => $exception->getMessage(),
            'exception' => get_class($exception),
            'file'      => $exception->getFile(),
            'line'      => $exception->getLine(),
            'trace'     => static::formatTrace($exception),
        ];

        if ($exception instanceof BatchException) {
            $data['coroutine_errors'] = array_map(
                static fn (Throwable $e) => static::format($e),
                $exception->getExceptions()
            );
        }

        return $data;
    }

    /**
     * Chuẩn hoá stack trace để luôn json_encode được.
     *
     * `getTrace()` giữ cả `args` của mỗi frame — có thể chứa resource/Closure/
     * object (VD socket khi gửi mail), khiến json_encode chết với lỗi
     * "Type is not supported". Bỏ `args` đi, chỉ giữ file/line/function/class.
     */
    private static function formatTrace(Throwable $exception): array
    {
        return array_map(static function (array $frame): array {
            unset($frame['args']);
            return $frame;
        }, $exception->getTrace());
    }
}