<script setup>
/**
 * VehiculoExternoFormModal — modal reutilizable para crear/editar un
 * vehículo externo (de terceros).
 *
 * Mismo patrón que MaterialFormModal: pensado para usarse tanto en
 * VehiculosExternos/Index.vue como embebido en cualquier otro formulario
 * (ej. un futuro formulario de carga de material) sin abandonar la
 * pantalla. Usa axios directamente y el controlador responde en JSON
 * cuando detecta una petición AJAX.
 *
 * Uso:
 *   <VehiculoExternoFormModal ref="modal" @created="onCreado" @updated="onActualizado" />
 *   modal.value.open()                  // crear
 *   modal.value.open(vehiculoExterno)   // editar
 */
import { ref, reactive, nextTick } from 'vue'
import axios from 'axios'
import InputError from '@/Components/InputError.vue'
import { useBootstrapModal } from '@/Composables/useBootstrapModal'

const emit = defineEmits(['created', 'updated'])

const modalEl = ref(null)
const modal = useBootstrapModal()

// null = creando un vehículo nuevo; objeto = editando ese vehículo.
const editando = ref(null)
const processing = ref(false)
const errors = reactive({ nro_placa: null, propietario: null })

const form = reactive({
    nro_placa: '',
    propietario: '',
})

function resetForm() {
    form.nro_placa = ''
    form.propietario = ''
    errors.nro_placa = null
    errors.propietario = null
}

function open(vehiculoExterno = null) {
    editando.value = vehiculoExterno
    form.nro_placa = vehiculoExterno?.nro_placa ?? ''
    form.propietario = vehiculoExterno?.propietario ?? ''
    errors.nro_placa = null
    errors.propietario = null

    nextTick(() => {
        modal.mostrar(modalEl.value)
    })
}

function close() {
    modal.ocultar()
}

async function submit() {
    processing.value = true
    errors.nro_placa = null
    errors.propietario = null

    try {
        if (editando.value) {
            const { data } = await axios.put(route('vehiculos-externos.update', editando.value.id), form)
            emit('updated', data.data)
        } else {
            const { data } = await axios.post(route('vehiculos-externos.store'), form)
            emit('created', data.data)
        }

        close()
        resetForm()
    } catch (error) {
        if (error.response?.status === 422) {
            const validationErrors = error.response.data.errors ?? {}
            errors.nro_placa = validationErrors.nro_placa?.[0] ?? null
            errors.propietario = validationErrors.propietario?.[0] ?? null
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
                        <h5 class="modal-title fw-medium">
                            {{ editando ? 'Editar Vehículo Externo' : 'Nuevo Vehículo Externo' }}
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="vehiculo_externo_placa" class="form-label fw-medium">
                                Nro. de Placa <span class="text-danger">*</span>
                            </label>
                            <input
                                id="vehiculo_externo_placa"
                                v-model="form.nro_placa"
                                type="text"
                                class="form-control"
                                :class="{ 'is-invalid': errors.nro_placa }"
                                placeholder="Ej: 1234-ABC"
                                maxlength="20"
                                autofocus
                            />
                            <InputError :message="errors.nro_placa" class="mt-1" />
                        </div>

                        <div class="mb-0">
                            <label for="vehiculo_externo_propietario" class="form-label fw-medium">
                                Propietario
                            </label>
                            <input
                                id="vehiculo_externo_propietario"
                                v-model="form.propietario"
                                type="text"
                                class="form-control"
                                :class="{ 'is-invalid': errors.propietario }"
                                placeholder="Nombre del propietario (opcional)"
                                maxlength="250"
                            />
                            <InputError :message="errors.propietario" class="mt-1" />
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
