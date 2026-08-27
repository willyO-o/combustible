<script setup>
import { ref, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import Maindashboard from '@/Layouts/Maindashboard.vue'
defineOptions({ layout: Maindashboard })

import Multiselect from '@vueform/multiselect'
import DateRangeFilter from '@/Components/DateRangeFilter.vue'

import { confirm, showToast } from '@/Utils/alertUtil'

const props = defineProps({
    actividades: Object,
    filters: Object,
    flash: Object,
    conductores: Array,
})


const filters = ref({
    nro_placa: props.filters?.nro_placa ?? '',
    fecha_desde: props.filters?.fecha_desde ?? '',
    fecha_hasta: props.filters?.fecha_hasta ?? '',
    id_conductor: props.filters?.id_conductor ?? '',
    estado_operacion: props.filters?.estado_operacion ?? '',
})

let debounceTimer = null
watch(filters, (val) => {
    clearTimeout(debounceTimer)
    debounceTimer = setTimeout(() => {
        router.get(route('operacion-diaria.index'), {
            nro_placa: val.nro_placa || undefined,
            // Sin "|| undefined": si el usuario limpia el filtro debe viajar
            // como '' explícito (no ausente), o el backend reaplicaría el
            // rango por defecto ("Este mes") al no encontrar la clave en el request.
            fecha_desde: val.fecha_desde,
            fecha_hasta: val.fecha_hasta,
            id_conductor: val.id_conductor || undefined,
            estado_operacion: val.estado_operacion || undefined,
        }, { preserveState: true, replace: true })
    }, 350)
}, { deep: true })

function clearFilters() {
    filters.value = { nro_placa: '', fecha_desde: '', fecha_hasta: '', id_conductor: '', estado_operacion: '' }
}

const confirmDelete = async (operacion) => {

    const result = await confirm(`¿Eliminar la opracion del — ${operacion.nro}?`, '¡Esta acción no se puede deshacer!')
    if (!result) return

    router.delete(route('operacion-diaria.destroy', operacion.id),
        {
            preserveScroll: true,
        },
    )
}

const estadoBadge = (estado) => {
    const map = {
        PENDIENTE: 'bg-info-transparent text-info',
        EN_PROGRESO: 'bg-warning-transparent text-warning',
        FINALIZADO: 'bg-secondary-transparent text-secondary',
        VERIFICADO: 'bg-success-transparent text-success',
        OBSERVADO: 'bg-danger-transparent text-danger'
    }
    return map[estado] ?? 'bg-secondary-transparent text-secondary'
}



</script>

<template>

    <Head title="Operaciones Diarias" />
        <div class="d-flex align-items-center justify-content-between page-header-breadcrumb flex-wrap gap-2 mb-4">
            <div>
                <nav>
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item">
                            <Link :href="route('dashboard')">Inicio</Link>
                        </li>
                        <li class="breadcrumb-item active">Operaciones Diarias</li>
                    </ol>
                </nav>
                <h1 class="page-title fw-medium fs-18 mb-0">Operaciones Diarias</h1>
            </div>
            <Link v-can="'operacion-diaria.crear'" :href="route('operacion-diaria.create')" class="btn btn-primary btn-wave">
                <i class="ri-add-line me-1"></i> Registrar Operación Diaria
            </Link>
        </div>

        <div v-if="flash?.success" class="alert alert-success alert-dismissible fade show">
            <i class="ri-checkbox-circle-line me-2"></i>{{ flash.success }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>

        <!-- Filtros -->
        <div class="card custom-card mb-2">

            <div class="card-body p-3">
                <div class="row g-1">
                    <div class="card-title">Filtros</div>

                    <div class="col-sm-6 col-xl-2">
                        <label class="form-label">Nro. Placa</label>
                        <input v-model="filters.nro_placa" type="text" class="form-control" placeholder="Placa..." />
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <DateRangeFilter v-model:fecha-desde="filters.fecha_desde"
                            v-model:fecha-hasta="filters.fecha_hasta" label="Fecha operación" default-range="Este mes" />
                    </div>
                    <div v-if="props.conductores.length" class="col-sm-6 col-xl-4">
                        <label class="form-label">Conductor</label>
                        <Multiselect v-model="filters.id_conductor" :options="props.conductores" label="label"
                            track-by="label" value-prop="id" placeholder="Seleccione" :searchable="true" />
                        <!-- <select v-model="filters.id_conductor" class="form-select">
                            <option value="">Todos</option>
                            <option value="VALE">VALE</option>
                            <option value="PREPAGO">PREPAGO</option>
                        </select> -->
                    </div>
                    <div class="col-sm-6 col-xl-2">
                        <label class="form-label">Estado</label>
                        <select v-model="filters.estado_operacion" class="form-select">
                            <option value="">Todos</option>
                            <option value="PENDIENTE">Pendiente</option>
                            <option value="EN_PROGRESO">En Progreso</option>
                            <option value="FINALIZADO">Finalizado</option>
                            <option value="VERIFICADO">Verificado</option>
                            <option value="OBSERVADO">Observado</option>
                        </select>
                    </div>
                </div>
                <div class="mt-3 d-flex justify-content-end">
                    <button type="button" class="btn btn-outline-secondary btn-sm btn-wave" @click="clearFilters">
                        <i class="ri-refresh-line me-1"></i> Limpiar
                    </button>
                </div>
            </div>
        </div>

        <!-- Tabla -->
        <div class="card custom-card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <div class="card-title">
                    Registros
                    <span class="badge bg-primary-transparent text-primary ms-2">{{ actividades.total }}</span>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover text-nowrap mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Nro</th>
                                <th>Vehículo</th>
                                <th>Fecha In./Fin</th>
                                <th>Conductor</th>
                                <th>Km / Hm</th>
                                <th>Horas Trabajadas</th>
                                <th>Estado</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="actividades.data.length === 0">
                                <td colspan="11" class="text-center py-4 text-muted">
                                    <i class="ri-gas-station-line fs-3 d-block mb-2"></i>
                                    No se encontraron registros
                                </td>
                            </tr>
                            <tr v-for="actividad in actividades.data" :key="actividad.id">
                                <td>{{ actividad.nro }}</td>

                                <td>
                                    <span class="fw-semibold d-block">{{ actividad.vehiculo?.codigo ?? '—' }}</span>
                                    <span class="fw-semibold">{{ actividad.vehiculo?.nro_placa ?? '—' }}</span>
                                    <small v-if="actividad.vehiculo?.marca" class="text-muted d-block">{{
                                        actividad.vehiculo.marca }}</small>
                                </td>
                                <td>
                                    <small class="text-muted d-block"> Turno: {{ actividad.turno }}</small>
                                    <small class="text-muted d-block"> {{ actividad.fecha_inicio }}</small>
                                    <small class="text-muted d-block"> {{ actividad.fecha_fin }}</small>
                                </td>
                                <td>
                                    {{ actividad.conductor?.persona?.nombre_completo ?? '—' }}
                                </td>
                                <td>
                                    <small v-if="actividad.kilometraje_inicio" class="text-muted d-block"> km I: <b> {{
                                        actividad.kilometraje_inicio }}</b> </small>
                                    <small v-if="actividad.kilometraje_inicio" class="text-muted d-block"> km F: <b>{{
                                        actividad.kilometraje_fin }}</b> </small>

                                    <small v-if="actividad.horometro_inicio" class="text-muted d-block"> Hm I: <b>{{
                                        actividad.horometro_inicio }}</b> </small>
                                    <small v-if="actividad.horometro_inicio" class="text-muted d-block"> Hm F: <b>{{
                                        actividad.horometro_fin }}</b> </small>
                                </td>
                                <td>
                                    {{ actividad.horas_trabajadas ?? '—' }} H
                                </td>
                                <td>
                                    <span class="badge" :class="estadoBadge(actividad.estado)">{{ actividad.estado
                                    }}</span>
                                    <small class="text-muted d-block"> {{ actividad.verificador?.nombre_completo
                                        }}</small>

                                </td>


                                <td class="text-center">
                                    <div class="d-flex gap-1 justify-content-center">
                                        <Link :href="route('operacion-diaria.show', actividad.id)"
                                            class="btn btn-sm btn-icon btn-info-light" title="Ver">
                                            <i class="ri-eye-line"></i>
                                        </Link>

                                        <a v-can="'operacion-diaria.informe'"
                                            :href="route('operacion-diaria.reporte.pdf', actividad.id)" target="_blank"
                                            class="btn btn-danger-light btn-sm btn-icon" title="PDF">
                                            <i class="ri-file-pdf-line"></i>
                                        </a>

                                        <Link v-can="'operacion-diaria.editar'" v-if="actividad.estado != 'VERIFICADO'" :href="route('operacion-diaria.edit', actividad.id)"
                                            class="btn btn-sm btn-icon btn-warning-light" title="Editar">
                                            <i class="ri-edit-line"></i>
                                        </Link>

                                        <button  v-can="'operacion-diaria.eliminar'" v-if="actividad.estado != 'VERIFICADO'" type="button" class="btn btn-sm btn-icon btn-danger-light"
                                            title="Eliminar" @click="confirmDelete(actividad)">
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
                <div class="text-muted small">Mostrando {{ actividades.from ?? 0 }} - {{ actividades.to ?? 0 }} de {{
                    actividades.total }}</div>
                <nav v-if="actividades.last_page > 1">
                    <ul class="pagination pagination-sm mb-0">
                        <li v-for="link in actividades.links" :key="link.label" class="page-item"
                            :class="{ active: link.active, disabled: !link.url }">
                            <Link v-if="link.url" :href="link.url" class="page-link" preserve-state
                                v-html="link.label" />
                            <span v-else class="page-link" v-html="link.label" />
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
</template>
