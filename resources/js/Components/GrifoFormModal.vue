<script setup>
/**
 * GrifoFormModal — modal reutilizable para crear un grifo (estación de
 * servicio) sin salir de la pantalla actual.
 *
 * Mismo patrón que MaterialFormModal/VehiculoExternoFormModal: pensado
 * para usarse embebido en otro formulario (ej. Vales/Create.vue) que
 * necesite dar de alta un grifo al vuelo. Usa axios directamente y el
 * controlador responde en JSON cuando detecta una petición AJAX, así el
 * formulario que lo invoca sigue en su lugar.
 *
 * Uso:
 *   <GrifoFormModal ref="grifoModal" @created="onGrifoCreado" />
 *   grifoModal.value.open()
 */
import { ref, reactive, nextTick } from 'vue'
import axios from 'axios'
import InputError from '@/Components/InputError.vue'
import { useBootstrapModal } from '@/Composables/useBootstrapModal'

const emit = defineEmits(['created'])

const modalEl = ref(null)
const modal = useBootstrapModal()

const processing = ref(false)
const errors = reactive({
    razon_social: null,
    nit: null,
    direccion: null,
    ciudad: null,
    telefono: null,
    estado_grifo: null,
})

const form = reactive({
    razon_social: '',
    nit: '',
    direccion: '',
    ciudad: '',
    telefono: '',
    estado_grifo: 'ACTIVO',
})

function resetForm() {
    form.razon_social = ''
    form.nit = ''
    form.direccion = ''
    form.ciudad = ''
    form.telefono = ''
    form.estado_grifo = 'ACTIVO'

    for (const campo in errors) {
        errors[campo] = null
    }
}

function open() {
    resetForm()

    nextTick(() => {
        modal.mostrar(modalEl.value)
    })
}

function close() {
    modal.ocultar()
}

async function submit() {
    processing.value = true

    for (const campo in errors) {
        errors[campo] = null
    }

    try {
        const { data } = await axios.post(route('grifos.store'), form)
        emit('created', data.data)

        close()
        resetForm()
    } catch (error) {
        if (error.response?.status === 422) {
            const validationErrors = error.response.data.errors ?? {}
            for (const campo in errors) {
                errors[campo] = validationErrors[campo]?.[0] ?? null
            }
        } else {
            throw error
        }
    } finally {
        processing.value = false
    }
}

defineExpose({ open, close })
</script>

<template>
    <div ref="modalEl" class="modal fade" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form @submit.prevent="submit">
                    <div class="modal-header">
                        <h5 class="modal-title fw-medium">Nuevo Surtidor</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">

                            <!-- Razón Social -->
                            <div class="col-sm-8">
                                <label class="form-label fw-medium">
                                    Razón Social <span class="text-danger">*</span>
                                </label>
                                <input
                                    v-model="form.razon_social"
                                    type="text"
                                    class="form-control"
                                    :class="{ 'is-invalid': errors.razon_social }"
                                    placeholder="Nombre o razón social del grifo"
                                    maxlength="255"
                                    autofocus
                                />
                                <InputError :message="errors.razon_social" class="mt-1" />
                            </div>

                            <!-- NIT -->
                            <div class="col-sm-4">
                                <label class="form-label fw-medium">
                                    NIT <span class="text-danger">*</span>
                                </label>
                                <input
                                    v-model="form.nit"
                                    type="text"
                                    class="form-control"
                                    :class="{ 'is-invalid': errors.nit }"
                                    placeholder="Ej: 1234567890"
                                    maxlength="30"
                                />
                                <InputError :message="errors.nit" class="mt-1" />
                            </div>

                            <!-- Ciudad -->
                            <div class="col-sm-6">
                                <label class="form-label fw-medium">Ciudad</label>
                                <input
                                    v-model="form.ciudad"
                                    type="text"
                                    class="form-control"
                                    :class="{ 'is-invalid': errors.ciudad }"
                                    placeholder="Ej: La Paz"
                                    maxlength="100"
                                />
                                <InputError :message="errors.ciudad" class="mt-1" />
                            </div>

                            <!-- Teléfono -->
                            <div class="col-sm-6">
                                <label class="form-label fw-medium">Teléfono</label>
                                <input
                                    v-model="form.telefono"
                                    type="text"
                                    class="form-control"
                                    :class="{ 'is-invalid': errors.telefono }"
                                    placeholder="Ej: 2123456"
                                    maxlength="20"
                                />
                                <InputError :message="errors.telefono" class="mt-1" />
                            </div>

                            <!-- Dirección -->
                            <div class="col-12">
                                <label class="form-label fw-medium">Dirección</label>
                                <textarea
                                    v-model="form.direccion"
                                    class="form-control"
                                    :class="{ 'is-invalid': errors.direccion }"
                                    rows="2"
                                    placeholder="Dirección del grifo"
                                    maxlength="250"
                                ></textarea>
                                <InputError :message="errors.direccion" class="mt-1" />
                            </div>

                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary btn-wave" data-bs-dismiss="modal">
                            Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary btn-wave" :disabled="processing">
                            <span v-if="processing" class="spinner-border spinner-border-sm me-1" role="status"></span>
                            <i v-else class="ri-save-line me-1"></i>
                            {{ processing ? 'Guardando...' : 'Guardar' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
