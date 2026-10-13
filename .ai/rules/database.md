---
paths:
  - 'database/**'
---

# Database

## PRODUCCIÓN: prohibido migrate:fresh / wipe / refresh — sólo cambios aditivos
El sistema está en producción (rama `produccion`, creada 2026-09-23 desde 5377e70) con datos reales. NUNCA ejecutar ni sugerir `migrate:fresh`, `migrate:refresh`, `migrate:reset`, `migrate:rollback` ni `db:wipe` contra esa base, ni `db:seed` sin `--class` (DatabaseSeeder carga datos demo).

- Cambios de esquema: SIEMPRE una migración NUEVA (`php artisan make:migration`), aditiva y segura con datos existentes (columnas nuevas `nullable()` o con `default()`; nunca editar una migración ya ejecutada en producción; no borrar/renombrar columnas con datos sin una migración de copia previa). Despliegue: `php artisan migrate --force` (antes `php artisan migrate:status` y respaldo con mysqldump).
- Permisos nuevos: agregarlos en UserSeeder (todosLosPermisos() + el permisosPara*() del rol) y config/acl.php, y en producción correr SÓLO `php artisan db:seed --class=Database\Seeders\ActualizarPermisosSeeder --force` (aditivo: crea los permisos faltantes y los da a los roles que los definen, sin syncPermissions, sin tocar usuarios ni las personalizaciones hechas en el módulo de Roles). NO correr UserSeeder (crea usuarios demo) ni UserSeederInit en producción: ambos hacen syncPermissions() y borran los ajustes de roles. Cubierto por tests/Feature/ActualizarPermisosSeederTest.php.
- Columnas que dejan de usarse (p.ej. actividad_realizada.hora_inicio/hora_fin) se conservan: se deja de escribirlas, no se eliminan.
