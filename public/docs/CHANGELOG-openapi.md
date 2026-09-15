# Changelog — `openapi.yaml`

Cambios de la documentación de la API respecto a la versión anterior.

## 1.8.0 — 2026-09-14

Alcance por rol en `GET /vales` y `GET /vales/pendientes`: jefe de área ve las de su área,
administrador/super-admin ven todas. Cambio exclusivo de la API, la web no se modificó.

### Corregido

#### `GET /vales`, `GET /vales/pendientes`

- Un jefe de área (puro, o combinado con `conductor`, pero sin `administrador`/`super-admin`)
  ahora ve los vales de los vehículos asignados a sus áreas a cargo, en vez de todos los vales
  del sistema — mismo criterio que `ValeController::restringirVehiculosPorAreaDeJefe` (web) para
  emitir vales.
- Un conductor que ADEMÁS es `jefe-area`, `administrador` o `super-admin` deja de verse limitado
  a sus propios vales (antes, tener el rol `conductor` bastaba para restringir el listado a
  `id_conductor` propio sin importar los demás roles) — mismo criterio de "conductor puro" usado
  en `POST`/`PUT /operacion-diaria` (ver 1.7.0).
- `administrador`/`super-admin` siguen viendo todos los vales del sistema, sin cambios.

## 1.7.0 — 2026-09-14

Corrección de `id_conductor` en `POST`/`PUT /operacion-diaria` para usuarios con más de un rol,
y de un `500` que debía ser `422`.

### Corregido

#### `POST /operacion-diaria`, `PUT /operacion-diaria/{id}`

- Un conductor **puro** (sin ningún otro rol) sigue operando siempre como él mismo — sin
  cambios de comportamiento para ese caso, sólo quedó documentado.
- Un usuario con rol `conductor` que ADEMÁS es `jefe-area`, `administrador` o `super-admin`
  (p.ej. un jefe de área que también conduce un vehículo) ahora puede registrar/editar una
  operación a nombre de **otro** conductor asignado al vehículo, enviando `id_conductor`
  explícitamente. Antes, tener el rol `conductor` bastaba para que el servidor ignorara
  cualquier `id_conductor` enviado y forzara siempre el propio, sin importar los demás roles.
- `id_conductor` que no está entre los conductores actualmente asignados al vehículo
  respondía `500` (`ConductorNoAsignadoException` sin capturar); ahora responde `422` con un
  mensaje claro, igual que `AreaNoAsignadaException`.

### Agregado

#### `OperacionDiariaStoreRequest`, `OperacionDiariaUpdateRequest`

