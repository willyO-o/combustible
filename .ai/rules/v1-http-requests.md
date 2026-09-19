---
paths:
  - 'app/Actions/OrdenTrabajo/*.php,app/Http/Controllers/OrdenTrabajoController.php,app/Http/Controllers/Api/V1/SolicitudMantenimientoController.php,app/Http/Requests/EmitirOrdenTrabajoRequest.php'
---

# V1 Http Requests

## Emisión de orden de trabajo: una Action compartida web + API
La emisión de órdenes (con o sin solicitud de origen, transacción + evento OrdenTrabajoAsignada) vive en CreateOrdenTrabajoAction, usada por OrdenTrabajoController::store (web) y por Api\V1\SolicitudMantenimientoController::emitirOrden (POST solicitudes-mantenimiento/{solicitud}/orden-trabajo). Si tocas la lógica de emisión, tócala en la Action, no en los controladores.
La API usa EmitirOrdenTrabajoRequest (payload mínimo: id_usuario_ejecuta y nota_emisor OBLIGATORIOS; id_taller y observacion opcionales; vehículo/conductor/categoría/lecturas se heredan de la solicitud). Autorización por PERMISO `mantenimiento.ordenes.crear` (el mismo que oculta el botón "Generar Orden de Trabajo" en Solicitud Show.vue), no por rol: `hasRole('super-admin') || checkPermissionTo('mantenimiento.ordenes.crear','web')` — guard explícito y checkPermissionTo (no hasPermissionTo, que lanza si el permiso no existe). Solicitud debe estar PENDIENTE y sin orden (422 {message} si no). El técnico se valida con whereHas('roles') y NO con User::role(): bajo auth:api el guard por defecto es 'api' y Spatie lanza RoleDoesNotExist (ver .ai/rules/v1.md).
Catálogos del formulario: un solo endpoint GET solicitudes-mantenimiento/{solicitud}/orden-trabajo/formulario (mismo Request/permiso, rules() vacías en GET) → {solicitud, valores_por_defecto.nota_emisor, catalogos{tecnicos, talleres}}. NO están en colecciones() a propósito (un solo origen). Documentado en openapi.yaml + CHANGELOG-openapi.md 1.11.0.
En tests API, crear usuarios/permisos con assignRole/givePermissionTo ANTES de actingAs($u,'api'): después el guard por defecto es 'api' y falla. Los tests deben crear el Permission y dárselo a los roles (UserSeeder no corre en tests).
