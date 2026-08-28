---
paths:
  - database/seeders/UserSeeder.php
---

# Seeders

## Rol tecnico-mantenimiento: solo órdenes de trabajo, sin operación diaria
permisosParaTecnicoMantenimiento() SOLO tiene mantenimiento.ordenes.{ver,estado.cambiar,ejecucion.registrar}. No incluir operacion-diaria.* (se quitó a propósito: un técnico solo ejecuta el trabajo asignado en órdenes de trabajo). Cubierto por tests/Feature/UserSeederTest.php. Nota: operacion-diaria no tiene gating de backend (ninguna ruta usa can:), el permiso solo controla menú/botones v-can en el frontend.
