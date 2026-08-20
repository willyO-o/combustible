<script setup>
import { Head, Link } from '@inertiajs/vue3'
import Maindashboard from '@/Layouts/Maindashboard.vue'
defineOptions({ layout: Maindashboard })

const props = defineProps({
    roles:           Object,
    rolesProtegidos: Array,
    flash:           Object,
})

const esProtegido = (role) => props.rolesProtegidos.includes(role.name)
</script>

<template>
    <Head title="Roles y Permisos" />

        <!-- Page header -->
        <div class="d-flex align-items-center justify-content-between page-header-breadcrumb flex-wrap gap-2 mb-4">
            <div>
                <nav>
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item">
                            <Link :href="route('dashboard')">Inicio</Link>
                        </li>
                        <li class="breadcrumb-item active">Roles y Permisos</li>
                    </ol>
                </nav>
                <h1 class="page-title fw-medium fs-18 mb-0">Roles y Permisos</h1>
            </div>
            <Link v-can="'roles.ver'" :href="route('roles.create')" class="btn btn-primary btn-wave">
                <i class="ri-add-line me-1"></i> Nuevo Rol
            </Link>
        </div>

        <!-- Flash messages -->
        <div v-if="flash?.success" class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="ri-checkbox-circle-line me-2"></i>{{ flash.success }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <div v-if="flash?.error" class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="ri-error-warning-line me-2"></i>{{ flash.error }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>

        <div class="alert alert-info-transparent d-flex align-items-start gap-2 mb-4">
            <i class="ri-information-line fs-20 mt-1"></i>
            <div>
                Los roles marcados como <span class="badge bg-warning-transparent text-warning">Protegido</span>
                tienen un conjunto base de permisos que no se puede quitar desde aquí; solo se les pueden agregar permisos adicionales.
            </div>
        </div>

        <!-- Tabla -->
        <div class="card custom-card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <div class="card-title">
                    Listado
                    <span class="badge bg-primary-transparent text-primary ms-2">
                        {{ roles.total }} registros
                    </span>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:60px">#</th>
                                <th>Rol</th>
                                <th class="text-center">Permisos asignados</th>
                                <th style="width:130px">Creado</th>
                                <th style="width:100px" class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="roles.data.length === 0">
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="ri-shield-user-line fs-3 d-block mb-2"></i>
                                    No se encontraron roles
                                </td>
                            </tr>
                            <tr v-for="(role, idx) in roles.data" :key="role.id">
                                <td class="text-muted small">
                                    {{ (roles.current_page - 1) * roles.per_page + idx + 1 }}
                                </td>
                                <td>
                                    <span class="fw-medium">{{ role.name }}</span>
                                    <span v-if="esProtegido(role)" class="badge bg-warning-transparent text-warning ms-2">
                                        Protegido
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-primary-transparent text-primary">
                                        {{ role.permissions_count }}
                                    </span>
                                </td>
                                <td class="text-muted small">
                                    {{ new Date(role.created_at).toLocaleDateString('es-BO') }}
                                </td>
                                <td class="text-center">
                                    <Link
                                        v-can="'roles.ver'"
                                        :href="route('roles.edit', role.id)"
                                        class="btn btn-sm btn-icon btn-info-light"
                                        title="Editar permisos"
                                    >
                                        <i class="ri-edit-line"></i>
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Paginador -->
            <div class="card-footer d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="text-muted small">
                    Mostrando
                    <strong>{{ roles.from ?? 0 }}</strong> -
                    <strong>{{ roles.to ?? 0 }}</strong>
                    de <strong>{{ roles.total }}</strong> resultados
                </div>
                <nav v-if="roles.last_page > 1" aria-label="Paginación">
                    <ul class="pagination pagination-sm mb-0">
                        <li
                            v-for="link in roles.links"
                            :key="link.label"
                            class="page-item"
                            :class="{ active: link.active, disabled: !link.url }"
                        >
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                class="page-link"
                                preserve-state
                                v-html="link.label"
                            />
                            <span v-else class="page-link" v-html="link.label" />
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
</template>
