<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CargaCombustibleController;
use App\Http\Controllers\Api\V1\NotificacionController;
use App\Http\Controllers\Api\V1\OperacionDiariaController;
use App\Http\Controllers\Api\V1\ParametrosController;
use App\Http\Controllers\Api\V1\SolicitudMantenimientoController;
use App\Http\Controllers\Api\V1\ValeController;
use Illuminate\Support\Facades\Route;

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
        Route::post('logout', [AuthController::class, 'logout'])->name('api.v1.auth.logout');
        Route::post('refresh', [AuthController::class, 'refresh'])->name('api.v1.auth.refresh');
        Route::get('me', [AuthController::class, 'me'])->name('api.v1.auth.me');
    });
});

// ── Operación Diaria ───────────────────────────────────────────────────────

Route::middleware('auth:api')->group(function () {
    Route::resource('operacion-diaria', OperacionDiariaController::class)
        ->parameters(['operacion-diaria' => 'operacionDiaria'])->except(['create', 'edit'])->names('api.v1.operacion-diaria');

    Route::post('operacion-actividad/validar', [OperacionDiariaController::class, 'validarActividad'])
        ->name('api.v1.operacion-diaria.agregar-actividad');

    Route::get('parametros', [ParametrosController::class, 'index'])->name('api.v1.parametros.index');
    Route::get('parametros/colecciones', [ParametrosController::class, 'colecciones'])->name('api.v1.parametros.colecciones');

    Route::get('notificaciones', [NotificacionController::class, 'index'])->name('api.v1.notificaciones.index');
    Route::post('notificaciones/{notificacion}/marcar-leida', [NotificacionController::class, 'marcarLeida'])->name('api.v1.notificaciones.marcar-leida');

    Route::get('vales/pendientes', [ValeController::class, 'valesPendientes'])->name('api.v1.vales.pendientes');

    Route::resource('vales', ValeController::class)
        ->parameters(['vales' => 'vale'])->except(['create', 'edit'])->names('api.v1.vales');

    Route::resource('cargas', CargaCombustibleController::class)
        ->parameters(['cargas' => 'carga'])->except(['create', 'edit'])->names('api.v1.cargas');

    Route::resource('solicitudes-mantenimiento', SolicitudMantenimientoController::class)
        ->parameters(['solicitudes-mantenimiento' => 'solicitud'])
        ->only(['index', 'store', 'show', 'update'])
        ->names('api.v1.solicitudes-mantenimiento');
});
