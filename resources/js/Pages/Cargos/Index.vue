<script setup>
import { computed, nextTick, ref, watch } from 'vue'
import { Head, Link, router, useForm, InfiniteScroll } from '@inertiajs/vue3'
import Maindashboard from '@/Layouts/Maindashboard.vue'
defineOptions({ layout: Maindashboard })
import InputError from '@/Components/InputError.vue'
import { confirm } from '@/Utils/alertUtil.js'
import { useBreakpoints, breakpointsTailwind } from '@vueuse/core'

const props = defineProps({
    cargos: Object,
    filters: Object,
    flash: Object,
})

// Debajo de "lg" (tablet en portrait y celular) se muestra un segundo
// listado en tarjetas con scroll infinito en vez de la tabla (ver
// .ai/rules/pages.md, "Listado responsivo con scroll infinito").
const breakpoints = useBreakpoints(breakpointsTailwind)
const isMobile = breakpoints.smaller('lg')

/* ------------------------------------------------------------------ */
/*  Filtros (mismo patrón con debounce que Personas/Index.vue)          */
/* ------------------------------------------------------------------ */
const filtros = ref({
    cargo: props.filters?.cargo ?? '',
    estado_cargo: props.filters?.estado_cargo ?? '',
})

let debounceTimer = null
watch(
    filtros,
    (val) => {
        clearTimeout(debounceTimer)
        debounceTimer = setTimeout(() => {
            router.get(
                route('cargos.index'),
                {
                    cargo: val.cargo || undefined,
                    estado_cargo: val.estado_cargo || undefined,
                },
                { preserveState: true, replace: true },
            )
        }, 350)
    },
    { deep: true },
)

const hayFiltros = computed(() => !!(filtros.value.cargo || filtros.value.estado_cargo))

function limpiarFiltros() {
    filtros.value = { cargo: '', estado_cargo: '' }
}

/* ------------------------------------------------------------------ */
/*  Alta en línea: escribir, Enter, y queda listo para el siguiente     */
/* ------------------------------------------------------------------ */
const nuevoInput = ref(null)
const ultimoAgregado = ref(null)

const nuevo = useForm({
    cargo: '',
    estado_cargo: 'ACTIVO',
})

function agregar() {
    if (!nuevo.cargo.trim() || nuevo.processing) {
        return
    }

    const nombre = nuevo.cargo.trim()

    nuevo.post(route('cargos.store'), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            ultimoAgregado.value = nombre
            nuevo.reset()
            nextTick(() => nuevoInput.value?.focus())
        },
        onError: () => nextTick(() => nuevoInput.value?.focus()),
    })
}

/* ------------------------------------------------------------------ */
/*  Edición en línea (una fila a la vez)                                */
/* ------------------------------------------------------------------ */
const editandoId = ref(null)
const edicion = useForm({
    cargo: '',
    estado_cargo: 'ACTIVO',
})
const edicionInputs = {}

function editar(cargo) {
    editandoId.value = cargo.id
    edicion.cargo = cargo.cargo
    edicion.estado_cargo = cargo.estado_cargo
    edicion.clearErrors()
    nextTick(() => edicionInputs[cargo.id]?.focus())
}

function cancelarEdicion() {
    editandoId.value = null
    edicion.reset()
    edicion.clearErrors()
}

function guardarEdicion(cargo) {
    if (edicion.processing) {
        return
    }

    edicion.put(route('cargos.update', cargo.id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => cancelarEdicion(),
    })
}

/* ------------------------------------------------------------------ */
/*  Activar / desactivar y eliminar                                     */
/* ------------------------------------------------------------------ */
const cambiandoEstadoId = ref(null)

function alternarEstado(cargo) {
    cambiandoEstadoId.value = cargo.id

    router.put(
        route('cargos.update', cargo.id),
        {
            cargo: cargo.cargo,
            estado_cargo: cargo.estado_cargo === 'ACTIVO' ? 'INACTIVO' : 'ACTIVO',
        },
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => (cambiandoEstadoId.value = null),
        },
    )
}

