<?php

namespace App\Http\Middleware;

use Closure;

class CleanUtf8Response
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);
        $content = $response->getContent();
        if (is_string($content)) {
            $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8');
            $content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $content);
            $response->setContent($content);
        }
        return $response;
    }
}