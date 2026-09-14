---
name: date-range-filter
description: Filtro de rango de fechas ("fecha desde/hasta") para listados de este proyecto, usando el componente reutilizable resources/js/Components/DateRangeFilter.vue (wrapper de daterange-picker-vue3 con presets en español y tema Vyzor) en vez de dos <input type="date"> sueltos. Usar al agregar o modificar un filtro de rango de fechas en cualquier página Vue (Index.vue de un listado), o cuando el usuario lo pida explícitamente ("filtro de fechas", "rango de fechas", "date range").
---

# Date Range Filter

Componente Vue reutilizable que reemplaza el patrón de dos `<input type="date">` (fecha desde /
fecha hasta) por un único selector de rango con presets en español, ya conectado al tema Vyzor
(colores y modo oscuro). Referencia real: `resources/js/Pages/Vales/Index.vue`.

## Cuándo usar esta skill

- Al agregar un filtro "fecha desde/hasta" a una página nueva (listado con filtros tipo
  `ListXxxAction`).
- Al encontrar dos `<input type="date">` sueltos en un filtro existente que conviene simplificar.
- Cuando el usuario lo pida explícitamente ("agrega un filtro de fechas", "rango de fechas").

No hace falta para un único campo de fecha (sin rango) — para eso sigue el patrón normal
`<input type="date">` del formulario correspondiente.

## Uso

```vue
<script setup>
import DateRangeFilter from '@/Components/DateRangeFilter.vue'
</script>

<template>
    <DateRangeFilter
        v-model:fecha-desde="filters.fecha_desde"
        v-model:fecha-hasta="filters.fecha_hasta"
        label="Fecha emisión"
        default-range="Este mes"
    />
</template>
```

- `v-model:fecha-desde` / `v-model:fecha-hasta`: string `'YYYY-MM-DD'` (sólo fecha, sin hora) —
  mismo formato que esperan los filtros `whereDate(...)` del backend (`ListXxxAction`). No hace
  falta transformar nada antes de mandarlo en el `router.get(...)`/`useForm` del listado.
- `label`: texto del `<label>` sobre el selector (opcional, default "Rango de fechas").
- `placeholder`: texto cuando no hay rango elegido (opcional).
- `opens`: `'left' | 'right' | 'center' | 'inline'`, hacia dónde abre el dropdown (default
  `'right'`).
- `default-range`: nombre exacto de uno de los presets (ver lista abajo). **Convención del
  proyecto: usar siempre `default-range="Este mes"`** salvo que el usuario pida explícitamente
  otro comportamiento — así está en las 6 páginas que ya usan este componente (Vales, Cargas de
  Combustible, Operación Diaria y los 3 reportes de combustible). Preselecciona ese rango cuando
  el padre todavía no trae `fechaDesde`/`fechaHasta` (primera carga sin filtros en la URL); al
  montar, el componente lo marca "activo" en la lista de presets **y** emite el rango hacia el
  `v-model` del padre, así el filtro/petición del listado queda sincronizado con lo marcado.
  Omitir el prop = sin selección por defecto.

## Presets incluidos (en español)

Hoy, Ayer, Últimos 3 días, Últimos 7 días, Esta semana (desde el lunes), Este mes (día 1 → hoy),
Mes anterior (día 1 → último día del mes calendario anterior), Últimos 3 meses, Últimos 6 meses,
Este año, Año pasado, y Rango personalizado (calendario manual). No hace falta tocar el
componente para usarlos — vienen todos activos siempre; sólo `default-range` controla cuál queda
preseleccionado al cargar la página.

## Qué NO hacer

- No uses dos `<input type="date">` para esto — ver la regla en `.ai/rules/pages.md`
  ("Filtros de rango de fechas usan DateRangeFilter.vue...").
- No reimportes el CSS de `daterange-picker-vue3` por componente — ya se importa una sola vez,
  globalmente, en `resources/js/app.js`.
- No agregues `append-to-body` — el teleport a `document.body` rompe el cálculo de posición del
  picker con el layout del template (sidebar fijo); se deja el posicionamiento CSS normal
  (`absolute`) que trae la librería por defecto.
- No agregues un bloque `<style>` en un componente nuevo que use esto — los overrides de color
  van en `resources/css/my-styles.css` (ver regla "Estilos de componentes nuevos..." en
  `.ai/rules/js.md`), usando las variables del tema (`--custom-white`, `--default-text-color`,
  `--primary-color`, `--primary01`, `--default-border`, etc.) para que funcione en modo claro y
  oscuro sin CSS aparte. Ese mismo archivo documenta un detalle importante de orden de imports:
  `my-styles.css` debe importarse en `app.js` **después** del CSS de las librerías vendor, o los
  overrides no ganan el cascade.
