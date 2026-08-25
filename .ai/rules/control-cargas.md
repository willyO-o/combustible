---
paths:
  - 'app/Http/Controllers/CargaMaterialController.php,database/seeders/UserSeeder.php,resources/js/Pages/ControlCargas/Show.vue'
---

# Control Cargas

## Control de Cargas: cerrar/pagar workflow — permiso solo para "pagar"
CargaMaterial pasa por ABIERTA → CERRADA → PAGADA. CargaMaterialController::cerrar() (POST control-cargas/{cargaMaterial}/cerrar) reutiliza assertPuedeGestionar() — mismo criterio que editar/registrar viajes, sin permiso aparte: "cualquier usuario" con acceso a la carga (dueño si es conductor; cualquiera si es jefe-area/administrador/super-admin) puede cerrarla. Registra id_usuario_cierre + fecha_cierre.

CargaMaterialController::pagar() (POST control-cargas/{cargaMaterial}/pagar) sí exige el permiso `control-cargas.marcar-pagado` (`$request->user()->can(...)`, no hasRole) — otorgado a jefe-area/administrador/super-admin pero NUNCA a conductor ni tecnico-mantenimiento (ver UserSeeder::permisosControlCargasPago(), separado de permisosControlCargas() a propósito para no colarlo en permisosParaConductor()). Sólo aplica sobre cargas CERRADA; monto_pago y observaciones son ambos opcionales (si se omiten, no se sobrescribe el valor existente — ver el uso de $request->filled() antes de asignarlos). En Show.vue los botones se gatillan con v-can="'control-cargas.editar'" (cerrar) y v-can="'control-cargas.marcar-pagado'" (pagar); el modal "Marcar como Pagado" sigue el mismo patrón useBootstrapModal() que "Registrar Viaje".

No hay endpoints equivalentes en Api/V1 (no se pidieron); si se agregan, replicar la misma lógica de permiso.
