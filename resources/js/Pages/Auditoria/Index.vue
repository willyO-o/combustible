<script setup>
import { computed, ref, watch } from 'vue'
import { Head, Link, router, InfiniteScroll } from '@inertiajs/vue3'
import Multiselect from '@vueform/multiselect'
import Maindashboard from '@/Layouts/Maindashboard.vue'
import AuditoriaDetalleModal from '@/Components/AuditoriaDetalleModal.vue'
import DateRangeFilter from '@/Components/DateRangeFilter.vue'
import { useBreakpoints, breakpointsTailwind } from '@vueuse/core'
import { avatarColor, claseEvento, iniciales } from '@/Utils/auditoriaUtil'
import { formatDate } from '@/Utils/dateUtil'

defineOptions({ layout: Maindashboard })

// Debajo de "lg" la tabla de 6 columnas deja de ser cómoda: se cambia por un
// listado en tarjetas con scroll infinito (mismo criterio que Vales/Index.vue).
const breakpoints = useBreakpoints(breakpointsTailwind)
const isMobile = breakpoints.smaller('lg')

const props = defineProps({
    auditorias: Object,
    filters: Object,
    opciones: Object,
})

const filters = ref({
    q: props.filters?.q ?? '',
    auditable_type: props.filters?.auditable_type ?? '',
    event: props.filters?.event ?? '',
    // Llega como texto en la query string; Multiselect compara contra el `id`
    // numérico de las opciones y no marcaría la seleccionada sin convertirlo.
    user_id: props.filters?.user_id ? Number(props.filters.user_id) : null,
    fecha_desde: props.filters?.fecha_desde ?? '',
    fecha_hasta: props.filters?.fecha_hasta ?? '',
})

let debounceTimer = null
watch(
    filters,
    (val) => {
        clearTimeout(debounceTimer)
        debounceTimer = setTimeout(() => {
            router.get(
                route('auditoria.index'),
                {
                    q: val.q || undefined,
                    auditable_type: val.auditable_type || undefined,
                    event: val.event || undefined,
                    user_id: val.user_id || undefined,
                    // Sin "|| undefined": al limpiar el filtro deben viajar
                    // como '' explícito o el backend reaplicaría "Este mes".
                    fecha_desde: val.fecha_desde,
                    fecha_hasta: val.fecha_hasta,
                },
                { preserveState: true, replace: true },
            )
        }, 350)
    },
    { deep: true },
)

function limpiarFiltros() {
    filters.value = {
        q: '',
        auditable_type: '',
        event: '',
        user_id: null,
        fecha_desde: '',
        fecha_hasta: '',
    }
}

const hayFiltrosAplicados = computed(() =>
    Boolean(filters.value.q || filters.value.auditable_type || filters.value.event || filters.value.user_id),
)

const auditoriaSeleccionada = ref(null)
const verDetalle = (id) => { auditoriaSeleccionada.value = id }
const cerrarDetalle = () => { auditoriaSeleccionada.value = null }

// Los campos afectados se muestran como chips; a partir del cuarto se resume
// en un "+N" para que la fila no crezca en alto.
const CAMPOS_VISIBLES = 3
const camposVisibles = (auditoria) => auditoria.campos.slice(0, CAMPOS_VISIBLES)
const camposRestantes = (auditoria) => Math.max(auditoria.total_campos - CAMPOS_VISIBLES, 0)

const fechaCorta = (fecha) => formatDate(fecha, true)
</script>

