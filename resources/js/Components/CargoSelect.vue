<script setup>
/**
 * Selector de cargo del personal (opcional) para Personas y Conductores.
 *
 * `cargos` llega del backend (Cargo::seleccionables(): los ACTIVOS más, al
 * editar, el que ya tiene la persona aunque se haya desactivado). Quien tiene
 * el permiso cargos.crear ve un botón "+" para dar de alta un cargo que
 * falta sin salir del formulario (SweetAlert2 + POST cargos.store-rapido);
 * el cargo nuevo queda agregado a la lista y seleccionado.
 *
 * Uso:
 *   <CargoSelect v-model="form.id_cargo" :cargos="props.cargos" :error="form.errors.id_cargo" />
 */
import { ref, watch } from 'vue'
import axios from 'axios'
import Swal from 'sweetalert2'

const props = defineProps({
    modelValue: { type: [Number, String], default: '' },
    cargos: { type: Array, default: () => [] },
    error: { type: String, default: '' },
    id: { type: String, default: 'id_cargo' },
})

const emit = defineEmits(['update:modelValue'])

// Copia local: el alta rápida agrega el cargo nuevo sin recargar la página.
const opciones = ref([...props.cargos])
watch(() => props.cargos, (valor) => (opciones.value = [...valor]))

function onChange(event) {
    const valor = event.target.value
    emit('update:modelValue', valor === '' ? '' : Number(valor))
}

async function crearRapido() {
    const { value: cargo } = await Swal.fire({
        title: 'Nuevo cargo',
        input: 'text',
        inputLabel: 'Nombre del cargo',
        inputPlaceholder: 'Ej: Técnico, Analista, Supervisor...',
        inputAttributes: { maxlength: '150', autocapitalize: 'sentences' },
        showCancelButton: true,
        confirmButtonText: 'Guardar',
        cancelButtonText: 'Cancelar',
        showLoaderOnConfirm: true,
        allowOutsideClick: () => !Swal.isLoading(),
        preConfirm: async (nombre) => {
            if (!nombre?.trim()) {
                Swal.showValidationMessage('Escribe el nombre del cargo.')
                return false
            }

            try {
                const { data } = await axios.post(route('cargos.store-rapido'), {
                    cargo: nombre.trim(),
                    estado_cargo: 'ACTIVO',
                })

                return data
            } catch (error) {
                Swal.showValidationMessage(
                    error.response?.data?.errors?.cargo?.[0] ?? 'No se pudo guardar el cargo. Intenta nuevamente.',
                )

                return false
            }
        },
    })

    if (cargo) {
        opciones.value = [...opciones.value, { ...cargo, estado_cargo: 'ACTIVO' }]
            .sort((a, b) => a.cargo.localeCompare(b.cargo, 'es'))
        emit('update:modelValue', cargo.id)
    }
}
</script>

<template>
    <div>
        <label :for="id" class="form-label fw-medium">Cargo</label>
        <div class="d-flex gap-2">
            <select :id="id" class="form-select" :class="{ 'is-invalid': error }" :value="modelValue ?? ''" @change="onChange">
                <option value="">— Sin cargo —</option>
                <option v-for="cargo in opciones" :key="cargo.id" :value="cargo.id">
                    {{ cargo.cargo }}{{ cargo.estado_cargo === 'INACTIVO' ? ' (inactivo)' : '' }}
                </option>
            </select>
            <button v-can="'cargos.crear'" type="button" class="btn btn-outline-primary btn-icon flex-shrink-0"
                title="Agregar un cargo nuevo" aria-label="Agregar un cargo nuevo" @click="crearRapido">
                <i class="ri-add-line" aria-hidden="true"></i>
            </button>
        </div>
        <div v-if="error" class="text-danger small mt-1">{{ error }}</div>
        <small v-else class="text-muted">Opcional. Es independiente de los roles de acceso.</small>
    </div>
</template>
