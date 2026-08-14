<script setup>
import { ref, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import Maindashboard from '@/Layouts/Maindashboard.vue'
defineOptions({ layout: Maindashboard })

const props = defineProps({
    grifos:  Object,
    filters: Object,
    flash:   Object,
})

const filters = ref({
    razon_social: props.filters?.razon_social ?? '',
    nit:          props.filters?.nit          ?? '',
    ciudad:       props.filters?.ciudad       ?? '',
    estado_grifo: props.filters?.estado_grifo ?? '',
})

let debounceTimer = null
watch(
    filters,
    (val) => {
        clearTimeout(debounceTimer)
        debounceTimer = setTimeout(() => {
            router.get(
                route('grifos.index'),
                {
                    razon_social: val.razon_social || undefined,
                    nit:          val.nit          || undefined,
                    ciudad:       val.ciudad       || undefined,
                    estado_grifo: val.estado_grifo || undefined,
                },
                { preserveState: true, replace: true },
            )
        }, 350)
    },
    { deep: true },
)

function clearFilters() {
    filters.value = { razon_social: '', nit: '', ciudad: '', estado_grifo: '' }
}

function confirmDelete(grifo) {
    if (confirm(`¿Eliminar el grifo "${grifo.razon_social}"?`)) {
        router.delete(route('grifos.destroy', grifo.id))
    }
}

const estadoBadge = (estado) =>
    estado === 'ACTIVO'
        ? 'bg-success-transparent text-success'
        : 'bg-danger-transparent text-danger'
</script>

<template>
    <Head title="Grifos" />

        <!-- Page header -->
        <div class="d-flex align-items-center justify-content-between page-header-breadcrumb flex-wrap gap-2 mb-4">
            <div>
                <nav>
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item"><Link :href="route('dashboard')">Inicio</Link></li>
                        <li class="breadcrumb-item active" aria-current="page">Surtidores</li>
                    </ol>
                </nav>
                <h1 class="page-title fw-medium fs-18 mb-0">Gestión de Surtidores</h1>
            </div>
            <div>
                <Link :href="route('grifos.create')" class="btn btn-primary btn-wave">
                    <i class="ri-add-line me-1"></i> Nuevo Surtidor
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
                        <label class="form-label">Razón Social</label>
                        <input
                            v-model="filters.razon_social"
                            type="text"
                            class="form-control"
                            placeholder="Buscar por razón social..."
                        />
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <label class="form-label">NIT</label>
                        <input
                            v-model="filters.nit"
                            type="text"
                            class="form-control"
                            placeholder="Buscar por NIT..."
                        />
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <label class="form-label">Ciudad</label>
                        <input
                            v-model="filters.ciudad"
                            type="text"
                            class="form-control"
                            placeholder="Buscar por ciudad..."
                        />
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <label class="form-label">Estado</label>
                        <select v-model="filters.estado_grifo" class="form-select">
                            <option value="">Todos</option>
                            <option value="ACTIVO">ACTIVO</option>
                            <option value="INACTIVO">INACTIVO</option>
                        </select>
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
                    Surtidores
                    <span class="badge bg-primary-transparent text-primary ms-2">
                        {{ grifos.total }} registros
                    </span>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover text-nowrap mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Razón Social</th>
                                <th>NIT</th>
                                <th>Ciudad</th>
                                <th>Teléfono</th>
                                <th>Dirección</th>
                                <th>Estado</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="grifos.data.length === 0">
                                <td colspan="8" class="text-center py-4 text-muted">
                                    <i class="ri-gas-station-line fs-3 d-block mb-2"></i>
                                    No se encontraron surtidores que coincidan con los filtros aplicados.
                                </td>
                            </tr>
                            <tr v-for="(grifo, idx) in grifos.data" :key="grifo.id">
                                <td class="text-muted small">{{ (grifos.current_page - 1) * grifos.per_page + idx + 1 }}</td>
                                <td><span class="fw-medium">{{ grifo.razon_social }}</span></td>
                                <td>{{ grifo.nit }}</td>
                                <td>{{ grifo.ciudad ?? '—' }}</td>
                                <td>{{ grifo.telefono ?? '—' }}</td>
                                <td>
                                    <span
                                        class="d-inline-block text-truncate"
                                        style="max-width:200px;"
                                        :title="grifo.direccion"
                                    >
                                        {{ grifo.direccion ?? '—' }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge" :class="estadoBadge(grifo.estado_grifo)">
                                        {{ grifo.estado_grifo }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex gap-1 justify-content-center">
                                        <Link
                                            :href="route('grifos.edit', grifo.id)"
                                            class="btn btn-sm btn-icon btn-info-light"
                                            title="Editar"
                                        >
                                            <i class="ri-edit-line"></i>
                                        </Link>
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-icon btn-danger-light"
                                            title="Eliminar"
                                            @click="confirmDelete(grifo)"
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
                    Mostrando {{ grifos.from ?? 0 }} - {{ grifos.to ?? 0 }}
                    de {{ grifos.total }} resultados
                </div>
                <nav v-if="grifos.last_page > 1">
                    <ul class="pagination pagination-sm mb-0">
                        <li
                            v-for="link in grifos.links"
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
