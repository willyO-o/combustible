---
paths:
  - public/docs/openapi.yaml
---

# Docs

## Sin jerga técnica de Laravel/PHP en la documentación OpenAPI
Los consumidores de esta documentación son desarrolladores frontend que no conocen el backend. No mencionar nombres de clases/Actions/Events/Form Requests, "el controlador", Eloquent, Fillable, SQLSTATE u otros mensajes de error crudos de Laravel/PHP, "seeder", etc. Describir el comportamiento en términos de la API (campos, condiciones, respuestas), no de la implementación interna.

Ejemplo de patrón a seguir: `is_offline` (usado por la app Android/Flutter para registrar en modo sin conexión) hace que un campo de fecha (`fecha_solicitud` en SolicitudMantenimientoRequest, `fecha_carga` en CargaCombustibleRequest) pase de opcional a obligatorio — documentar esa condicionalidad en el schema (`required`, `nullable`, `description`) y en la descripción del endpoint.

## colecciones: Grifo/TipoCombustible se devuelven completos (columnas reales)
En GET /parametros/colecciones, `estaciones_servicio` y `tipos_combustible` son `Grifo::...->get()` y `TipoCombustible::...->get()` — modelos completos, sin renombrar columnas. Grifo real: id, razon_social, nit, direccion, ciudad, telefono, estado_grifo, es_principal (NO existe `nombre_grifo`). TipoCombustible real: id, tipo_combustible, estado_tipo_combustible (NO existe `nombre_tipo_combustible`). Los schemas openapi Grifo/TipoCombustible ya reflejan esto.
