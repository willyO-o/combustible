---
paths:
  - resources/js/Pages/Operacion/Create.vue
---

# Resources Js Pages Operacion

## Operación diaria en alta: jornada por defecto 08:00 – 18:00
A pedido del usuario (2026-10-16): en un alta, fecha_inicio = hoy 08:00 (const HORA_INGRESO) y fecha_fin = hoy 18:00 (const HORA_SALIDA), ambas editables y también usadas como horaSugerida del DateTimeInput. Reemplaza el 08:30 / fecha_fin vacía citado en components-js-pages.md. En edición se conservan las fechas guardadas.
