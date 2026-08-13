<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\OperacionDiariaController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\ParametrosController;
/*
|--------------------------------------------------------------------------
| API Routes — v1
|--------------------------------------------------------------------------
|
| Todas las rutas aquí tienen el prefijo /api/v1 (definido en bootstrap/app.php).
|
*/

// ── Autenticación ──────────────────────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->name('api.v1.auth.login');

    Route::middleware('auth:api')->group(function () {
        Route::post('logout',  [AuthController::class, 'logout'])->name('api.v1.auth.logout');
        Route::post('refresh', [AuthController::class, 'refresh'])->name('api.v1.auth.refresh');
        Route::get('me',       [AuthController::class, 'me'])->name('api.v1.auth.me');
    });
});

// ── Operación Diaria ───────────────────────────────────────────────────────

Route::middleware('auth:api')->group(function () {
    Route::resource('operacion-diaria', OperacionDiariaController::class)
    ->parameters(['operacion-diaria' => 'operacionDiaria'])->except(['create', 'edit'])->names([
        'index'   => 'api.v1.operacion-diaria.index',
        'store'   => 'api.v1.operacion-diaria.store',
        'show'    => 'api.v1.operacion-diaria.show',
        'update'  => 'api.v1.operacion-diaria.update',
        'destroy' => 'api.v1.operacion-diaria.destroy',
    ]);

    Route::post('operacion-actividad/validar', [OperacionDiariaController::class, 'validarActividad'])
        ->name('api.v1.operacion-diaria.agregar-actividad');

    Route::get('parametros', [ParametrosController::class, 'index'])->name('api.v1.parametros.index');
    Route::get('parametros/colecciones', [ParametrosController::class, 'colecciones'])->name('api.v1.parametros.colecciones');
});
