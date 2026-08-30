<script setup>
import { ref, watch, computed } from 'vue'
import { Head, Link, router, InfiniteScroll } from '@inertiajs/vue3'
import Maindashboard from '@/Layouts/Maindashboard.vue'
import { confirm } from '@/Utils/alertUtil.js'
import { useBreakpoints, breakpointsTailwind } from '@vueuse/core'
defineOptions({ layout: Maindashboard })

const props = defineProps({
    tipos: Object,
    ambito: { type: String, default: 'taller' },
    filters: Object,
    flash: Object,
})

// Un único listado sirve para los dos "tableros" (taller / operación diaria):
// el tablero de operación diaria muestra además las columnas tipo de valor y
// unidad de medida. El ámbito viaja como query param.
const esOpDiaria = computed(() => props.ambito === 'operacion_diaria')

const tableros = [
    { key: 'taller', label: 'Taller', icon: 'ri-hammer-line' },
    { key: 'operacion_diaria', label: 'Operación Diaria', icon: 'ri-calendar-check-line' },
]

// Debajo de "lg" (tablet en portrait y celular) se muestra un segundo
// listado en tarjetas con scroll infinito en vez de la tabla (ver
// .ai/rules/pages.md, "Listado responsivo con scroll infinito").
const breakpoints = useBreakpoints(breakpointsTailwind)
const isMobile = breakpoints.smaller('lg')

const filters = ref({
    tipo_mantenimiento: props.filters?.tipo_mantenimiento ?? '',
    estado_tipo_mantenimiento: props.filters?.estado_tipo_mantenimiento ?? '',
})

let debounceTimer = null
watch(
    filters,
    (val) => {
        clearTimeout(debounceTimer)
        debounceTimer = setTimeout(() => {
            router.get(
                route('tipos-mantenimiento.index'),
                {
                    ambito: props.ambito,
                    tipo_mantenimiento: val.tipo_mantenimiento || undefined,
                    estado_tipo_mantenimiento: val.estado_tipo_mantenimiento || undefined,
                },
                { preserveState: true, replace: true },
            )
        }, 350)
    },
    { deep: true },
)

function clearFilters() {
    filters.value = { tipo_mantenimiento: '', estado_tipo_mantenimiento: '' }
}

async function confirmDelete(tipo) {
    const confirmado = await confirm(
        `¿Eliminar el tipo de mantenimiento "${tipo.tipo_mantenimiento}"?`,
        'Eliminar Tipo de Mantenimiento',
        'Sí, eliminar',
    )

    if (!confirmado) {
        return
    }

    router.delete(route('tipos-mantenimiento.destroy', tipo.id))
}

const estadoBadge = (estado) =>
    estado === 'ACTIVO'
        ? 'bg-success-transparent text-success'
        : 'bg-danger-transparent text-danger'

const tipoValorLabel = (v) => (v === 'cantidad' ? 'Cantidad' : v === 'booleano' ? 'Sí / No' : '—')

// Nº de columnas de la tabla (para el colspan del estado vacío).
const colSpan = computed(() => (esOpDiaria.value ? 7 : 5))
</script>

