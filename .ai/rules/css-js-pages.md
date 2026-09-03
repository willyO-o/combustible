---
paths:
  - 'resources/css/my-styles.css,resources/js/Pages/**/*.vue'
---

# Css Js Pages

## Los .form-switch de Bootstrap necesitan el fix global de my-styles.css (choque con @tailwindcss/forms)
`@tailwindcss/forms` (cargado por `@tailwind base` en resources/css/app.css, plugin en tailwind.config.js) reestiliza TODOS los `[type=checkbox]`/`[type=radio]` nativos del proyecto. Su regla `input:where([type=checkbox]):checked{background-size:100% 100%;background-position:50%}` (especificidad 0,1,1 — el `:where` no aporta) le gana a `.form-check-input{background-size:contain}` de Bootstrap (0,1,0), así que la perilla de un `.form-switch` al activarse se estiraba y quedaba un punto grande al centro en vez de un círculo deslizado a la derecha.

Fix GLOBAL ya aplicado en `resources/css/my-styles.css` (al final): `.form-switch .form-check-input{background-repeat:no-repeat;background-size:contain;background-position:left center}` + `:checked{background-position:right center}` — especificidad 0,2,0, le gana al plugin, y my-styles.css se importa último en app.js. Corrige TODOS los switches del proyecto de una vez. NO hace falta override por página. El override específico de `.mant-row .form-switch` (Operacion/Create.vue) es más específico y sigue mandando en ese contexto (redibuja la perilla con tokens del tema). El theme switcher usa `.form-check.switch-select` (radios), no `.form-switch` — no lo afecta.
