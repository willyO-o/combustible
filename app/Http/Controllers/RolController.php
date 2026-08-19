<?php

namespace App\Http\Controllers;

use App\Http\Requests\RolRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Módulo de Roles y Permisos: sólo administrador/super-admin pueden crear
 * roles y asignarles permisos existentes. El catálogo de permisos (agrupado,
 * con etiqueta legible) y las listas de roles ocultos/protegidos viven en
 * config/acl.php — este controlador nunca hardcodea esas reglas.
 */
class RolController extends Controller
{
    public function index(Request $request): Response
    {
        $this->autorizar($request);

        $roles = Role::whereNotIn('name', config('acl.roles_ocultos'))
            ->withCount('permissions')
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Roles/Index', [
            'roles' => $roles,
            'rolesProtegidos' => config('acl.roles_protegidos'),
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->autorizar($request);

        return Inertia::render('Roles/Create', [
            'modulos' => $this->catalogoPermisos(),
        ]);
    }

    /**
     * El acceso ya queda restringido a administrador/super-admin por
     * RolRequest::authorize().
     */
    public function store(RolRequest $request): RedirectResponse
    {
        $role = Role::create([
            'name' => $request->validated('name'),
            'guard_name' => 'web',
        ]);

        $role->syncPermissions($request->validated('permisos', []));

        return redirect()->route('roles.index')
            ->with('success', "Rol \"{$role->name}\" creado exitosamente.");
    }

    public function edit(Request $request, Role $role): Response
    {
        $this->autorizar($request);
        $this->abortSiOculto($role);

        return Inertia::render('Roles/Edit', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
            ],
            'permisosAsignados' => $role->permissions->pluck('name'),
            'modulos' => $this->catalogoPermisos(),
            'esProtegido' => in_array($role->name, config('acl.roles_protegidos'), true),
        ]);
    }

    /**
     * Actualiza los permisos del rol (el nombre no es editable).
     *
     * El acceso y el bloqueo de roles ocultos ya quedan restringidos por
     * RolRequest::authorize(). Para los roles protegidos (config/acl.php),
     * los permisos base nunca se quitan: sólo se agregan los que falten.
     */
    public function update(RolRequest $request, Role $role): RedirectResponse
    {
        $permisosSolicitados = $request->validated('permisos', []);

        if (in_array($role->name, config('acl.roles_protegidos'), true)) {
            $permisosSolicitados = array_unique([
                ...$role->permissions->pluck('name')->all(),
                ...$permisosSolicitados,
            ]);
        }

        $role->syncPermissions($permisosSolicitados);

        return redirect()->route('roles.index')
            ->with('success', "Permisos del rol \"{$role->name}\" actualizados exitosamente.");
    }

    private function autorizar(Request $request): void
    {
        if (! $request->user()->hasAnyRole(['super-admin', 'administrador'])) {
            abort(403, 'Sólo un administrador puede gestionar roles y permisos.');
        }
    }

    private function abortSiOculto(Role $role): void
    {
        if (in_array($role->name, config('acl.roles_ocultos'), true)) {
            abort(404);
        }
    }

    /**
     * Catálogo de permisos agrupado para el formulario, a partir de
     * config/acl.php. Si en base de datos existe algún permiso que no está
     * catalogado ahí, se agrega en un grupo "Otros permisos" para que nunca
     * quede oculto ni impida asignarlo.
     *
     * @return array<int, array{label: string, permisos: array<int, array{name: string, label: string}>}>
     */
    private function catalogoPermisos(): array
    {
        $modulos = config('acl.modulos', []);

        $catalogados = collect($modulos)->flatMap(fn ($modulo) => array_keys($modulo['permisos']))->all();

        $grupos = collect($modulos)->map(fn ($modulo) => [
            'label' => $modulo['label'],
            'permisos' => collect($modulo['permisos'])->map(fn ($label, $name) => [
                'name' => $name,
                'label' => $label,
            ])->values(),
        ])->values();

        $sinCatalogar = Permission::whereNotIn('name', $catalogados)
            ->orderBy('name')
            ->pluck('name');

        if ($sinCatalogar->isNotEmpty()) {
            $grupos->push([
                'label' => 'Otros permisos',
                'permisos' => $sinCatalogar->map(fn ($name) => [
                    'name' => $name,
                    'label' => $name,
                ])->values(),
            ]);
        }

        return $grupos->all();
    }
}
