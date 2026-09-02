---
paths:
  - 'database/seeders/UserSeeder.php,database/seeders/UserSeederInit.php'
---

# Seeders Seeders

## UserSeederInit = arranque en blanco (hereda el ACL de UserSeeder)
database/seeders/UserSeederInit.php es el seeder de arranque para producción "en blanco": crea TODO el ACL (los 5 roles + todos los permisos) y SOLO 2 usuarios sin id_persona — super-admin wmct2025@gmail.com y administrador solucionesglobalestecnolicas@gmail.com (password inicial PlusMetals#2026Sysmen). Se ejecuta a mano: `php artisan db:seed --class=Database\Seeders\UserSeederInit`; NO está en DatabaseSeeder (ese sigue usando UserSeeder con usuarios de ejemplo para dev/demo).

`UserSeederInit extends UserSeeder` y reutiliza sus definiciones de permisos/roles — por eso en UserSeeder los métodos todosLosPermisos(), permisosParaConductor/JefeArea/TecnicoMantenimiento(), crearPermisos(), crearRolConPermisos() y crearUsuarioConRol() son `protected` (no `private`). Si agregas un permiso/rol nuevo, hazlo en UserSeeder y ambos seeders lo heredan. Cubierto por tests/Feature/UserSeederInitTest.php (incluye un test de paridad permisos vía reflection sobre todosLosPermisos()).
