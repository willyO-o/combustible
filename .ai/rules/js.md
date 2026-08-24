---
paths:
  - 'resources/js/**/*.vue'
---

# Js

## No i18n layer — hardcoded Spanish UI text
Write UI copy as hardcoded Spanish strings directly in Vue templates. Don't introduce vue-i18n, $t(), or Laravel's __()/trans() translation calls in the frontend. lang/ files are only for framework/validation messages, not app-authored feature text.

## Bootstrap modals must use useBootstrapModal(), never `new Modal()` directly
Bootstrap's Modal injects its backdrop directly into document.body, outside Vue's tree, and only removes it when the async hide() transition finishes. Inertia mounts a fresh page-component instance on every visit (including a redirect back to the "same" route after a form inside a modal submits, unless preserveState:true) — so the old instance, and the DOM the modal's transition depends on, gets torn down mid-close, orphaning the backdrop and blocking the whole page until a manual reload. Fixed app-wide (2026) by always going through resources/js/Composables/useBootstrapModal.js, which disposes the instance synchronously in onBeforeUnmount. Never call `new Modal(el)` / `.hide()` directly in a component — use `const modal = useBootstrapModal()` then `modal.mostrar(el)` / `modal.ocultar()`.

## Never watch(..., { deep: true }) on Inertia's page.props (or nested slices)
With `deep: true`, Vue skips the old-vs-new value comparison and always invokes the callback whenever the watched getter re-runs — not only when the value actually changed. `usePoll()` (header.vue polls notificaciones every 30s) reassigns `page.props` on every tick even for partial reloads that don't touch the props you care about, which re-runs any getter that reads through `page.props`. Combined, a `deep: true` watch on `page.props.flash` (Maindashboard.vue) fired every poll tick and re-toasted the same stale flash message every ~30s, since flash is never cleared client-side after being shown (Laravel's flash data ages out server-side after one request, but Inertia never re-delivers `flash` on a partial reload that excludes it).
Fix: watch the primitive leaf value directly (`() => page.props.flash?.success`), not the parent object with `deep: true`. Vue then does a proper Object.is comparison and only fires on a real change. Same trap applies to any other `watch()` reading through `page.props`.

## Quick-add flows inside an already-open Bootstrap modal use SweetAlert2, not a nested modal
Never open a second Bootstrap modal component (e.g. MaterialFormModal) while another Bootstrap modal is already open — two stacked `.modal`/backdrops render badly (double backdrop, z-index issues). For a "nivel 2" quick-add inside an open modal (e.g. adding a Material from within the "Registrar Viaje" modal in ControlCargas/Show.vue), use `Swal.fire({ input: 'text', preConfirm: async (val) => {...} })` (sweetalert2, already installed) instead — it renders above the Bootstrap modal cleanly. Use `Swal.showValidationMessage()` inside `preConfirm` to surface 422 validation errors, and return the created record from `preConfirm` so the caller gets it from `Swal.fire()`'s resolved `{ value }`. Reserve MaterialFormModal/VehiculoExternoFormModal (actual Bootstrap modals) for top-level pages where no other modal is already open (e.g. Materiales/Index.vue).

## SweetAlert2 inside a Bootstrap modal needs target: modalEl
When calling Swal.fire() while a Bootstrap modal is open (see "Quick-add flows inside an already-open Bootstrap modal use SweetAlert2" rule), always pass `target: modalEl.value` (the open modal's root element). Without it, SweetAlert2 mounts its popup on document.body, outside the modal's DOM subtree; Bootstrap's modal focus trap sees focus land outside `.modal` (via `_element.contains(event.target)`) and yanks it back on every keystroke, making the Swal input look disabled/unusable. Mounting the popup inside the modal element makes the focus trap treat it as part of the modal.
