<?php

namespace Ryra\Core\Twig\Middleware;

use Ryra\Core\Http\Request;
use Ryra\Core\Http\Response;
use Ryra\Core\Twig\TwigEnvironment;

class ShareErrorsFromSession
{
    public function __construct(protected TwigEnvironment $twig) {}

    public function handle(Request $request, \Closure $next): Response
    {
        $this->twig->addGlobal('errors', $request->getSession()->getErrors());
        return $next($request);
    }
}