async function eliminar(cargo) {
    const confirmado = await confirm(
        `¿Eliminar el cargo "${cargo.cargo}"?`,
        'Eliminar Cargo',
        'Sí, eliminar',
    )

    if (!confirmado) {
        return
    }

    router.delete(route('cargos.destroy', cargo.id), { preserveScroll: true })
}

const estadoBadge = (estado) =>
    estado === 'ACTIVO'
        ? 'bg-success-transparent text-success'
        : 'bg-danger-transparent text-danger'

const textoPersonas = (n) => (n === 1 ? '1 persona' : `${n} personas`)
</script>

<template>
    <Head title="Cargos" />

    <!-- Page header -->
    <div class="d-flex align-items-center justify-content-between page-header-breadcrumb flex-wrap gap-2 mb-4">
        <div>
            <nav>
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item">
                        <Link :href="route('dashboard')">Inicio</Link>
                    </li>
                    <li class="breadcrumb-item active">Cargos</li>
                </ol>
            </nav>
            <h1 class="page-title fw-medium fs-18 mb-0">Cargos del Personal</h1>
            <p class="text-muted fs-13 mb-0 mt-1">
                Se eligen al registrar personas y conductores. Un cargo inactivo deja de ofrecerse, pero se conserva
                en quienes ya lo tienen.
            </p>
        </div>
    </div>

    <!-- Flash messages -->
    <div v-if="flash?.error" class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="ri-error-warning-line me-2"></i>{{ flash.error }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>

    <div class="card custom-card">
        <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="card-title">
                Listado
                <span class="badge bg-primary-transparent text-primary ms-2">{{ cargos.total }} registros</span>
            </div>
            <div class="d-flex align-items-center flex-wrap gap-2 cargos-filtros">
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="ri-search-line" aria-hidden="true"></i></span>
                    <input v-model="filtros.cargo" type="search" class="form-control" placeholder="Buscar cargo..."
                        aria-label="Buscar cargo" />
                </div>
                <select v-model="filtros.estado_cargo" class="form-select form-select-sm" aria-label="Filtrar por estado">
                    <option value="">Todos los estados</option>
                    <option value="ACTIVO">Activos</option>
                    <option value="INACTIVO">Inactivos</option>
                </select>
                <button v-if="hayFiltros" type="button" class="btn btn-sm btn-light" @click="limpiarFiltros">
                    <i class="ri-close-line me-1" aria-hidden="true"></i>Limpiar
                </button>
            </div>
        </div>

        <!-- Alta en línea -->
        <form v-can="'cargos.crear'" class="cargos-alta" @submit.prevent="agregar">
            <label for="nuevo-cargo" class="form-label fw-medium mb-1">Agregar cargo</label>
            <div class="d-flex gap-2">
                <input id="nuevo-cargo" ref="nuevoInput" v-model="nuevo.cargo" type="text" class="form-control"
                    :class="{ 'is-invalid': nuevo.errors.cargo }" maxlength="150" autocomplete="off"
                    placeholder="Ej: Técnico, Analista, Supervisor..." />
                <button type="submit" class="btn btn-primary btn-wave flex-shrink-0"
                    :disabled="nuevo.processing || !nuevo.cargo.trim()">
                    <span v-if="nuevo.processing" class="spinner-border spinner-border-sm me-1" role="status"></span>
                    <i v-else class="ri-add-line me-1" aria-hidden="true"></i>Agregar
                </button>
            </div>
            <InputError :message="nuevo.errors.cargo" class="mt-1" />
            <small v-if="!nuevo.errors.cargo" class="text-muted d-block mt-1">
                Presiona <kbd>Enter</kbd> para agregarlo y seguir con el siguiente.
            </small>
        </form>

        <!-- Vista tabla: desktop -->
        <div v-if="!isMobile" class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:60px">#</th>
                            <th>Cargo</th>
                            <th style="width:150px" class="text-center">Personas</th>
                            <th style="width:150px">Estado</th>
                            <th style="width:130px" class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="cargos.data.length === 0">
                            <td colspan="5" class="text-center py-5 text-muted">
                                <i class="ri-briefcase-line fs-3 d-block mb-2" aria-hidden="true"></i>
                                {{ hayFiltros ? 'Ningún cargo coincide con la búsqueda.' : 'Aún no hay cargos. Agrega el primero arriba.' }}
                            </td>
                        </tr>
                        <tr v-for="(cargo, idx) in cargos.data" :key="cargo.id"
                            :class="{ 'cargos-fila--nueva': cargo.cargo === ultimoAgregado }">
                            <td class="text-muted small">{{ (cargos.current_page - 1) * cargos.per_page + idx + 1 }}</td>

                            <td>
                                <form v-if="editandoId === cargo.id" class="cargos-edicion" @submit.prevent="guardarEdicion(cargo)"
                                    @keydown.esc="cancelarEdicion">
                                    <input :ref="(el) => (edicionInputs[cargo.id] = el)" v-model="edicion.cargo" type="text"
                                        class="form-control form-control-sm" :class="{ 'is-invalid': edicion.errors.cargo }"
                                        maxlength="150" aria-label="Nombre del cargo" />
                                    <InputError :message="edicion.errors.cargo" class="mt-1" />
                                </form>
                                <span v-else class="fw-medium">{{ cargo.cargo }}</span>
                            </td>

                            <td class="text-center">
                                <Link v-if="cargo.personas_count > 0" v-can="'personas.ver'"
                                    :href="route('personas.index', { id_cargo: cargo.id })"
                                    class="badge bg-primary-transparent text-primary text-decoration-none cargos-numero"
                                    :title="`Ver ${textoPersonas(cargo.personas_count)} con este cargo`">
                                    {{ cargo.personas_count }}
                                </Link>
                                <span v-else class="text-muted cargos-numero">0</span>
                            </td>

                            <td>
                                <button v-can="'cargos.editar'" type="button" class="badge border-0 cargos-estado"
                                    :class="estadoBadge(cargo.estado_cargo)" :disabled="cambiandoEstadoId === cargo.id"
                                    :title="cargo.estado_cargo === 'ACTIVO' ? 'Clic para desactivar' : 'Clic para activar'"
                                    @click="alternarEstado(cargo)">
                                    <span v-if="cambiandoEstadoId === cargo.id" class="spinner-border spinner-border-sm me-1" role="status"></span>
                                    {{ cargo.estado_cargo }}
                                    <i class="ri-arrow-left-right-line ms-1" aria-hidden="true"></i>
                                </button>
                            </td>

                            <td class="text-center">
                                <div v-if="editandoId === cargo.id" class="d-flex gap-1 justify-content-center">
                                    <button type="button" class="btn btn-sm btn-icon btn-success-light" title="Guardar"
                                        aria-label="Guardar" :disabled="edicion.processing" @click="guardarEdicion(cargo)">
                                        <i class="ri-check-line"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-icon btn-light" title="Cancelar (Esc)"
                                        aria-label="Cancelar" @click="cancelarEdicion">
                                        <i class="ri-close-line"></i>
                                    </button>
                                </div>
                                <div v-else class="d-flex gap-1 justify-content-center">
                                    <button v-can="'cargos.editar'" type="button" class="btn btn-sm btn-icon btn-info-light"
                                        title="Editar" aria-label="Editar" @click="editar(cargo)">
                                        <i class="ri-edit-line"></i>
                                    </button>
                                    <button v-can="'cargos.eliminar'" type="button" class="btn btn-sm btn-icon btn-danger-light"
                                        :disabled="cargo.personas_count > 0"
                                        :title="cargo.personas_count > 0 ? 'En uso: desactívalo en lugar de eliminarlo' : 'Eliminar'"
                                        aria-label="Eliminar" @click="eliminar(cargo)">
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
            <div v-if="cargos.data.length === 0" class="text-center py-4 text-muted">
                <i class="ri-briefcase-line fs-3 d-block mb-2" aria-hidden="true"></i>
                {{ hayFiltros ? 'Ningún cargo coincide con la búsqueda.' : 'Aún no hay cargos. Agrega el primero arriba.' }}
            </div>

            <InfiniteScroll v-else data="cargos" only-next as="div" class="d-flex flex-column gap-2">
                <div v-for="cargo in cargos.data" :key="cargo.id" class="list-card-mobile border rounded-3 p-3"
                    :class="{ 'cargos-fila--nueva': cargo.cargo === ultimoAgregado }">
                    <form v-if="editandoId === cargo.id" class="mb-2" @submit.prevent="guardarEdicion(cargo)"
                        @keydown.esc="cancelarEdicion">
                        <input :ref="(el) => (edicionInputs[cargo.id] = el)" v-model="edicion.cargo" type="text"
                            class="form-control" :class="{ 'is-invalid': edicion.errors.cargo }" maxlength="150"
                            aria-label="Nombre del cargo" />
                        <InputError :message="edicion.errors.cargo" class="mt-1" />
                    </form>
                    <div v-else class="d-flex align-items-center justify-content-between gap-2 mb-2">
                        <span class="fw-semibold text-break">{{ cargo.cargo }}</span>
                        <span class="badge flex-shrink-0" :class="estadoBadge(cargo.estado_cargo)">{{ cargo.estado_cargo }}</span>
                    </div>

                    <div class="d-flex align-items-center justify-content-between small mb-2">
                        <span class="text-muted">Personas con este cargo</span>
                        <span class="badge bg-primary-transparent text-primary cargos-numero">{{ cargo.personas_count }}</span>
                    </div>

                    <div class="d-flex align-items-center justify-content-end gap-1 border-top pt-2">
                        <template v-if="editandoId === cargo.id">
                            <button type="button" class="btn btn-sm btn-success-light" :disabled="edicion.processing"
                                @click="guardarEdicion(cargo)">
                                <i class="ri-check-line me-1" aria-hidden="true"></i>Guardar
                            </button>
                            <button type="button" class="btn btn-sm btn-light" @click="cancelarEdicion">Cancelar</button>
                        </template>
                        <template v-else>
                            <button v-can="'cargos.editar'" type="button" class="btn btn-sm btn-light"
                                :disabled="cambiandoEstadoId === cargo.id" @click="alternarEstado(cargo)">
                                {{ cargo.estado_cargo === 'ACTIVO' ? 'Desactivar' : 'Activar' }}
                            </button>
                            <button v-can="'cargos.editar'" type="button" class="btn btn-icon btn-info-light" title="Editar"
                                aria-label="Editar" @click="editar(cargo)">
                                <i class="ri-edit-line"></i>
                            </button>
                            <button v-can="'cargos.eliminar'" type="button" class="btn btn-icon btn-danger-light"
                                :disabled="cargo.personas_count > 0"
                                :title="cargo.personas_count > 0 ? 'En uso: desactívalo en lugar de eliminarlo' : 'Eliminar'"
                                aria-label="Eliminar" @click="eliminar(cargo)">
                                <i class="ri-delete-bin-line"></i>
                            </button>
                        </template>
                    </div>
                </div>

                <template #next="{ loading, hasMore }">
                    <div v-if="loading" class="text-center text-muted small py-2">
                        <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                        Cargando más cargos...
                    </div>
                    <div v-else-if="!hasMore" class="text-center text-muted small py-2">
                        No hay más cargos para mostrar.
                    </div>
                </template>
            </InfiniteScroll>
        </div>

        <!-- Paginador: sólo la tabla desktop (mobile usa scroll infinito). -->
        <div v-if="!isMobile" class="card-footer d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="text-muted small">
                Mostrando
                <strong>{{ cargos.from ?? 0 }}</strong> -
                <strong>{{ cargos.to ?? 0 }}</strong>
                de <strong>{{ cargos.total }}</strong> resultados
            </div>
            <nav v-if="cargos.last_page > 1" aria-label="Paginación">
                <ul class="pagination pagination-sm mb-0">
                    <li v-for="link in cargos.links" :key="link.label" class="page-item"
                        :class="{ active: link.active, disabled: !link.url }">
                        <Link v-if="link.url" :href="link.url" class="page-link" preserve-state v-html="link.label" />
                        <span v-else class="page-link" v-html="link.label" />
                    </li>
                </ul>
            </nav>
        </div>
    </div>
</template>
