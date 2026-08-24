<script setup>
import { ref, nextTick } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import Maindashboard from '@/Layouts/Maindashboard.vue'
defineOptions({ layout: Maindashboard })
import InputError from '@/Components/InputError.vue'
import MaterialFormModal from '@/Components/MaterialFormModal.vue'
import { useBootstrapModal } from '@/Composables/useBootstrapModal'

const props = defineProps({
    carga: Object,
    viajes: Array,
    materiales: { type: Array, default: () => [] },
    flash: Object,
})

// Copia local editable: al dar de alta un material nuevo desde el "+", se
// agrega aquí mismo y queda seleccionado de inmediato.
const materialesList = ref([...props.materiales])
const materialModal = ref(null)

function onMaterialCreado(material) {
    materialesList.value.push(material)
    form.id_material = material.id
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
</script>

<template>
    <Head :title="`Carga #${carga.nro_carga}`" />

    <!-- Page header -->
    <div class="d-flex align-items-center justify-content-between page-header-breadcrumb flex-wrap gap-2 mb-4">
        <div>
            <nav>
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item">
                        <Link :href="route('dashboard')">Inicio</Link>
                    </li>
                    <li class="breadcrumb-item">
                        <Link :href="route('control-cargas.index')">Control de Cargas</Link>
                    </li>
                    <li class="breadcrumb-item active">Carga #{{ carga.nro_carga }}</li>
                </ol>
            </nav>
            <h1 class="page-title fw-medium fs-18 mb-0">Carga #{{ carga.nro_carga }}</h1>
        </div>
        <Link :href="route('control-cargas.index')" class="btn btn-outline-secondary btn-wave">
            <i class="ri-arrow-left-line me-1"></i> Volver
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

    <!-- Datos de la carga -->
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
            <div v-if="carga.observaciones" class="small text-muted mt-2">
                <i class="ri-file-text-line me-1"></i>{{ carga.observaciones }}
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
            Aún no se registraron viajes en esta carga.
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
                                    @click="materialModal?.open()"
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
                                <label class="form-label fw-medium">Origen</label>
                                <input
                                    v-model="form.origen"
                                    type="text"
                                    class="form-control"
                                    :class="{ 'is-invalid': form.errors.origen }"
                                    placeholder="Ej: Cantera Norte (opcional)"
                                    maxlength="255"
                                />
                                <InputError :message="form.errors.origen" class="mt-1" />
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label fw-medium">Destino</label>
                                <input
                                    v-model="form.destino"
                                    type="text"
                                    class="form-control"
                                    :class="{ 'is-invalid': form.errors.destino }"
                                    placeholder="Ej: Planta (opcional)"
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
                        <button type="submit" class="btn btn-primary btn-wave" :disabled="form.processing || !form.foto || !form.id_material">
                            <span v-if="form.processing" class="spinner-border spinner-border-sm me-1" role="status"></span>
                            <i v-else class="ri-save-line me-1"></i>
                            {{ form.processing ? 'Guardando...' : 'Registrar Viaje' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal reutilizable para dar de alta un material al vuelo -->
    <MaterialFormModal ref="materialModal" @created="onMaterialCreado" />
</template>
