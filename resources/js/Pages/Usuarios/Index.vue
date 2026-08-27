<script setup>
import { ref, watch } from 'vue'
import { Head, Link, router, InfiniteScroll } from '@inertiajs/vue3'
import Maindashboard from '@/Layouts/Maindashboard.vue'
defineOptions({ layout: Maindashboard })

import { showToast, confirm } from '@/Utils/alertUtil.js'
import { useBreakpoints, breakpointsTailwind } from '@vueuse/core'

const props = defineProps({
    usuarios: Object,
    filters: Object,
    flash: Object,
})

// Debajo de "lg" (tablet en portrait y celular) se muestra un segundo
// listado en tarjetas con scroll infinito en vez de la tabla (ver
// .ai/rules/pages.md, "Listado responsivo con scroll infinito").
const breakpoints = useBreakpoints(breakpointsTailwind)
const isMobile = breakpoints.smaller('lg')

const filters = ref({
    name: props.filters?.name ?? '',
    email: props.filters?.email ?? '',
    estado_usuario: props.filters?.estado_usuario ?? '',
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
                    name: val.name || undefined,
                    email: val.email || undefined,
                    estado_usuario: val.estado_usuario || undefined,
                },
                { preserveState: true, replace: true },
            )
        }, 350)
    },
    { deep: true },
)

function clearFilters() {
    filters.value = { name: '', email: '', estado_usuario: '' }
}

