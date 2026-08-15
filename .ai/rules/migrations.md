---
paths:
  - 'database/migrations/*.php'
---

# Migrations

## Foreign keys via foreignId()->constrained()
Define foreign key columns with $table->foreignId('id_xxx')->constrained('table_name') using an explicit table name, plus explicit onDelete()/onUpdate() behavior. Don't use foreignIdFor() or manual foreign()->references()->on().

## Always write a real down() method
Every migration includes a down() method that reverses its up() (typically Schema::dropIfExists, plus reverting any column additions to existing tables). Don't omit down().

## Status/type fields as DB enum() columns
Represent fixed status/type values with a migration-level $table->enum('col', [...]) column rather than a string() column backed by a PHP enum cast on the model.
