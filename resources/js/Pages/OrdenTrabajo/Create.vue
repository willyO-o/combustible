<script setup>
import { computed, watch } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import Maindashboard from '@/Layouts/Maindashboard.vue'
defineOptions({ layout: Maindashboard })
import Multiselect from '@vueform/multiselect'

const props = defineProps({
    // Cada vehículo trae 'conductoresAsignados' (ACTIVO/PROVISIONAL
    // vigentes) para el combo "Conductor" que sólo se muestra cuando no hay
    // solicitud de origen seleccionada. Ver
    // OrdenTrabajoController::vehiculosConConductores().
    vehiculos: Array,
    talleres: Array,
    usuarios: Array,
    solicitudesPendientes: Array,
    solicitudPreseleccionada: Object,
})

// Si llegó preseleccionada una solicitud, pre-rellenar el formulario
const form = useForm({
    id_vehiculo:                props.solicitudPreseleccionada?.id_vehiculo ?? '',
    id_conductor:                props.solicitudPreseleccionada?.id_conductor ?? '',
    id_solicitud_mantenimiento: props.solicitudPreseleccionada?.id ?? '',
    id_taller:                  '',
    id_usuario_ejecuta:         '',
    tipo_mantenimiento:         props.solicitudPreseleccionada?.tipo_mantenimiento ?? 'PREVENTIVO',
    nota_emisor:                props.solicitudPreseleccionada?.descripcion_problema ?? '',
    kilometraje_actual:         props.solicitudPreseleccionada?.kilometraje_actual ?? '',
    horometro_actual:           props.solicitudPreseleccionada?.horometro_actual ?? '',
    observacion:                '',
})

// Multiselect necesita un campo 'label' en cada opción (ver
// .ai/rules/pages.md); como el prop no lo trae, se sintetiza aquí en vez de
// tocar el controller.
const solicitudesOpt = computed(() => props.solicitudesPendientes.map(s => ({
    ...s,
    label: `#${s.nro} – ${s.vehiculo?.codigo} – ${s.vehiculo?.nro_placa} – ${s.tipo_mantenimiento}`,
})))

const vehiculosOpt = computed(() => props.vehiculos.map(v => ({
    ...v,
    label: `${v.codigo} – ${v.nro_placa} – ${v.marca}`,
})))

// Al elegir (o quitar) la solicitud de origen, copiar o liberar los datos
// que dependen de ella (vehículo, conductor, categoría, kilometraje/horómetro).
watch(() => form.id_solicitud_mantenimiento, (val) => {
    const s = props.solicitudesPendientes.find(x => x.id == val)
    if (s) {
        form.id_vehiculo          = s.id_vehiculo
        form.id_conductor         = s.id_conductor ?? ''
        form.tipo_mantenimiento   = s.tipo_mantenimiento
        form.nota_emisor          = s.descripcion_problema
        form.kilometraje_actual   = s.kilometraje_actual ?? ''
        form.horometro_actual     = s.horometro_actual ?? ''
    } else {
        // Se quitó la solicitud de origen: liberar los campos para elegir manualmente.
        form.id_vehiculo        = ''
        form.id_conductor       = ''
        form.kilometraje_actual = ''
        form.horometro_actual   = ''
    }
})

// Si la solicitud llegó preseleccionada por la URL, queda fija (no se puede quitar).
// Si se seleccionó manualmente del combo, sus datos (vehículo, conductor, categoría,
// kilometraje/horómetro) también quedan bloqueados: reflejan siempre la solicitud de
// origen. Sin ninguna solicitud seleccionada, el vehículo puede elegirse libremente.
const modoUrl = computed(() => !!props.solicitudPreseleccionada)
const solicitudSeleccionada = computed(() => {
    if (modoUrl.value) return props.solicitudPreseleccionada
    return props.solicitudesPendientes.find(s => s.id == form.id_solicitud_mantenimiento) ?? null
})
const datosLocked = computed(() => !!solicitudSeleccionada.value)

// Conductores actualmente asignados al vehículo elegido, sólo usados cuando
// no hay solicitud de origen (si la hay, el conductor viene de esa
// solicitud, ver el watch de id_solicitud_mantenimiento más arriba).
const conductoresDelVehiculo = computed(() => {
    const vehiculoSeleccionado = props.vehiculos.find((v) => v.id === form.id_vehiculo)
    return vehiculoSeleccionado?.conductoresAsignados ?? []
})

