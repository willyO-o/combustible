---
paths:
  - resources/js/Components/PageLoader.vue
---

# Components

## PageLoader usa v-show, no v-if (evita parpadeo del logo)
El overlay de PageLoader.vue se muestra/oculta con v-show, NO con v-if. Con v-if el subárbol se recreaba en cada navegación de Inertia y el <img> del logo (logo-plus-metals.svg) tardaba un frame en decodificarse -> "a veces no cargaba el logo". Con v-show el <img> se descarga/decodifica una vez al montar la app y queda listo. Oculto (display:none) no tiene costo de render ni corre las animaciones CSS. El componente se monta una sola vez a nivel raíz en app.js. No revertir a v-if.
Estilos del loader: en resources/css/my-styles.css (sección "PageLoader.vue"), no en <style> del .vue.

## PageLoader: contenido = logo + "Cargando…" + barra "lingote"
El loader es: logo estático (logo-plus-metals.svg; en dark se invierte a blanco con `filter: brightness(0) invert(1)`), texto "Cargando…" con puntos parpadeantes, y una barra fina (`.page-loader-bar`) donde una veta naranja óxido (#cb5d00→#ff8a3d) barre de lado a lado (`::before` + `@keyframes page-loader-sweep`, anima `left`). La pista de la barra usa `--bs-secondary-bg` / `--default-border` para seguir el tema. Se probaron y descartaron: ícono de surtidor de RemixIcon, logo con relleno de abajo a arriba, y cubos 3D "PLUS METALS" (colada). prefers-reduced-motion: barra estática con la veta al centro tenue.
