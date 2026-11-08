<?php

namespace Ryra\Core\ApiDocs;

use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;
use Ryra\Core\Route\Attributes\Delete;
use Ryra\Core\Route\Attributes\Get;
use Ryra\Core\Route\Attributes\Group;
use Ryra\Core\Route\Attributes\Patch;
use Ryra\Core\Route\Attributes\Post;
use Ryra\Core\Route\Attributes\Put;
use Ryra\Core\BaseDTO\BaseDTO;
use Ryra\Core\ApiDocs\Attributes\ApiParameter;
use Ryra\Core\ApiDocs\Attributes\ApiResponse;
use Ryra\Core\ApiDocs\Attributes\ApiTag;
use Ryra\Core\ApiDocs\Attributes\Description;
use Ryra\Core\ValidatedDTO\ValidatedDTO;
use Ryra\Core\ValidatedDTO\Rules\TypeInteger;
use Ryra\Core\ValidatedDTO\Rules\TypeFloat;
use Ryra\Core\ValidatedDTO\Rules\TypeString;
use Ryra\Core\ValidatedDTO\Rules\TypeBoolean;
use Ryra\Core\ValidatedDTO\Rules\TypeEnum;
use Ryra\Core\ValidatedDTO\Rules\TypeList;
use Ryra\Core\ValidatedDTO\Rules\TypeAssociativeArray;
use Ryra\Core\ValidatedDTO\Rules\Min;
use Ryra\Core\ValidatedDTO\Rules\Max;
use Ryra\Core\ValidatedDTO\Rules\In;
use Ryra\Core\ValidatedDTO\Rules\Required;
use Ryra\Core\ValidatedDTO\Rules\Nullable;
use Ryra\Core\ValidatedDTO\Rules\Date;
use Ryra\Core\ValidatedDTO\Rules\File;
use Ryra\Core\ValidatedDTO\Rules\UploadedFile;
use Ryra\Core\ValidatedDTO\Rules\Image;
use Ryra\Core\ValidatedDTO\Rules\Regex;

class OpenApiGenerator
{
    protected array $paths = [];
    protected array $schemas = [];
    protected array $tags = [];
    protected array $tagGroups = [];
    protected array $scannedDTOs = [];
    protected ?array $cachedSpec = null;

    public function __construct(
        protected string $title = 'API Documentation',
        protected string $version = '1.0.0',
        protected string $description = 'API documentation generated from attributes',
        protected array $servers = []
    ) {
        if (empty($this->servers)) {
            $this->servers = [
                ['url' => 'http://localhost', 'description' => 'Local server']
            ];
        }
    }

    /**
     * Scan controllers and generate OpenAPI specification
     */
    public function generate(array $controllerClasses): array
    {
        if ($this->cachedSpec !== null) {
            return $this->cachedSpec;
        }

        foreach ($controllerClasses as $controllerClass) {
            if (!class_exists($controllerClass)) {
                continue;
            }

            $this->scanController($controllerClass);
        }

        $this->cachedSpec = $this->buildOpenApiSpec();

        return $this->cachedSpec;
    }

