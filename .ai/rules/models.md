---
paths:
  - 'app/Models/**'
  - 'app/Models/*.php'
---

# Models

## Eventos de modelo con closures en booted()
Registra eventos de modelo con closures dentro de `booted()` o `boot()` (ej. `static::creating(fn() => ...)`). No uses el patrón Observer ni `#[ObservedBy]`.

## Mass assignment via #[Fillable] attribute
Declare mass-assignable fields with the #[Fillable([...])] class attribute (Illuminate\Database\Eloquent\Attributes\Fillable), not a protected $fillable or $guarded property.

## Accessors use legacy getXxxAttribute() style
Write accessors as legacy public function getXxxAttribute() methods, not the Attribute::make() class-based style.

## Secondary UUID column for public-facing identifiers
Keep primary keys auto-incrementing integers. When a model needs a public-facing identifier, add a secondary uuid column and scope HasUuids to it via uniqueIds(): array { return ['uuid']; }, rather than using HasUuids on the primary key.

## No local query scopes
Express list filters as inline conditional ->where() chains in the controller or Action, not as model scope*() methods or #[Scope] attributes.

## Model lifecycle hooks via boot()/booted() closures
Implement model lifecycle side effects (auto-numbering, defaulting the current user, cache invalidation) as static::<event>() closures inside a boot()/booted() override, not Observer classes.

## Shape query results with ->get()->map()
Shape Eloquent query results for Inertia/JSON output with ->get()->map(fn ($x) => [...]) collection pipelines. Reserve foreach for side-effecting operations (file/report building, sync jobs).
