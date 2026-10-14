<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Para Form Requests de rutas WEB que también se envían por axios desde un
 * modal o un alta rápida (MaterialFormModal, VehiculoExternoFormModal,
 * GrifoFormModal, CargoSelect, el alta rápida de material de Control de
 * Cargas).
 *
 * bootstrap/app.php sólo renderiza las excepciones como JSON en rutas
 * api/*, así que un error de validación en una ruta web respondía con un
 * redirect aunque el cliente pidiera JSON: axios lo seguía, recibía la página
 * HTML con 200 y el modal se cerraba como si hubiera guardado sin registrar
 * nada. Con este trait, una petición web que pide JSON recibe 422 con
 * {message, errors} (la misma forma que Laravel usa por defecto), y los
 * formularios Inertia normales siguen recibiendo el redirect con errores.
 *
 * Las rutas api/* no se tocan: ya responden JSON con su propio formato.
 */
trait RespondeErroresEnJson
{
    protected function failedValidation(Validator $validator): void
    {
        if ($this->expectsJson() && ! $this->is('api/*')) {
            throw new HttpResponseException(response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422));
        }

        parent::failedValidation($validator);
    }
}