<template>

    <Head title="Auditoría" />

    <!-- Page header -->
    <div class="d-flex align-items-center justify-content-between page-header-breadcrumb flex-wrap gap-2 mb-4">
        <div>
            <nav>
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item">
                        <Link :href="route('dashboard')">Inicio</Link>
                    </li>
                    <li class="breadcrumb-item active">Auditoría</li>
                </ol>
            </nav>
            <h1 class="page-title fw-medium fs-18 mb-0">Bitácora de auditoría</h1>
        </div>
        <p class="text-muted mb-0 fs-12 auditoria-encabezado-nota">
            <i class="ri-shield-check-line me-1"></i>
            Cada creación, modificación, eliminación y restauración del sistema queda registrada aquí.
        </p>
    </div>

    <!-- Filtros -->
    <div class="card custom-card mb-4">
        <div class="card-header">
            <div class="card-title">Filtros de búsqueda</div>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-sm-6 col-xl-3">
                    <label class="form-label" for="auditoria-q">Buscar</label>
                    <input id="auditoria-q" v-model="filters.q" type="search" class="form-control"
                        placeholder="Placa, nro. de vale, usuario…" />
                </div>
                <div class="col-sm-6 col-xl-3">
                    <label class="form-label" for="auditoria-modulo">Módulo</label>
                    <select id="auditoria-modulo" v-model="filters.auditable_type" class="form-select">
                        <option value="">Todos los módulos</option>
                        <option v-for="modulo in opciones.modulos" :key="modulo.clave" :value="modulo.clave">
                            {{ modulo.label }}
                        </option>
                    </select>
                </div>
                <div class="col-sm-6 col-xl-2">
                    <label class="form-label" for="auditoria-evento">Acción</label>
                    <select id="auditoria-evento" v-model="filters.event" class="form-select">
                        <option value="">Todas</option>
                        <option v-for="evento in opciones.eventos" :key="evento.clave" :value="evento.clave">
                            {{ evento.label }}
                        </option>
                    </select>
                </div>
                <div class="col-sm-6 col-xl-2">
                    <label class="form-label">Usuario</label>
                    <Multiselect v-model="filters.user_id" :options="opciones.usuarios" value-prop="id" label="label"
                        :searchable="true" :filter-results="true" :can-clear="true" placeholder="Todos"
                        no-options-text="Sin usuarios" no-results-text="Sin resultados" />
                </div>
                <div class="col-sm-6 col-xl-2">
                    <DateRangeFilter v-model:fecha-desde="filters.fecha_desde"
                        v-model:fecha-hasta="filters.fecha_hasta" label="Fecha" default-range="Este mes" />
                </div>
            </div>
            <div class="mt-3 d-flex justify-content-end">
                <button type="button" class="btn btn-outline-secondary btn-wave" @click="limpiarFiltros">
                    <i class="ri-refresh-line me-1"></i> Limpiar filtros
                </button>
            </div>
        </div>
    </div>

    <!-- Listado -->
    <div class="card custom-card">
        <div class="card-header d-flex align-items-center justify-content-between">
            <div class="card-title">
                Movimientos
                <span class="badge bg-primary-transparent text-primary ms-2">
                    {{ auditorias.total }} registros
                </span>
            </div>
        </div>

        <!-- Vista tabla: escritorio -->
        <div v-if="!isMobile" class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 auditoria-tabla">
                    <thead class="table-light">
                        <tr>
                            <th style="min-width: 150px">Fecha</th>
                            <th style="min-width: 130px">Acción</th>
                            <th style="min-width: 220px">Módulo / Registro</th>
                            <th style="min-width: 190px">Usuario</th>
                            <th>Campos afectados</th>
                            <th style="min-width: 120px">Origen</th>
                            <th class="text-center" style="min-width: 70px">Detalle</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="auditorias.data.length === 0">
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="ri-history-line fs-3 d-block mb-2"></i>
                                <p class="mb-1 fw-medium">Sin movimientos en este período</p>
                                <p class="mb-0 fs-12">
                                    {{ hayFiltrosAplicados
                                        ? 'Prueba quitando algún filtro o ampliando el rango de fechas.'
                                        : 'Amplía el rango de fechas para ver movimientos anteriores.' }}
                                </p>
                            </td>
                        </tr>
                        <tr v-for="auditoria in auditorias.data" :key="auditoria.id" class="auditoria-fila"
                            @click="verDetalle(auditoria.id)">
                            <td>
                                <span class="d-block fw-medium auditoria-cifra">{{ fechaCorta(auditoria.fecha) }}</span>
                                <small class="text-muted">{{ auditoria.fecha_humana }}</small>
                            </td>
                            <td>
                                <span class="badge" :class="claseEvento(auditoria.evento.clave)">
                                    <i :class="auditoria.evento.icono" class="me-1"></i>{{ auditoria.evento.label }}
                                </span>
                            </td>
                            <td>
                                <span class="fw-medium d-block">
                                    <i :class="auditoria.modelo.icono" class="me-1 text-muted"></i>{{ auditoria.modelo.label }}
                                </span>
                                <small class="text-muted d-block">
                                    {{ auditoria.registro.descriptor ?? 'Registro' }}
                                    <span class="auditoria-cifra">#{{ auditoria.registro.id }}</span>
                                </small>
                            </td>
                            <td>
                                <div v-if="auditoria.usuario" class="d-flex align-items-center gap-2">
                                    <img v-if="auditoria.usuario.foto_url" :src="auditoria.usuario.foto_url" alt=""
                                        class="avatar avatar-sm avatar-rounded" />
                                    <span v-else
                                        class="avatar avatar-sm rounded-circle fw-semibold d-flex align-items-center justify-content-center"
                                        :class="avatarColor(auditoria.usuario.nombre)">
                                        {{ iniciales(auditoria.usuario.nombre) }}
                                    </span>
                                    <div>
                                        <span class="fw-medium d-block">{{ auditoria.usuario.nombre }}</span>
                                        <small class="text-muted">{{ auditoria.usuario.email }}</small>
                                    </div>
                                </div>
                                <div v-else class="d-flex align-items-center gap-2 text-muted">
                                    <span
                                        class="avatar avatar-sm rounded-circle bg-secondary-transparent text-secondary d-flex align-items-center justify-content-center">
                                        <i class="ri-terminal-box-line"></i>
                                    </span>
                                    <div>
                                        <span class="fw-medium d-block">Sistema</span>
                                        <small>Sin sesión de usuario</small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex flex-wrap gap-1">
                                    <span v-for="campo in camposVisibles(auditoria)" :key="campo"
                                        class="badge fw-normal auditoria-chip">
                                        {{ campo }}
                                    </span>
                                    <span v-if="camposRestantes(auditoria)"
                                        class="badge bg-primary-transparent text-primary fw-normal">
                                        +{{ camposRestantes(auditoria) }}
                                    </span>
                                    <span v-if="auditoria.total_campos === 0" class="text-muted fs-12">Sin campos</span>
                                </div>
                            </td>
                            <td class="text-muted auditoria-cifra">{{ auditoria.ip ?? '—' }}</td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-icon btn-primary-light"
                                    title="Ver antes y después" @click.stop="verDetalle(auditoria.id)">
                                    <i class="ri-eye-line"></i>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Vista tarjetas: tablet y celular, con scroll infinito -->
        <div v-else class="card-body p-2">
            <div v-if="auditorias.data.length === 0" class="text-center py-5 text-muted">
                <i class="ri-history-line fs-3 d-block mb-2"></i>
                <p class="mb-1 fw-medium">Sin movimientos en este período</p>
                <p class="mb-0 fs-12">Amplía el rango de fechas o quita algún filtro.</p>
            </div>

            <InfiniteScroll v-else data="auditorias" only-next as="div" class="d-flex flex-column gap-2">
                <div v-for="auditoria in auditorias.data" :key="auditoria.id"
                    class="list-card-mobile border rounded-3 p-3" role="button" tabindex="0"
                    :aria-label="`Ver detalle de ${auditoria.evento.label} en ${auditoria.modelo.label}`"
                    @click="verDetalle(auditoria.id)" @keydown.enter.prevent="verDetalle(auditoria.id)"
                    @keydown.space.prevent="verDetalle(auditoria.id)">

                    <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                        <span class="badge" :class="claseEvento(auditoria.evento.clave)">
                            <i :class="auditoria.evento.icono" class="me-1"></i>{{ auditoria.evento.label }}
                        </span>
                        <small class="text-muted">{{ auditoria.fecha_humana }}</small>
                    </div>

                    <div class="mb-2">
                        <div class="fw-semibold">
                            <i :class="auditoria.modelo.icono" class="me-1 text-muted"></i>{{ auditoria.modelo.label }}
                        </div>
                        <small class="text-muted">
                            {{ auditoria.registro.descriptor ?? 'Registro' }}
                            <span class="auditoria-cifra">#{{ auditoria.registro.id }}</span>
                        </small>
                    </div>

                    <div class="d-flex flex-wrap gap-1 mb-2">
                        <span v-for="campo in camposVisibles(auditoria)" :key="campo"
                            class="badge fw-normal auditoria-chip">{{ campo }}</span>
                        <span v-if="camposRestantes(auditoria)"
                            class="badge bg-primary-transparent text-primary fw-normal">
                            +{{ camposRestantes(auditoria) }}
                        </span>
                    </div>

                    <div class="d-flex align-items-center justify-content-between border-top pt-2">
                        <div class="d-flex align-items-center gap-2 min-w-0">
                            <img v-if="auditoria.usuario?.foto_url" :src="auditoria.usuario.foto_url" alt=""
                                class="avatar avatar-sm avatar-rounded" />
                            <span v-else
                                class="avatar avatar-sm rounded-circle fw-semibold d-flex align-items-center justify-content-center"
                                :class="auditoria.usuario ? avatarColor(auditoria.usuario.nombre) : 'bg-secondary-transparent text-secondary'">
                                <template v-if="auditoria.usuario">{{ iniciales(auditoria.usuario.nombre) }}</template>
                                <i v-else class="ri-terminal-box-line"></i>
                            </span>
                            <span class="fs-12 text-truncate">{{ auditoria.usuario?.nombre ?? 'Sistema' }}</span>
                        </div>
                        <span class="fs-12 text-muted auditoria-cifra">{{ fechaCorta(auditoria.fecha) }}</span>
                    </div>
                </div>

                <template #next="{ loading, hasMore }">
                    <div v-if="loading" class="text-center text-muted small py-2">
                        <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                        Cargando más movimientos...
                    </div>
                    <div v-else-if="!hasMore" class="text-center text-muted small py-2">
                        No hay más movimientos para mostrar.
                    </div>
                </template>
            </InfiniteScroll>
        </div>

        <!-- Paginador: sólo la tabla de escritorio (el listado mobile usa
             scroll infinito). -->
        <div v-if="!isMobile" class="card-footer d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="text-muted small">
                Mostrando {{ auditorias.from ?? 0 }} - {{ auditorias.to ?? 0 }}
                de {{ auditorias.total }} resultados
            </div>
            <nav v-if="auditorias.last_page > 1">
                <ul class="pagination pagination-sm mb-0">
                    <li v-for="link in auditorias.links" :key="link.label" class="page-item"
                        :class="{ active: link.active, disabled: !link.url }">
                        <Link v-if="link.url" :href="link.url" class="page-link" preserve-state v-html="link.label" />
                        <span v-else class="page-link" v-html="link.label" />
                    </li>
                </ul>
            </nav>
        </div>
    </div>

    <!-- Modal de detalle (antes / después) -->
    <AuditoriaDetalleModal :auditoria-id="auditoriaSeleccionada" @close="cerrarDetalle" />

</template>
