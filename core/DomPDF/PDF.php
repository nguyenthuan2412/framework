<?php

namespace Ryra\Core\DomPDF;

use Ryra\Core\Config\Repository;
use Ryra\Core\Http\Response;
use Dompdf\Adapter\CPDF;
use Dompdf\Options;
use Dompdf\Dompdf;
use Exception;
use Ryra\Core\Filesystem\Facade\Filesystem;
use Ryra\Core\Http\HeaderUtils;
use Ryra\Core\Support\Str;
use Ryra\Core\Twig\Facade\Twig;

/**
 * @method PDF setBaseHost(string $baseHost)
 * @method PDF setBasePath(string $basePath)
 * @method PDF setCanvas(\Dompdf\Canvas $canvas)
 * @method PDF setCallbacks(array<string, mixed> $callbacks)
 * @method PDF setCss(\Dompdf\Css\Stylesheet $css)
 * @method PDF setDefaultView(string $defaultView, array<string, mixed> $options)
 * @method PDF setDom(\DOMDocument $dom)
 * @method PDF setFontMetrics(\Dompdf\FontMetrics $fontMetrics)
 * @method PDF setHttpContext(resource|array<string, mixed> $httpContext)
 * @method PDF setPaper(string|float[] $paper, string $orientation = 'portrait')
 * @method PDF setProtocol(string $protocol)
 * @method PDF setTree(\Dompdf\Frame\FrameTree $tree)
 * @method string getBaseHost()
 * @method string getBasePath()
 * @method \Dompdf\Canvas getCanvas()
 * @method array<string, mixed> getCallbacks()
 * @method \Dompdf\Css\Stylesheet getCss()
 * @method \DOMDocument getDom()
 * @method \Dompdf\FontMetrics getFontMetrics()
 * @method resource getHttpContext()
 * @method Options getOptions()
 * @method \Dompdf\Frame\FrameTree getTree()
 * @method string getPaperOrientation()
 * @method float[] getPaperSize()
 * @method string getProtocol()
 */
class PDF
{
    protected bool $rendered = false;

    protected bool $showWarnings;

    protected string $public_path;

    public function __construct(protected Dompdf $dompdf, protected Repository $config)
    {
        $this->showWarnings = $this->config->get('dompdf.show_warnings', false);
    }

    /**
     * Get the DomPDF instance
     */
    public function getDomPDF(): Dompdf
    {
        return $this->dompdf;
    }

    /**
     * Show or hide warnings
     */
    public function setWarnings(bool $warnings): self
    {
        $this->showWarnings = $warnings;
        return $this;
    }

    /**
     * Load a HTML string
     *
     * @param string|null $encoding Not used yet
     */
    public function loadHTML(string $string, ?string $encoding = null): self
    {
        $string = $this->convertEntities($string);
        $this->dompdf->loadHtml($string, $encoding);
        $this->rendered = false;
        return $this;
    }

    /**
     * Load a HTML file
     */
    public function loadFile(string $file): self
    {
        $this->dompdf->loadHtmlFile($file);
        $this->rendered = false;
        return $this;
    }

    /**
     * Add metadata info
     * @param array<string, string> $info
     */
    public function addInfo(array $info): static
    {
        foreach ($info as $name => $value) {
            $this->dompdf->addInfo($name, $value);
        }
        return $this;
    }

    /**
     * Load a View and convert to HTML
     */
    public function loadView(string $view, array $data = [], ?string $encoding = null): static
    {
        $html = Twig::render($view, $data);
        return $this->loadHTML($html, $encoding);
    }

    /**
     * Set/Change an option (or array of options) in Dompdf
     */
    public function setOption(array|string $attribute, mixed $value = null): static
    {
        $this->dompdf->getOptions()->set($attribute, $value);
        return $this;
    }

    /**
     * Replace all the Options from DomPDF
     *
     * @param array<string, mixed> $options
     */
    public function setOptions(array $options, bool $mergeWithDefaults = false): self
    {
        if ($mergeWithDefaults) {
            $options = array_merge(app()->make('dompdf.options'), $options);
        }

        $this->dompdf->setOptions(new Options($options));
        return $this;
    }

    /**
     * Output the PDF as a string.
     *
     * The options parameter controls the output. Accepted options are:
     *
     * 'compress' = > 1 or 0 - apply content stream compression, this is
     *    on (1) by default
     *
     * @param array<string, int> $options
     *
     * @return string The rendered PDF as string
     */
    public function output(array $options = []): string
    {
        if (!$this->rendered) {
            $this->render();
        }
        return (string) $this->dompdf->output($options);
    }

    /**
     * Save the PDF to a file
     */
    public function save(string $filename, ?string $disk = null): static
    {
        $disk = $disk ?: $this->config->get('dompdf.disk');

        if (! is_null($disk)) {
            Filesystem::driver($disk)->put($filename, $this->output());
            return $this;
        }

        Filesystem::write($filename, $this->output());
        return $this;
    }

    /**
     * Make the PDF downloadable by the user
     */
    public function download(string $filename = 'document.pdf', array $headers = [], ?string $disposition = 'inline'): Response
    {
        $output = $this->output();
        $fallback = $this->fallbackName($filename);

        $response = Response::streamDownload($output, $filename ?? $fallback, $headers, $disposition);
        $response->headers->set('content-type', 'application/pdf');
        return $response;
    }

    /**
     * Return a response with the PDF to show in the browser
     */
    public function stream(string $filename = 'document.pdf'): Response
    {
        $output = $this->output();
        $fallback = $this->fallbackName($filename);

        return Response::stream($output, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition('inline', $filename, $fallback),
        ]);
    }

    /**
     * Render the PDF
     */
    public function render(): void
    {
        $this->dompdf->render();

        if ($this->showWarnings) {
            global $_dompdf_warnings;
            if (!empty($_dompdf_warnings) && count($_dompdf_warnings)) {
                $warnings = '';
                foreach ($_dompdf_warnings as $msg) {
                    $warnings .= $msg . "\n";
                }

                if (!empty($warnings)) {
                    throw new Exception($warnings);
                }
            }
        }
        $this->rendered = true;
    }

    public function setEncryption(string $password, string $ownerpassword = '', array $pc = []): void
    {
        $this->render();
        $canvas = $this->dompdf->getCanvas();
        if (! $canvas instanceof CPDF) {
            throw new \RuntimeException('Encryption is only supported when using CPDF');
        }
        $canvas->get_cpdf()->setEncryption($password, $ownerpassword, $pc);
    }

    protected function convertEntities(string $subject): string
    {
        if (false === $this->config->get('dompdf.convert_entities', true)) {
            return $subject;
        }

        $entities = [
            '€' => '&euro;',
            '£' => '&pound;',
        ];

        foreach ($entities as $search => $replace) {
            $subject = str_replace($search, $replace, $subject);
        }
        return $subject;
    }

    /**
     * Dynamically handle calls into the dompdf instance.
     */
    public function __call(string $method, array $parameters): mixed
    {
        if (method_exists($this, $method)) {
            return $this->$method(...$parameters);
        }

        if (method_exists($this->dompdf, $method)) {
            $return = $this->dompdf->$method(...$parameters);

            return $return == $this->dompdf ? $this : $return;
        }

        throw new \UnexpectedValueException("Method [{$method}] does not exist on PDF instance.");
    }

    /**
     * Make a safe fallback filename
     */
    protected function fallbackName(string $filename): string
    {
        return str_replace('%', '', Str::ascii($filename));
    }
}