// Al elegir vehículo manualmente (sin solicitud de origen), se limpia el
// conductor si ya no pertenece al nuevo vehículo, y se autoselecciona si sólo
// queda una opción. Cuando SÍ hay solicitud de origen, el watch de arriba ya
// controla id_vehiculo/id_conductor directamente, así que este watch no debe
// interferir.
watch(() => form.id_vehiculo, (val) => {
    if (form.id_solicitud_mantenimiento) {
        return
    }

    if (!val) {
        form.id_conductor = ''
        return
    }

    const conductores = props.vehiculos.find((v) => v.id === val)?.conductoresAsignados ?? []

    if (!conductores.some((c) => c.id === form.id_conductor)) {
        form.id_conductor = conductores.length === 1 ? conductores[0].id : ''
    }
})

const esExterno = computed(() => !!form.id_taller)

function submit() {
    form.post(route('mantenimiento.ordenes.store'))
}
</script>

<template>
    <Head title="Nueva Orden de Trabajo" />
        <!-- Breadcrumb -->
        <div class="d-flex align-items-center justify-content-between page-header-breadcrumb flex-wrap gap-2 mb-4">
            <div>
                <nav>
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item"><Link :href="route('dashboard')">Inicio</Link></li>
                        <li class="breadcrumb-item">
                            <Link :href="route('mantenimiento.ordenes.index')">Órdenes de Trabajo</Link>
                        </li>
                        <li class="breadcrumb-item active">Nueva Orden</li>
                    </ol>
                </nav>
                <h1 class="page-title fw-medium fs-18 mb-0">Emitir Orden de Trabajo</h1>
            </div>
            <Link :href="route('mantenimiento.ordenes.index')" class="btn btn-outline-secondary btn-wave">
                <i class="ri-arrow-left-line me-1"></i> Volver
            </Link>
        </div>

        <!-- Solicitud origen (si la hay) -->
        <div v-if="solicitudPreseleccionada" class="alert alert-info d-flex align-items-start gap-2 mb-4">
            <i class="ri-information-line fs-20 mt-1"></i>
            <div>
                <strong>Solicitud origen #{{ solicitudPreseleccionada.nro }}</strong> —
                {{ solicitudPreseleccionada.tipo_mantenimiento }} —
                Vehículo: {{ solicitudPreseleccionada.vehiculo?.codigo }} – {{ solicitudPreseleccionada.vehiculo?.nro_placa }}
                <br />
                <span class="text-muted">{{ solicitudPreseleccionada.descripcion_problema }}</span>
            </div>
        </div>

        <form @submit.prevent="submit">
            <div class="card custom-card">
                <div class="card-header">
                    <div class="card-title">
                        <i class="ri-file-list-3-line me-2 text-primary"></i>
                        Paso 2 – Emisión de Orden de Trabajo
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <!-- Solicitud origen -->
                        <div class="col-sm-6 col-xl-4">
                            <label class="form-label fw-medium">Solicitud de Origen (opcional)</label>
                            <Multiselect v-model="form.id_solicitud_mantenimiento" :options="solicitudesOpt"
                                value-prop="id" label="label" :searchable="true" :filter-results="true"
                                :disabled="modoUrl" :can-clear="!modoUrl" placeholder="Buscar solicitud..."
                                no-options-text="Sin solicitudes pendientes" no-results-text="Sin resultados"
                                :class="{ 'is-invalid-multiselect': form.errors.id_solicitud_mantenimiento }" />
                            <div v-if="form.errors.id_solicitud_mantenimiento" class="text-danger small mt-1">
                                {{ form.errors.id_solicitud_mantenimiento }}
                            </div>
                            <small v-if="modoUrl" class="text-muted">
                                Fijada desde la solicitud de origen, no se puede cambiar.
                            </small>
                        </div>

                        <!-- Vehículo -->
                        <div class="col-sm-6 col-xl-4">
                            <label class="form-label fw-medium">
                                Vehículo <span class="text-danger">*</span>
                            </label>
                            <Multiselect v-model="form.id_vehiculo" :options="vehiculosOpt" value-prop="id"
                                label="label" :searchable="true" :filter-results="true" :disabled="datosLocked"
                                placeholder="Buscar vehículo..." no-options-text="Sin vehículos activos"
                                no-results-text="Sin resultados"
                                :class="{ 'is-invalid-multiselect': form.errors.id_vehiculo }" />
                            <div v-if="form.errors.id_vehiculo" class="text-danger small mt-1">
                                {{ form.errors.id_vehiculo }}
                            </div>
                            <small v-if="datosLocked" class="text-muted">
                                Viene de la solicitud de origen.
                            </small>
                        </div>

                        <!-- Conductor: sólo se pide cuando no hay solicitud de origen
                             (si la hay, el conductor viene de ella). No es obligatorio:
                             si el vehículo no tiene conductor asignado, la orden se
                             emite igual y la solicitud que se genera queda sin conductor. -->
                        <div v-if="!datosLocked" class="col-sm-6 col-xl-4">
                            <label class="form-label fw-medium">Conductor</label>
                            <Multiselect v-model="form.id_conductor" :options="conductoresDelVehiculo"
                                value-prop="id" label="label" :searchable="true" :filter-results="true"
                                :disabled="!form.id_vehiculo" placeholder="Buscar conductor..."
                                no-options-text="Este vehículo no tiene conductores asignados"
                                no-results-text="Sin resultados"
                                :class="{ 'is-invalid-multiselect': form.errors.id_conductor }" />
                            <div v-if="form.errors.id_conductor" class="text-danger small mt-1">
                                {{ form.errors.id_conductor }}
                            </div>
                            <small v-else-if="form.id_vehiculo && conductoresDelVehiculo.length === 0" class="text-muted">
                                Este vehículo no tiene conductores asignados actualmente.
                            </small>
                        </div>

                        <!-- Responsable de ejecución -->
                        <div class="col-sm-6 col-xl-4">
                            <label class="form-label fw-medium">
                                Tecnico asignado <span class="text-danger">*</span>
                            </label>
                            <select v-model="form.id_usuario_ejecuta" class="form-select"
                                :class="{ 'is-invalid': form.errors.id_usuario_ejecuta }">
                                <option value="">— Seleccione —</option>
                                <option v-for="u in usuarios" :key="u.id" :value="u.id">{{ u.name }}</option>
                            </select>
                            <div v-if="form.errors.id_usuario_ejecuta" class="invalid-feedback">
                                {{ form.errors.id_usuario_ejecuta }}
                            </div>
                        </div>

                        <!-- Categoría -->
                        <div class="col-sm-6 col-xl-4">
                            <label class="form-label fw-medium">
                                Categoría <span class="text-danger">*</span>
                            </label>
                            <select v-model="form.tipo_mantenimiento" class="form-select" :disabled="datosLocked"
                                :class="{ 'is-invalid': form.errors.tipo_mantenimiento }">
                                <option value="PREVENTIVO">Preventivo</option>
                                <option value="CORRECTIVO">Correctivo</option>
                            </select>
                        </div>

                        <!-- Taller (si se deja vacío la orden es interna) -->
                        <div class="col-sm-6 col-xl-4">
                            <label class="form-label fw-medium">
                                Taller Externo
                                <span class="text-muted">(vacío = orden interna)</span>
                            </label>
                            <select v-model="form.id_taller" class="form-select"
                                :class="{ 'is-invalid': form.errors.id_taller }">
                                <option value="">— Interno —</option>
                                <option v-for="t in talleres" :key="t.id" :value="t.id">
                                    {{ t.razon_social }} ({{ t.nit }})
                                </option>
                            </select>
                            <div v-if="form.errors.id_taller" class="invalid-feedback">{{ form.errors.id_taller }}</div>
                            <small class="text-muted">
                                Orden {{ esExterno ? 'EXTERNA' : 'INTERNA' }}
                            </small>
                        </div>

                        <!-- Km actual -->
                        <div class="col-sm-6 col-xl-4">
                            <label class="form-label fw-medium">Kilometraje Actual</label>
                            <div class="input-group">
                                <input v-model="form.kilometraje_actual" type="number" min="0" :disabled="datosLocked"
                                    class="form-control" :class="{ 'is-invalid': form.errors.kilometraje_actual }"
                                    placeholder="Ej: 85000" />
                                <span class="input-group-text">km</span>
                                <div v-if="form.errors.kilometraje_actual" class="invalid-feedback">
                                    {{ form.errors.kilometraje_actual }}
                                </div>
                            </div>
                        </div>

                        <!-- Nota del emisor -->
                        <div class="col-12">
                            <label class="form-label fw-medium">Nota del Emisor (trabajo a realizar)</label>
                            <textarea v-model="form.nota_emisor" rows="3"
                                class="form-control" :class="{ 'is-invalid': form.errors.nota_emisor }"
                                placeholder="Detalle el trabajo que se debe realizar..."></textarea>
                            <div v-if="form.errors.nota_emisor" class="invalid-feedback">
                                {{ form.errors.nota_emisor }}
                            </div>
                        </div>

                        <!-- Observación -->
                        <div class="col-12">
                            <label class="form-label fw-medium">Observación</label>
                            <textarea v-model="form.observacion" rows="2"
                                class="form-control" placeholder="Observaciones adicionales..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-end gap-2">
                    <Link :href="route('mantenimiento.ordenes.index')" class="btn btn-outline-secondary btn-wave">
                        Cancelar
                    </Link>
                    <button type="submit" class="btn btn-primary btn-wave" :disabled="form.processing">
                        <span v-if="form.processing" class="spinner-border spinner-border-sm me-1"></span>
                        <i v-else class="ri-file-list-3-line me-1"></i>
                        Emitir Orden de Trabajo
                    </button>
                </div>
            </div>
        </form>
</template>
