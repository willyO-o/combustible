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
