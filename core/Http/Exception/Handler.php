<?php

namespace Ryra\Core\Http\Exception;

use Ryra\Core\ErrorHandler\ErrorRenderer\HtmlErrorRenderer;
use Ryra\Core\ErrorHandler\ErrorRenderer\JsonErrorRenderer;
use Ryra\Core\ErrorHandler\Exception\DumperException;
use Ryra\Core\Coroutine\Exception\BatchException;
use Ryra\Core\ValidatedDTO\ValidationException;
use Ryra\Core\Http\Response;
use Ryra\Core\Http\Request;
use Ryra\Core\Log\Log;
use Ryra\Core\App;
use Throwable;

class Handler
{
    public function __construct(protected App $app) {}

    public function render(Request $request, Throwable $th): Response
    {
        try {
            $this->logException($th);

            if ($th instanceof HttpException) {
                if ($request->wantsJson()) {
                    return Response::json([
                        'message' => $th->getMessage(),
                    ])->setStatusCode($th->getStatusCode());
                }
    
                return Response::view(
                    '@http_exception/http_exception.twig', [
                    'status' => $th->getStatusCode(),
                    'message' => $th->getMessage(),
                ])->setStatusCode($th->getStatusCode());
            }
    
            if ($th instanceof ValidationException) {
                return $th->getDTO()::failedValidation($request, $th);
            }

            return $this->handlerServerError($request, $th);
        } catch (Throwable $th) {
            Log::error($th);
            return $this->handlerServerError($request, $th);
        }
    }

    protected function handlerServerError(Request $request, Throwable $th): Response
    {
        if (config('app.debug')) {
            if ($request->wantsJson() && !($th instanceof DumperException)) {
                return JsonErrorRenderer::render($th);
            }

            return HtmlErrorRenderer::render($th);
        }

        if ($request->wantsJson()) {
            return Response::json([
                'message' => 'Server Error',
            ])->setStatusCode(500);
        }

        return Response::view('@http_exception/http_exception.twig', [
            'status' => 500,
            'message' => 'Server Error'
        ])->setStatusCode(500);
    }

    protected function logException(Throwable $th): void
    {
        Log::error($th);
        if ($th instanceof BatchException) {
            foreach ($th->getExceptions() as $key => $e) {
                Log::error($e, ['batch_key' => $key]);
            }
        }
    }
}