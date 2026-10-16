---
paths:
  - 'routes/web.php,tests/Feature/**/*.php,tests/ConPermisosReales.php,resources/js/Directives/can.js'
---

# Directives

## Cada ruta web autenticada exige su permiso (middleware permission: en routes/web.php)
Desde 2026-10-16 (pedido del usuario): antes sólo v-can ocultaba botones y casi ningún controlador web verificaba permisos (cualquier usuario autenticado podía hacer un POST a cualquier módulo). Ahora cada ruta declara en routes/web.php el MISMO permiso que usa la interfaz (v-can, `permission` en Data/sidebar/nav.ts), con el alias `permission` de Spatie (registrado en bootstrap/app.php) y `->middlewareFor()` en los resources. "a|b" = basta con uno. Se usa así en las rutas compartidas: alta rápida de material (control-cargas.viajes.registrar), de vehículo externo (control-cargas.crear|editar) y de grifo (vales.crear|editar), además de los buscadores search.*. super-admin pasa siempre (canAny → Gate::before).
Sin permiso propio quedan sólo dashboard (sus widgets ya verifican), profile, password.update, logout y notificaciones. El dashboard NO debe exigir permiso: es el destino del login y del 409 de VerificarSesionDePestana.
Hay dos excepciones de "sólo lo propio", resueltas en el controlador: VehiculoController::show (sin vehiculos.ver, sólo los vehículos asignados al conductor, que llega por las alertas del dashboard) y SolicitudMantenimientoController::show (sin mantenimiento.solicitudes.ver, sólo la solicitud de una orden asignada al técnico). En el front, tienePermiso() de Directives/can.js aplica la misma regla que v-can desde el script.
Tests: tests/Feature/PermisosRutasWebTest.php falla si una ruta autenticada nueva no declara permiso o si usa un permiso que UserSeeder no crea. Los tests que crean roles con Role::firstOrCreate() deben usar el trait Tests\ConPermisosReales (siembra ActualizarPermisosSeeder), si no, reciben 403. Al agregar una pantalla o un botón: declarar su permiso en la ruta y ocultarlo con el mismo v-can. Pendiente: la API móvil (routes/api.php) todavía no verifica permisos por ruta.
