# Cambios en la API v1 — para el equipo Flutter

**Fecha:** 2026-08-28
**Base URL:** `/api/v1`
**Documentación completa (Swagger):** `public/docs/openapi.yaml` (abrir el visor de la API)

Este documento resume **todo lo que cambió en la API** en esta ronda. Está dividido en:

1. Cambios que **rompen supuestos** del cliente actual (revisar sí o sí).
2. Cambios de comportamiento menores.
3. Módulo **nuevo**: Órdenes de Trabajo (técnico de mantenimiento).
4. Usuarios de prueba.
5. Checklist de acciones para el front.

---

## 1. Cambios que ROMPEN supuestos — `GET /parametros/colecciones`

> Este endpoint es el que alimenta los `select` / catálogos de la app. Cambiaron
> nombres de campos y valores. **Hay que actualizar los modelos de Dart.**

### 1.1 `data.estaciones_servicio[]` — cambió la forma del objeto

Antes cada grifo venía así:

```json
{ "id": 1, "nombre_grifo": "Grifo Central YPFB", "estado_grifo": "ACTIVO" }
```

**Ahora** se devuelve el registro completo del grifo (se renombró la columna en la base de datos: `nombre_grifo` ya **no existe**):

```json
{
  "id": 1,
  "razon_social": "Grifo Central YPFB S.R.L.",
  "nit": "1023456789",
  "direccion": "Av. 6 de Agosto #123",
  "ciudad": "Oruro",
  "telefono": "25551234",
  "estado_grifo": "ACTIVO",
  "es_principal": true,
  "created_at": "...",
  "updated_at": "..."
}
```

➡️ **Acción front:** usar `razon_social` en vez de `nombre_grifo` para mostrar el nombre del grifo.

### 1.2 `data.tipos_combustible[]` — cambió un campo

Antes: `{ "id": 1, "nombre_tipo_combustible": "Diésel", "estado_tipo_combustible": "ACTIVO" }`

**Ahora:** `nombre_tipo_combustible` → **`tipo_combustible`**

```json
{ "id": 1, "tipo_combustible": "Diésel", "estado_tipo_combustible": "ACTIVO" }
```

### 1.3 `data.cargas_combustible.estado_carga` — cambiaron los valores

Antes: `["PENDIENTE", "USADO", "ANULADO"]` ← **estaban mal** (eran los estados de un *vale*).

**Ahora:** `["REGISTRADO", "VERIFICADO", "ANULADO"]` ← estos son los estados reales de una carga de combustible.

### 1.4 `data.vehiculos[]` — mismo formato, distinto contenido y ya no falla

- **La forma de cada vehículo NO cambió** (`id, uuid, nro_placa, codigo, anio, marca, modelo, estado_vehiculo, id_tipo_combustible, id_tipo_vehiculo, url_fotografia, tipo_medicion`).
- **Cambió qué vehículos incluye:** ahora unifica (sin duplicados) los vehículos que el usuario tiene **asignados como conductor** + **todos los vehículos activos de las áreas que administra como jefe de área**.
- Un usuario puede tener ambos roles, uno solo, o ninguno.
- **Si no tiene ninguno, `data.vehiculos` llega como `[]`** (lista vacía) — el front debe soportar ese caso.
- **Ya NO devuelve error 500** cuando el usuario no tiene un registro de conductor (antes, un jefe de área o un técnico reventaba este endpoint). El caso de respuesta `500` "no tiene persona/conductor asociado" **fue eliminado del contrato**.

### 1.5 `data.ordenes_trabajo` — **NUEVO** bloque

Se agregó una colección de apoyo para el módulo de órdenes de trabajo (ver sección 3):

