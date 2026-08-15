---
paths:
  - 'app/Actions/**/*.php'
---

# Actions

## Explicit eager-loading, no model $with defaults
Eager-load relations explicitly with ->with()/->load() at the call site, often scoping to selected columns via 'relation:col1,col2'. Don't set model-level $with defaults.

## Action class conventions
Name Action classes <Verb><Domain>Action, grouped under app/Actions/<Domain>/. Give each a single public execute() method. Inject collaborator Actions/dependencies via the constructor. Wrap multi-step writes in DB::transaction(). Dispatch domain events from inside the Action, not the controller.

## Domain-grouping is specific to app/Actions
Group Action classes into app/Actions/<Domain>/ subfolders named after the business domain. Don't extend this domain-grouping to Requests, Controllers, or Services — those stay flat.
