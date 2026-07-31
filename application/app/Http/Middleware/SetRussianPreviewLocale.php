<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetRussianPreviewLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('storefront.ru_preview_enabled') && app()->environment(['local', 'testing', 'staging']), 404);

        app()->setLocale('ru');

        try {
            return $next($request);
        } finally {
            app()->setLocale(config('app.locale'));
        }
    }
}