    /**
     * Scan a controller class
     */
    protected function scanController(string $controllerClass): void
    {
        $reflection = new ReflectionClass($controllerClass);
        $groupAttributes = $reflection->getAttributes(Group::class);
        $tagAttributes = $reflection->getAttributes(ApiTag::class);

        $groupPrefix = '';
        $groupMiddlewares = [];

        // Get group prefix and middlewares
        if (!empty($groupAttributes)) {
            $group = $groupAttributes[0]->newInstance();
            $groupPrefix = $group->getPrefix();
            $groupMiddlewares = $group->getMiddlewares();
        }

        // Extract module name from namespace (Ryra\Modules\{ModuleName}\Controllers\...)
        $namespaceParts = explode('\\', $controllerClass);
        $moduleName = null;
        if (count($namespaceParts) >= 3 && $namespaceParts[0] === 'Ryra' && $namespaceParts[1] === 'Modules') {
            $moduleName = $namespaceParts[2];
        }

        // Get tags
        $tags = [];
        if (!empty($tagAttributes)) {
            foreach ($tagAttributes as $tagAttr) {
                $tag = $tagAttr->newInstance();
                $tags[] = $tag->getName();

                // Add to global tags
                if (!isset($this->tags[$tag->getName()])) {
                    $this->tags[$tag->getName()] = [
                        'name' => $tag->getName(),
                        'description' => $tag->getDescription(),
                    ];
                }
            }
        }

        // If no tags, use controller name
        if (empty($tags)) {
            $shortName = $reflection->getShortName();
            $tagName = str_replace('Controller', '', $shortName);
            $tags[] = $tagName;

            if (!isset($this->tags[$tagName])) {
                $this->tags[$tagName] = [
                    'name' => $tagName,
                    'description' => isset($group) ? ($group->getDescription() ?? "{$tagName} operations") : "{$tagName} operations",
                ];
            }
        }

        // Track tag groups by module
        if ($moduleName) {
            if (!isset($this->tagGroups[$moduleName])) {
                $this->tagGroups[$moduleName] = [];
            }
            foreach ($tags as $tag) {
                if (!in_array($tag, $this->tagGroups[$moduleName])) {
                    $this->tagGroups[$moduleName][] = $tag;
                }
            }
        }

        // Scan methods
        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->class !== $controllerClass) {
                continue;
            }

