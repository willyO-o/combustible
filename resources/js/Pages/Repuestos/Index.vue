<script setup>
import { ref, watch } from 'vue'
import { Head, Link, router, InfiniteScroll } from '@inertiajs/vue3'
import Maindashboard from '@/Layouts/Maindashboard.vue'
import { confirm } from '@/Utils/alertUtil.js'
import { useBreakpoints, breakpointsTailwind } from '@vueuse/core'
defineOptions({ layout: Maindashboard })

const props = defineProps({
    repuestos: Object,
    filters:   Object,
    flash:     Object,
})

// Debajo de "lg" (tablet en portrait y celular) se muestra un segundo
// listado en tarjetas con scroll infinito en vez de la tabla (ver
// .ai/rules/pages.md, "Listado responsivo con scroll infinito").
const breakpoints = useBreakpoints(breakpointsTailwind)
const isMobile = breakpoints.smaller('lg')

const filters = ref({
    nombre_repuesto: props.filters?.nombre_repuesto ?? '',
    codigo_repuesto: props.filters?.codigo_repuesto ?? '',
    estado_repuesto: props.filters?.estado_repuesto ?? '',
})

let debounceTimer = null
watch(
    filters,
    (val) => {
        clearTimeout(debounceTimer)
        debounceTimer = setTimeout(() => {
            router.get(
                route('repuestos.index'),
                {
                    nombre_repuesto: val.nombre_repuesto || undefined,
                    codigo_repuesto: val.codigo_repuesto || undefined,
                    estado_repuesto: val.estado_repuesto || undefined,
                },
                { preserveState: true, replace: true },
            )
        }, 350)
    },
    { deep: true },
)

function clearFilters() {
    filters.value = { nombre_repuesto: '', codigo_repuesto: '', estado_repuesto: '' }
}

async function confirmDelete(repuesto) {
    const confirmado = await confirm(`¿Eliminar el repuesto "${repuesto.nombre_repuesto}"?`, 'Eliminar Repuesto', 'Sí, eliminar')

    if (!confirmado) {
        return
    }

    router.delete(route('repuestos.destroy', repuesto.id))
}

const estadoBadge = (estado) => {
    const map = {
        ACTIVO:   'bg-success-transparent text-success',
        INACTIVO: 'bg-secondary-transparent text-secondary',
        AGOTADO:  'bg-danger-transparent text-danger',
    }
    return map[estado] ?? 'bg-light text-dark'
}
</script>

