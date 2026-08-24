---
paths:
  - 'routes/*.php'
  - routes/web.php
---

# Routes

## Assign middleware in route files, not controllers
Attach middleware via ->middleware() on routes/route groups. Do not implement HasMiddleware, #[Middleware] attributes, or $this->middleware() inside controllers.

## "materiales" resource needs explicit ->parameters() (irregular Spanish plural)
Str::singular('materiales') incorrectly resolves to "materiale" (not "material"), so Route::resource('materiales', MaterialController::class) alone binds the URI segment as {materiale}, breaking implicit model binding against a $material controller parameter and $this->route('material') in MaterialRequest. Always add ->parameters(['materiales' => 'material']) for this resource. Same class of bug as the pre-existing TipoVehiculoRequest issue noted in .ai/rules/requests.md — check route:list for any new Spanish-plural resource before trusting the default.
