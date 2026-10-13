---
paths:
  - 'resources/js/Components/DateTimeInput.vue,resources/js/Pages/**/*.vue'
---

# Components Js Pages

## Fecha+hora: usar DateTimeInput (24 h fijo), nunca input datetime-local
`<input type="datetime-local">` lo dibuja el navegador según la configuración regional del equipo (12 h a. m./p. m. en unos, 24 h en otros, mes/día en inglés). Desde 2026-10-13 se usa resources/js/Components/DateTimeInput.vue (wrapper de daterange-picker-vue3, mismo paquete que DateRangeFilter): texto fijo 'dd/mm/aaaa HH:mm', selector de hora 00-23 / minutos 00-59 sin AM/PM. v-model = MISMO string que el input nativo 'YYYY-MM-DDTHH:mm' (acepta también 'YYYY-MM-DD HH:mm' de fecha_i_f/fecha_emision del backend) → validación y cálculos no cambian. Props: invalid, disabled, placeholder, horaSugerida ('HH:mm' propuesta al abrir vacío), opens. Usado en Operacion/Create.vue (fecha_inicio/fecha_fin) y Vales/Create.vue (fecha_emision, disabled).
Trampas del paquete que el componente neutraliza (no quitar): (1) escribe directo sobre el objeto v-model (Reflect.set) antes de "Aplicar" → el valor real vive en props.modelValue y al cerrarse (toggle false) se reconstruye en nextTick; (2) timePickerIncrement por defecto 5 → se fuerza 1 (si no, un 08:37 guardado se pierde); (3) el selector de hora sólo aparece con fecha → al abrir vacío se propone hoy+horaSugerida; (4) autoApply debe ser false (con true se cierra al tocar el día, antes de elegir la hora); (5) showDropdowns=false: los select de mes/año no siguen el tema oscuro. Errores: <div class="text-danger small mt-1"> aparte (no hay input-group). Operación diaria en alta: fecha_inicio = hoy 08:30 (const HORA_INGRESO, hora de ingreso del personal). Verificado en Chrome headless con un harness (23 escenarios: cancelar/Esc/clic afuera no cambian, aplicar emite formato nativo, 00:00/23:59, vacío, disabled) + capturas claro/oscuro; sin tests JS en el proyecto.
