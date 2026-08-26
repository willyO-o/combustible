<script setup>
import { computed, nextTick, ref } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import Maindashboard from '@/Layouts/Maindashboard.vue'
defineOptions({ layout: Maindashboard })
import InputError from '@/Components/InputError.vue'
import { useBootstrapModal } from '@/Composables/useBootstrapModal'
import { confirm as confirmar, showToast } from '@/Utils/alertUtil.js'

const props = defineProps({
    orden: Object,
    tiposMantenimiento: Array,
    repuestos: Array,
    puedeModificar: Boolean,
    flash: Object,
})

// El km/horómetro final (a pedir al culminar) depende del tipo de medición
// del vehículo de la orden. Las lecturas por ítem (horometro/kilometraje de
// cada detalle) se piden siempre, porque no siempre coinciden con la lectura
// final del vehículo.
const tipoMedicion = computed(() => props.orden.vehiculo?.tipo_medicion)

const hoy = () => new Date().toISOString().substring(0, 10)

/* ------------------------------------------------------------------ *
 * Agregar un ítem del detalle
 * ------------------------------------------------------------------ */
const itemVacio = () => ({
    id_tipo_mantenimiento: '',
    id_repuesto: '',
    fecha: hoy(),
    horometro: '',
    kilometraje: '',
    cantidad: 1,
})

const form = useForm(itemVacio())

function agregarDetalle() {
    form.post(route('mantenimiento.ordenes.ejecucion.detalles.store', props.orden.id), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset()
            form.fecha = hoy()
            form.cantidad = 1
        },
    })
}

/* ------------------------------------------------------------------ *
 * Editar un ítem ya registrado
 * ------------------------------------------------------------------ */
const modalEditarEl = ref(null)
const modalEditar = useBootstrapModal()
const detalleEnEdicion = ref(null)

const formEditar = useForm({
    id_tipo_mantenimiento: '',
    id_repuesto: '',
    fecha: '',
    horometro: '',
    kilometraje: '',
    cantidad: 1,
})

function abrirEdicion(detalle) {
    detalleEnEdicion.value = detalle
    formEditar.id_tipo_mantenimiento = detalle.id_tipo_mantenimiento
    formEditar.id_repuesto = detalle.id_repuesto ?? ''
    formEditar.fecha = detalle.fecha?.substring(0, 10) ?? hoy()
    formEditar.horometro = detalle.horometro ?? ''
    formEditar.kilometraje = detalle.kilometraje ?? ''
    formEditar.cantidad = detalle.cantidad
    formEditar.clearErrors()

    nextTick(() => modalEditar.mostrar(modalEditarEl.value))
}

function guardarEdicion() {
    formEditar.put(
        route('mantenimiento.ordenes.ejecucion.detalles.update', [props.orden.id, detalleEnEdicion.value.id]),
        {
            preserveScroll: true,
            onSuccess: () => {
                modalEditar.ocultar()
                showToast('Detalle actualizado')
            },
        }
    )
}

async function eliminarDetalle(detalle) {
    const confirmado = await confirmar('¿Eliminar este ítem del detalle?', 'Confirmación', 'Sí, eliminar')
    if (!confirmado) return

    router.delete(route('mantenimiento.ordenes.ejecucion.detalles.destroy', [props.orden.id, detalle.id]), {
        preserveScroll: true,
        onSuccess: () => showToast('Detalle eliminado'),
    })
}

/* ------------------------------------------------------------------ *
 * Culminar la orden
 * ------------------------------------------------------------------ */
const formCulminar = useForm({
    kilometraje_actual: props.orden.kilometraje_actual ?? '',
    horometro_actual: props.orden.horometro_actual ?? '',
    observacion: props.orden.observacion ?? '',
})

const puedeCulminar = computed(() => (props.orden.detalles?.length ?? 0) > 0)

async function culminar() {
    const confirmado = await confirmar(
        'Al culminar la orden ya no se podrán agregar, editar ni eliminar ítems del detalle. ¿Desea continuar?',
        'Confirmación',
        'Sí, culminar'
    )
    if (!confirmado) return

    formCulminar.post(route('mantenimiento.ordenes.ejecucion.culminar', props.orden.id))
}
</script>

