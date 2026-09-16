---
paths:
  - 'resources/js/app.js,resources/js/Pages/**/*.vue,vite.config.js'
---

# Js Js Pages

## Blindaje global contra "Cannot assign to property 'inheritAttrs' of [object Module]" en producción
Bug de interop de Rollup en build de producción (nunca en `npm run dev`): un import() dinámico de una página puede resolver al namespace del módulo ES (congelado) en vez de a `module.default`. `@inertiajs/vue3` intenta desenvolverlo con `module.default || module`, pero si falla igual cae en el namespace crudo y Vue truena al escribirle `inheritAttrs`. Ya estaba parchado puntualmente en DateRangeFilter.vue (`desenvolverComponente()`, ver su comentario) para el import de `daterange-picker-vue3`; ahora también está blindado GLOBALMENTE en `resolve()` de `createInertiaApp` (resources/js/app.js): `module.default ?? module`, y si el resultado sigue `Object.isFrozen()`, se clona con spread (`{...pagina}`) antes de dárselo a Inertia — así ninguna página, presente o futura, puede volver a pegar este error sin importar cuál sea el módulo/librería que lo dispare.

`vite.config.js` tiene `build.sourcemap: 'hidden'` (genera .map en public/build/assets pero SIN el comentario sourceMappingURL, así que el navegador del usuario final nunca los pide) — útil para mapear un stack minificado de un error sólo-en-producción a su archivo .vue real: `node -e "const {TraceMap,originalPositionFor}=require('@jridgewell/trace-mapping'); ..."` sobre el .map correspondiente al chunk del stack trace.