<template>

    <Head title="Tipos de Mantenimiento" />

    <!-- Page header -->
    <div class="d-flex align-items-center justify-content-between page-header-breadcrumb flex-wrap gap-2 mb-4">
        <div>
            <nav>
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item">
                        <Link :href="route('dashboard')">Inicio</Link>
                    </li>
                    <li class="breadcrumb-item active">Tipos de Mantenimiento</li>
                </ol>
            </nav>
            <h1 class="page-title fw-medium fs-18 mb-0">Tipos de Mantenimiento</h1>
        </div>
        <Link v-can="'tipos-mantenimiento.crear'" :href="route('tipos-mantenimiento.create', { ambito })"
            class="btn btn-primary btn-wave">
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

    <!-- Tableros (taller / operación diaria) -->
    <ul class="nav nav-tabs tab-style-8 mb-4" role="tablist">
        <li v-for="t in tableros" :key="t.key" class="nav-item" role="presentation">
            <Link class="nav-link" :class="{ active: ambito === t.key }"
                :href="route('tipos-mantenimiento.index', { ambito: t.key })" preserve-scroll>
                <i :class="t.icon" class="me-1"></i> {{ t.label }}
            </Link>
        </li>
    </ul>

    <!-- Filtros -->
    <div class="card custom-card mb-4">
        <div class="card-header">
            <div class="card-title">Filtros de búsqueda</div>
        </div>
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-sm-6 col-xl-5">
                    <label class="form-label">Tipo de Mantenimiento</label>
                    <input v-model="filters.tipo_mantenimiento" type="text" class="form-control"
                        placeholder="Buscar por tipo..." />
                </div>
                <div class="col-sm-4 col-xl-3">
                    <label class="form-label">Estado</label>
                    <select v-model="filters.estado_tipo_mantenimiento" class="form-select">
                        <option value="">Todos</option>
                        <option value="ACTIVO">ACTIVO</option>
                        <option value="INACTIVO">INACTIVO</option>
                    </select>
                </div>
                <div class="col-sm-2 col-xl-2">
                    <button type="button" class="btn btn-outline-secondary btn-wave w-100" @click="clearFilters">
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
        <!-- Vista tabla: desktop -->
        <div v-if="!isMobile" class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:60px">#</th>
                            <th>Tipo de Mantenimiento</th>
                            <th v-if="esOpDiaria" style="width:130px">Tipo de Valor</th>
                            <th v-if="esOpDiaria" style="width:150px">Unidad de Medida</th>
                            <th style="width:160px">Estado</th>
                            <th style="width:130px">Creado</th>
                            <th style="width:120px" class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="tipos.data.length === 0">
                            <td :colspan="colSpan" class="text-center py-5 text-muted">
                                <i class="ri-tools-line fs-3 d-block mb-2"></i>
                                No se encontraron tipos de mantenimiento
                            </td>
                        </tr>
                        <tr v-for="(tipo, idx) in tipos.data" :key="tipo.id">
                            <td class="text-muted small">
                                {{ (tipos.current_page - 1) * tipos.per_page + idx + 1 }}
                            </td>
                            <td>
                                <span class="fw-medium">{{ tipo.tipo_mantenimiento }}</span>
                            </td>
                            <td v-if="esOpDiaria">
                                <span class="badge bg-info-transparent text-info">{{ tipoValorLabel(tipo.tipo_valor)
                                    }}</span>
                            </td>
                            <td v-if="esOpDiaria" class="text-muted">
                                {{ tipo.unidad_medida || '—' }}
                            </td>
                            <td>
                                <span class="badge" :class="estadoBadge(tipo.estado_tipo_mantenimiento)">
                                    {{ tipo.estado_tipo_mantenimiento }}
                                </span>
                            </td>
                            <td class="text-muted small">
                                {{ new Date(tipo.created_at).toLocaleDateString('es-BO') }}
                            </td>
                            <td class="text-center">
                                <div class="d-flex gap-1 justify-content-center">
                                    <Link v-can="'tipos-mantenimiento.editar'"
                                        :href="route('tipos-mantenimiento.edit', tipo.id)"
                                        class="btn btn-sm btn-icon btn-info-light" title="Editar">
                                        <i class="ri-edit-line"></i>
                                    </Link>
                                    <button v-can="'tipos-mantenimiento.eliminar'" type="button"
                                        class="btn btn-sm btn-icon btn-danger-light" title="Eliminar"
                                        @click="confirmDelete(tipo)">
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
            <div v-if="tipos.data.length === 0" class="text-center py-4 text-muted">
                <i class="ri-tools-line fs-3 d-block mb-2"></i>
                No se encontraron tipos de mantenimiento
            </div>

            <InfiniteScroll v-else data="tipos" only-next as="div" class="d-flex flex-column gap-2">
                <div v-for="tipo in tipos.data" :key="tipo.id" class="list-card-mobile border rounded-3 p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2">
                            <span
                                class="avatar avatar-sm rounded-circle bg-warning-transparent text-warning d-flex align-items-center justify-content-center fw-semibold">
                                {{ tipo.tipo_mantenimiento.charAt(0).toUpperCase() }}
                            </span>
                            <div>
                                <span class="fw-medium d-block">{{ tipo.tipo_mantenimiento }}</span>
                                <small class="text-muted">{{ new Date(tipo.created_at).toLocaleDateString('es-BO')
                                    }}</small>
                            </div>
                        </div>
                        <span class="badge" :class="estadoBadge(tipo.estado_tipo_mantenimiento)">
                            {{ tipo.estado_tipo_mantenimiento }}
                        </span>
                    </div>

                    <div v-if="esOpDiaria" class="d-flex flex-wrap gap-2 mt-2">
                        <span class="badge bg-info-transparent text-info">
                            Valor: {{ tipoValorLabel(tipo.tipo_valor) }}
                        </span>
                        <span v-if="tipo.unidad_medida" class="badge bg-light text-dark border">
                            Unidad: {{ tipo.unidad_medida }}
                        </span>
                    </div>

                    <div class="d-flex align-items-center justify-content-end gap-1 border-top pt-2 mt-2">
                        <Link v-can="'tipos-mantenimiento.editar'" :href="route('tipos-mantenimiento.edit', tipo.id)"
                            class="btn btn-icon btn-info-light" title="Editar">
                            <i class="ri-edit-line"></i>
                        </Link>
                        <button v-can="'tipos-mantenimiento.eliminar'" type="button"
                            class="btn btn-icon btn-danger-light" title="Eliminar" @click="confirmDelete(tipo)">
                            <i class="ri-delete-bin-line"></i>
                        </button>
                    </div>
                </div>

                <!-- Indicador de carga / fin de lista del scroll infinito -->
                <template #next="{ loading, hasMore }">
                    <div v-if="loading" class="text-center text-muted small py-2">
                        <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                        Cargando más tipos...
                    </div>
                    <div v-else-if="!hasMore" class="text-center text-muted small py-2">
                        No hay más tipos para mostrar.
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
                <strong>{{ tipos.from ?? 0 }}</strong> -
                <strong>{{ tipos.to ?? 0 }}</strong>
                de <strong>{{ tipos.total }}</strong> resultados
            </div>
            <nav v-if="tipos.last_page > 1" aria-label="Paginación">
                <ul class="pagination pagination-sm mb-0">
                    <li v-for="link in tipos.links" :key="link.label" class="page-item"
                        :class="{ active: link.active, disabled: !link.url }">
                        <Link v-if="link.url" :href="link.url" class="page-link" preserve-state v-html="link.label" />
                        <span v-else class="page-link" v-html="link.label" />
                    </li>
                </ul>
            </nav>
        </div>
    </div>
</template>
