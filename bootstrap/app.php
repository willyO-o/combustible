<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\VerificarSesionDePestana;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            // Bloquea acciones de una pestaña que quedó con la sesión de otro
            // usuario (ver la clase y resources/js/bootstrap.js).
            VerificarSesionDePestana::class,
        ]);

        // Permiso requerido por cada ruta web (routes/web.php), el mismo que
        // usa v-can en la interfaz. Pasa con cualquiera de los permisos
        // separados por "|"; el super-admin pasa siempre (Gate::before).
        $middleware->alias([
            'permission' => PermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Respuesta JSON estándar para todas las rutas /api/*
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
    ])
    ->create();
