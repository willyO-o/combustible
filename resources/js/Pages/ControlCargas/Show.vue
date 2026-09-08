<script setup>
import { ref, nextTick } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import Maindashboard from '@/Layouts/Maindashboard.vue'
defineOptions({ layout: Maindashboard })
import axios from 'axios'
import Swal from 'sweetalert2'
import InputError from '@/Components/InputError.vue'
import { useBootstrapModal } from '@/Composables/useBootstrapModal'
import { confirm as confirmar, showToast } from '@/Utils/alertUtil.js'

const props = defineProps({
    carga: Object,
    viajes: Array,
    materiales: { type: Array, default: () => [] },
    flash: Object,
})

// Copia local editable: al dar de alta un material nuevo desde el "+", se
// agrega aquí mismo y queda seleccionado de inmediato.
const materialesList = ref([...props.materiales])

function onMaterialCreado(material) {
    materialesList.value.push(material)
    form.id_material = material.id
}

/**
 * Alta rápida de material desde dentro del modal "Registrar Viaje". Se usa
 * SweetAlert2 (en vez de MaterialFormModal, otro modal de Bootstrap) porque
 * dos modales de Bootstrap superpuestos generan un doble backdrop y se ven
 * mal; SweetAlert2 se dibuja por encima sin ese problema.
 */
async function crearMaterialRapido() {
    const { value: material } = await Swal.fire({
        // El "focus trap" del modal de Bootstrap detecta el foco en el
        // popup de SweetAlert2 (montado por defecto en <body>, fuera del
        // .modal) y lo devuelve a la fuerza al modal en cada tecla,
        // haciendo que el input se vea como deshabilitado. Montar el popup
        // dentro del propio elemento del modal evita eso.
        target: modalEl.value,
        title: 'Nuevo Material',
        input: 'text',
        inputLabel: 'Nombre del material',
        inputPlaceholder: 'Ej: Arena, Grava, Cemento...',
        inputAttributes: { maxlength: '150', autocapitalize: 'off' },
        showCancelButton: true,
        confirmButtonText: 'Guardar',
        cancelButtonText: 'Cancelar',
        showLoaderOnConfirm: true,
        allowOutsideClick: () => !Swal.isLoading(),
        preConfirm: async (nombre) => {
            if (!nombre?.trim()) {
                Swal.showValidationMessage('El nombre del material es obligatorio.')
                return false
            }

            try {
                const { data } = await axios.post(route('materiales.store'), { material: nombre.trim() })

                return data.data
            } catch (error) {
                const mensaje = error.response?.data?.errors?.material?.[0] ?? 'No se pudo guardar el material.'
                Swal.showValidationMessage(mensaje)

                return false
            }
        },
    })

    if (material) {
        onMaterialCreado(material)
    }
}

const estadoBadge = (estado) => ({
    ABIERTA: 'bg-success-transparent text-success',
    CERRADA: 'bg-secondary-transparent text-secondary',
    PAGADA: 'bg-primary-transparent text-primary',
}[estado] ?? 'bg-secondary-transparent text-secondary')

/* ------------------------------------------------------------------ *
 * Modal: registrar viaje
 * ------------------------------------------------------------------ */
const modalEl = ref(null)
const modal = useBootstrapModal()
const fotoInput = ref(null)
const fotoPreview = ref(null)

const form = useForm({
    id_material: '',
    foto: null,
    origen: '',
    destino: '',
    detalle: '',
})

function abrirModal() {
    form.reset()
    form.clearErrors()
    fotoPreview.value = null

    nextTick(() => {
        modal.mostrar(modalEl.value)
    })
}

function dispararCamara() {
    fotoInput.value?.click()
}

function onFotoSeleccionada(event) {
    const archivo = event.target.files?.[0] ?? null
    form.foto = archivo
    form.clearErrors('foto')

    if (!archivo) {
        fotoPreview.value = null
        return
    }

    const lector = new FileReader()
    lector.onload = (e) => {
        fotoPreview.value = e.target.result
    }
    lector.readAsDataURL(archivo)
}

function submit() {
    form.post(route('control-cargas.viajes.registrar', props.carga.id), {
        preserveScroll: true,
        onSuccess: () => {
            modal.ocultar()
            form.reset()
            fotoPreview.value = null
        },
    })
}

/* ------------------------------------------------------------------ *
 * Cerrar carga
 * ------------------------------------------------------------------ */
