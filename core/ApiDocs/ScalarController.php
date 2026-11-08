<?php

namespace Ryra\Core\ApiDocs;

use Ryra\Core\Contracts\Config\Repository;
use Ryra\Core\Route\Attributes\Group;
use Ryra\Core\Route\Attributes\Get;
use Ryra\Core\Http\Response;
use Ryra\Core\App;

#[Group(
    prefix: 'api-docs',
    middlewares: [BasicAuth::class]
)]
class ScalarController
{
    public function __construct(
        protected OpenApiGenerator $openApiGenerator,
        protected Repository $config
    ) {}

    /**
     * Serve Scalar UI HTML
     */
    #[Get(
        uri: '',
        summary: 'Scalar API documentation'
    )]
    public function index(): Response
    {
        $html = $this->getScalarUIHtml();
        return Response::html($html);
    }

    /**
     * Serve OpenAPI JSON specification
     */
    #[Get(uri: 'openapi.json', summary: 'OpenAPI JSON specification')]
    public function json(): Response
    {
        $controllers = $this->getControllers();
        $spec = $this->openApiGenerator->generate($controllers);

        return Response::json($spec);
    }

    /**
     * Get all controller classes
     */
    protected function getControllers(): array
    {
        $controllers = [];
        $modulesPath = join_paths(App::root(), 'modules');

        if (!is_dir($modulesPath)) {
            return $controllers;
        }

        // Scan all modules
        $modules = scandir($modulesPath);
        foreach ($modules as $module) {
            if ($module === '.' || $module === '..') {
                continue;
            }

            $controllerPath = "{$modulesPath}/{$module}/src/Controllers";
            if (!is_dir($controllerPath)) {
                continue;
            }

            $files = glob("{$controllerPath}/*.php");
            foreach ($files as $file) {
                $className = $this->getClassNameFromFile($file, $module);
                if ($className && class_exists($className)) {
                    $controllers[] = $className;
                }
            }
        }

        return $controllers;
    }

    /**
     * Get class name from file path
     */
    protected function getClassNameFromFile(string $file, string $module): ?string
    {
        $filename = basename($file, '.php');

        // Convert snake_case to PascalCase (e.g., mail_template -> MailTemplate)
        $moduleName = str_replace(' ', '', ucwords(str_replace('_', ' ', $module)));

        return "Ryra\\Modules\\" . $moduleName . "\\Controllers\\" . $filename;
    }

    /**
     * Get Scalar UI HTML
     */
    protected function getScalarUIHtml(): string
    {
        return '
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="description" content="Scalar API Reference" />
    <title>'.$this->config->get('api_docs.title').'</title>
    <style>
        body {
            margin: 0;
            padding: 0;
        }
    </style>
</head>
<body>
    <script
        id="api-reference"
        data-url="/api-docs/openapi.json"
        data-configuration=\'{"theme":"purple","layout":"modern","showSidebar":true,"darkMode":true,"orderSchemaPropertiesBy":"preserve","orderRequiredPropertiesFirst":false}\'
    ></script>
    <script src="https://cdn.jsdelivr.net/npm/@scalar/api-reference"></script>
</body>
</html>
';
    }
}
