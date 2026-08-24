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
