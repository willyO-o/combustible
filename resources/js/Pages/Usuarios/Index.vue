<script setup>
import { ref, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import Maindashboard from '@/Layouts/Maindashboard.vue'

const props = defineProps({
    usuarios: Object,
    roles:    Array,
    filters:  Object,
    flash:    Object,
})

const filters = ref({
    name:   props.filters?.name   ?? '',
    email:  props.filters?.email  ?? '',
    id_rol: props.filters?.id_rol ?? '',
})

let debounceTimer = null
watch(
    filters,
    (val) => {
        clearTimeout(debounceTimer)
        debounceTimer = setTimeout(() => {
            router.get(
                route('usuarios.index'),
                {
                    name:   val.name   || undefined,
                    email:  val.email  || undefined,
                    id_rol: val.id_rol || undefined,
                },
                { preserveState: true, replace: true },
            )
        }, 350)
    },
    { deep: true },
)

function clearFilters() {
    filters.value = { name: '', email: '', id_rol: '' }
}

function confirmDelete(usuario) {
    if (confirm(`¿Eliminar el usuario "${usuario.name}"? Esta acción no se puede deshacer.`)) {
        router.delete(route('usuarios.destroy', usuario.id))
    }
}

const avatarColor = (name) => {
    const colors = [
        'bg-primary-transparent text-primary',
        'bg-success-transparent text-success',
        'bg-warning-transparent text-warning',
        'bg-info-transparent text-info',
        'bg-danger-transparent text-danger',
    ]
    return colors[name.charCodeAt(0) % colors.length]
}

const formatDate = (d) =>
    d ? new Date(d).toLocaleDateString('es-BO', { day: '2-digit', month: '2-digit', year: 'numeric' }) : '—'
</script>

<template>
    <Head title="Usuarios" />

    <Maindashboard>
        <!-- Page header -->
        <div class="d-flex align-items-center justify-content-between page-header-breadcrumb flex-wrap gap-2 mb-4">
            <div>
                <nav>
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item"><Link :href="route('dashboard')">Inicio</Link></li>
                        <li class="breadcrumb-item active">Usuarios</li>
                    </ol>
                </nav>
                <h1 class="page-title fw-medium fs-18 mb-0">Gestión de Usuarios</h1>
            </div>
            <Link :href="route('usuarios.create')" class="btn btn-primary btn-wave">
                <i class="ri-user-add-line me-1"></i> Nuevo Usuario
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

        <!-- Filtros -->
        <div class="card custom-card mb-4">
            <div class="card-header"><div class="card-title">Filtros de búsqueda</div></div>
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-sm-6 col-xl-3">
                        <label class="form-label">Nombre</label>
                        <input v-model="filters.name" type="text" class="form-control" placeholder="Buscar por nombre..." />
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <label class="form-label">Correo electrónico</label>
                        <input v-model="filters.email" type="text" class="form-control" placeholder="Buscar por email..." />
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <label class="form-label">Rol</label>
                        <select v-model="filters.id_rol" class="form-select">
                            <option value="">Todos los roles</option>
                            <option v-for="rol in roles" :key="rol.id" :value="rol.id">{{ rol.rol }}</option>
                        </select>
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <button type="button" class="btn btn-outline-secondary btn-wave w-100" @click="clearFilters">
                            <i class="ri-refresh-line me-1"></i> Limpiar filtros
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabla -->
        <div class="card custom-card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <div class="card-title">
                    Usuarios
                    <span class="badge bg-primary-transparent text-primary ms-2">{{ usuarios.total }} registros</span>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:50px">#</th>
                                <th>Usuario</th>
                                <th>Correo</th>
                                <th>Roles</th>
                                <th style="width:130px">Verificado</th>
                                <th style="width:130px">Creado</th>
                                <th style="width:150px" class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="usuarios.data.length === 0">
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="ri-user-search-line fs-3 d-block mb-2"></i>
                                    No se encontraron usuarios
                                </td>
                            </tr>
                            <tr v-for="(usuario, idx) in usuarios.data" :key="usuario.id">
                                <td class="text-muted small">
                                    {{ (usuarios.current_page - 1) * usuarios.per_page + idx + 1 }}
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span
                                            class="avatar avatar-sm rounded-circle fw-semibold d-flex align-items-center justify-content-center"
                                            :class="avatarColor(usuario.name)"
                                        >
                                            {{ usuario.name.charAt(0).toUpperCase() }}
                                        </span>
                                        <span class="fw-medium">{{ usuario.name }}</span>
                                    </div>
                                </td>
                                <td class="text-muted">{{ usuario.email }}</td>
                                <td>
                                    <div class="d-flex flex-wrap gap-1">
                                        <span
                                            v-if="usuario.roles?.length === 0"
                                            class="badge bg-secondary-transparent text-secondary"
                                        >
                                            Sin roles
                                        </span>
                                        <span
                                            v-for="rol in usuario.roles"
                                            :key="rol.id"
                                            class="badge bg-primary-transparent text-primary"
                                        >
                                            {{ rol.rol }}
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <span v-if="usuario.email_verified_at" class="badge bg-success-transparent text-success">
                                        <i class="ri-shield-check-line me-1"></i>Verificado
                                    </span>
                                    <span v-else class="badge bg-warning-transparent text-warning">Pendiente</span>
                                </td>
                                <td class="text-muted small">{{ formatDate(usuario.created_at) }}</td>
                                <td class="text-center">
                                    <div class="d-flex gap-1 justify-content-center">
                                        <Link
                                            :href="route('usuarios.edit', usuario.id)"
                                            class="btn btn-sm btn-icon btn-info-light"
                                            title="Editar"
                                        >
                                            <i class="ri-edit-line"></i>
                                        </Link>
                                        <Link
                                            :href="route('usuarios.edit-password', usuario.id)"
                                            class="btn btn-sm btn-icon btn-warning-light"
                                            title="Cambiar contraseña"
                                        >
                                            <i class="ri-lock-password-line"></i>
                                        </Link>
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-icon btn-danger-light"
                                            title="Eliminar"
                                            @click="confirmDelete(usuario)"
                                        >
                                            <i class="ri-delete-bin-line"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Paginador -->
            <div class="card-footer d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="text-muted small">
                    Mostrando <strong>{{ usuarios.from ?? 0 }}</strong> - <strong>{{ usuarios.to ?? 0 }}</strong>
                    de <strong>{{ usuarios.total }}</strong> resultados
                </div>
                <nav v-if="usuarios.last_page > 1">
                    <ul class="pagination pagination-sm mb-0">
                        <li
                            v-for="link in usuarios.links"
                            :key="link.label"
                            class="page-item"
                            :class="{ active: link.active, disabled: !link.url }"
                        >
                            <Link v-if="link.url" :href="link.url" class="page-link" preserve-state v-html="link.label" />
                            <span v-else class="page-link" v-html="link.label" />
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
    </Maindashboard>
</template>