            $this->scanMethod($method, $groupPrefix, $tags, $groupMiddlewares);
        }
    }

    /**
     * Scan a controller method
     */
    protected function scanMethod(
        ReflectionMethod $method,
        string $groupPrefix,
        array $tags,
        array $groupMiddlewares
    ): void {
        $routeAttributes = array_merge(
            $method->getAttributes(Get::class),
            $method->getAttributes(Post::class),
            $method->getAttributes(Put::class),
            $method->getAttributes(Patch::class),
            $method->getAttributes(Delete::class)
        );

        if (empty($routeAttributes)) {
            return;
        }

        foreach ($routeAttributes as $routeAttr) {
            $route = $routeAttr->newInstance();
            $httpMethod = strtolower($route->getMethod());
            $path = $groupPrefix . $route->getUri();

            // Convert route parameters from {id:\d+} to {id}
            $path = preg_replace('/\{([^:}]+):[^}]+\}/', '{$1}', $path);

            if (!isset($this->paths[$path])) {
                $this->paths[$path] = [];
            }

            // Build operation
            $operation = [
                'tags' => $tags,
                'summary' => $route->getSummary() ?? $route->getDescription() ?? $method->getName(),
                'operationId' => $method->getDeclaringClass()->getShortName() . '.' . $method->getName(),
            ];

            if ($route->getDescription()) {
                $operation['description'] = $route->getDescription();
            }

            if ($route->isDeprecated()) {
                $operation['deprecated'] = true;
            }

            // Add parameters
            $operation['parameters'] = $this->extractParameters($path, $method);

            // Add request body
            $requestBody = $this->extractRequestBody($method);
            if ($requestBody) {
                $operation['requestBody'] = $requestBody;
            }

            // Add responses
            $operation['responses'] = $this->extractResponses($method, $route);

            // Add security if middleware present
            $middlewares = array_merge($groupMiddlewares, $route->getMiddlewares());
            if (!empty($middlewares)) {
                $operation['security'] = $this->extractSecurity($middlewares);
            }

            $this->paths[$path][$httpMethod] = $operation;
        }
    }

    /**
     * Extract path and query parameters
     */
    protected function extractParameters(string $path, ReflectionMethod $method): array
    {
        $parameters = [];

        // Check for ApiParameter attributes first
        $parameterAttrs = $method->getAttributes(ApiParameter::class);
        if (!empty($parameterAttrs)) {
            foreach ($parameterAttrs as $paramAttr) {
                $param = $paramAttr->newInstance();
                $paramDef = [
                    'name' => $param->getName(),
                    'in' => $param->getIn(),
                    'required' => $param->isRequired(),
                    'schema' => $param->getSchema(),
                ];

                if ($param->getDescription()) {
                    $paramDef['description'] = $param->getDescription();
                }

                if ($param->getExample() !== null) {
                    $paramDef['example'] = $param->getExample();
                }

                $parameters[] = $paramDef;
            }
        } else {
            // Auto-detect path parameters
            if (preg_match_all('/\{([^}]+)\}/', $path, $matches)) {
                foreach ($matches[1] as $paramName) {
                    $parameters[] = [
                        'name' => $paramName,
                        'in' => 'path',
                        'required' => true,
                        'schema' => ['type' => 'string'],
                    ];
                }
            }

            // Check for Request parameter to extract query parameters
            foreach ($method->getParameters() as $param) {
                $type = $param->getType();
                if ($type instanceof ReflectionNamedType && $type->getName() === 'Ryra\Core\Http\Request') {
                    // Add common query parameters
                    $parameters[] = [
                        'name' => 'lang',
                        'in' => 'header',
                        'description' => 'Preferred language (vi, en)',
                        'required' => false,
                        'schema' => ['type' => 'string', 'enum' => ['vi', 'en']],
                    ];
                }
            }
        }

        return $parameters;
    }

    /**
     * Extract request body from method parameters
     */
    protected function extractRequestBody(ReflectionMethod $method): ?array
    {
        // Auto-detect from DTO parameters
        foreach ($method->getParameters() as $param) {
            $type = $param->getType();
            if (!$type instanceof ReflectionNamedType) {
                continue;
            }

            $typeName = $type->getName();
            if (!class_exists($typeName)) {
                continue;
            }

            // Check if it's a DTO
            if (is_subclass_of($typeName, ValidatedDTO::class) ||
                is_subclass_of($typeName, BaseDTO::class) ||
                str_ends_with($typeName, 'DTO')) {

                $schemaName = $this->getSchemaName($typeName);
                $this->scanDTO($typeName);

                return [
                    'required' => !$type->allowsNull(),
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => "#/components/schemas/{$schemaName}"],
                        ],
                    ],
                ];
            }
        }

        return null;
    }

    /**
     * Extract responses
     */
    protected function extractResponses(ReflectionMethod $method, Get $route): array
    {
        $responses = [];

        // Check for responses in route attribute first
        $routeResponses = $route->getResponses();
        if (!empty($routeResponses)) {
            foreach ($routeResponses as $statusCode => $schemaOrDto) {
                $responses[$statusCode] = [
                    'description' => $this->getDefaultDescriptionForStatusCode($statusCode),
                ];

                // Check if it's a DTO class
                if (is_string($schemaOrDto) && class_exists($schemaOrDto)) {
                    $this->scanDTO($schemaOrDto);
                    $schemaName = $this->getSchemaName($schemaOrDto);
                    $responses[$statusCode]['content'] = [
                        'application/json' => [
                            'schema' => ['$ref' => "#/components/schemas/{$schemaName}"],
                        ],
                    ];
                } elseif (is_array($schemaOrDto)) {
                    // It's an inline schema
                    $responses[$statusCode]['content'] = [
                        'application/json' => [
                            'schema' => $schemaOrDto,
                        ],
                    ];
                }
            }
        }

        // Check for custom ApiResponse attributes (can override or add more)
        $responseAttrs = $method->getAttributes(ApiResponse::class);
        if (!empty($responseAttrs)) {
            foreach ($responseAttrs as $responseAttr) {
                $response = $responseAttr->newInstance();
                $responses[$response->getStatusCode()] = [
                    'description' => $response->getDescription(),
                ];

                if ($response->getSchema()) {
                    $responses[$response->getStatusCode()]['content'] = [
                        'application/json' => [
                            'schema' => $response->getSchema(),
                        ],
                    ];
                }
            }
        }

        // Add default responses if none specified
        if (empty($responses)) {
            $responses['200'] = [
                'description' => 'Successful response',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'message' => ['type' => 'string'],
                            ],
                        ],
                    ],
                ],
            ];

            $responses['400'] = [
                'description' => 'Bad request',
            ];

            $responses['500'] = [
                'description' => 'Internal server error',
            ];
        }

        return $responses;
    }

    /**
     * Get default description for HTTP status code
     */
    protected function getDefaultDescriptionForStatusCode(int $statusCode): string
    {
        return match ($statusCode) {
            200 => 'Successful response',
            201 => 'Created successfully',
            204 => 'No content',
            400 => 'Bad request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not found',
            422 => 'Validation error',
            500 => 'Internal server error',
            default => 'Response',
        };
    }

    /**
     * Extract security requirements
     */
    protected function extractSecurity(array $middlewares): array
    {
        $security = [];

        foreach ($middlewares as $middleware) {
            if (str_contains($middleware, 'Auth')) {
                $security[] = ['bearerAuth' => []];
                break;
            }
        }

        return $security;
    }

    /**
     * Scan DTO class and generate schema
     */
    protected function scanDTO(string $dtoClass): void
    {
        if (isset($this->scannedDTOs[$dtoClass])) {
            return;
        }

        $this->scannedDTOs[$dtoClass] = true;
        $reflection = new ReflectionClass($dtoClass);
        $properties = [];
        $required = [];
        $order = 0;

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $propertyName = $property->getName();
            $propertySchema = $this->getPropertySchema($property);

            // Add x-order to maintain property order in Swagger UI
            $propertySchema['x-order'] = $order++;

            $properties[$propertyName] = $propertySchema;

            // Check if required
            // 1. Check for Required attribute
            // 2. Fallback to type hint (not nullable)
            if ($this->isPropertyRequired($property)) {
                $required[] = $propertyName;
            }
        }

        $schemaName = $this->getSchemaName($dtoClass);
        $this->schemas[$schemaName] = [
            'type' => 'object',
            'properties' => $properties,
        ];

        if (!empty($required)) {
            $this->schemas[$schemaName]['required'] = $required;
        }
    }

    /**
     * Get property schema from reflection
     */
    protected function getPropertySchema(ReflectionProperty $property): array
    {
        $schema = [];

        // Get validation attributes
        $attributes = $property->getAttributes();

        // Try to get type from validation rules first
        $typeFromRules = $this->getTypeFromValidationRules($attributes);
        if ($typeFromRules) {
            $schema = array_merge($schema, $typeFromRules);
        }

        // If no type from rules, fallback to PHP type hints
        if (empty($schema['type']) && empty($schema['$ref'])) {
            $type = $property->getType();
            if ($type instanceof ReflectionNamedType) {
                $typeName = $type->getName();

                // Check if type is a DTO class
                if (class_exists($typeName) &&
                    (is_subclass_of($typeName, ValidatedDTO::class) ||
                     is_subclass_of($typeName, BaseDTO::class))) {
                    // Scan the nested DTO
                    $this->scanDTO($typeName);
                    $schemaName = $this->getSchemaName($typeName);
                    return ['$ref' => "#/components/schemas/{$schemaName}"];
                }

                $schema = match ($typeName) {
                    'int' => ['type' => 'integer'],
                    'float' => ['type' => 'number', 'format' => 'float'],
                    'bool' => ['type' => 'boolean'],
                    'array' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'string' => ['type' => 'string'],
                    default => ['type' => 'string'],
                };
            } else {
                $schema['type'] = 'string';
            }
        }

        // Add constraints from validation rules
        $constraints = $this->getConstraintsFromValidationRules($attributes);
        $schema = array_merge($schema, $constraints);

        // Add description from Description attribute
        foreach ($attributes as $attribute) {
            if ($attribute->getName() === Description::class) {
                $instance = $attribute->newInstance();
                $schema['description'] = (string) $instance;
                break;
            }
        }

        // Add default value if property has one
        if ($property->hasDefaultValue()) {
            $defaultValue = $property->getDefaultValue();
            if ($defaultValue !== null) {
                $schema['default'] = $defaultValue;
            }
        }

        return $schema;
    }

    /**
     * Get OpenAPI type from validation rules
     */
    protected function getTypeFromValidationRules(array $attributes): ?array
    {
        foreach ($attributes as $attribute) {
            $attributeName = $attribute->getName();

            // Handle TypeInteger
            if ($attributeName === TypeInteger::class) {
                return ['type' => 'integer'];
            }

            // Handle TypeFloat
            if ($attributeName === TypeFloat::class) {
                return ['type' => 'number', 'format' => 'float'];
            }

            // Handle TypeString
            if ($attributeName === TypeString::class) {
                return ['type' => 'string'];
            }

            // Handle TypeBoolean
            if ($attributeName === TypeBoolean::class) {
                return ['type' => 'boolean'];
            }

            // Handle TypeEnum
            if ($attributeName === TypeEnum::class) {
                $instance = $attribute->newInstance();
                return $this->getEnumSchema($instance);
            }

            // Handle TypeList
            if ($attributeName === TypeList::class) {
                $instance = $attribute->newInstance();
                $ruleOrSchema = $instance->ruleOrSchema;

                // Check if ruleOrSchema is a DTO class
                if ($ruleOrSchema && class_exists($ruleOrSchema) &&
                    (is_subclass_of($ruleOrSchema, ValidatedDTO::class) ||
                     is_subclass_of($ruleOrSchema, BaseDTO::class))) {
                    // Scan the nested DTO
                    $this->scanDTO($ruleOrSchema);
                    $schemaName = $this->getSchemaName($ruleOrSchema);

                    return [
                        'type' => 'array',
                        'items' => ['$ref' => "#/components/schemas/{$schemaName}"]
                    ];
                }

                // Fallback to simple array
                return ['type' => 'array', 'items' => ['type' => 'string']];
            }

            // Handle TypeAssociativeArray
            if ($attributeName === TypeAssociativeArray::class) {
                $instance = $attribute->newInstance();
                $schemaValidate = $instance->schemaValidate;

                // Check if schemaValidate is a DTO class
                if ($schemaValidate && class_exists($schemaValidate) &&
                    (is_subclass_of($schemaValidate, ValidatedDTO::class) ||
                     is_subclass_of($schemaValidate, BaseDTO::class))) {
                    // Scan the nested DTO
                    $this->scanDTO($schemaValidate);
                    $schemaName = $this->getSchemaName($schemaValidate);

                    return [
                        'type' => 'object',
                        'additionalProperties' => ['$ref' => "#/components/schemas/{$schemaName}"]
                    ];
                }

                // Fallback to simple object
                return ['type' => 'object'];
            }

            // Handle Date
            if ($attributeName === Date::class) {
                $instance = $attribute->newInstance();
                $reflection = new \ReflectionClass($instance);
                $formatProperty = $reflection->getProperty('format');
                $formatProperty->setAccessible(true);
                $format = $formatProperty->getValue($instance);

                // Remove PHP date format modifiers (!, |, etc.) for cleaner display
                $cleanFormat = $this->cleanDateFormat($format);

                // Convert PHP format to common format (dd/MM/yyyy)
                $commonFormat = $this->phpFormatToCommonFormat($cleanFormat);

                $schema = [
                    'type' => 'string',
                    'description' => "Date format: {$commonFormat}",
                    'example' => $this->getDateExample($format),
                ];

                // Add pattern based on common date formats
                $pattern = $this->getDatePattern($cleanFormat);
                if ($pattern) {
                    $schema['pattern'] = $pattern;
                }

                return $schema;
            }

            // Handle File, UploadedFile, and Image
            if ($attributeName === File::class ||
                $attributeName === UploadedFile::class ||
                $attributeName === Image::class) {
                return [
                    'type' => 'string',
                    'format' => 'binary',
                ];
            }
        }

        return null;
    }

    /**
     * Clean PHP date format by removing modifiers
     */
    protected function cleanDateFormat(string $phpFormat): string
    {
        // Remove PHP date format modifiers
        // ! = reset all fields to Unix epoch
        // | = reset all fields to current date/time
        return ltrim($phpFormat, '!|');
    }

    /**
     * Convert PHP date format to common format notation
     */
    protected function phpFormatToCommonFormat(string $phpFormat): string
    {
        // Direct mapping from PHP format to common format
        // Order matters: longer patterns first to avoid partial replacements
        $replacements = [
            'Y' => 'yyyy',
            'y' => 'yy',
            'm' => 'MM',
            'n' => 'M',
            'd' => 'dd',
            'j' => 'd',
            'H' => 'HH',
            'h' => 'hh',
            'G' => 'H',
            'g' => 'h',
            'i' => 'mm',
            's' => 'ss',
            'A' => 'A',
            'a' => 'a',
        ];

        // Convert each character
        $result = '';
        $length = strlen($phpFormat);

        for ($i = 0; $i < $length; $i++) {
            $char = $phpFormat[$i];
            if (isset($replacements[$char])) {
                $result .= $replacements[$char];
            } else {
                // Keep separators and other characters as-is
                $result .= $char;
            }
        }

        return $result;
    }

    /**
     * Get regex pattern for date format validation
     */
    protected function getDatePattern(string $phpFormat): ?string
    {
        // Clean format first (remove ! and | modifiers)
        $cleanFormat = $this->cleanDateFormat($phpFormat);

        // Map common PHP date formats to regex patterns
        $patterns = [
            'Y-m-d' => '^\d{4}-\d{2}-\d{2}$',
            'd/m/Y' => '^\d{2}/\d{2}/\d{4}$',
            'd-m-Y' => '^\d{2}-\d{2}-\d{4}$',
            'm/d/Y' => '^\d{2}/\d{2}/\d{4}$',
            'Y/m/d' => '^\d{4}/\d{2}/\d{2}$',
            'H:i:s' => '^\d{2}:\d{2}:\d{2}$',
            'H:i' => '^\d{2}:\d{2}$',
            'Y-m-d H:i:s' => '^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$',
            'd/m/Y H:i:s' => '^\d{2}/\d{2}/\d{4} \d{2}:\d{2}:\d{2}$',
        ];

        return $patterns[$cleanFormat] ?? null;
    }

    /**
     * Get example date string based on format
     */
    protected function getDateExample(string $format): string
    {
        return date($format);
    }

    /**
     * Get schema for enum type
     */
    protected function getEnumSchema(TypeEnum $enumRule): array
    {
        // Use reflection to access protected property
        $reflection = new ReflectionClass($enumRule);
        $enumProperty = $reflection->getProperty('enum');
        $enumProperty->setAccessible(true);
        $enumClass = $enumProperty->getValue($enumRule);

        if (!enum_exists($enumClass)) {
            return ['type' => 'string'];
        }

        // Get enum cases
        $cases = $enumClass::cases();
        $values = [];
        $varNames = [];
        $type = 'string';

        // Check if it's a backed enum
        if (method_exists($enumClass, 'cases') && !empty($cases)) {
            foreach ($cases as $case) {
                $varNames[] = $case->name;

                if (property_exists($case, 'value')) {
                    // Backed enum
                    $values[] = $case->value;

                    // Determine type from first value
                    if (is_int($case->value)) {
                        $type = 'integer';
                    }
                } else {
                    // Pure enum, use name
                    $values[] = $case->name;
                }
            }
        }

        // Use enum with x-enum-varnames extension for better Scalar UI support
        return [
            'type' => $type,
            'enum' => $values,
            'x-enum-varnames' => $varNames,
        ];
    }

    /**
     * Get constraints from validation rules
     */
    protected function getConstraintsFromValidationRules(array $attributes): array
    {
        $constraints = [];

        foreach ($attributes as $attribute) {
            $attributeName = $attribute->getName();

            // Handle Min
            if ($attributeName === Min::class) {
                $instance = $attribute->newInstance();
                $reflection = new ReflectionClass($instance);
                $minProperty = $reflection->getProperty('min');
                $minProperty->setAccessible(true);
                $constraints['minimum'] = $minProperty->getValue($instance);
            }

            // Handle Max
            if ($attributeName === Max::class) {
                $instance = $attribute->newInstance();
                $reflection = new ReflectionClass($instance);
                $maxProperty = $reflection->getProperty('max');
                $maxProperty->setAccessible(true);
                $constraints['maximum'] = $maxProperty->getValue($instance);
            }

            // Handle In (enum values)
            if ($attributeName === In::class) {
                $instance = $attribute->newInstance();
                $reflection = new ReflectionClass($instance);
                $allowedProperty = $reflection->getProperty('allowedValues');
                $allowedProperty->setAccessible(true);
                $allowedValues = $allowedProperty->getValue($instance);

                if (!empty($allowedValues)) {
                    $constraints['enum'] = $allowedValues;
                }
            }

            // Handle Regex
            if ($attributeName === Regex::class) {
                $instance = $attribute->newInstance();
                $reflection = new ReflectionClass($instance);
                $patternProperty = $reflection->getProperty('pattern');
                $patternProperty->setAccessible(true);
                $pattern = $patternProperty->getValue($instance);

                // Remove PHP regex delimiters and flags for OpenAPI pattern
                $openApiPattern = $this->convertPhpRegexToOpenApi($pattern);
                if ($openApiPattern) {
                    $constraints['pattern'] = $openApiPattern;
                }
            }
        }

        return $constraints;
    }

    /**
     * Check if property is required
     */
    protected function isPropertyRequired(ReflectionProperty $property): bool
    {
        $attributes = $property->getAttributes();

        // Check for Required attribute
        foreach ($attributes as $attribute) {
            if ($attribute->getName() === Required::class) {
                return true;
            }

            // If has Nullable attribute, it's not required
            if ($attribute->getName() === Nullable::class) {
                return false;
            }
        }

        // Fallback to type hint
        $type = $property->getType();
        return $type && !$type->allowsNull();
    }

    /**
     * Convert PHP regex pattern to OpenAPI pattern
     * Removes delimiters and flags from PHP regex
     */
    protected function convertPhpRegexToOpenApi(string $phpPattern): ?string
    {
        // PHP regex has format: /pattern/flags or #pattern#flags etc.
        // OpenAPI pattern is just the pattern without delimiters

        if (empty($phpPattern)) {
            return null;
        }

        // Get the delimiter (first character)
        $delimiter = $phpPattern[0];

        // Common delimiters: / # ~ @ ! %
        if (!in_array($delimiter, ['/', '#', '~', '@', '!', '%', '{', '(', '['])) {
            // If no standard delimiter, assume it's already a raw pattern
            return $phpPattern;
        }

        // Handle paired delimiters
        $closeDelimiter = match ($delimiter) {
            '{' => '}',
            '(' => ')',
            '[' => ']',
            default => $delimiter,
        };

        // Find the closing delimiter (last occurrence)
        $lastPos = strrpos($phpPattern, $closeDelimiter);

        if ($lastPos === false || $lastPos === 0) {
            // Invalid regex format, return as-is
            return $phpPattern;
        }

        // Extract the pattern without delimiters and flags
        return substr($phpPattern, 1, $lastPos - 1);
    }

    /**
     * Get schema name from class name
     * Uses full namespace path to avoid conflicts
     */
    protected function getSchemaName(string $className): string
    {
        $parts = explode('\\', $className);

        // Check if it's a module class (Ryra\Modules\{ModuleName}\...)
        if (count($parts) >= 3 && $parts[0] === 'Ryra' && $parts[1] === 'Modules') {
            // Remove "Ryra\Modules\" prefix, keep the rest
            // Ryra\Modules\Customer\DTOs\LoginDTO -> Customer_DTOs_LoginDTO
            $relevantParts = array_slice($parts, 2);
            return implode('_', $relevantParts);
        }

        return end($parts);
    }

    /**
     * Build final OpenAPI specification
     */
    protected function buildOpenApiSpec(): array
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => $this->title,
                'version' => $this->version,
                'description' => $this->description,
            ],
            'servers' => $this->servers,
            'tags' => array_values($this->tags),
            'paths' => $this->paths,
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'JWT',
                    ],
                ],
                'schemas' => $this->schemas,
            ],
        ];

        // Add tag groups by module for hierarchical navigation
        if (!empty($this->tagGroups)) {
            $spec['x-tagGroups'] = [];
            foreach ($this->tagGroups as $module => $tags) {
                $spec['x-tagGroups'][] = [
                    'name' => $module,
                    'tags' => $tags,
                ];
            }
        }

        return $spec;
    }
}
