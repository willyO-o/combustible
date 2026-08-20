<script setup>
import { ref, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import Maindashboard from '@/Layouts/Maindashboard.vue'
defineOptions({ layout: Maindashboard })

import { showToast, confirm } from '@/Utils/alertUtil.js'

const props = defineProps({
    personas: Object,
    filters: Object,
    flash: Object,
})

// Filtros reactivos inicializados con los valores que llegan del servidor
const filters = ref({
    ci: props.filters?.ci ?? '',
    nombres: props.filters?.nombres ?? '',
    paterno: props.filters?.paterno ?? '',
    celular: props.filters?.celular ?? '',
})

// Debounce para no hacer petición en cada tecla
let debounceTimer = null
watch(
    filters,
    (val) => {
        clearTimeout(debounceTimer)
        debounceTimer = setTimeout(() => {
            router.get(
                route('personas.index'),
                {
                    ci: val.ci || undefined,
                    nombres: val.nombres || undefined,
                    paterno: val.paterno || undefined,
                    celular: val.celular || undefined,
                },
                { preserveState: true, replace: true },
            )
        }, 350)
    },
    { deep: true },
)

function clearFilters() {
    filters.value = { ci: '', nombres: '', paterno: '', celular: '' }
}

const confirmDelete = async (persona) => {
    const confirmar = await confirm(`¿Eliminar a ${persona.nombres} ${persona.paterno ?? ''}?`, 'Confirmación', 'Si, eliminar')
    if (!confirmar) return

    router.delete(route('personas.destroy', persona.id), {
        preserveScroll: true,
        onSuccess: () => {
            showToast('Persona eliminada')
        },
    })
}

const estadoBadge = (estado) => {
    const map = {
        ACTIVO: 'bg-success-transparent text-success',
        INACTIVO: 'bg-warning-transparent text-warning',
        RETIRADO: 'bg-danger-transparent text-danger',
    }
    return map[estado] ?? 'bg-secondary-transparent text-secondary'
}

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

const fotoUrl = (foto) => (foto ? `/storage/${foto}` : '/images/faces/1.jpg')
</script>

<template>
    <Head title="Personas" />

    <!-- Page header -->
    <div class="d-flex align-items-center justify-content-between page-header-breadcrumb flex-wrap gap-2 mb-4">
        <div>
            <nav>
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item">
                        <Link :href="route('dashboard')">Inicio</Link>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Personas</li>
                </ol>
            </nav>
            <h1 class="page-title fw-medium fs-18 mb-0">Gestión de Personas</h1>
        </div>
        <div>
            <Link v-can="'personas.crear'" :href="route('personas.create')" class="btn btn-primary btn-wave">
                <i class="ri-add-line me-1"></i> Nueva Persona
            </Link>
        </div>
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
        <div class="card-header">
            <div class="card-title">Filtros de búsqueda</div>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-sm-6 col-xl-3">
                    <label class="form-label">Carnet de Identidad</label>
                    <input v-model="filters.ci" type="text" class="form-control" placeholder="Buscar por CI..." />
                </div>
                <div class="col-sm-6 col-xl-3">
                    <label class="form-label">Nombres</label>
                    <input v-model="filters.nombres" type="text" class="form-control" placeholder="Buscar por nombres..." />
                </div>
                <div class="col-sm-6 col-xl-3">
                    <label class="form-label">Apellido Paterno</label>
                    <input v-model="filters.paterno" type="text" class="form-control" placeholder="Buscar por apellido..." />
                </div>
                <div class="col-sm-6 col-xl-3">
                    <label class="form-label">Celular</label>
                    <input v-model="filters.celular" type="text" class="form-control" placeholder="Buscar por celular..." />
                </div>
            </div>
            <div class="mt-3 d-flex justify-content-end">
                <button type="button" class="btn btn-outline-secondary btn-wave" @click="clearFilters">
                    <i class="ri-refresh-line me-1"></i> Limpiar filtros
                </button>
            </div>
        </div>
    </div>

    <!-- Tabla -->
    <div class="card custom-card">
        <div class="card-header d-flex align-items-center justify-content-between">
            <div class="card-title">
                Personas
                <span class="badge bg-primary-transparent text-primary ms-2">
                    {{ personas.total }} registros
                </span>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover text-nowrap mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Foto</th>
                            <th>CI</th>
                            <th>Nombre Completo</th>
                            <th>Celular</th>
                            <th>Tipo</th>
                            <th>Estado</th>
                            <th>Usuario</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="personas.data.length === 0">
                            <td colspan="9" class="text-center py-4 text-muted">
                                <i class="ri-user-search-line fs-3 d-block mb-2"></i>
                                No se encontraron personas
                            </td>
                        </tr>
                        <tr v-for="(persona, index) in personas.data" :key="persona.id">
                            <td>{{ index + 1 }}</td>
                            <td>
                                <span class="avatar avatar-lg">
                                    <img :src="fotoUrl(persona.foto)" :alt="persona.nombres" class="rounded-circle"
                                        style="width:36px;height:36px;object-fit:cover;" />
                                </span>
                            </td>
                            <td><span class="fw-medium">{{ persona.ci }}</span></td>
                            <td>{{ persona.nombres }} {{ persona.paterno ?? '—' }} {{ persona.materno ?? '—' }}</td>
                            <td>{{ persona.celular ?? '—' }}</td>
                            <td>
                                <span class="badge" :class="tipoBadge(persona.tipo_actual)">
                                    {{ tipoLabel(persona.tipo_actual) }}
                                </span>
                            </td>
                            <td>
                                <span class="badge" :class="estadoBadge(persona.estado_persona)">
                                    {{ persona.estado_persona }}
                                </span>
                            </td>
                            <td>
                                <span v-if="persona.user" class="badge" :class="estadoBadge(persona.user.estado_usuario)">
                                    {{ persona.user.estado_usuario }}
                                </span>
                                <span v-else class="text-muted">Sin usuario</span>
                            </td>
                            <td class="text-center">
                                <div class="d-flex gap-1 justify-content-center">
                                    <Link v-can="'personas.editar'" :href="route('personas.edit', persona.id)" class="btn btn-sm btn-icon btn-light" title="Editar">
                                        <i class="ri-edit-line"></i>
                                    </Link>
                                    <button v-can="'personas.eliminar'" type="button" class="btn btn-sm btn-icon btn-light" title="Eliminar"
                                        @click="confirmDelete(persona)">
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
                Mostrando {{ personas.from ?? 0 }} - {{ personas.to ?? 0 }}
                de {{ personas.total }} resultados
            </div>
            <nav v-if="personas.last_page > 1">
                <ul class="pagination pagination-sm mb-0">
                    <li v-for="link in personas.links" :key="link.label" class="page-item"
                        :class="{ active: link.active, disabled: !link.url }">
                        <Link v-if="link.url" :href="link.url" class="page-link" preserve-state v-html="link.label" />
                        <span v-else class="page-link" v-html="link.label" />
                    </li>
                </ul>
            </nav>
        </div>
    </div>
</template>
