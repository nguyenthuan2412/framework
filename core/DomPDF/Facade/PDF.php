<?php

namespace Ryra\Core\DomPDF\Facade;

use Ryra\Core\DomPDF\PDF as BasePDF;
use Ryra\Core\Support\Facade;

/**
 * @method static BasePDF setBaseHost(string $baseHost)
 * @method static BasePDF setBasePath(string $basePath)
 * @method static BasePDF setCanvas(\Dompdf\Canvas $canvas)
 * @method static BasePDF setCallbacks(array<string, mixed> $callbacks)
 * @method static BasePDF setCss(\Dompdf\Css\Stylesheet $css)
 * @method static BasePDF setDefaultView(string $defaultView, array<string, mixed> $options)
 * @method static BasePDF setDom(\DOMDocument $dom)
 * @method static BasePDF setFontMetrics(\Dompdf\FontMetrics $fontMetrics)
 * @method static BasePDF setHttpContext(resource|array<string, mixed> $httpContext)
 * @method static BasePDF setPaper(string|float[] $paper, string $orientation = 'portrait')
 * @method static BasePDF setProtocol(string $protocol)
 * @method static BasePDF setTree(\Dompdf\Frame\FrameTree $tree)
 * @method static BasePDF setWarnings(bool $warnings)
 * @method static BasePDF setOption(array<string, mixed>|string $attribute, $value = null)
 * @method static BasePDF setOptions(array<string, mixed> $options)
 * @method static BasePDF loadView(string $view, array<string, mixed> $data = [], ?string $encoding = null)
 * @method static BasePDF loadHTML(string $string, ?string $encoding = null)
 * @method static BasePDF loadFile(string $file)
 * @method static BasePDF addInfo(array<string, string> $info)
 * @method static string output(array<string, int> $options = [])
 * @method static BasePDF save()
 * @method static \Ryra\Core\Http\Response download(string $filename = 'document.pdf')
 * @method static \Ryra\Core\Http\Response stream(string $filename = 'document.pdf')
 */
class PDF extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'dompdf.wrapper';
    }
}