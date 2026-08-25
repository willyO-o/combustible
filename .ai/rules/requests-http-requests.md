---
paths:
  - 'app/Http/Controllers/Api/V1/*.php,app/Http/Requests/CargaMaterialRequest.php,app/Http/Requests/ViajeRequest.php'
---

# Requests Http Requests

## Control de Cargas API: cargas-material + viajes, opcionalmente juntos
Api\V1\CargaMaterialController mirrors the web ControlCargas module: index/store/show for cargas-material, plus POST cargas-material/{cargaMaterial}/viajes for a separate trip. POST cargas-material accepts an optional nested `viaje` block (id_material, foto, origen, destino, detalle) to register the carga's first trip in the same request — CargaMaterialRequest only requires those `viaje.*` fields when `$this->is('api/*') && $this->has('viaje')` is true (web callers never send this block, so it's a no-op there). Same shared-FormRequest-with-`$this->is('api/*')` pattern as CargaCombustibleRequest's is_offline handling — don't fork a separate Api Request class for this. Documented in public/docs/openapi.yaml under the "Control de Cargas" tag.
