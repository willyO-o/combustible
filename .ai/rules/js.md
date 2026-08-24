---
paths:
  - 'resources/js/**/*.vue'
---

# Js

## No i18n layer — hardcoded Spanish UI text
Write UI copy as hardcoded Spanish strings directly in Vue templates. Don't introduce vue-i18n, $t(), or Laravel's __()/trans() translation calls in the frontend. lang/ files are only for framework/validation messages, not app-authored feature text.

## Bootstrap modals must use useBootstrapModal(), never `new Modal()` directly
Bootstrap's Modal injects its backdrop directly into document.body, outside Vue's tree, and only removes it when the async hide() transition finishes. Inertia mounts a fresh page-component instance on every visit (including a redirect back to the "same" route after a form inside a modal submits, unless preserveState:true) — so the old instance, and the DOM the modal's transition depends on, gets torn down mid-close, orphaning the backdrop and blocking the whole page until a manual reload. Fixed app-wide (2026) by always going through resources/js/Composables/useBootstrapModal.js, which disposes the instance synchronously in onBeforeUnmount. Never call `new Modal(el)` / `.hide()` directly in a component — use `const modal = useBootstrapModal()` then `modal.mostrar(el)` / `modal.ocultar()`.
