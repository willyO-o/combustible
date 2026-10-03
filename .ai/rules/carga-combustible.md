---
paths:
  - app/Actions/CargaCombustible/ListCargaCombustibleAction.php
---

# Carga Combustible

## Listado de cargas: precedencia de rol administrador > jefe-area > conductor
ListCargaCombustibleAction (compartida por CargaCombustibleController web y Api\V1\CargaCombustibleController) aplica el mismo alcance que ListValeAction: administrador/super-admin ven todo aunque tengan otros roles; jefe-area (puro o con conductor) ve cargas de vehículos de sus áreas a cargo (whereHas('vehiculo.areasAsignadas') + persona->encargadoAreas()); conductor puro sólo las suyas (id_conductor = id_persona). Nunca filtrar con hasRole('conductor') a secas: un usuario con los 3 roles que registra una carga con el vale de OTRO conductor (id_conductor del vale ≠ su id_persona) dejaba de verla. Tests en tests/Feature/CargaCombustibleControllerTest.php.
