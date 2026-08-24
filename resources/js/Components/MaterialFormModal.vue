<script setup>
/**
 * MaterialFormModal — modal reutilizable para crear/editar un material.
 *
 * Pensado para usarse tanto en Materiales/Index.vue como embebido en
 * cualquier otro formulario que necesite dar de alta un material sin
 * abandonar la pantalla (ej. un futuro formulario de carga de material):
 * en vez de useForm/Inertia (que navegaría a materiales.index al guardar),
 * usa axios directamente y el controlador responde en JSON cuando detecta
 * una petición AJAX, así el formulario que lo invoca sigue en su lugar.
 *
 * Uso:
 *   <MaterialFormModal ref="materialModal" @created="onCreado" @updated="onActualizado" />
 *   materialModal.value.open()            // crear
 *   materialModal.value.open(material)    // editar
 */
import { ref, reactive, nextTick } from 'vue'
import axios from 'axios'
import InputError from '@/Components/InputError.vue'
import { useBootstrapModal } from '@/Composables/useBootstrapModal'

const emit = defineEmits(['created', 'updated'])

const modalEl = ref(null)
const modal = useBootstrapModal()

// null = creando un material nuevo; objeto = editando ese material.
const editando = ref(null)
const processing = ref(false)
const errors = reactive({ material: null })

const form = reactive({
    material: '',
})

function resetForm() {
    form.material = ''
    errors.material = null
}

function open(material = null) {
    editando.value = material
    form.material = material?.material ?? ''
    errors.material = null

    nextTick(() => {
        modal.mostrar(modalEl.value)
    })
}

function close() {
    modal.ocultar()
}

async function submit() {
    processing.value = true
    errors.material = null

    try {
        if (editando.value) {
            const { data } = await axios.put(route('materiales.update', editando.value.id), form)
            emit('updated', data.data)
        } else {
            const { data } = await axios.post(route('materiales.store'), form)
            emit('created', data.data)
        }

        close()
        resetForm()
    } catch (error) {
        if (error.response?.status === 422) {
            errors.material = error.response.data.errors?.material?.[0] ?? null
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
                            {{ editando ? 'Editar Material' : 'Nuevo Material' }}
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-0">
                            <label for="material_nombre" class="form-label fw-medium">
                                Material <span class="text-danger">*</span>
                            </label>
                            <input
                                id="material_nombre"
                                v-model="form.material"
                                type="text"
                                class="form-control"
                                :class="{ 'is-invalid': errors.material }"
                                placeholder="Ej: Arena, Grava, Cemento..."
                                maxlength="150"
                                autofocus
                            />
                            <InputError :message="errors.material" class="mt-1" />
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