```json
"ordenes_trabajo": {
  "estados_orden": ["PENDIENTE", "EN_EJECUCION", "CULMINADO", "CANCELADO", "VERIFICADO"],

  "estados": [
    { "value": "PENDIENTE",    "label": "Pendiente",    "descripcion": "La orden fue emitida y está a la espera de que el técnico inicie el trabajo." },
    { "value": "EN_EJECUCION",  "label": "En ejecución", "descripcion": "El técnico inició el mantenimiento y está registrando el detalle del trabajo realizado." },
    { "value": "CULMINADO",     "label": "Culminado",    "descripcion": "El técnico terminó el trabajo y registró las lecturas finales. El detalle queda congelado." },
    { "value": "VERIFICADO",    "label": "Verificado",   "descripcion": "El usuario que emitió la orden validó la ejecución. Estado final." },
    { "value": "CANCELADO",     "label": "Cancelado",    "descripcion": "La orden fue anulada y no se ejecutará." }
  ],

  "acciones_tecnico": [
    { "accion": "iniciar",          "metodo": "PATCH",  "ruta": "ordenes-trabajo/{orden}/iniciar",            "desde": ["PENDIENTE"],                  "estado_resultante": "EN_EJECUCION" },
    { "accion": "agregar_detalle",  "metodo": "POST",   "ruta": "ordenes-trabajo/{orden}/detalles",           "desde": ["PENDIENTE", "EN_EJECUCION"],  "estado_resultante": null },
    { "accion": "eliminar_detalle", "metodo": "DELETE", "ruta": "ordenes-trabajo/{orden}/detalles/{detalle}", "desde": ["PENDIENTE", "EN_EJECUCION"],  "estado_resultante": null },
    { "accion": "culminar",         "metodo": "POST",   "ruta": "ordenes-trabajo/{orden}/culminar",           "desde": ["EN_EJECUCION"],               "estado_resultante": "CULMINADO" }
  ],

  "tipos_mantenimiento": [
    { "id": 3, "tipo_mantenimiento": "Cambio de aceite" }
  ],

  "repuestos": [
    { "id": 8, "nombre_repuesto": "Filtro de aceite", "codigo_repuesto": "FIL-001", "unidad_medida": "UNIDAD", "stock_actual": 10 }
  ]
}
```

- `tipos_mantenimiento` y `repuestos` sólo traen los **activos**.
- `estados` sirve para pintar chips/estados con etiqueta y descripción.
- `acciones_tecnico` es una "máquina de estados" lista para consumir: qué puede hacer el técnico, con qué método/ruta, desde qué estados y a qué estado lleva la orden.

---

## 2. Cambios de comportamiento menores

### 2.1 Carga de combustible — `estado_carga` por defecto

Al crear una carga con `POST /cargas` **sin** enviar `estado_carga`, antes quedaba `null` en la base de datos. **Ahora queda `"REGISTRADO"`** automáticamente.

➡️ En `GET /cargas` los ítems ahora siempre traen `estado_carga` con valor (`"REGISTRADO"` / `"VERIFICADO"` / `"ANULADO"`), ya no `null`. Si el front lo enviaba explícitamente, no cambia nada.

---

## 3. MÓDULO NUEVO — Órdenes de Trabajo (técnico de mantenimiento)

Tag en Swagger: **"Órdenes de Trabajo"**.

Es el **Paso 3 del flujo de mantenimiento** desde la app del técnico: consulta sus
órdenes asignadas, marca el inicio del trabajo, va registrando el detalle **ítem por
ítem**, y al terminar marca la orden como culminada con las lecturas finales del
vehículo.

**La emisión de la orden (Paso 2) y los estados `VERIFICADO` / `CANCELADO` siguen
siendo sólo del panel web.** El técnico sólo maneja:

```
PENDIENTE  --(iniciar)-->  EN_EJECUCION  --(culminar)-->  CULMINADO
                                │
                                └── agregar / eliminar ítems de detalle
```

Todos los endpoints requieren `Authorization: Bearer {token}`.

### Reglas de acceso (todas las rutas del módulo)

- Un **técnico de mantenimiento** sólo puede ver/operar las órdenes que tiene
  asignadas (`id_usuario_ejecuta` = su usuario). Cualquier otra → **`403`**.
- Jefe de área / administrador / super-admin pueden operar cualquier orden.

---

### 3.1 `GET /ordenes-trabajo` — listar mis órdenes

