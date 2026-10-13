---
paths:
  - 'resources/js/Pages/Operacion/Create.vue,resources/css/my-styles.css'
---

# Css

## Operación diaria: franja de lecturas calculadas (.op-calc) — horas + recorrido/horómetro
En Operacion/Create.vue, la antigua insignia "Horas trabajadas" se reemplazó (2026-10-13, pedido del usuario) por la franja `.op-calc` con dos celdas: Horas trabajadas (horasTrabajadasCalculadas, de las fechas) y, según tipoMedicion, Recorrido (km) u Horas de horómetro (computed lecturaCalculada = fin − inicio, redondeo a 1 decimal; estado 'pendiente' | 'invalido' (fin <= inicio, mismo criterio que el gt: del backend) | 'ok'). Sólo referencia: NO se envía ni se persiste. Horómetro: usoDeJornada = % de la jornada y aviso ámbar si supera horas trabajadas + 0.1 h (lectura mal tipeada). Labels de lectura: "Horómetro inicial/final".
CSS en my-styles.css bajo `.op-calc*` (tokens del tema, claro/oscuro). Divisor = box-shadow 1px izquierda+arriba recortado por overflow:hidden (apila en angosto). Ámbar/coral del tema no llegan a 4.5:1 como texto → `--op-calc-tinta: color-mix(... 50%, var(--default-text-color))`; el minificador borra el fallback duplicado, pero sin color-mix el color se hereda (aceptable). Sin tests JS en el proyecto (no hay vitest/jest).
