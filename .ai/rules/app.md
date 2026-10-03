---
paths:
  - 'app/**/*.php'
---

# App

## Dependency acquisition via constructor/method injection only
Acquire dependencies via constructor injection or controller/route method injection. Never use app(), resolve(), or App::make() as a service locator.

## Use paginate() for all listing endpoints
Use paginate() for listing endpoints — web index pages call ->paginate(N)->withQueryString(), API list actions call ->paginate($perPage) from a request-provided per_page. Don't use simplePaginate() or cursorPaginate().

## Use now()/today() helpers, not Carbon:: static calls
Use the now()/today() helpers for current-time values. Don't call Carbon::now()/Carbon::today() directly, and don't introduce CarbonImmutable.

## Roles combinados: usar User::esConductorPuro(), nunca hasRole('conductor') a secas
Un usuario puede tener varios roles (p.ej. administrador + jefe-area + conductor). Para limitar a "sólo sus propios registros" usar `$user->esConductorPuro()` (conductor sin jefe-area/administrador/super-admin), no `hasRole('conductor')`, que dejaba a un administrador+conductor viendo sólo lo suyo o recibiendo 403. En listados, precedencia: administrador/super-admin (todo) > jefe-area (sus áreas) > conductor (lo suyo) — ver ListValeAction, ListCargaCombustibleAction, ListOperacionesDiariasAction. Excepción documentada: el formulario web de Operación Diaria (conductor siempre gana, ver operacion.md).