Lista **paginada** (misma estructura de paginación que el resto de listados:
`current_page`, `data`, `last_page`, `per_page`, `total`, `next_page_url`, ...).

**Query params (todos opcionales):**

| Param | Ejemplo | Nota |
|---|---|---|
| `estado_orden` | `EN_EJECUCION` | filtra por estado exacto |
| `id_vehiculo` | `1` | |
| `per_page` | `10` | default 10 |
| `page` | `1` | default 1 |

El técnico recibe **sólo sus órdenes asignadas** automáticamente (no hace falta filtrar).

**Cada ítem de `data[]`:**

```json
{
  "id": 1,
  "id_solicitud_mantenimiento": 12,
  "id_vehiculo": 1,
  "id_conductor": 1,
  "id_taller": null,
  "id_usuario_emite": 2,
  "id_usuario_ejecuta": 5,
  "nro_orden": 1,
  "gestion": "2026",
  "fecha_emision": "2026-08-27T14:30:00.000000Z",
  "fecha_ejecucion": null,
  "fecha_culminacion": null,
  "nota_emisor": "Revisar también el sistema de frenos.",
  "observacion": null,
  "tipo_mantenimiento": "PREVENTIVO",
  "kilometraje_actual": 85000,
  "horometro_actual": null,
  "estado_orden": "PENDIENTE",
  "nro": "000001/2026",
  "tipo_orden": "INTERNO",
  "detalles_count": 3,
  "created_at": "...",
  "updated_at": "...",
  "vehiculo": { "id": 1, "codigo": "PM-TRA-0001", "nro_placa": "148-JLK", "marca": "Volvo", "modelo": "FMX", "tipo_medicion": "kilometraje", "url_fotografia": null },
  "solicitudMantenimiento": { "id": 12, "nro_solicitud": 12, "gestion": 2026, "descripcion_problema": "hay derrame de aceite", "nro": "000012", "fecha": null },
  "taller": null,
  "usuarioEmite": { "id": 2, "name": "Jefe de Transportes" }
}
```

> Los objetos anidados pueden traer campos calculados extra (`nro`, `url_fotografia`,
> etc.); ignorar los que no se usen. La lista canónica está en el `openapi.yaml`.

- `nro` → correlativo formateado (`"000001/2026"`), útil para mostrar.
- `tipo_orden` → `"INTERNO"` o `"EXTERNO"` (derivado: EXTERNO si tiene `id_taller`).
- `detalles_count` → cuántos ítems de detalle ya tiene (sólo en el listado).
- `vehiculo.tipo_medicion` → **clave para saber qué lectura pedir** (`kilometraje` u `horometro`).

---

### 3.2 `GET /ordenes-trabajo/{orden}` — ver una orden con su detalle

Devuelve la orden completa + **todos los ítems de detalle** ya cargados.

```json
{
  "data": {
    "...": "todos los campos de la orden (ver 3.1)",
    "vehiculo": { "...": "objeto vehículo completo" },
    "conductor": { "id": 1, "persona": { "id": 1, "nombres": "Juan", "paterno": "García", "materno": "López", "...": "" } },
    "solicitudMantenimiento": { "...": "" },
    "taller": { "...": "o null" },
    "usuarioEmite": { "id": 2, "name": "Jefe de Transportes" },
    "usuarioEjecuta": { "id": 5, "name": "Técnico Mantenimiento 1" },
    "detalles": [
      {
        "id": 15,
        "id_orden_trabajo": 1,
        "id_repuesto": 8,
        "id_tipo_mantenimiento": 3,
        "fecha": "2026-08-27",
        "horometro": null,
        "kilometraje": "85230.50",
        "cantidad": 2,
        "created_at": "...",
        "updated_at": "...",
        "repuesto": { "id": 8, "nombre_repuesto": "Filtro de aceite", "codigo_repuesto": "FIL-001", "unidad_medida": "UNIDAD" },
        "tipo_mantenimiento": { "id": 3, "tipo_mantenimiento": "Cambio de aceite" }
      }
    ]
  }
}
```

