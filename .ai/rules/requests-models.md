---
paths:
  - 'app/Http/Controllers/Api/V1/CargaMaterialController.php,app/Http/Controllers/CargaMaterialController.php,app/Http/Requests/ViajeRequest.php,app/Http/Requests/CargaMaterialRequest.php,app/Models/Viaje.php'
---

# Requests Models

## Viaje.fecha_hora_carga follows the is_offline pattern (forced override, not model default)
Viaje (tabla `viaje`) tiene `fecha_hora_carga` (dateTime, NOT NULL, sin default en la migración). Sigue el mismo patrón is_offline que CargaCombustible::fecha_carga / SolicitudMantenimiento::fecha_solicitud: ViajeRequest exige `fecha_hora_carga` sólo cuando `$this->is('api/*') && $this->boolean('is_offline')`; CargaMaterialRequest replica lo mismo bajo `viaje.fecha_hora_carga`/`viaje.is_offline` para el bloque anidado de POST /cargas-material.

Importante: la lógica que asigna el valor vive en el CONTROLADOR (los 3 call sites: CargaMaterialController::registrarViaje() web y API, y CargaMaterialController@store API con el bloque viaje anidado), no en Viaje::boot() — se fuerza `fecha_hora_carga = now()` siempre que `is_offline` sea falsy, sobrescribiendo cualquier valor enviado (igual que CreateCargaCombustibleAction). Viaje::boot() sólo asigna `id_usuario_registro`; no lo extiendas para defaultear fechas, o divergirá de este patrón.

ViajeFactory y cualquier `->viajes()->create([...])` directo en tests deben incluir `fecha_hora_carga` explícitamente (la columna es NOT NULL sin default a nivel de BD).

Documentado en openapi.yaml: schema Viaje, ViajeStoreRequest (`fecha_hora_carga`/`is_offline`), CargaMaterialStoreRequest (`viaje[fecha_hora_carga]`/`viaje[is_offline]`), y las descripciones de POST /cargas-material y POST /cargas-material/{cargaMaterial}/viajes.
