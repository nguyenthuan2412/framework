<?php

namespace Ryra\Core\Asset;

use Ryra\Core\Http\BinaryFileResponse;
use Ryra\Core\Http\Exception\NotFoundHttpException;
use Ryra\Core\Http\Response;
use Ryra\Core\Http\ResponseHeaderBag;
use Ryra\Core\Module\ModuleManager;
use Ryra\Core\Route\Attributes\Get;
use Ryra\Core\Session\Middleware\StartSession;

class AssetController
{
    public function __construct(protected ModuleManager $moduleManager) {}

    #[Get(
        uri: 'assets/{module}/{dirFile:.*}',
        excludedMiddlewares: [
            StartSession::class
        ]
    )]
    public function register(string $module, string $dirFile): BinaryFileResponse
    {
        if (!$this->moduleManager->has($module)) {
            throw new NotFoundHttpException();
        }

        $module = $this->moduleManager->get($module);
        $file = "{$module->getPath()}/assets/$dirFile";
        if (!is_file($file) || strpos($dirFile, '/..') !== false) {
            throw new NotFoundHttpException();
        }

        return Response::download($file)
            ->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE);
    }
}