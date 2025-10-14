<?php

use Illuminate\Foundation\Application;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\AntiBotMiddleware;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\VisitorLoggingMiddleware;
use App\Http\Middleware\PermissionMiddleware;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',

    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);
    // Middleware de logging de visitantes (antes que antibots para capturar todo)
        $middleware->prepend(VisitorLoggingMiddleware::class);

        // Middleware de antibots (comentado por ahora)
        $middleware->prepend(AntiBotMiddleware::class);


        $middleware->alias([
            'admin' => AdminMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'antibot' => AntiBotMiddleware::class,
            'visitor.log' => VisitorLoggingMiddleware::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            '/telebot/webhook/bot/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
