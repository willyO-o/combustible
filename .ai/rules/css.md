---
paths:
  - 'resources/js/Pages/Operacion/Create.vue,resources/css/my-styles.css'
---

# Css

## Operación diaria: franja de lecturas calculadas (.op-calc) — horas + recorrido/horómetro
En Operacion/Create.vue, la antigua insignia "Horas trabajadas" se reemplazó (2026-10-13, pedido del usuario) por la franja `.op-calc` con dos celdas: Horas trabajadas (horasTrabajadasCalculadas, de las fechas) y, según tipoMedicion, Recorrido (km) u Horas de horómetro (computed lecturaCalculada = fin − inicio, redondeo a 1 decimal; estado 'pendiente' | 'invalido' (fin <= inicio, mismo criterio que el gt: del backend) | 'ok'). Sólo referencia: NO se envía ni se persiste. Horómetro: usoDeJornada = % de la jornada y aviso ámbar si supera horas trabajadas + 0.1 h (lectura mal tipeada). Labels de lectura: "Horómetro inicial/final".
CSS en my-styles.css bajo `.op-calc*` (tokens del tema, claro/oscuro). Divisor = box-shadow 1px izquierda+arriba recortado por overflow:hidden (apila en angosto). Ámbar/coral del tema no llegan a 4.5:1 como texto → `--op-calc-tinta: color-mix(... 50%, var(--default-text-color))`; el minificador borra el fallback duplicado, pero sin color-mix el color se hereda (aceptable). Sin tests JS en el proyecto (no hay vitest/jest).

## Operación diaria: tarjetas de actividad según tipo de medición (.op-act)
Desde 2026-10-19 (pedido del usuario) el resumen de "Actividades realizadas" en Operacion/Create.vue ya no muestra siempre Origen/Destino/Cantidad: lista <ol class="op-act-lista"> con una tarjeta .op-act por actividad. Kilometraje (esRecorrido(r) = tipoMedicion === 'kilometraje'; sin vehículo elegido, por si la actividad trae origen/destino) → "Recorrido" origen → destino + "Material" (o "Sin carga"); horómetro → "Lugar"; actividades antiguas que traen lugar en un vehículo por km lo muestran igual. Cantidad = cantidad + unidad o "—". Config de textos por tipo en MODOS_ACTIVIDAD (etiqueta, icono, descripción, ejemplo de unidad) → modoActual: badge en el header de la tarjeta y en el modal "Nueva actividad" (que antes tenía un texto de relleno de plantilla). agregarActividad() hace push de formActividad.data() (antes {...formActividad}, que arrastraba errors/processing/métodos de useForm) y vacía el campo que no corresponde al tipo (lugar en km; origen/destino/id_material en horómetro). abrirModalActividad()/cerrarModalActividad() limpian errores. CSS .op-act* en my-styles.css (tokens del tema; texto de error con color-mix como .op-calc).
