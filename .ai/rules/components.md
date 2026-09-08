---
paths:
  - resources/js/Components/PageLoader.vue
---

# Components

## PageLoader usa v-show, no v-if (evita parpadeo del logo)
El overlay de PageLoader.vue se muestra/oculta con v-show, NO con v-if. Con v-if el subárbol se recreaba en cada navegación de Inertia y el <img> del logo (logo-plus-metals.svg) tardaba un frame en decodificarse -> "a veces no cargaba el logo". Con v-show el <img> se descarga/decodifica una vez al montar la app y queda listo. Oculto (display:none) no tiene costo de render ni corre las animaciones CSS. El componente se monta una sola vez a nivel raíz en app.js. No revertir a v-if.
Estilos del loader: en resources/css/my-styles.css (sección "PageLoader.vue"), no en <style> del .vue.
