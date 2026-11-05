<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sólo los encargados de regularización (OPERACION_REGULARIZACION_ENCARGADOS
 * del .env) entran a la gestión de regularizaciones. No pasa por permisos ni
 * por Gate::before: el super-admin tampoco entra si su correo no está.
 */
class SoloEncargadoDeRegularizacion
{
    /**
     * Permiso SÓLO de interfaz (no existe en la tabla permissions): se agrega
     * a auth.permissions del encargado para que v-can muestre el menú. Es
     * exclusivo en Directives/can.js: el super-admin no lo recibe por
     * defecto. La protección real es este middleware.
     */
    public const PERMISO_INTERFAZ = 'operacion-diaria.regularizacion.gestionar';

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            $request->user()?->esEncargadoDeRegularizacion() ?? false,
            Response::HTTP_FORBIDDEN,
            'Sólo el encargado de regularización puede gestionar las habilitaciones.',
        );

        return $next($request);
    }
}