const cambiarEstado = async (usuario, nuevoEstado) => {
    const accion = nuevoEstado === 'INACTIVO' ? 'inactivar' : 'activar'
    const confirmar = await confirm(`¿Deseas ${accion} al usuario "${usuario.name}"?`, 'Confirmación', `Sí, ${accion}`)
    if (!confirmar) return

    router.patch(route('usuarios.estado', usuario.id), { estado_usuario: nuevoEstado }, {
        preserveScroll: true,
        onSuccess: () => showToast(`Usuario ${accion === 'inactivar' ? 'inactivado' : 'activado'}`),
    })
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

const estadoBadge = (estado) => (estado === 'ACTIVO' ? 'bg-success-transparent text-success' : 'bg-danger-transparent text-danger')

const tipoLabel = (tipo) => ({
    conductor: 'Conductor',
    'jefe-area': 'Jefe de Área',
    personal: 'Personal',
}[tipo] ?? 'Sin asignar')

const tipoBadge = (tipo) => ({
    conductor: 'bg-primary-transparent text-primary',
    'jefe-area': 'bg-info-transparent text-info',
    personal: 'bg-secondary-transparent text-secondary',
}[tipo] ?? 'bg-light text-muted')
</script>

<template>
    <Head title="Usuarios" />

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
        <Link v-can="'usuarios.crear'" :href="route('usuarios.create')" class="btn btn-primary btn-wave">
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
                    <label class="form-label">Estado</label>
                    <select v-model="filters.estado_usuario" class="form-select">
                        <option value="">Todos</option>
                        <option value="ACTIVO">ACTIVO</option>
                        <option value="INACTIVO">INACTIVO</option>
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
        <!-- Vista tabla: desktop -->
        <div v-if="!isMobile" class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:50px">#</th>
                            <th>Usuario</th>
                            <th>Correo</th>
                            <th>Persona (CI)</th>
                            <th>Tipo</th>
                            <th style="width:120px">Estado</th>
                            <th style="width:170px" class="text-center">Acciones</th>
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
                                    <span class="avatar avatar-sm rounded-circle fw-semibold d-flex align-items-center justify-content-center"
                                        :class="avatarColor(usuario.name)">
                                        {{ usuario.name.charAt(0).toUpperCase() }}
                                    </span>
                                    <span class="fw-medium">{{ usuario.name }}</span>
                                </div>
                            </td>
                            <td class="text-muted">{{ usuario.email }}</td>
                            <td>
                                <span v-if="usuario.persona">{{ usuario.persona.ci }}</span>
                                <span v-else class="text-muted">—</span>
                            </td>
                            <td>
                                <span class="badge" :class="tipoBadge(usuario.persona?.tipo_actual)">
                                    {{ tipoLabel(usuario.persona?.tipo_actual) }}
                                </span>
                            </td>
                            <td>
                                <span class="badge" :class="estadoBadge(usuario.estado_usuario)">{{ usuario.estado_usuario }}</span>
                            </td>
                            <td class="text-center">
                                <div class="d-flex gap-1 justify-content-center">
                                    <Link v-can="'usuarios.editar'" :href="route('usuarios.edit', usuario.id)" class="btn btn-sm btn-icon btn-info-light" title="Editar">
                                        <i class="ri-edit-line"></i>
                                    </Link>
                                    <Link v-can="'usuarios.contrasena.cambiar'" :href="route('usuarios.edit-password', usuario.id)" class="btn btn-sm btn-icon btn-warning-light" title="Cambiar contraseña">
                                        <i class="ri-lock-password-line"></i>
                                    </Link>
                                    <button v-if="usuario.estado_usuario === 'ACTIVO'" v-can="'usuarios.eliminar'" type="button" class="btn btn-sm btn-icon btn-danger-light"
                                        title="Inactivar" @click="cambiarEstado(usuario, 'INACTIVO')">
                                        <i class="ri-user-unfollow-line"></i>
                                    </button>
                                    <button v-else v-can="'usuarios.eliminar'" type="button" class="btn btn-sm btn-icon btn-success-light"
                                        title="Activar" @click="cambiarEstado(usuario, 'ACTIVO')">
                                        <i class="ri-user-follow-line"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Vista tarjetas: tablet y celular, con scroll infinito -->
        <div v-else class="card-body p-2">
            <div v-if="usuarios.data.length === 0" class="text-center py-4 text-muted">
                <i class="ri-user-search-line fs-3 d-block mb-2"></i>
                No se encontraron usuarios
            </div>

            <InfiniteScroll v-else data="usuarios" only-next as="div" class="d-flex flex-column gap-2">
                <div v-for="usuario in usuarios.data" :key="usuario.id" class="list-card-mobile border rounded-3 p-3">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="avatar avatar-sm rounded-circle fw-semibold d-flex align-items-center justify-content-center"
                            :class="avatarColor(usuario.name)">
                            {{ usuario.name.charAt(0).toUpperCase() }}
                        </span>
                        <div class="flex-grow-1">
                            <div class="fw-semibold">{{ usuario.name }}</div>
                            <small class="text-muted">{{ usuario.email }}</small>
                        </div>
                        <span class="badge" :class="estadoBadge(usuario.estado_usuario)">{{ usuario.estado_usuario }}</span>
                    </div>

                    <div class="row g-2 small mb-2">
                        <div class="col-6">
                            <span class="text-muted d-block">Persona (CI)</span>
                            <span v-if="usuario.persona">{{ usuario.persona.ci }}</span>
                            <span v-else class="text-muted">—</span>
                        </div>
                        <div class="col-6">
                            <span class="text-muted d-block">Tipo</span>
                            <span class="badge" :class="tipoBadge(usuario.persona?.tipo_actual)">
                                {{ tipoLabel(usuario.persona?.tipo_actual) }}
                            </span>
                        </div>
                    </div>

                    <div class="d-flex align-items-center justify-content-end gap-1 border-top pt-2">
                        <Link v-can="'usuarios.editar'" :href="route('usuarios.edit', usuario.id)" class="btn btn-icon btn-info-light" title="Editar">
                            <i class="ri-edit-line"></i>
                        </Link>
                        <Link v-can="'usuarios.contrasena.cambiar'" :href="route('usuarios.edit-password', usuario.id)" class="btn btn-icon btn-warning-light" title="Cambiar contraseña">
                            <i class="ri-lock-password-line"></i>
                        </Link>
                        <button v-if="usuario.estado_usuario === 'ACTIVO'" v-can="'usuarios.eliminar'" type="button" class="btn btn-icon btn-danger-light"
                            title="Inactivar" @click="cambiarEstado(usuario, 'INACTIVO')">
                            <i class="ri-user-unfollow-line"></i>
                        </button>
                        <button v-else v-can="'usuarios.eliminar'" type="button" class="btn btn-icon btn-success-light"
                            title="Activar" @click="cambiarEstado(usuario, 'ACTIVO')">
                            <i class="ri-user-follow-line"></i>
                        </button>
                    </div>
                </div>

                <!-- Indicador de carga / fin de lista del scroll infinito -->
                <template #next="{ loading, hasMore }">
                    <div v-if="loading" class="text-center text-muted small py-2">
                        <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                        Cargando más usuarios...
                    </div>
                    <div v-else-if="!hasMore" class="text-center text-muted small py-2">
                        No hay más usuarios para mostrar.
                    </div>
                </template>
            </InfiniteScroll>
        </div>

        <!-- Paginador: sólo la tabla desktop. El listado mobile usa scroll
             infinito (InfiniteScroll arriba) en vez de páginas numeradas. -->
        <div v-if="!isMobile" class="card-footer d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="text-muted small">
                Mostrando <strong>{{ usuarios.from ?? 0 }}</strong> - <strong>{{ usuarios.to ?? 0 }}</strong>
                de <strong>{{ usuarios.total }}</strong> resultados
            </div>
            <nav v-if="usuarios.last_page > 1">
                <ul class="pagination pagination-sm mb-0">
                    <li v-for="link in usuarios.links" :key="link.label" class="page-item"
                        :class="{ active: link.active, disabled: !link.url }">
                        <Link v-if="link.url" :href="link.url" class="page-link" preserve-state v-html="link.label" />
                        <span v-else class="page-link" v-html="link.label" />
                    </li>
                </ul>
            </nav>
        </div>
    </div>
</template>
