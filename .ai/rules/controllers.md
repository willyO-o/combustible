---
paths:
  - 'app/Http/Controllers/**/*.php'
  - 'app/Http/Controllers/*.php'
---

# Controllers

## Controllers hold business logic; Actions are the exception
Write model-mutation and conditional logic directly in the controller method. Only extract to an app/Actions/{Domain}/{Verb}{Domain}Action class for complex, multi-step operations.

## No Policy classes — authorize via inline hasRole()
Authorize actions with inline auth()->user()->hasRole('role-name') conditionals in the controller/request, not Policy classes, Gate::define, $this->authorize(), the can middleware, or @can. A global Gate::before bypass in AppServiceProvider grants full access to the super-admin role.

## No repository or query-object layer
Query Eloquent directly in controllers for CRUD. Only domains that already have an app/Actions/{Domain} folder get a dedicated List*Action wrapping the query builder — don't introduce app/Repositories or app/Queries.

## Explicit eager-loading, no model $with defaults
Eager-load relations explicitly with ->with()/->load() at the call site, often scoping to selected columns via 'relation:col1,col2'. Don't set model-level $with defaults.

## Use response() helper for JSON, not Response:: facade
Return JSON with the response()->json([...]) helper. Don't use the Response:: facade.

## JSON responses via response()->json(), no API Resources
Return JSON via response()->json([...]) with raw Eloquent models/arrays, typically wrapped in data/message keys. Don't introduce API Resource classes (app/Http/Resources).

## Shape query results with ->get()->map()
Shape Eloquent query results for Inertia/JSON output with ->get()->map(fn ($x) => [...]) collection pipelines. Reserve foreach for side-effecting operations (file/report building, sync jobs).

## auth() helper in domain code, Auth:: only in Breeze auth flows
Use the auth() helper for current-user checks in domain code. The Auth:: facade appears only in the Breeze-generated authentication controllers (app/Http/Controllers/Auth/**) — don't extend Auth:: usage beyond that scaffolding.