<template>
    <Head title="Repuestos" />

        <!-- Page header -->
        <div class="d-flex align-items-center justify-content-between page-header-breadcrumb flex-wrap gap-2 mb-4">
            <div>
                <nav>
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item">
                            <Link :href="route('dashboard')">Inicio</Link>
                        </li>
                        <li class="breadcrumb-item active">Repuestos</li>
                    </ol>
                </nav>
                <h1 class="page-title fw-medium fs-18 mb-0">Catálogo de Repuestos</h1>
            </div>
            <Link v-can="'repuestos.crear'" :href="route('repuestos.create')" class="btn btn-primary btn-wave">
                <i class="ri-add-line me-1"></i> Nuevo Repuesto
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
                    <div class="col-sm-6 col-xl-4">
                        <label class="form-label">Nombre</label>
                        <input
                            v-model="filters.nombre_repuesto"
                            type="text"
                            class="form-control"
                            placeholder="Buscar por nombre..."
                        />
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <label class="form-label">Código</label>
                        <input
                            v-model="filters.codigo_repuesto"
                            type="text"
                            class="form-control"
                            placeholder="Buscar por código..."
                        />
                    </div>
                    <div class="col-sm-4 col-xl-3">
                        <label class="form-label">Estado</label>
                        <select v-model="filters.estado_repuesto" class="form-select">
                            <option value="">Todos</option>
                            <option value="ACTIVO">ACTIVO</option>
                            <option value="INACTIVO">INACTIVO</option>
                            <option value="AGOTADO">AGOTADO</option>
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
                        {{ repuestos.total }} registros
                    </span>
                </div>
            </div>
            <!-- Vista tabla: desktop -->
            <div v-if="!isMobile" class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:60px">#</th>
                                <th>Código</th>
                                <th>Nombre</th>
                                <th>Unidad</th>
                                <th class="text-end">Stock</th>
                                <th style="width:130px">Estado</th>
                                <th style="width:120px" class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="repuestos.data.length === 0">
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="ri-tools-line fs-3 d-block mb-2"></i>
                                    No se encontraron repuestos
                                </td>
                            </tr>
                            <tr v-for="(repuesto, idx) in repuestos.data" :key="repuesto.id">
                                <td class="text-muted small">
                                    {{ (repuestos.current_page - 1) * repuestos.per_page + idx + 1 }}
                                </td>
                                <td><span class="badge bg-primary-transparent text-primary">{{ repuesto.codigo_repuesto }}</span></td>
                                <td>
                                    <span class="fw-medium">{{ repuesto.nombre_repuesto }}</span>
                                    <small v-if="repuesto.descripcion_repuesto" class="d-block text-muted">
                                        {{ repuesto.descripcion_repuesto.substring(0, 60) }}{{ repuesto.descripcion_repuesto.length > 60 ? '…' : '' }}
                                    </small>
                                </td>
                                <td>{{ repuesto.unidad_medida }}</td>
                                <td class="text-end fw-medium">{{ repuesto.stock_actual }}</td>
                                <td>
                                    <span class="badge" :class="estadoBadge(repuesto.estado_repuesto)">
                                        {{ repuesto.estado_repuesto }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex gap-1 justify-content-center">
                                        <Link
                                            v-can="'repuestos.editar'"
                                            :href="route('repuestos.edit', repuesto.id)"
                                            class="btn btn-sm btn-icon btn-info-light"
                                            title="Editar"
                                        >
                                            <i class="ri-edit-line"></i>
                                        </Link>
                                        <button
                                            v-can="'repuestos.eliminar'"
                                            type="button"
                                            class="btn btn-sm btn-icon btn-danger-light"
                                            title="Eliminar"
                                            @click="confirmDelete(repuesto)"
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

            <!-- Vista tarjetas: tablet y celular, con scroll infinito -->
            <div v-else class="card-body p-2">
                <div v-if="repuestos.data.length === 0" class="text-center py-4 text-muted">
                    <i class="ri-tools-line fs-3 d-block mb-2"></i>
                    No se encontraron repuestos
                </div>

                <InfiniteScroll v-else data="repuestos" only-next as="div" class="d-flex flex-column gap-2">
                    <div v-for="repuesto in repuestos.data" :key="repuesto.id" class="list-card-mobile border rounded-3 p-3">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="badge bg-primary-transparent text-primary">{{ repuesto.codigo_repuesto }}</span>
                            <span class="badge" :class="estadoBadge(repuesto.estado_repuesto)">
                                {{ repuesto.estado_repuesto }}
                            </span>
                        </div>

                        <div class="mb-2">
                            <span class="fw-semibold d-block">{{ repuesto.nombre_repuesto }}</span>
                            <small v-if="repuesto.descripcion_repuesto" class="text-muted">
                                {{ repuesto.descripcion_repuesto.substring(0, 80) }}{{ repuesto.descripcion_repuesto.length > 80 ? '…' : '' }}
                            </small>
                        </div>

                        <div class="row g-2 small mb-2">
                            <div class="col-6">
                                <span class="text-muted d-block">Unidad</span>
                                <span>{{ repuesto.unidad_medida }}</span>
                            </div>
                            <div class="col-6">
                                <span class="text-muted d-block">Stock</span>
                                <span class="fw-medium">{{ repuesto.stock_actual }}</span>
                            </div>
                        </div>

                        <div class="d-flex align-items-center justify-content-end gap-1 border-top pt-2">
                            <Link v-can="'repuestos.editar'" :href="route('repuestos.edit', repuesto.id)"
                                class="btn btn-icon btn-info-light" title="Editar">
                                <i class="ri-edit-line"></i>
                            </Link>
                            <button v-can="'repuestos.eliminar'" type="button" class="btn btn-icon btn-danger-light"
                                title="Eliminar" @click="confirmDelete(repuesto)">
                                <i class="ri-delete-bin-line"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Indicador de carga / fin de lista del scroll infinito -->
                    <template #next="{ loading, hasMore }">
                        <div v-if="loading" class="text-center text-muted small py-2">
                            <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                            Cargando más repuestos...
                        </div>
                        <div v-else-if="!hasMore" class="text-center text-muted small py-2">
                            No hay más repuestos para mostrar.
                        </div>
                    </template>
                </InfiniteScroll>
            </div>

            <!-- Paginador: sólo la tabla desktop. El listado mobile usa
                 scroll infinito (InfiniteScroll arriba) en vez de páginas
                 numeradas. -->
            <div v-if="!isMobile" class="card-footer d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="text-muted small">
                    Mostrando
                    <strong>{{ repuestos.from ?? 0 }}</strong> -
                    <strong>{{ repuestos.to ?? 0 }}</strong>
                    de <strong>{{ repuestos.total }}</strong> resultados
                </div>
                <nav v-if="repuestos.last_page > 1" aria-label="Paginación">
                    <ul class="pagination pagination-sm mb-0">
                        <li
                            v-for="link in repuestos.links"
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