async function cerrarCarga() {
    const confirmado = await confirmar(
        `¿Finalizar el registro de viajes del flete #${props.carga.nro}? Ya no se podrán registrar más viajes.`,
        'Confirmación',
        'Sí, finalizar',
    )
    if (!confirmado) return

    router.post(route('control-cargas.cerrar', props.carga.id), {}, {
        preserveScroll: true,
        onSuccess: () => showToast('Registro de viajes finalizado exitosamente'),
    })
}

/* ------------------------------------------------------------------ *
 * Modal: marcar como pagado
 * ------------------------------------------------------------------ */
const modalPagoEl = ref(null)
const modalPago = useBootstrapModal()

const formPago = useForm({
    monto_pago: '',
    observaciones: '',
})

function abrirModalPago() {
    formPago.reset()
    formPago.clearErrors()

    nextTick(() => {
        modalPago.mostrar(modalPagoEl.value)
    })
}

function submitPago() {
    formPago.post(route('control-cargas.pagar', props.carga.id), {
        preserveScroll: true,
        onSuccess: () => {
            modalPago.ocultar()
            formPago.reset()
            showToast('Flete marcado como pagado')
        },
    })
}
</script>

<template>
    <Head :title="`Flete #${carga.nro}`" />

    <!-- Page header -->
    <div class="d-flex align-items-center justify-content-between page-header-breadcrumb flex-wrap gap-2 mb-4">
        <div>
            <nav>
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item">
                        <Link :href="route('dashboard')">Inicio</Link>
                    </li>
                    <li class="breadcrumb-item">
                        <Link :href="route('control-cargas.index')">Fletes y Viajes</Link>
                    </li>
                    <li class="breadcrumb-item active">Flete #{{ carga.nro }}</li>
                </ol>
            </nav>
            <h1 class="page-title fw-medium fs-18 mb-0">Flete #{{ carga.nro }}</h1>
        </div>
        <div class="d-flex gap-2">
            <Link
                v-can="'control-cargas.ver'"
                :href="route('control-cargas.imprimir', carga.id)"
                target="_blank"
                class="btn btn-outline-warning btn-wave"
            >
                <i class="ri-printer-line me-1"></i> Imprimir informe
            </Link>
            <Link :href="route('control-cargas.index')" class="btn btn-outline-secondary btn-wave">
                <i class="ri-arrow-left-line me-1"></i> Volver
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

    <!-- Datos del flete -->
    <div class="card custom-card mb-3">
        <div class="card-body">
            <div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-2">
                <div>
                    <div class="fw-semibold fs-16">{{ carga.vehiculo_externo?.nro_placa ?? '—' }}</div>
                    <small class="text-muted">{{ carga.vehiculo_externo?.propietario || 'Sin propietario registrado' }}</small>
                </div>
                <span class="badge" :class="estadoBadge(carga.estado_carga)">{{ carga.estado_carga }}</span>
            </div>
            <div v-if="carga.nombre_conductor" class="small text-muted mb-1">
                <i class="ri-user-line me-1"></i>{{ carga.nombre_conductor }}
                <span v-if="carga.telefono"> — {{ carga.telefono }}</span>
            </div>
            <div class="small text-muted mb-1">
                <i class="ri-time-line me-1"></i>Abierta: {{ carga.fecha_apertura }} · por {{ carga.abierta_por }}
            </div>
            <div v-if="carga.es_al_exterior" class="small text-muted mb-1">
                <i class="ri-earth-line me-1"></i>Al exterior — {{ carga.pais }}
            </div>
            <div v-if="carga.detalle" class="small text-muted mb-1">
                <i class="ri-sticky-note-line me-1"></i>{{ carga.detalle }}
            </div>
            <div v-if="carga.fecha_cierre" class="small text-muted mb-1">
                <i class="ri-flag-line me-1"></i>Registro de viajes finalizado: {{ carga.fecha_cierre }} · por {{ carga.cerrada_por }}
            </div>
            <div v-if="carga.fecha_pago" class="small text-muted mb-1">
                <i class="ri-money-dollar-circle-line me-1"></i>Pagada: {{ carga.fecha_pago }}
                <span v-if="carga.monto_pago"> — Bs. {{ carga.monto_pago }}</span>
            </div>
            <div v-if="carga.observaciones" class="small text-muted mt-2">
                <i class="ri-file-text-line me-1"></i>{{ carga.observaciones }}
            </div>

            <div v-if="carga.estado_carga !== 'PAGADA'" class="d-flex flex-wrap gap-2 mt-3 pt-2 border-top">
                <Link
                    v-if="carga.estado_carga === 'ABIERTA'"
                    v-can="'control-cargas.editar'"
                    :href="route('control-cargas.edit', carga.id)"
                    class="btn btn-outline-info btn-sm btn-wave"
                >
                    <i class="ri-edit-line me-1"></i> Editar
                </Link>
                <button
                    v-if="carga.estado_carga === 'ABIERTA'"
                    v-can="'control-cargas.editar'"
                    type="button"
                    class="btn btn-outline-danger btn-sm btn-wave"
                    @click="cerrarCarga"
                >
                    <i class="ri-flag-line me-1"></i> Finalizar Registro de Viajes
                </button>
                <button
                    v-if="carga.estado_carga === 'CERRADA'"
                    v-can="'control-cargas.marcar-pagado'"
                    type="button"
                    class="btn btn-success btn-sm btn-wave"
                    @click="abrirModalPago"
                >
                    <i class="ri-money-dollar-circle-line me-1"></i> Marcar como Pagado
                </button>
            </div>
        </div>
    </div>

    <!-- Viajes -->
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h2 class="fs-16 fw-medium mb-0">
            Viajes
            <span class="badge bg-secondary-transparent text-secondary ms-1">{{ viajes.length }}</span>
        </h2>
        <button
            v-if="carga.estado_carga === 'ABIERTA'"
            v-can="'control-cargas.viajes.registrar'"
            type="button"
            class="btn btn-primary btn-wave"
            @click="abrirModal"
        >
            <i class="ri-camera-line me-1"></i> Registrar Viaje
        </button>
    </div>

    <div v-if="viajes.length === 0" class="card custom-card">
        <div class="card-body text-center py-5 text-muted">
            <i class="ri-route-line fs-1 d-block mb-2"></i>
            Aún no se registraron viajes en este flete.
        </div>
    </div>

    <div v-else class="row row-cols-1 row-cols-sm-2 row-cols-xl-3 g-3">
        <div v-for="viaje in viajes" :key="viaje.id" class="col">
            <div class="card custom-card h-100 mb-0">
                <a :href="viaje.foto_url" target="_blank" rel="noopener">
                    <img :src="viaje.foto_url" class="card-img-top" style="height:180px;object-fit:cover" alt="Evidencia del viaje" />
                </a>
                <div class="card-body">
                    <span class="badge bg-primary-transparent text-primary mb-2">
                        <i class="ri-stack-line me-1"></i>{{ viaje.material ?? 'Sin material' }}
                    </span>
                    <div v-if="viaje.origen || viaje.destino" class="small fw-medium mb-1">
                        <i class="ri-map-pin-line me-1"></i>{{ viaje.origen || '—' }}
                        <i class="ri-arrow-right-line mx-1"></i>{{ viaje.destino || '—' }}
                    </div>
                    <div v-if="viaje.detalle" class="small text-muted mb-1">{{ viaje.detalle }}</div>
                    <div class="small text-muted">
                        <i class="ri-time-line me-1"></i>{{ viaje.fecha }} · {{ viaje.registrado_por }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: registrar viaje -->
    <div ref="modalEl" class="modal fade" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
            <div class="modal-content">
                <form @submit.prevent="submit">
                    <div class="modal-header">
                        <h5 class="modal-title fw-medium">Registrar Viaje</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Material -->
                        <div class="mb-3">
                            <label class="form-label fw-medium">
                                Material <span class="text-danger">*</span>
                            </label>
                            <div class="d-flex gap-2">
                                <select
                                    v-model="form.id_material"
                                    class="form-select"
                                    :class="{ 'is-invalid': form.errors.id_material }"
                                >
                                    <option value="" disabled>Seleccione un material...</option>
                                    <option v-for="m in materialesList" :key="m.id" :value="m.id">
                                        {{ m.material }}
                                    </option>
                                </select>
                                <button
                                    type="button"
                                    class="btn btn-outline-primary flex-shrink-0"
                                    title="Registrar nuevo material"
                                    @click="crearMaterialRapido"
                                >
                                    <i class="ri-add-line"></i>
                                </button>
                            </div>
                            <InputError :message="form.errors.id_material" class="mt-1" />
                        </div>

                        <!-- Captura de foto: pensado para celular (cámara trasera) -->
                        <div class="mb-3 text-center">
                            <input
                                ref="fotoInput"
                                type="file"
                                accept="image/*"
                                capture="environment"
                                class="d-none"
                                @change="onFotoSeleccionada"
                            />
                            <div
                                class="border rounded-3 d-flex align-items-center justify-content-center mx-auto"
                                :class="{ 'border-danger': form.errors.foto }"
                                style="max-width:280px;aspect-ratio:4/3;cursor:pointer;overflow:hidden;background:var(--bs-tertiary-bg, #f8f9fa)"
                                @click="dispararCamara"
                            >
                                <img v-if="fotoPreview" :src="fotoPreview" class="w-100 h-100" style="object-fit:cover" alt="Vista previa" />
                                <div v-else class="text-muted py-4">
                                    <i class="ri-camera-line fs-1 d-block mb-1"></i>
                                    Tomar / Elegir foto
                                </div>
                            </div>
                            <button type="button" class="btn btn-link btn-sm mt-1" @click="dispararCamara">
                                {{ fotoPreview ? 'Cambiar foto' : 'Seleccionar foto' }}
                            </button>
                            <InputError :message="form.errors.foto" class="mt-1" />
                        </div>

                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label fw-medium">Origen <span class="text-danger">*</span></label>
                                <input
                                    v-model="form.origen"
                                    type="text"
                                    class="form-control"
                                    :class="{ 'is-invalid': form.errors.origen }"
                                    placeholder="Ej: Cantera Norte"
                                    maxlength="255"
                                />
                                <InputError :message="form.errors.origen" class="mt-1" />
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label fw-medium">Destino <span class="text-danger">*</span></label>
                                <input
                                    v-model="form.destino"
                                    type="text"
                                    class="form-control"
                                    :class="{ 'is-invalid': form.errors.destino }"
                                    placeholder="Ej: Planta"
                                    maxlength="255"
                                />
                                <InputError :message="form.errors.destino" class="mt-1" />
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-medium">Detalle</label>
                                <textarea
                                    v-model="form.detalle"
                                    class="form-control"
                                    :class="{ 'is-invalid': form.errors.detalle }"
                                    rows="2"
                                    placeholder="Detalle adicional (opcional)"
                                    maxlength="255"
                                ></textarea>
                                <InputError :message="form.errors.detalle" class="mt-1" />
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary btn-wave" data-bs-dismiss="modal">
                            Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary btn-wave" :disabled="form.processing || !form.foto || !form.id_material || !form.origen || !form.destino">
                            <span v-if="form.processing" class="spinner-border spinner-border-sm me-1" role="status"></span>
                            <i v-else class="ri-save-line me-1"></i>
                            {{ form.processing ? 'Guardando...' : 'Registrar Viaje' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: marcar como pagado -->
    <div ref="modalPagoEl" class="modal fade" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form @submit.prevent="submitPago">
                    <div class="modal-header">
                        <h5 class="modal-title fw-medium">Marcar como Pagado</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small">
                            Ambos datos son opcionales y pueden completarse después editando el flete.
                        </p>
                        <div class="mb-3">
                            <label class="form-label fw-medium">Monto de Pago</label>
                            <div class="input-group">
                                <span class="input-group-text">Bs.</span>
                                <input
                                    v-model="formPago.monto_pago"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="form-control"
                                    :class="{ 'is-invalid': formPago.errors.monto_pago }"
                                    placeholder="0.00"
                                />
                            </div>
                            <InputError :message="formPago.errors.monto_pago" class="mt-1" />
                        </div>
                        <div class="mb-1">
                            <label class="form-label fw-medium">Observaciones</label>
                            <textarea
                                v-model="formPago.observaciones"
                                class="form-control"
                                :class="{ 'is-invalid': formPago.errors.observaciones }"
                                rows="3"
                                placeholder="Observaciones (opcional)"
                            ></textarea>
                            <InputError :message="formPago.errors.observaciones" class="mt-1" />
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary btn-wave" data-bs-dismiss="modal">
                            Cancelar
                        </button>
                        <button type="submit" class="btn btn-success btn-wave" :disabled="formPago.processing">
                            <span v-if="formPago.processing" class="spinner-border spinner-border-sm me-1" role="status"></span>
                            <i v-else class="ri-money-dollar-circle-line me-1"></i>
                            {{ formPago.processing ? 'Guardando...' : 'Marcar como Pagado' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