- `detalle.repuesto` es `null` cuando el ítem es sólo mano de obra (`id_repuesto` null).
- `horometro` / `kilometraje` vienen como **string decimal** (`"85230.50"`) o `null`.

**Errores:** `403` si la orden no es del técnico · `404` si no existe.

---

### 3.3 `PATCH /ordenes-trabajo/{orden}/iniciar` — marcar como iniciado

**Sin cuerpo.** Pasa la orden de `PENDIENTE` → `EN_EJECUCION` y registra `fecha_ejecucion`.

**Respuesta `200`:**

```json
{
  "message": "Mantenimiento iniciado.",
  "data": { "...": "la orden actualizada (estado_orden = EN_EJECUCION)" }
}
```

**Errores:**

| Código | Caso | Cuerpo |
|---|---|---|
| `422` | la orden no está `PENDIENTE` | `{ "message": "Sólo se puede iniciar una orden que está PENDIENTE." }` |
| `403` | la orden no es del técnico | `{ "message": "No tiene acceso a esta orden de trabajo." }` |
| `404` | no existe | — |

---

### 3.4 `POST /ordenes-trabajo/{orden}/detalles` — agregar UN ítem de detalle

Se llama **una vez por cada ítem** (repuesto/insumo aplicado, o mano de obra). No hace
falta mandar todos juntos.

**Cuerpo (`application/json`):**

```json
{
  "id_tipo_mantenimiento": 3,      // requerido — de ordenes_trabajo.tipos_mantenimiento
  "id_repuesto": 8,                // opcional (null = mano de obra) — de ordenes_trabajo.repuestos
  "fecha": "2026-08-27",           // requerido (YYYY-MM-DD)
  "kilometraje": 85230.5,          // requerido SI vehiculo.tipo_medicion == "kilometraje"
  "horometro": null,               // requerido SI vehiculo.tipo_medicion == "horometro"
  "cantidad": 2                    // requerido, entero >= 1
}
```

- `id_orden_trabajo` sale de la URL, **no se envía**.
- Sólo se puede agregar mientras la orden está `PENDIENTE` o `EN_EJECUCION`.

**Respuesta `201`:**

```json
{
  "message": "Detalle de mantenimiento registrado exitosamente.",
  "data": { "...": "el ítem creado, con repuesto y tipo_mantenimiento embebidos (ver 3.2)" }
}
```

**Errores:**

| Código | Caso | Cuerpo |
|---|---|---|
| `422` (validación) | falta un campo / lectura equivocada | `{ "success": false, "message": "Los datos enviados no son válidos.", "errors": { "kilometraje": ["El kilometraje es obligatorio."] } }` |
| `422` (estado) | la orden ya fue culminada/cancelada | `{ "message": "La orden ya no admite cambios en su detalle de trabajo (sólo mientras está PENDIENTE o EN_EJECUCION)." }` |
| `403` | la orden no es del técnico | `{ "message": "Sólo puede registrar el detalle de las órdenes que tiene asignadas." }` |

> ⚠️ Nótese que el `422` de validación trae `{ success, message, errors }` y el `422`
> de "estado no permitido" trae sólo `{ message }`. Distinguirlos por la presencia de
> `errors`.

---

### 3.5 `DELETE /ordenes-trabajo/{orden}/detalles/{detalle}` — quitar un ítem

Para borrar un ítem cargado por error. Sólo mientras la orden está `PENDIENTE` / `EN_EJECUCION`.

**Respuesta `200`:** `{ "message": "Detalle de mantenimiento eliminado exitosamente." }`

**Errores:** `403` (orden ajena) · `404` (el ítem no existe o no pertenece a esa orden) · `422` (orden ya congelada).

---

### 3.6 `POST /ordenes-trabajo/{orden}/culminar` — marcar como culminado

Cierra la ejecución: `EN_EJECUCION` → `CULMINADO`, registra `fecha_culminacion` y las
**lecturas finales** del vehículo. Después de esto el detalle queda **congelado**.

**Requisitos:**
- La orden debe estar **`EN_EJECUCION`** (hay que iniciarla antes).
- Debe tener **al menos 1 ítem de detalle**.

