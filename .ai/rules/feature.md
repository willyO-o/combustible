---
paths:
  - 'tests/Feature/**/*.php'
---

# Feature

## Feature tests use RefreshDatabase
Feature tests that touch the database use the RefreshDatabase trait, not DatabaseTruncation or DatabaseMigrations.

## Use factories for test fixtures
Create test fixtures with model factories (Model::factory()->create()) rather than manual Model::create() or raw inserts.

## A failing assertRedirect/assertOk on a validation-error redirect crashes with "Call to a member function all() on array"
config/session.php has 'serialization' => 'json' (Laravel 11 hardening). When a test assertion fails against a response that flashed validation errors to session, Laravel's own TestResponseAssert::injectResponseContext() tries to enrich the failure message via $session->get('errors')->all() — but json session serialization turns the flashed ViewErrorBag into a plain array on read, so ->all() fatals with "Call to a member function all() on array". This masks the REAL failure (a wrong assertion or bad test payload) behind a confusing framework-internal error. If you see this message, the actual bug is almost always in the test itself (e.g. posting an incomplete payload that unexpectedly fails validation) — fix the test data/assertion, don't chase this error text. To see the real underlying exception while debugging, temporarily call $this->withoutExceptionHandling() (then remove it).

## Testing a partial Inertia reload (X-Inertia headers) manually: version header + `->original` shape
To simulate what `<InfiniteScroll>`/`router.reload({only:[...]})` sends, a test needs `'X-Inertia' => 'true'`, `'X-Inertia-Partial-Component' => '<Component/Name>'`, `'X-Inertia-Partial-Data' => 'propName'` headers, PLUS a matching `'X-Inertia-Version'`. `Inertia\Middleware::handle()` recomputes the version on EVERY request from `hash_file('xxh128', public_path('build/manifest.json'))` (or null if that file doesn't exist) and overwrites anything set via `Inertia::version(...)` beforehand — stubbing the version ahead of time does nothing; a mismatch silently returns 409 instead of running the controller. Compute it the same way in the test:
```php
$version = file_exists(public_path('build/manifest.json'))
    ? hash_file('xxh128', public_path('build/manifest.json'))
    : null;
```
Also note `$response->original` has a DIFFERENT shape depending on the request type: for a normal full-page visit (no `X-Inertia` header) Inertia returns a Blade `view('app')->with('page', $page)`, so `$response->original` is that `View` and you need `->getData()['page']`; for an `X-Inertia` XHR/partial request it returns `new JsonResponse($page)` directly, and Laravel's `JsonResponse::setData()` stores the raw array on `->original` — so it's already the `$page` array (`$response->original['props']['vales']...`), calling `->getData()` on it fatals with "Call to a member function getData() on array". See ValeControllerTest::test_index_marca_vales_como_scrolleable_para_el_infinite_scroll_mobile for a worked example (also asserts the `mergeProps` key that `Inertia::scroll()` adds — ver .ai/rules/pages.md).