<template>
    <Head :title="`Registrar Ejecución – Orden #${orden.nro}`" />
        <!-- Breadcrumb -->
        <div class="d-flex align-items-center justify-content-between page-header-breadcrumb flex-wrap gap-2 mb-4">
            <div>
                <nav>
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item"><Link :href="route('dashboard')">Inicio</Link></li>
                        <li class="breadcrumb-item">
                            <Link :href="route('mantenimiento.ordenes.index')">Órdenes</Link>
                        </li>
                        <li class="breadcrumb-item">
                            <Link :href="route('mantenimiento.ordenes.show', orden.id)">Orden #{{ orden.nro }}</Link>
                        </li>
                        <li class="breadcrumb-item active">Registrar Ejecución</li>
                    </ol>
                </nav>
                <h1 class="page-title fw-medium fs-18 mb-0">
                    Paso 3 – Registro de Ejecución
                </h1>
            </div>
            <Link :href="route('mantenimiento.ordenes.show', orden.id)" class="btn btn-outline-secondary btn-wave">
                <i class="ri-arrow-left-line me-1"></i> Volver
            </Link>
        </div>

        <!-- Flash -->
        <div v-if="flash?.success" class="alert alert-success alert-dismissible fade show">
            <i class="ri-checkbox-circle-line me-2"></i>{{ flash.success }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <div v-if="flash?.error" class="alert alert-danger alert-dismissible fade show">
            <i class="ri-error-warning-line me-2"></i>{{ flash.error }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>

        <!-- Resumen de la orden -->
        <div class="alert alert-primary d-flex align-items-start gap-2 mb-4">
            <i class="ri-file-list-3-line fs-20 mt-1"></i>
            <div>
                <strong>Orden #{{ orden.nro }}</strong> —
                Vehículo: <strong>{{ orden.vehiculo?.nro_placa }} {{ orden.vehiculo?.marca }}</strong> —
                <span class="badge bg-info-transparent text-info">{{ orden.tipo_mantenimiento }}</span>
                <span v-if="orden.taller"> — Taller: {{ orden.taller.razon_social }}</span>
            </div>
        </div>

        <div v-if="!puedeModificar" class="alert alert-secondary d-flex align-items-start gap-2 mb-4">
            <i class="ri-lock-line fs-20 mt-1"></i>
            <div>
                Esta orden ya fue <strong>{{ orden.estado_orden }}</strong>: el detalle de trabajo quedó
                congelado y ya no admite modificaciones.
            </div>
        </div>

        <!-- Agregar ítem -->
        <div v-if="puedeModificar" class="card custom-card mb-4">
            <div class="card-header">
                <div class="card-title">
                    <i class="ri-add-circle-line me-2 text-success"></i>
                    Agregar Ítem del Detalle
                </div>
            </div>
            <div class="card-body">
                <form @submit.prevent="agregarDetalle">
                    <div class="row g-3">
                        <div class="col-sm-6 col-lg-3">
                            <label class="form-label fw-medium">Fecha <span class="text-danger">*</span></label>
                            <input v-model="form.fecha" type="date"
                                class="form-control" :class="{ 'is-invalid': form.errors.fecha }" />
                            <InputError :message="form.errors.fecha" class="mt-1" />
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <label class="form-label fw-medium">Tipo de Mantenimiento <span class="text-danger">*</span></label>
                            <select v-model="form.id_tipo_mantenimiento" class="form-select"
                                :class="{ 'is-invalid': form.errors.id_tipo_mantenimiento }">
                                <option value="">— Seleccione —</option>
                                <option v-for="t in tiposMantenimiento" :key="t.id" :value="t.id">
                                    {{ t.tipo_mantenimiento }}
                                </option>
                            </select>
                            <InputError :message="form.errors.id_tipo_mantenimiento" class="mt-1" />
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <label class="form-label fw-medium">Repuesto</label>
                            <select v-model="form.id_repuesto" class="form-select"
                                :class="{ 'is-invalid': form.errors.id_repuesto }">
                                <option value="">— Sin repuesto (mano de obra) —</option>
                                <option v-for="r in repuestos" :key="r.id" :value="r.id">
                                    {{ r.codigo_repuesto }} – {{ r.nombre_repuesto }} ({{ r.unidad_medida }}) — Stock: {{ r.stock_actual }}
                                </option>
                            </select>
                            <InputError :message="form.errors.id_repuesto" class="mt-1" />
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <label class="form-label fw-medium">Cantidad <span class="text-danger">*</span></label>
                            <input v-model="form.cantidad" type="number" min="1" step="1"
                                class="form-control" :class="{ 'is-invalid': form.errors.cantidad }" />
                            <InputError :message="form.errors.cantidad" class="mt-1" />
                        </div>
                        <div v-if="tipoMedicion === 'horometro'" class="col-sm-6 col-lg-3">
                            <label class="form-label fw-medium">Horómetro <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input v-model="form.horometro" type="number" min="0" step="0.01"
                                    class="form-control" :class="{ 'is-invalid': form.errors.horometro }" />
                                <span class="input-group-text">h</span>
                            </div>
                            <InputError :message="form.errors.horometro" class="mt-1" />
                        </div>
                        <div v-else-if="tipoMedicion === 'kilometraje'" class="col-sm-6 col-lg-3">
                            <label class="form-label fw-medium">Kilometraje <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input v-model="form.kilometraje" type="number" min="0" step="0.01"
                                    class="form-control" :class="{ 'is-invalid': form.errors.kilometraje }" />
                                <span class="input-group-text">km</span>
                            </div>
                            <InputError :message="form.errors.kilometraje" class="mt-1" />
                        </div>
                        <div class="col-lg-9 d-flex align-items-end">
                            <button type="submit" class="btn btn-success btn-wave" :disabled="form.processing">
                                <span v-if="form.processing" class="spinner-border spinner-border-sm me-1"></span>
                                <i v-else class="ri-add-line me-1"></i>
                                Agregar Ítem
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabla de detalle -->
        <div class="card custom-card mb-4">
            <div class="card-header">
                <div class="card-title">
                    <i class="ri-archive-line me-2"></i>
                    Detalle del Trabajo Realizado
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="min-width:110px">Fecha</th>
                                <th style="min-width:200px">Tipo de Mantenimiento</th>
                                <th style="min-width:220px">Repuesto</th>
                                <th style="min-width:90px">Cantidad</th>
                                <th style="min-width:100px">{{ tipoMedicion === 'kilometraje' ? 'Kilometraje' : 'Horómetro' }}</th>
                                <th v-if="puedeModificar" style="width:110px"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!orden.detalles || orden.detalles.length === 0">
                                <td :colspan="puedeModificar ? 6 : 5" class="text-center py-3 text-muted">
                                    Aún no hay ítems registrados.
                                </td>
                            </tr>
                            <tr v-for="detalle in orden.detalles" :key="detalle.id">
                                <td>{{ detalle.fecha?.substring(0, 10).split('-').reverse().join('/') }}</td>
                                <td>{{ detalle.tipo_mantenimiento?.tipo_mantenimiento ?? '—' }}</td>
                                <td>
                                    <span v-if="detalle.repuesto">{{ detalle.repuesto.codigo_repuesto }} – {{ detalle.repuesto.nombre_repuesto }}</span>
                                    <span v-else class="text-muted">— Mano de obra —</span>
                                </td>
                                <td class="text-end">{{ detalle.cantidad }}</td>
                                <td class="text-end">
                                    {{ (tipoMedicion === 'kilometraje' ? detalle.kilometraje : detalle.horometro) != null
                                        ? Number(tipoMedicion === 'kilometraje' ? detalle.kilometraje : detalle.horometro).toFixed(2)
                                        : '—' }}
                                </td>
                                <td v-if="puedeModificar" class="text-center">
                                    <button type="button" class="btn btn-sm btn-outline-primary me-1"
                                        title="Editar" @click="abrirEdicion(detalle)">
                                        <i class="ri-pencil-line"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger"
                                        title="Eliminar" @click="eliminarDetalle(detalle)">
                                        <i class="ri-delete-bin-line"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Culminar la orden -->
        <div v-if="puedeModificar" class="card custom-card">
            <div class="card-header bg-success-transparent">
                <div class="card-title text-success">
                    <i class="ri-checkbox-circle-line me-2"></i>
                    Culminar Orden de Trabajo
                </div>
            </div>
            <div class="card-body">
                <p class="text-muted">
                    Registre las lecturas finales del vehículo y culmine la orden. Una vez culminada, el
                    detalle de trabajo ya no podrá modificarse.
                </p>
                <form @submit.prevent="culminar">
                    <div class="row g-3">
                        <div v-if="tipoMedicion === 'kilometraje'" class="col-sm-6 col-xl-4">
                            <label class="form-label fw-medium">
                                Kilometraje Final <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <input v-model="formCulminar.kilometraje_actual" type="number" min="0"
                                    class="form-control" :class="{ 'is-invalid': formCulminar.errors.kilometraje_actual }"
                                    placeholder="Ej: 87500" />
                                <span class="input-group-text">km</span>
                            </div>
                            <InputError :message="formCulminar.errors.kilometraje_actual" class="mt-1" />
                        </div>

                        <div v-else-if="tipoMedicion === 'horometro'" class="col-sm-6 col-xl-4">
                            <label class="form-label fw-medium">
                                Horómetro Final <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <input v-model="formCulminar.horometro_actual" type="number" min="0"
                                    class="form-control" :class="{ 'is-invalid': formCulminar.errors.horometro_actual }"
                                    placeholder="Ej: 1200" />
                                <span class="input-group-text">h</span>
                            </div>
                            <InputError :message="formCulminar.errors.horometro_actual" class="mt-1" />
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-medium">Observaciones</label>
                            <textarea v-model="formCulminar.observacion" rows="2"
                                class="form-control" placeholder="Observaciones adicionales..."></textarea>
                        </div>
                    </div>

                    <div v-if="!puedeCulminar" class="alert alert-warning mt-3 mb-0 py-2">
                        Debe registrar al menos un ítem del detalle antes de culminar la orden.
                    </div>

                    <div class="d-flex justify-content-end mt-3">
                        <button type="submit" class="btn btn-success btn-wave"
                            :disabled="formCulminar.processing || !puedeCulminar">
                            <span v-if="formCulminar.processing" class="spinner-border spinner-border-sm me-1"></span>
                            <i v-else class="ri-checkbox-circle-line me-1"></i>
                            Culminar Orden
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal: editar ítem -->
        <div ref="modalEditarEl" class="modal fade" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <form @submit.prevent="guardarEdicion">
                        <div class="modal-header">
                            <h5 class="modal-title fw-medium">Editar Ítem del Detalle</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <label class="form-label fw-medium">Fecha <span class="text-danger">*</span></label>
                                    <input v-model="formEditar.fecha" type="date"
                                        class="form-control" :class="{ 'is-invalid': formEditar.errors.fecha }" />
                                    <InputError :message="formEditar.errors.fecha" class="mt-1" />
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label fw-medium">Cantidad <span class="text-danger">*</span></label>
                                    <input v-model="formEditar.cantidad" type="number" min="1" step="1"
                                        class="form-control" :class="{ 'is-invalid': formEditar.errors.cantidad }" />
                                    <InputError :message="formEditar.errors.cantidad" class="mt-1" />
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label fw-medium">Tipo de Mantenimiento <span class="text-danger">*</span></label>
                                    <select v-model="formEditar.id_tipo_mantenimiento" class="form-select"
                                        :class="{ 'is-invalid': formEditar.errors.id_tipo_mantenimiento }">
                                        <option value="">— Seleccione —</option>
                                        <option v-for="t in tiposMantenimiento" :key="t.id" :value="t.id">
                                            {{ t.tipo_mantenimiento }}
                                        </option>
                                    </select>
                                    <InputError :message="formEditar.errors.id_tipo_mantenimiento" class="mt-1" />
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label fw-medium">Repuesto</label>
                                    <select v-model="formEditar.id_repuesto" class="form-select"
                                        :class="{ 'is-invalid': formEditar.errors.id_repuesto }">
                                        <option value="">— Sin repuesto (mano de obra) —</option>
                                        <option v-for="r in repuestos" :key="r.id" :value="r.id">
                                            {{ r.codigo_repuesto }} – {{ r.nombre_repuesto }}
                                        </option>
                                    </select>
                                    <InputError :message="formEditar.errors.id_repuesto" class="mt-1" />
                                </div>
                                <div v-if="tipoMedicion === 'horometro'" class="col-sm-6">
                                    <label class="form-label fw-medium">Horómetro <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input v-model="formEditar.horometro" type="number" min="0" step="0.01"
                                            class="form-control" :class="{ 'is-invalid': formEditar.errors.horometro }" />
                                        <span class="input-group-text">h</span>
                                    </div>
                                    <InputError :message="formEditar.errors.horometro" class="mt-1" />
                                </div>
                                <div v-else-if="tipoMedicion === 'kilometraje'" class="col-sm-6">
                                    <label class="form-label fw-medium">Kilometraje <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input v-model="formEditar.kilometraje" type="number" min="0" step="0.01"
                                            class="form-control" :class="{ 'is-invalid': formEditar.errors.kilometraje }" />
                                        <span class="input-group-text">km</span>
                                    </div>
                                    <InputError :message="formEditar.errors.kilometraje" class="mt-1" />
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary btn-wave" data-bs-dismiss="modal">
                                Cancelar
                            </button>
                            <button type="submit" class="btn btn-primary btn-wave" :disabled="formEditar.processing">
                                <span v-if="formEditar.processing" class="spinner-border spinner-border-sm me-1"></span>
                                <i v-else class="ri-save-line me-1"></i>
                                Guardar Cambios
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
</template>
