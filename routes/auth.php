<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas de autenticación
|--------------------------------------------------------------------------
|
| Los usuarios se crean únicamente desde el módulo de Usuarios (un
| administrador), con una contraseña autogenerada a partir del C.I. Por eso
| aquí solo quedan habilitadas login, logout y el cambio de la propia
| contraseña (usado desde Profile/Edit.vue). El registro público, el
| reseteo de contraseña por correo, la verificación de email y la
| reconfirmación de contraseña quedan deshabilitados: sus controladores y
| páginas Auth/* siguen en el repo mas no están enrutados.
|
*/

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
