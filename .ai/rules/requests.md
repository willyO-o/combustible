---
paths:
  - 'app/Http/Requests/**/*.php'
  - 'app/Http/Requests/*.php'
---

# Requests

## Form Requests for resource store/update
Validate primary create/update endpoints with a dedicated Form Request class in app/Http/Requests. Reserve inline $request->validate() for small, single-purpose endpoints that aren't full resource mutations.

## Validation messages/attributes live in the Form Request
Put custom validation error text and field labels in the Form Request's messages()/attributes() methods, not in lang/*/validation.php.

## Route::resource with custom ->parameters() renames the FormRequest route-binding key
When a resource route uses ->parameters(['plural-kebab' => 'camelCaseName']) (e.g. tipos-vehiculo => tipoVehiculo), the bound model is only reachable in the Form Request via $this->route('camelCaseName'), NOT the snake_case/kebab version. Using the wrong key silently returns null — Rule::unique(...)->ignore(null) then matches the record's own row and every update() fails validation on unchanged unique fields. Found this pre-existing bug in TipoVehiculoRequest (route('tipo_vehiculo') vs the real key tipoVehiculo) while adding intervalo_mantenimiento_tipo support — check the route's ->parameters() mapping (or run route:list) before writing $this->route('...') in a Form Request.
