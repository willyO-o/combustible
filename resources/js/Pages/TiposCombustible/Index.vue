<script setup>
import { ref, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import Maindashboard from '@/Layouts/Maindashboard.vue'
import { confirm } from '@/Utils/alertUtil.js'
defineOptions({ layout: Maindashboard })

const props = defineProps({
    tipos:   Object,
    filters: Object,
    flash:   Object,
})

const filters = ref({
    tipo_combustible:        props.filters?.tipo_combustible        ?? '',
    estado_tipo_combustible: props.filters?.estado_tipo_combustible ?? '',
})

let debounceTimer = null
watch(
    filters,
    (val) => {
        clearTimeout(debounceTimer)
        debounceTimer = setTimeout(() => {
            router.get(
                route('tipos-combustible.index'),
                {
                    tipo_combustible:        val.tipo_combustible        || undefined,
                    estado_tipo_combustible: val.estado_tipo_combustible || undefined,
                },
                { preserveState: true, replace: true },
            )
        }, 350)
    },
    { deep: true },
)

function clearFilters() {
    filters.value = { tipo_combustible: '', estado_tipo_combustible: '' }
}

async function confirmDelete(tipo) {
    const confirmado = await confirm(
        `¿Eliminar el tipo de combustible "${tipo.tipo_combustible}"?`,
        'Eliminar Tipo de Combustible',
        'Sí, eliminar',
    )

    if (!confirmado) {
        return
    }

    router.delete(route('tipos-combustible.destroy', tipo.id))
}

const estadoBadge = (estado) =>
    estado === 'ACTIVO'
        ? 'bg-success-transparent text-success'
        : 'bg-danger-transparent text-danger'
</script>

<template>
    <Head title="Tipos de Combustible" />

        <!-- Page header -->
        <div class="d-flex align-items-center justify-content-between page-header-breadcrumb flex-wrap gap-2 mb-4">
            <div>
                <nav>
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item">
                            <Link :href="route('dashboard')">Inicio</Link>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">Tipos de Combustible</li>
                    </ol>
                </nav>
                <h1 class="page-title fw-medium fs-18 mb-0">Tipos de Combustible</h1>
            </div>
            <Link :href="route('tipos-combustible.create')" class="btn btn-primary btn-wave">
                <i class="ri-add-line me-1"></i> Nuevo Tipo
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
            <div class="card-header">
                <div class="card-title">Filtros de búsqueda</div>
            </div>
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-sm-6 col-xl-5">
                        <label class="form-label">Tipo de Combustible</label>
                        <input
                            v-model="filters.tipo_combustible"
                            type="text"
                            class="form-control"
                            placeholder="Buscar por tipo..."
                        />
                    </div>
                    <div class="col-sm-4 col-xl-3">
                        <label class="form-label">Estado</label>
                        <select v-model="filters.estado_tipo_combustible" class="form-select">
                            <option value="">Todos</option>
                            <option value="ACTIVO">ACTIVO</option>
                            <option value="INACTIVO">INACTIVO</option>
                        </select>
                    </div>
                    <div class="col-sm-2 col-xl-2">
                        <button
                            type="button"
                            class="btn btn-outline-secondary btn-wave w-100"
                            @click="clearFilters"
                        >
                            <i class="ri-refresh-line me-1"></i> Limpiar
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabla -->
        <div class="card custom-card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <div class="card-title">
                    Listado
                    <span class="badge bg-primary-transparent text-primary ms-2">
                        {{ tipos.total }} registros
                    </span>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:60px">#</th>
                                <th>Tipo de Combustible</th>
                                <th style="width:160px">Estado</th>
                                <th style="width:130px">Creado</th>
                                <th style="width:120px" class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="tipos.data.length === 0">
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="ri-drop-line fs-3 d-block mb-2"></i>
                                    No se encontraron tipos de combustible
                                </td>
                            </tr>
                            <tr v-for="(tipo, idx) in tipos.data" :key="tipo.id">
                                <td class="text-muted small">
                                    {{ (tipos.current_page - 1) * tipos.per_page + idx + 1 }}
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="avatar avatar-sm rounded-circle bg-primary-transparent text-primary d-flex align-items-center justify-content-center fw-semibold">
                                            {{ tipo.tipo_combustible.charAt(0).toUpperCase() }}
                                        </span>
                                        <span class="fw-medium">{{ tipo.tipo_combustible }}</span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge" :class="estadoBadge(tipo.estado_tipo_combustible)">
                                        {{ tipo.estado_tipo_combustible }}
                                    </span>
                                </td>
                                <td class="text-muted small">
                                    {{ new Date(tipo.created_at).toLocaleDateString('es-BO') }}
                                </td>
                                <td class="text-center">
                                    <div class="d-flex gap-1 justify-content-center">
                                        <Link
                                            :href="route('tipos-combustible.edit', tipo.id)"
                                            class="btn btn-sm btn-icon btn-info-light"
                                            title="Editar"
                                        >
                                            <i class="ri-edit-line"></i>
                                        </Link>
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-icon btn-danger-light"
                                            title="Eliminar"
                                            @click="confirmDelete(tipo)"
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
                    Mostrando
                    <strong>{{ tipos.from ?? 0 }}</strong> -
                    <strong>{{ tipos.to ?? 0 }}</strong>
                    de <strong>{{ tipos.total }}</strong> resultados
                </div>
                <nav v-if="tipos.last_page > 1" aria-label="Paginación">
                    <ul class="pagination pagination-sm mb-0">
                        <li
                            v-for="link in tipos.links"
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
