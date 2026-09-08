---
paths:
  - resources/js/Data/app.js
---

# Data

## Nombre del sistema: SIGET (no "Combustible")
El sistema se llama SIGET; nombre completo "Sistema Integral de Gestión de Maquinaria y Operaciones". Antes se lo llamaba "Sistema de Control de Combustible" / "Combustible" — ya no usar eso para el producto (sí para el dominio: carga/tipo de combustible).
Fuentes de verdad:
- Backend: config('app.name') = SIGET, config('app.long_name') = nombre completo (env APP_NAME / APP_LONG_NAME).
- Frontend: import { APP_NAME, APP_LONG_NAME } from '@/Data/app' (leen VITE_APP_NAME / VITE_APP_LONG_NAME con fallback).
Usados en: title de app.blade.php + metas PWA, Inertia title template en app.js, manifest de vite.config.js, Login.vue (marca), Vales/Publico.vue (fallback de nombre de empresa y footer), openapi.yaml (info.title, ejemplo app_name), swagger.blade.php.
NO confundir con el nombre de la empresa cliente ("Plus Metals Ltda" / ParametrosEmpresa.nombre_empresa), que es un dato configurable en BD y tiene prioridad donde se usa.
Cambiar APP_NAME en .env cambia el prefijo de cookie de sesión (Str::slug(APP_NAME).'-session') -> al desplegar, un re-login único.