**Cuerpo (`application/json`):**

```json
{
  "kilometraje_actual": 85300,     // entero — requerido SI vehiculo.tipo_medicion == "kilometraje"
  "horometro_actual": null,        // entero — requerido SI vehiculo.tipo_medicion == "horometro"
  "observacion": "Se cambió aceite y filtros. Todo en orden."   // opcional, máx 500
}
```

**Respuesta `200`:**

```json
{
  "message": "Mantenimiento culminado exitosamente.",
  "data": { "...": "la orden con estado_orden = CULMINADO, fecha_culminacion, y detalles[] embebidos" }
}
```

**Errores:**

| Código | Caso | Cuerpo |
|---|---|---|
| `422` (validación) | falta la lectura final | `{ "success": false, "message": "Los datos enviados no son válidos.", "errors": { "kilometraje_actual": ["El kilometraje actual es obligatorio."] } }` |
| `422` (estado) | la orden no está `EN_EJECUCION` | `{ "message": "Sólo se puede culminar una orden que está EN_EJECUCION (primero debe iniciarla)." }` |
| `422` (sin detalle) | no hay ítems | `{ "message": "Debe registrar al menos un ítem del detalle antes de culminar la orden." }` |
| `403` | la orden no es del técnico | `{ "message": "Sólo puede culminar la ejecución de las órdenes que tiene asignadas." }` |

---

## 4. Usuarios de prueba

Los usuarios de **técnico de mantenimiento** ya existen en el entorno de pruebas y se
puede entrar con ellos por `POST /auth/login` (misma tabla que el resto):

| Rol | Email | Contraseña |
|---|---|---|
| tecnico-mantenimiento | `tecnico1@gmail.com` | `tecnico123` |
| tecnico-mantenimiento | `tecnico2@gmail.com` | `tecnico123` |

> Nota: un técnico normalmente **no tiene `persona` vinculada**. `GET /auth/me` y
> `POST /auth/login` responden igual, pero los campos derivados de la persona
> (`nombre_completo`, `nombres`, `paterno`, `ci`, `celular`, `direccion`, ...) vienen
> `null`. `roles` traerá `["tecnico-mantenimiento"]`.

---

## 5. Checklist para el front

- [ ] `colecciones`: en `estaciones_servicio` usar **`razon_social`** (ya no `nombre_grifo`); el objeto ahora trae más campos (`nit`, `direccion`, `ciudad`, `telefono`, `es_principal`).
- [ ] `colecciones`: en `tipos_combustible` usar **`tipo_combustible`** (ya no `nombre_tipo_combustible`).
- [ ] `colecciones`: `cargas_combustible.estado_carga` ahora es **`[REGISTRADO, VERIFICADO, ANULADO]`**.
- [ ] `colecciones`: manejar `data.vehiculos == []` (usuario sin conductor ni área). Ya **no** hay `500` en este endpoint.
- [ ] `colecciones`: mapear el nuevo bloque `data.ordenes_trabajo` (estados + acciones + catálogos).
- [ ] Nuevo modelo Dart `OrdenTrabajo` + `DetalleMantenimiento` (ver 3.1 / 3.2).
- [ ] Pantalla del técnico: listar (`GET /ordenes-trabajo`) → detalle (`GET /ordenes-trabajo/{id}`) → iniciar → agregar ítems 1×1 → culminar.
- [ ] Elegir la lectura (`kilometraje` vs `horometro` / `kilometraje_actual` vs `horometro_actual`) según `orden.vehiculo.tipo_medicion`.
- [ ] Distinguir los dos tipos de `422` por la presencia de la clave `errors`.
- [ ] Deshabilitar los botones "agregar ítem" / "eliminar ítem" / "culminar" cuando `estado_orden` no esté en `PENDIENTE` / `EN_EJECUCION`.

---

*Cualquier duda sobre un endpoint puntual, el contrato exacto (campos, enums, ejemplos y
todos los códigos de error) está en `public/docs/openapi.yaml`.*
