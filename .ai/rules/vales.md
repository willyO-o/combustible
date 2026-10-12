---
paths:
  - 'app/Http/Requests/ValeRequest.php,app/Models/Vale.php,app/Http/Controllers/ValeController.php,app/Http/Controllers/Api/V1/ValeController.php,app/Http/Controllers/Api/V1/ParametrosController.php,resources/js/Pages/Vales/Create.vue'
---

# Vales

## Fecha de vencimiento del vale: obligatoria en web, opcional en API
`fecha_vencimiento` se recibe sólo como día 'Y-m-d'; ValeRequest::prepareForValidation() la convierte a 'Y-m-d 23:59:59' y la valida `date|after_or_equal:tomorrow` (debe ser posterior a hoy). Web (Vales/Create.vue, crear y editar): `required`; `tiempo_expiracion` de ParametrosEmpresa es sólo la sugerencia (Vale::vencimientoSugerido() = hoy + N días, prop `vencimientoSugerido`). API (`POST /vales`): `nullable`; si no viene o es null, Vale::boot() la calcula como fecha de emisión + N días (comportamiento previo). La app obtiene la sugerencia en `GET /parametros` → `vales` {tiempo_expiracion, fecha_vencimiento_sugerida, fecha_vencimiento_minima} (Api/V1/ParametrosController::parametrosVale(), reutiliza Vale::vencimientoSugerido(), que cae a ValeConfig por defecto si no hay ParametrosEmpresa). Documentado en openapi.yaml 1.13.0 (schema ParametrosVale). Al editar (web), si cambia fecha_vencimiento se pone notificado_vencimiento_at = null para que vuelva el aviso "por vencer".
