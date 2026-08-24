---
paths:
  - 'app/Http/Controllers/CargaMaterialController.php,app/Models/CargaMaterial.php,app/Models/Viaje.php'
---

# Models Models

## Control de Cargas: visibilidad de cargas por rol es una decisión asumida
carga_material/vehiculo_externo no tienen FK a área ni a conductor, así que "listar sus vehículos" (pedido del usuario) se implementó en CargaMaterialController::index() como: un usuario con SOLO el rol conductor ve nada más las cargas ABIERTA que él mismo abrió (id_usuario_apertura = auth id); jefe-area (y administrador/super-admin) ven TODAS las cargas ABIERTA del sistema, sin filtrar por área, porque no hay forma de escopearlas por área con el esquema actual. Si en el futuro se agrega esa relación (o se pide otro criterio), ajustar solo ese query — el resto del flujo (crear carga, registrar viaje) no depende de este filtro. Además: CargaMaterial::boot() fuerza id_usuario_apertura/fecha_apertura/estado_carga/nro_carga desde el modelo (ignora lo que venga en el request), igual que Vale/OperacionDiaria; para sembrar en tests una carga "abierta por otro usuario" hay que actingAs(ese usuario) antes de factory()->create().
