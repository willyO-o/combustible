<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CargaCombustibleController;
use App\Http\Controllers\Api\V1\CargaMaterialController;
use App\Http\Controllers\Api\V1\DispositivoController;
use App\Http\Controllers\Api\V1\MaterialController;
use App\Http\Controllers\Api\V1\NotificacionController;
use App\Http\Controllers\Api\V1\OperacionDiariaController;
use App\Http\Controllers\Api\V1\OrdenTrabajoController;
use App\Http\Controllers\Api\V1\ParametrosController;
use App\Http\Controllers\Api\V1\SolicitudMantenimientoController;
use App\Http\Controllers\Api\V1\ValeController;
use App\Http\Controllers\Api\V1\VehiculoController;
use App\Http\Controllers\Api\V1\VehiculoExternoController;
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
        Route::match(['put', 'patch'], 'me', [AuthController::class, 'updateMe'])->name('api.v1.auth.me.update');
    });
});

// ── Operación Diaria ───────────────────────────────────────────────────────

Route::middleware('auth:api')->group(function () {
    // Reporte de una operación diaria en PDF (mismo formato que el sistema
    // web), para que la app móvil lo descargue y lo muestre en el dispositivo.
    Route::get('operacion-diaria/{operacionDiaria}/pdf', [OperacionDiariaController::class, 'pdf'])
        ->name('api.v1.operacion-diaria.pdf');

    Route::resource('operacion-diaria', OperacionDiariaController::class)
        ->parameters(['operacion-diaria' => 'operacionDiaria'])->except(['create', 'edit'])->names('api.v1.operacion-diaria');

    Route::post('operacion-actividad/validar', [OperacionDiariaController::class, 'validarActividad'])
        ->name('api.v1.operacion-diaria.agregar-actividad');

    Route::get('parametros', [ParametrosController::class, 'index'])->name('api.v1.parametros.index');
    Route::get('parametros/colecciones', [ParametrosController::class, 'colecciones'])->name('api.v1.parametros.colecciones');

    Route::get('notificaciones', [NotificacionController::class, 'index'])->name('api.v1.notificaciones.index');
    Route::post('notificaciones/{notificacion}/marcar-leida', [NotificacionController::class, 'marcarLeida'])->name('api.v1.notificaciones.marcar-leida');

    // Registro de tokens FCM para push (app Flutter): se registra al iniciar
    // sesión (o cuando Firebase rota el token) y se elimina al cerrar sesión.
    Route::post('dispositivos', [DispositivoController::class, 'store'])->name('api.v1.dispositivos.store');
    Route::delete('dispositivos', [DispositivoController::class, 'destroy'])->name('api.v1.dispositivos.destroy');

    Route::get('vales/pendientes', [ValeController::class, 'valesPendientes'])->name('api.v1.vales.pendientes');
    Route::get('vales/{vale}/pdf', [ValeController::class, 'pdf'])->name('api.v1.vales.pdf');

    // Vales: listar, emitir (jefe-area) y ver el detalle. La edición/anulación
    // de un vale sigue siendo sólo web.
    Route::resource('vales', ValeController::class)
        ->parameters(['vales' => 'vale'])->only(['index', 'store', 'show'])->names('api.v1.vales');

    // Comprobante de egreso de combustible en PDF (mismo formato que el
    // sistema web), para descargarlo/mostrarlo desde la app móvil.
    Route::get('cargas/{carga}/pdf', [CargaCombustibleController::class, 'pdf'])
        ->name('api.v1.cargas.pdf');

    Route::resource('cargas', CargaCombustibleController::class)
        ->parameters(['cargas' => 'carga'])->except(['create', 'edit'])->names('api.v1.cargas');

    // Control de cargas de material: abrir un flete (opcionalmente con su
    // primer viaje), registrar viajes adicionales por separado y avanzar el
    // flujo ABIERTA -> CERRADA -> PAGADA.
    Route::post('cargas-material/{cargaMaterial}/viajes', [CargaMaterialController::class, 'registrarViaje'])
        ->name('api.v1.cargas-material.viajes.registrar');
    Route::post('cargas-material/{cargaMaterial}/cerrar', [CargaMaterialController::class, 'cerrar'])
        ->name('api.v1.cargas-material.cerrar');
    Route::post('cargas-material/{cargaMaterial}/pagar', [CargaMaterialController::class, 'pagar'])
        ->name('api.v1.cargas-material.pagar');

    Route::resource('cargas-material', CargaMaterialController::class)
        ->parameters(['cargas-material' => 'cargaMaterial'])
        ->only(['index', 'store', 'show'])
        ->names('api.v1.cargas-material');

    // Catálogo de materiales (cola, broza, concentrado, etc.): listar y dar
    // de alta uno nuevo bajo demanda al registrar un viaje.
    Route::resource('materiales', MaterialController::class)
        ->only(['index', 'store'])
        ->names('api.v1.materiales');

    // Catálogo de vehículos externos (no pertenecen a la flota propia):
    // listar y dar de alta uno nuevo bajo demanda al abrir una carga.
    Route::resource('vehiculos-externos', VehiculoExternoController::class)
        ->only(['index', 'store'])
        ->names('api.v1.vehiculos-externos');

    // Vehículos: detalle del mantenimiento preventivo sugerido de un vehículo
    // (todo el cálculo ya viene resuelto desde el servidor; ver
    // Api\V1\VehiculoController::mantenimientoSugerido).
    Route::get('vehiculos/{vehiculo}/mantenimiento-sugerido', [VehiculoController::class, 'mantenimientoSugerido'])
        ->name('api.v1.vehiculos.mantenimiento-sugerido');

    Route::get('solicitudes-mantenimiento/{solicitud}/pdf', [SolicitudMantenimientoController::class, 'pdf'])->name('api.v1.solicitudes-mantenimiento.pdf');

    // Emitir la orden de trabajo de una solicitud PENDIENTE (requiere el
    // permiso mantenimiento.ordenes.crear): payload simplificado, el resto se
    // hereda de la solicitud. El GET entrega los datos y catálogos del formulario.
    Route::get('solicitudes-mantenimiento/{solicitud}/orden-trabajo/formulario', [SolicitudMantenimientoController::class, 'formularioOrden'])
        ->name('api.v1.solicitudes-mantenimiento.orden-trabajo.formulario');
    Route::post('solicitudes-mantenimiento/{solicitud}/orden-trabajo', [SolicitudMantenimientoController::class, 'emitirOrden'])
        ->name('api.v1.solicitudes-mantenimiento.orden-trabajo.store');

    Route::resource('solicitudes-mantenimiento', SolicitudMantenimientoController::class)
        ->parameters(['solicitudes-mantenimiento' => 'solicitud'])
        ->only(['index', 'store', 'show', 'update'])
        ->names('api.v1.solicitudes-mantenimiento');

    // Órdenes de trabajo (Paso 3 del flujo de mantenimiento): el técnico de
    // mantenimiento consulta las órdenes que tiene asignadas y registra el
    // detalle del trabajo realizado ítem por ítem.
    Route::get('ordenes-trabajo', [OrdenTrabajoController::class, 'index'])
        ->name('api.v1.ordenes-trabajo.index');
    Route::get('ordenes-trabajo/{orden}', [OrdenTrabajoController::class, 'show'])
        ->name('api.v1.ordenes-trabajo.show');
    Route::patch('ordenes-trabajo/{orden}/iniciar', [OrdenTrabajoController::class, 'iniciar'])
        ->name('api.v1.ordenes-trabajo.iniciar');
    Route::post('ordenes-trabajo/{orden}/culminar', [OrdenTrabajoController::class, 'culminar'])
        ->name('api.v1.ordenes-trabajo.culminar');
    Route::post('ordenes-trabajo/{orden}/detalles', [OrdenTrabajoController::class, 'storeDetalle'])
        ->name('api.v1.ordenes-trabajo.detalles.store');
    Route::match(['put', 'patch'], 'ordenes-trabajo/{orden}/detalles/{detalle}', [OrdenTrabajoController::class, 'updateDetalle'])
        ->name('api.v1.ordenes-trabajo.detalles.update');
    Route::delete('ordenes-trabajo/{orden}/detalles/{detalle}', [OrdenTrabajoController::class, 'destroyDetalle'])
        ->name('api.v1.ordenes-trabajo.detalles.destroy');
});
