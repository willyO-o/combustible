---
paths:
  - 'resources/js/Pages/**/*.vue,resources/js/Components/SearchSelect.vue,resources/js/Utils/filtroUtil.js'
---

# Utils

## Espera de filtros y búsquedas: ESPERA_FILTROS_MS (700 ms), nunca un número suelto
Desde 2026-10-26 (pedido del usuario: los filtros se disparaban muy rápido) todos los debounce de filtros de listados y reportes (watch(filters) → setTimeout → router.get) y el :delay de SearchSelect.vue usan `ESPERA_FILTROS_MS` de resources/js/Utils/filtroUtil.js (700 ms). Antes cada pantalla tenía su número (300–500 ms). En un listado o reporte nuevo: `import { ESPERA_FILTROS_MS } from '@/Utils/filtroUtil'` y `}, ESPERA_FILTROS_MS)`. Para cambiar la espera en todo el sistema se edita sólo esa constante. NO aplica a CiInput.vue (450 ms: aviso de CI duplicado, no es un filtro) ni a los temporizadores de UI (PageLoader, ConexionAviso, Modal, sidebar, toasts).