- `id_conductor` documentado como propiedad real (antes decía "se calcula en el servidor y no
  debe enviarse", que ya no era del todo cierto): obligatorio en el registro para
  jefe-area/administrador/super-admin, opcional en la actualización, siempre ignorado (se usa
  el propio) para un conductor puro.

## 1.6.0 — 2026-09-14

Evidencia fotográfica opcional en los controles de mantenimiento de operación diaria.

### Agregado

#### `POST /operacion-diaria`, `PUT /operacion-diaria/{id}`

- `mantenimientos[].evidencia`: foto de lo realizado en ese control, convertida a `.webp` en el
  servidor antes de guardarse. **Obligatoria** para todo control que se registre con `valor` o
  `realizado = SI` (marcar `NO` no la exige) — sin ella la petición falla con `422`
  (`mantenimientos.{i}.evidencia`). Sólo funciona enviando la petición completa como
  `multipart/form-data` (nueva alternativa de `requestBody`, documentada junto a
  `application/json`) — un cuerpo JSON no puede llevar archivos.
- `mantenimientos[].eliminar_evidencia`: borra la evidencia ya guardada de un control sin subir
  una nueva — sólo tiene efecto si el control además deja de traer `valor`/`realizado = SI` en
  el mismo envío (si no, sigue siendo obligatoria y la petición falla).
- En una edición, sin archivo nuevo para un control que ya reenvía `valor`/`realizado = SI`, su
  evidencia ya guardada se conserva automáticamente — no hace falta reenviarla.
- `MantenimientoOperacionRegistrado.pivot.evidencia`: nuevo campo en las respuestas de
  `store`/`show`/`update` con la ruta relativa de la evidencia guardada (o `null`).
- Nueva sección "Envío con evidencia fotográfica" en la descripción de
  `POST /operacion-diaria`, con el mismo formato de arreglo anidado que ya usa `respaldos` en
  `POST /cargas`.

#### Varios

- `info.version`: `1.5.0` → `1.6.0`; el ejemplo de `GET /parametros` (`api_version`) y el
  esquema `ParametrosGenerales` pasan a `"1.6.0"`. `ParametrosController::index()` devuelve
  `api_version: "1.6.0"`.

## 1.5.0 — 2026-09-06

Dos cambios en `GET /parametros/colecciones`, ambos **retrocompatibles** (un cliente que no
cambie su petición recibe exactamente lo mismo que antes):

1. Devuelve **todos** los vehículos activos del sistema cuando el usuario autenticado es
   `administrador` o `super-admin` (roles que se pueden combinar con `conductor` o `jefe-area`).
2. Nuevo parámetro `incluir` para pedir sólo algunas secciones y aligerar la respuesta.

### Cambiado

#### `GET /parametros/colecciones`

- Para un `administrador` / `super-admin`, `vehiculos` lista todos los vehículos con
  `estado_vehiculo = ACTIVO`, sin repetir ninguno (aunque el mismo usuario también sea
  conductor o jefe de área de alguno de ellos). El resto de los usuarios ve, como hasta ahora,
  sólo los que conduce más los de las áreas que administra.

### Agregado

#### `GET /parametros/colecciones`

- Parámetro de consulta opcional `incluir`: lista de secciones de `data` a devolver, como
  arreglo (`?incluir[]=vehiculos&incluir[]=estaciones_servicio`) o separada por comas
  (`?incluir=vehiculos,estaciones_servicio`). Valores permitidos: `vehiculos`,
  `estaciones_servicio`, `tipos_combustible`, `cargas_combustible`,
  `solicitudes_mantenimiento`, `ordenes_trabajo`, `operaciones_diarias`, `control_cargas`.
  `data` trae sólo las claves pedidas, en el orden solicitado; las secciones omitidas no se
  calculan. Sin el parámetro se devuelven todas (comportamiento anterior). Un valor
  desconocido devuelve `422`.
- Descripción y ejemplos del endpoint actualizados.

#### Varios

- `info.version`: `1.4.0` → `1.5.0`; el ejemplo de `GET /parametros` (`api_version`) y el
  esquema `ParametrosGenerales` pasan a `"1.5.0"`. `ParametrosController::index()` devuelve
  `api_version` `"1.5.0"`.

## 1.4.0 — 2026-09-06

`GET /parametros/colecciones` ahora expone, además del conductor titular, la lista completa de
conductores asignados a cada vehículo. Cambio **retrocompatible**: sólo se agrega un campo a
`vehiculos[]`; nada se quita ni cambia de tipo.

### Cambiado

#### `GET /parametros/colecciones`

- Cada `vehiculos[]` trae ahora `conductores_asignados`: un arreglo con todos los conductores
  actualmente asignados al vehículo (`ACTIVO` o `PROVISIONAL`) — el titular más eventuales
  reemplazos por permiso o vacaciones. Cada elemento es `{ id, label, meta }` (`label` listo
  para un selector; `meta` reservado, hoy siempre `[]`). Lista vacía si el vehículo no tiene
  conductores. `id_conductor` / `conductor_asignado` (el titular) siguen igual y son un
  subconjunto de esta lista.
- Esquema `VehiculoAsignado` y descripción del endpoint actualizados.

#### Varios

- `info.version`: `1.3.0` → `1.4.0`; el ejemplo de `GET /parametros` (`api_version`) y el
  esquema `ParametrosGenerales` pasan a `"1.4.0"`. `ParametrosController::index()` devuelve
  `api_version` `"1.4.0"`.

## 1.3.0 — 2026-09-04

`POST /solicitudes-mantenimiento` ahora también lo pueden usar `jefe-area`, `administrador` y
`super-admin` (antes sólo `conductor`). Cambio **retrocompatible** para clientes existentes: un
conductor "puro" sigue registrando exactamente igual (nada nuevo que enviar, mismo
comportamiento). `PUT /solicitudes-mantenimiento/{solicitud}` (editar) no cambia — sigue siendo
sólo del conductor autor.

### Cambiado

#### `POST /solicitudes-mantenimiento`

- Descripción del endpoint actualizada: ya no dice "sólo disponible para el rol `conductor`".
- `SolicitudMantenimientoStoreRequest.id_conductor`: descripción actualizada — ahora es
  **obligatorio** (y debe ser uno de los conductores `ACTIVO`/`PROVISIONAL` realmente
  asignados a `id_vehiculo`) cuando el usuario autenticado es `jefe-area` (aunque además tenga
  el rol `conductor`, ese prevalece), `administrador` o `super-admin`. Para un `conductor` sin
  ningún rol de gestión no cambia: se ignora cualquier valor recibido y se asigna siempre su
  propia persona.
- `id_vehiculo` para esos mismos roles ya no exige una asignación propia del usuario — acepta
  cualquier vehículo activo.

#### Varios

- `info.version`: `1.2.0` → `1.3.0`; el ejemplo de `GET /parametros` (`api_version`) y el
  esquema `ParametrosGenerales` pasan a `"1.3.0"`. `ParametrosController::index()` devuelve
  `api_version` `"1.3.0"`.

## 1.2.0 — 2026-09-03

Nuevo módulo **Vehículos** con un único endpoint: el **mantenimiento preventivo sugerido**
de un vehículo. El cálculo (última lectura del vehículo vs. último mantenimiento registrado,
siguiente múltiplo de la frecuencia con tolerancia del 5%) se resuelve **en el servidor**; la
respuesta ya trae `estado`, `estado_label` y una `descripcion` lista para mostrar, más los
números crudos. Cambio **retrocompatible** (endpoint, tag y esquemas nuevos; nada se quita ni
cambia de tipo).

### Añadido

#### `GET /vehiculos/{vehiculo}/mantenimiento-sugerido` — **nuevo**

- Tag nuevo **`Vehículos`**.
- Devuelve `data.vehiculo` (datos básicos), `data.resumen` (conteos por estado +
  `requiere_atencion`) y `data.alertas[]` (una por intervalo de mantenimiento del tipo de
  vehículo), ordenadas por urgencia `VENCIDO` → `PROXIMO` → `AL_DIA` → `SIN_DATOS`.
- Cada alerta: `frecuencia`, `tolerancia`, `lectura_actual`, `ultimo_mantenimiento`,
  `proximo_objetivo`, `restante`, `estado`, `estado_label`, `descripcion`, `unidad` (`km`/`h`).
- Nuevos esquemas **`MantenimientoSugeridoResponse`** y **`MantenimientoSugerido`**.
- Acceso: el **conductor** sólo puede consultar vehículos que tiene asignados (otro → `403`);
  jefe-area / técnico / administrador / super-admin sin restricción.

#### Varios

- `info.version`: `1.1.0` → `1.2.0`; el ejemplo de `GET /parametros` (`api_version`) y el
  esquema `ParametrosGenerales` pasan a `"1.2.0"`. `ParametrosController::index()` devuelve
  `api_version` `"1.2.0"`.

## 1.1.0 — 2026-09-02

Módulo **Operación Diaria**: se documenta la funcionalidad de controles de mantenimiento
(tabla `mantenimiento_operacion_diaria`) y el material trasladado por actividad
(`actividad_realizada.id_material`). Además se agregan endpoints para descargar en PDF el
reporte de operación diaria y el comprobante de egreso de combustible, se completa el
módulo **Control de Cargas** ("fletes") en la API con los pasos `cerrar` y `pagar`, el
detalle de las **Órdenes de Trabajo** gana el endpoint para corregir un ítem, y el módulo
de **Vales** gana la emisión de vales (jefe de área). Todos los cambios son
**retrocompatibles** (endpoints, campos y colecciones nuevos, opcionales; nada se quita ni
cambia de tipo).

### Añadido

#### `GET /parametros/colecciones`

- Nueva colección **`data.operaciones_diarias`** en el esquema `ColeccionesResponse` y en el
  ejemplo (antes el código ya devolvía `operaciones_diarias.actividades_sugeridas` pero no
  estaba documentada):
  - `operaciones_diarias.actividades_sugeridas` — actividades ya registradas en las áreas del
    usuario, para autocompletar nombre y unidad de medida.
  - `operaciones_diarias.tipos_mantenimiento` — **nuevo**: catálogo de controles de
    mantenimiento de ámbito `operacion_diaria` (`id`, `tipo_mantenimiento`, `tipo_valor`,
    `unidad_medida`). Es el catálogo que alimenta el arreglo `mantenimientos` de
    `POST`/`PUT /operacion-diaria`.

#### `POST /operacion-diaria` y `PUT/PATCH /operacion-diaria/{id}` — petición

- Nuevo campo opcional **`mantenimientos`** (`array`) en `OperacionDiariaStoreRequest` y
  `OperacionDiariaUpdateRequest`, con el nuevo esquema **`MantenimientoOperacionInput`**:
  - `id_tipo_mantenimiento` (obligatorio) — debe existir y ser de ámbito `operacion_diaria`.
  - `valor` (`number`, nullable) — para tipos `tipo_valor = cantidad`.
  - `realizado` (`SI` | `NO`, nullable) — para tipos `tipo_valor = booleano`.
  - Cada envío **reemplaza por completo** el conjunto guardado; sólo se persisten los
    controles que traen `valor` o `realizado`.
- Nuevo campo opcional **`actividades_realizadas[].id_material`** (`integer`, nullable) en
  `ActividadRealizadaInput` — material trasladado en la actividad. Sólo aplica a vehículos con
  `tipo_medicion = kilometraje`; si se envía, debe existir en `control_cargas.materiales`.
- Descripciones de ambos endpoints ampliadas con las reglas de `mantenimientos` e
  `id_material`.
- Ejemplos de petición actualizados: `POST` (`con_kilometraje`) ahora incluye `id_material` y
  un bloque `mantenimientos`; el ejemplo de `PUT` pasó de un cuerpo parcial inválido a uno
  completo y válido con `mantenimientos`.

#### Descarga de reportes/comprobantes en PDF (nuevos endpoints)

Se añaden endpoints para descargar el PDF de un registro desde la app móvil, siguiendo el
mismo patrón que los ya existentes `GET /vales/{vale}/pdf` y
`GET /solicitudes-mantenimiento/{solicitud}/pdf` (respuesta binaria `application/pdf`,
`Content-Disposition: attachment`, restricción por rol `conductor` → sólo lo propio):

- **`GET /operacion-diaria/{operacionDiaria}/pdf`** — reporte de la operación diaria
  (lecturas, jornada de actividades con su material y controles de mantenimiento). Equivale
  a la impresión del sistema web.
- **`GET /cargas/{carga}/pdf`** — comprobante de egreso de combustible. Equivale a la
  impresión del sistema web.

#### Vales — emisión desde la app (jefe de área)

El módulo web permite al jefe de área emitir vales; la API sólo permitía listarlos y
descargarlos. Se agrega:

- **`POST /vales`** — emite un vale. Requiere el permiso `vales.crear` (`jefe-area`,
  `administrador`, `super-admin`; `conductor` → `403`). `nro_vale`, `gestion`,
  `fecha_emision`, `fecha_vencimiento`, `id_user`, `id_tipo_combustible` (del vehículo) y
  `estado_vale` (`PENDIENTE`) se resuelven en el servidor. Un jefe de área sólo puede emitir
  para vehículos de sus áreas a cargo (`422` en `id_vehiculo` si no). Nuevos esquemas
  `ValeStoreRequest` / `ValeStoreResponse`.
- **`GET /vales/{vale}`** — detalle de un vale con sus relaciones. Un `conductor` sólo ve los
  suyos (`403` en otro caso). (Antes este path sólo tenía `.../pdf`.)
- `GET /parametros/colecciones` → cada `vehiculos[]` trae ahora `id_conductor` y
  `conductor_asignado` (`{id, nombre_completo, ci}`) — el conductor titular del vehículo, para
  autocompletar `id_conductor` al emitir el vale.
- Limpieza: `POST/PUT/PATCH/DELETE` sobre `/vales/{vale}` (update/destroy) ya **no** se
  registran — antes existían como rutas rotas (apuntaban a métodos inexistentes). La
  edición/anulación de vales sigue siendo sólo web.

#### Órdenes de Trabajo — corregir un ítem del detalle

El módulo web permite editar un ítem del detalle ya registrado; la API sólo permitía
agregar y eliminar. Se agrega:

- **`PUT`/`PATCH /ordenes-trabajo/{orden}/detalles/{detalle}`** — corrige un ítem del
  detalle (p. ej. una `cantidad` o lectura mal cargada). **No es un parche parcial**: mismo
  cuerpo y reglas que `POST .../detalles`, se reenvía completo. Sólo mientras la orden está
  `PENDIENTE`/`EN_EJECUCION`; misma restricción de acceso que el alta (un técnico, sólo sus
  órdenes asignadas). `403`/`404`/`422` análogos.
- `GET /parametros/colecciones` → `ordenes_trabajo.acciones_tecnico` incluye ahora la acción
  `editar_detalle` (`PATCH`, desde `PENDIENTE`/`EN_EJECUCION`).

#### Control de Cargas / fletes — flujo `ABIERTA → CERRADA → PAGADA`

El módulo web ya tenía el cierre y el marcado de pago (además de renombrar "carga de
material" → "flete" en la interfaz y los mensajes); la API sólo permitía abrir el flete y
registrar viajes. Se agregan:

- **`POST /cargas-material/{cargaMaterial}/cerrar`** — pasa el flete de `ABIERTA` a `CERRADA`
  (registra `id_usuario_cierre` y `fecha_cierre`). Sin cuerpo. Mismo criterio de acceso que
  registrar viajes (un `conductor` sólo los fletes que abrió; `jefe-area`/`administrador`/
  `super-admin`, cualquiera). `422` si el flete no está `ABIERTA`.
- **`POST /cargas-material/{cargaMaterial}/pagar`** — pasa el flete de `CERRADA` a `PAGADA`
  (registra `fecha_pago` y, opcionalmente, `monto_pago` y `observaciones` — si se omiten, no
  se sobrescriben). **Requiere el permiso `control-cargas.marcar-pagado`** (`jefe-area`,
  `administrador`, `super-admin`; nunca `conductor` ni `tecnico-mantenimiento`). `422` si el
  flete no está `CERRADA` o si falla la validación de `monto_pago`/`observaciones`.
- Ambas respuestas devuelven `{ message, data }` con el `CargaMaterial` recargado.

Correcciones de mensajes ya obsoletos en la doc (el sistema renombró "carga" → "flete"):
`POST /cargas-material` → `"Flete #… registrado exitosamente."`;
`POST /cargas-material/{id}/viajes` (flete cerrado) →
`"No se pueden registrar viajes: este flete ya está cerrado."`; ejemplo de error de `pais`
→ `"… cuando el flete es al exterior."`.

#### `POST` / `GET` / `PUT` `/operacion-diaria` — respuesta

- El esquema `OperacionDiaria` incluye ahora **`mantenimientos_operacion`** (`array` de
  `MantenimientoOperacionRegistrado`), presente en `store`, `show` y `update` (no en el
  listado). Cada elemento trae el tipo del catálogo + `pivot { id, valor, realizado }`.
- Nuevo esquema **`MantenimientoOperacionRegistrado`**.
- Ejemplos de respuesta de `POST`, `GET {id}` y `PUT` actualizados con
  `mantenimientos_operacion`.
- El esquema `ActividadRealizada.pivot` documenta `id_material` y el objeto anidado
  `material { id, material }` (presente sólo cuando `id_material` no es nulo); los ejemplos de
  respuesta lo reflejan.
- Descripción de `GET /operacion-diaria/{id}` y de `OperacionDiariaUpdateResponse` ampliada
  para nombrar las relaciones `mantenimientos_operacion` y el material de cada actividad.

### Cambiado

- `info.version`: `1.0.0` → `1.1.0`; el ejemplo de `GET /parametros` (`api_version`) pasa a
  `"1.1.0"`.

### Notas de implementación (fuera de `openapi.yaml`)

Para que la documentación reflejara el comportamiento real se ajustó el backend:

- `Api\V1\ParametrosController::colecciones()` ahora expone
  `operaciones_diarias.tipos_mantenimiento`.
- `Api\V1\OperacionDiariaController` (`store`/`show`/`update`) ahora carga y devuelve la
  relación `mantenimientos_operacion`; nuevo método `pdf()` + ruta
  `GET /operacion-diaria/{operacionDiaria}/pdf`.
- `Api\V1\CargaCombustibleController`: nuevo método `pdf()` + ruta
  `GET /cargas/{carga}/pdf` (comprobante de egreso).
- `Api\V1\ValeController`: nuevos métodos `store()` y `show()`; ruta `vales` pasa a
  `->only(['index','store','show'])`. `ValeRequest::authorize()` exige `vales.crear` sólo en
  `POST` de la API (guard `web` explícito) + `failedValidation`/`failedAuthorization` con
  envelope JSON. `ParametrosController::colecciones()` agrega `id_conductor`/`conductor_asignado`
  a cada vehículo.
- `Api\V1\OrdenTrabajoController`: nuevo método `updateDetalle()` + ruta
  `PUT/PATCH /ordenes-trabajo/{orden}/detalles/{detalle}`; `ParametrosController::colecciones()`
  añade la acción `editar_detalle` a `ordenes_trabajo.acciones_tecnico`.
- `Api\V1\CargaMaterialController`: nuevos métodos `cerrar()` y `pagar()` + rutas
  `POST /cargas-material/{cargaMaterial}/cerrar` y `.../pagar`. `pagar()` consulta el permiso
  con guard explícito (`hasRole('super-admin') || hasPermissionTo('control-cargas.marcar-pagado', 'web')`)
  porque tras `auth:api` el guard por defecto es `api` y `->can()` no encuentra la permission
  de guard `web`.
- `SincronizarActividadesRealizadasAction`: `origen`, `destino` y `lugar` se leen con
  `?? null`. Antes, omitir cualquiera de ellos (como hacen los ejemplos, ya que son
  mutuamente excluyentes según el `tipo_medicion`) provocaba un `500`.
- Tests nuevos/ampliados: `tests/Feature/Api/V1/OperacionDiariaPdfControllerTest.php`,
  `tests/Feature/Api/V1/CargaCombustiblePdfControllerTest.php`,
  `tests/Feature/Api/V1/OperacionDiariaControllerTest.php`,
  `tests/Feature/Api/V1/CargaMaterialControllerTest.php` (cerrar/pagar),
  `tests/Feature/Api/V1/OrdenTrabajoControllerTest.php` (editar detalle),
  `tests/Feature/Api/V1/ValeControllerTest.php` (emitir vale).

## 1.0.0

Versión inicial documentada.
