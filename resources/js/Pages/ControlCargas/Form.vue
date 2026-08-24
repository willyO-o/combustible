<script setup>
import { ref, computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import Maindashboard from '@/Layouts/Maindashboard.vue'
defineOptions({ layout: Maindashboard })
import InputError from '@/Components/InputError.vue'
import VehiculoExternoFormModal from '@/Components/VehiculoExternoFormModal.vue'

const props = defineProps({
    carga: Object, // null en creación
    vehiculosExternos: { type: Array, default: () => [] },
})

const isEditing = computed(() => !!props.carga)

// Copia local editable: al dar de alta un vehículo nuevo desde el "+", se
// agrega aquí mismo y queda seleccionado de inmediato.
const vehiculosList = ref([...props.vehiculosExternos])

const vehiculoModal = ref(null)

const form = useForm({
    ...(isEditing.value ? { _method: 'PUT' } : {}),
    id_vehiculo_externo: '',
    nombre_conductor: props.carga?.nombre_conductor ?? '',
    telefono: props.carga?.telefono ?? '',
    observaciones: props.carga?.observaciones ?? '',
})

function onVehiculoCreado(vehiculoExterno) {
    vehiculosList.value.push(vehiculoExterno)
    form.id_vehiculo_externo = vehiculoExterno.id
}

function submit() {
    if (isEditing.value) {
        form.post(route('control-cargas.update', props.carga.id))
    } else {
        form.post(route('control-cargas.store'))
    }
}
</script>

<template>
    <Head :title="isEditing ? `Editar Carga #${carga.nro}` : 'Nueva Carga'" />

    <!-- Breadcrumb -->
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
                    <li class="breadcrumb-item active">{{ isEditing ? 'Editar' : 'Nueva' }}</li>
                </ol>
            </nav>
            <h1 class="page-title fw-medium fs-18 mb-0">
                <template v-if="isEditing">Editar Carga #{{ carga.nro }}</template>
                <template v-else>Nueva Carga</template>
            </h1>
        </div>
        <Link :href="route('control-cargas.index')" class="btn btn-outline-secondary btn-wave">
            <i class="ri-arrow-left-line me-1"></i> Volver
        </Link>
    </div>

    <div class="row justify-content-center">
        <div class="col-xl-7 col-lg-9">
            <form @submit.prevent="submit">
                <div class="card custom-card">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="ri-truck-line me-2"></i>Datos de la Carga
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row g-4">

                            <!-- Vehículo externo -->
                            <div class="col-12">
                                <label class="form-label fw-medium">
                                    Vehículo Externo <span v-if="!isEditing" class="text-danger">*</span>
                                </label>

                                <!-- Al editar el vehículo queda fijo: ya pueden existir viajes registrados en base a él. -->
                                <div v-if="isEditing" class="form-control-plaintext bg-light rounded-2 px-3 py-2">
                                    <strong>{{ carga.vehiculo_externo?.nro_placa ?? '—' }}</strong>
                                    <span v-if="carga.vehiculo_externo?.propietario" class="text-muted"> — {{ carga.vehiculo_externo.propietario }}</span>
                                </div>
                                <div v-else class="d-flex gap-2">
                                    <select
                                        v-model="form.id_vehiculo_externo"
                                        class="form-select"
                                        :class="{ 'is-invalid': form.errors.id_vehiculo_externo }"
                                    >
                                        <option value="" disabled>Seleccione un vehículo...</option>
                                        <option v-for="v in vehiculosList" :key="v.id" :value="v.id">
                                            {{ v.nro_placa }}<template v-if="v.propietario"> — {{ v.propietario }}</template>
                                        </option>
                                    </select>
                                    <button
                                        type="button"
                                        class="btn btn-outline-primary flex-shrink-0"
                                        title="Registrar nuevo vehículo"
                                        @click="vehiculoModal?.open()"
                                    >
                                        <i class="ri-add-line"></i>
                                    </button>
                                </div>
                                <InputError :message="form.errors.id_vehiculo_externo" class="mt-1" />
                            </div>

                            <!-- Conductor externo -->
                            <div class="col-sm-6">
                                <label class="form-label fw-medium">Nombre del Conductor</label>
                                <input
                                    v-model="form.nombre_conductor"
                                    type="text"
                                    class="form-control"
                                    :class="{ 'is-invalid': form.errors.nombre_conductor }"
                                    placeholder="Nombre completo (opcional)"
                                    maxlength="250"
                                />
                                <InputError :message="form.errors.nombre_conductor" class="mt-1" />
                            </div>

                            <div class="col-sm-6">
                                <label class="form-label fw-medium">Teléfono</label>
                                <input
                                    v-model="form.telefono"
                                    type="text"
                                    class="form-control"
                                    :class="{ 'is-invalid': form.errors.telefono }"
                                    placeholder="Teléfono (opcional)"
                                    maxlength="30"
                                />
                                <InputError :message="form.errors.telefono" class="mt-1" />
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-medium">Observaciones</label>
                                <textarea
                                    v-model="form.observaciones"
                                    class="form-control"
                                    :class="{ 'is-invalid': form.errors.observaciones }"
                                    rows="3"
                                    placeholder="Observaciones (opcional)"
                                ></textarea>
                                <InputError :message="form.errors.observaciones" class="mt-1" />
                            </div>

                        </div>
                    </div>
                </div>

                <!-- Botones -->
                <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                    <Link :href="route('control-cargas.index')" class="btn btn-outline-secondary btn-wave">
                        Cancelar
                    </Link>
                    <button type="submit" class="btn btn-primary btn-wave" :disabled="form.processing">
                        <span v-if="form.processing" class="spinner-border spinner-border-sm me-1" role="status"></span>
                        <i v-else class="ri-save-line me-1"></i>
                        <template v-if="isEditing">{{ form.processing ? 'Actualizando...' : 'Actualizar' }}</template>
                        <template v-else>{{ form.processing ? 'Abriendo...' : 'Abrir Carga' }}</template>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal reutilizable para dar de alta un vehículo al vuelo -->
    <VehiculoExternoFormModal ref="vehiculoModal" @created="onVehiculoCreado" />
</template>
