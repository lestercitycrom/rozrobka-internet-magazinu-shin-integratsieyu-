<?php

use App\Http\Middleware\Admin\EnsureActiveAdmin;
use App\Http\Middleware\Admin\SetAdminLocale;
use App\Http\Middleware\SetPublicLocale;
use App\Http\Middleware\SetRussianPreviewLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware('web')->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin.active' => EnsureActiveAdmin::class,
            'admin.locale' => SetAdminLocale::class,
            'public.locale' => SetPublicLocale::class,
            'public.preview.ru' => SetRussianPreviewLocale::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
