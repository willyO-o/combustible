---
paths:
  - 'resources/js/**/*.vue'
---

# Js

## No i18n layer — hardcoded Spanish UI text
Write UI copy as hardcoded Spanish strings directly in Vue templates. Don't introduce vue-i18n, $t(), or Laravel's __()/trans() translation calls in the frontend. lang/ files are only for framework/validation messages, not app-authored feature text.
