<script setup>
import { computed, watch } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import Maindashboard from '@/Layouts/Maindashboard.vue'
defineOptions({ layout: Maindashboard })
import InputError from '@/Components/InputError.vue'

const props = defineProps({
    ambito: { type: String, default: 'taller' },
})

const form = useForm({
    tipo_mantenimiento:        '',
    estado_tipo_mantenimiento: 'ACTIVO',
    // El ámbito no es editable: viene del tablero desde el que se creó.
    ambito:                    props.ambito,
    tipo_valor:                '',
    unidad_medida:             '',
})

const esOpDiaria = computed(() => form.ambito === 'operacion_diaria')
const requiereUnidad = computed(() => esOpDiaria.value && form.tipo_valor === 'cantidad')

// Si el valor deja de ser "cantidad" la unidad no aplica.
watch(() => form.tipo_valor, (v) => {
    if (v !== 'cantidad') {
        form.unidad_medida = ''
    }
})

function submit() {
    form.post(route('tipos-mantenimiento.store'))
}
</script>

<template>
    <Head title="Nuevo Tipo de Mantenimiento" />

        <!-- Breadcrumb -->
        <div class="d-flex align-items-center justify-content-between page-header-breadcrumb flex-wrap gap-2 mb-4">
            <div>
                <nav>
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item">
                            <Link :href="route('dashboard')">Inicio</Link>
                        </li>
                        <li class="breadcrumb-item">
                            <Link :href="route('tipos-mantenimiento.index', { ambito })">Tipos de Mantenimiento</Link>
                        </li>
                        <li class="breadcrumb-item active">Nuevo</li>
                    </ol>
                </nav>
                <h1 class="page-title fw-medium fs-18 mb-0">
                    Registrar Tipo de Mantenimiento
                    <span class="badge ms-2" :class="esOpDiaria ? 'bg-info-transparent text-info' : 'bg-secondary-transparent text-secondary'">
                        {{ esOpDiaria ? 'Operación Diaria' : 'Taller' }}
                    </span>
                </h1>
            </div>
            <Link :href="route('tipos-mantenimiento.index', { ambito })" class="btn btn-outline-secondary btn-wave">
                <i class="ri-arrow-left-line me-1"></i> Volver
            </Link>
        </div>

        <div class="row justify-content-center">
            <div class="col-xl-6 col-lg-8">
                <div class="card custom-card">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="ri-tools-line me-2"></i>Datos del Tipo de Mantenimiento
                        </div>
                    </div>
                    <div class="card-body">
                        <form @submit.prevent="submit">
                            <div class="row g-4">

                                <!-- Tipo de mantenimiento -->
                                <div class="col-12">
                                    <label for="tipo_mantenimiento" class="form-label fw-medium">
                                        Tipo de Mantenimiento
                                        <span class="text-danger">*</span>
                                    </label>
                                    <input
                                        id="tipo_mantenimiento"
                                        v-model="form.tipo_mantenimiento"
                                        type="text"
                                        class="form-control"
                                        :class="{ 'is-invalid': form.errors.tipo_mantenimiento }"
                                        placeholder="Ej: Cambio de aceite, Revisión de frenos..."
                                        maxlength="150"
                                        autofocus
                                    />
                                    <InputError :message="form.errors.tipo_mantenimiento" class="mt-1" />
                                </div>

                                <!-- Tipo de valor / Unidad de medida: sólo operación diaria -->
                                <template v-if="esOpDiaria">
                                    <div class="col-12">
                                        <label class="form-label fw-medium">
                                            Tipo de Valor
                                            <span class="text-danger">*</span>
                                        </label>
                                        <div class="d-flex gap-4 mt-1">
                                            <div class="form-check">
                                                <input id="tipo_valor_cantidad" v-model="form.tipo_valor" class="form-check-input" type="radio" value="cantidad" />
                                                <label for="tipo_valor_cantidad" class="form-check-label">Cantidad</label>
                                            </div>
                                            <div class="form-check">
                                                <input id="tipo_valor_booleano" v-model="form.tipo_valor" class="form-check-input" type="radio" value="booleano" />
                                                <label for="tipo_valor_booleano" class="form-check-label">Sí / No</label>
                                            </div>
                                        </div>
                                        <div class="form-text">
                                            "Cantidad" registra un número (con unidad); "Sí / No" registra sólo si se realizó o no.
                                        </div>
                                        <InputError :message="form.errors.tipo_valor" class="mt-1" />
                                    </div>

                                    <div v-if="requiereUnidad" class="col-12">
                                        <label for="unidad_medida" class="form-label fw-medium">
                                            Unidad de Medida
                                            <span class="text-danger">*</span>
                                        </label>
                                        <input
                                            id="unidad_medida"
                                            v-model="form.unidad_medida"
                                            type="text"
                                            class="form-control"
                                            :class="{ 'is-invalid': form.errors.unidad_medida }"
                                            placeholder="Ej: Litros, Km, Horas, PSI..."
                                            maxlength="100"
                                        />
                                        <InputError :message="form.errors.unidad_medida" class="mt-1" />
                                    </div>
                                </template>

                                <!-- Estado -->
                                <div class="col-12">
                                    <label class="form-label fw-medium">
                                        Estado
                                        <span class="text-danger">*</span>
                                    </label>
                                    <div class="d-flex gap-4 mt-1">
                                        <div class="form-check">
                                            <input
                                                id="estado_activo"
                                                v-model="form.estado_tipo_mantenimiento"
                                                class="form-check-input"
                                                type="radio"
                                                value="ACTIVO"
                                            />
                                            <label for="estado_activo" class="form-check-label">
                                                <span class="badge bg-success-transparent text-success">ACTIVO</span>
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input
                                                id="estado_inactivo"
                                                v-model="form.estado_tipo_mantenimiento"
                                                class="form-check-input"
                                                type="radio"
                                                value="INACTIVO"
                                            />
                                            <label for="estado_inactivo" class="form-check-label">
                                                <span class="badge bg-danger-transparent text-danger">INACTIVO</span>
                                            </label>
                                        </div>
                                    </div>
                                    <InputError :message="form.errors.estado_tipo_mantenimiento" class="mt-1" />
                                </div>

                            </div>

                            <!-- Botones -->
                            <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                                <Link
                                    :href="route('tipos-mantenimiento.index', { ambito })"
                                    class="btn btn-outline-secondary btn-wave"
                                >
                                    Cancelar
                                </Link>
                                <button
                                    type="submit"
                                    class="btn btn-primary btn-wave"
                                    :disabled="form.processing"
                                >
                                    <span
                                        v-if="form.processing"
                                        class="spinner-border spinner-border-sm me-1"
                                        role="status"
                                    ></span>
                                    <i v-else class="ri-save-line me-1"></i>
                                    {{ form.processing ? 'Guardando...' : 'Guardar' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
</template>
