---
paths:
  - 'app/Http/Requests/**/*.php'
---

# Requests

## Form Requests for resource store/update
Validate primary create/update endpoints with a dedicated Form Request class in app/Http/Requests. Reserve inline $request->validate() for small, single-purpose endpoints that aren't full resource mutations.

## Validation messages/attributes live in the Form Request
Put custom validation error text and field labels in the Form Request's messages()/attributes() methods, not in lang/*/validation.php.
