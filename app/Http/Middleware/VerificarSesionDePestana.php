<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloquea las acciones enviadas desde una pestaña que quedó mostrando la
 * sesión de OTRO usuario.
 *
 * La cookie de sesión es una sola para todas las pestañas del navegador: si en
 * la pestaña B se cierra sesión y se entra con otro usuario, la pestaña A sigue
 * mostrando la interfaz (menú, botones, formularios) del usuario anterior, pero
 * todo lo que envíe viaja ya con la sesión nueva — y el token CSRF tampoco lo
 * frena, porque axios lo lee de la cookie XSRF-TOKEN, que se renueva en cada
 * respuesta. Para distinguirlo, el frontend (resources/js/bootstrap.js) manda en
 * cada petición al propio sistema la cabecera X-Sesion-Usuario con la identidad
 * con la que se dibujó la pestaña (id del usuario, o "invitado" en el login).
 *
 * Sólo se verifica lo que puede cambiar datos (todo método que no sea
 * GET/HEAD/OPTIONS) y las recargas parciales de Inertia (p.ej. el poll de
 * notificaciones del header cada 30 s, que así corrige sola una pestaña
 * desactualizada). Las visitas GET completas NO se verifican: el navegador
 * repite la cabecera al seguir el redirect que hay después de iniciar o cerrar
 * sesión, y esa respuesta ya trae la identidad correcta para la pestaña.
 *
 * Si la cabecera no llega (API, enlaces directos, descargas, tests) no se hace
 * nada. El cierre de sesión nunca se bloquea. Si la sesión se cerró del todo,
 * en las rutas con "auth" ese middleware corre antes (tiene prioridad) y manda
 * al login como siempre; aquí sólo llega el caso inverso: el login de una
 * pestaña vieja cuando ya hay otra sesión abierta.
 */
class VerificarSesionDePestana
{
    /**
     * Cabecera con la identidad con la que se dibujó la pestaña.
     */
    public const CABECERA = 'X-Sesion-Usuario';

    /**
     * Valor de la cabecera cuando la pestaña se dibujó sin sesión (login).
     */
    public const INVITADO = 'invitado';

    /**
     * Cabecera de la respuesta JSON que avisa al frontend del cambio de sesión.
     */
    public const CABECERA_RESPUESTA = 'X-Sesion-Cambiada';

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $identidadDeLaPestana = $request->header(self::CABECERA);

        if ($identidadDeLaPestana === null || ! $this->debeVerificarse($request)) {
            return $next($request);
        }

        if ($identidadDeLaPestana === $this->identidadDeLaSesion($request)) {
            return $next($request);
        }

        // La pestaña se recarga por completo en el dashboard, ya con la sesión
        // real: si quedó sin sesión, el dashboard la manda al login.
        if ($request->header('X-Inertia')) {
            return Inertia::location(route('dashboard'));
        }

        return response()->json([
            'message' => 'La sesión cambió en otra pestaña. Actualiza esta pestaña para continuar; no se guardó ningún cambio.',
            'sesion_cambiada' => true,
        ], Response::HTTP_CONFLICT)->header(self::CABECERA_RESPUESTA, '1');
    }

    /**
     * ¿La petición puede cambiar datos o es una recarga parcial de Inertia?
     */
    private function debeVerificarse(Request $request): bool
    {
        if ($request->routeIs('logout')) {
            return false;
        }

        if (! $request->isMethodSafe()) {
            return true;
        }

        return $request->hasHeader('X-Inertia-Partial-Data')
            || $request->hasHeader('X-Inertia-Partial-Except');
    }

    /**
     * Identidad de la sesión con la que llegó la petición, en el mismo formato
     * que la cabecera que manda el frontend.
     */
    private function identidadDeLaSesion(Request $request): string
    {
        $usuario = $request->user();

        return $usuario ? (string) $usuario->getAuthIdentifier() : self::INVITADO;
    }
}
