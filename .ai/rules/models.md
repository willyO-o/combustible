---
paths:
  - 'app/Models/**'
  - 'app/Models/*.php'
  - app/Models/CargaCombustible.php
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

## carga_combustible.estado_carga: REGISTRADO/VERIFICADO/ANULADO, default REGISTRADO
`carga_combustible.estado_carga` es varchar(30) nullable (sin enum en BD). Valores válidos: REGISTRADO, VERIFICADO, ANULADO (así lo valida CargaCombustibleRequest y lo publica GET /parametros/colecciones). NO son PENDIENTE/USADO/ANULADO (eso es estado_vale). El modelo pone `estado_carga ??= 'REGISTRADO'` en creating() cuando el cliente no lo envía.

## Todo modelo nuevo debe ser Auditable (owen-it/laravel-auditing)
Los 35 modelos de app/Models implementan `OwenIt\Auditing\Contracts\Auditable` + `use \OwenIt\Auditing\Auditable;` (import del CONTRATO como `Auditable`, trait con su FQN inline — así lo documenta el paquete y así quedó en todo el proyecto). Un modelo nuevo sin eso queda fuera de la bitácora de Auditoría sin que nada falle: agrégalo siempre.

Además, si el modelo se puede listar en la pantalla de Auditoría, dale su entrada en `config/auditoria.php` -> `modelos` (label en español, ícono RemixIcon, `descriptor` con las columnas que lo identifican y `formato` opcional tipo 'Vale N° {nro_vale}/{gestion}'); y si tiene llaves foráneas nuevas, agrégalas a `relaciones` + `atributos` para que el detalle muestre "Gasolina 95 (#2)" y no "id_tipo_combustible: 2". Hay una comprobación de humo lista en la descripción de AuditoriaControllerTest: todo lo catalogado debe poder consultarse contra la BD.

Campos sensibles: `config/audit.php -> exclude` (password, remember_token, token) nunca se graban; `config/auditoria.php -> ocultos` los vuelve a filtrar al mostrarlos.
