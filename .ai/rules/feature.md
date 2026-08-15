---
paths:
  - 'tests/Feature/**/*.php'
---

# Feature

## Feature tests use RefreshDatabase
Feature tests that touch the database use the RefreshDatabase trait, not DatabaseTruncation or DatabaseMigrations.

## Use factories for test fixtures
Create test fixtures with model factories (Model::factory()->create()) rather than manual Model::create() or raw inserts.
