<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import Maindashboard from '@/Layouts/Maindashboard.vue'
import SearchSelect from '@/Components/SearchSelect.vue'

import { ref, watch } from 'vue'

const props = defineProps({
    nextNroVale: String,
    tiposCombustible: Array,
})

const form = useForm({
    nro_vale: props.nextNroVale,
    fecha_emision: new Date().toISOString().substring(0, 10),
    litros: '',
    precio: '',
    id_vehiculo: null,
    id_conductor: null,
    id_grifo: null,
    estado_vale: 'PENDIENTE',
    id_tipo_combustible: null,
})

const paramsConductor = ref({id_vehiculo: null})

// Los SearchSelect retornan el objeto completo {id, label}
// Extraemos el id antes de enviar
function submit() {
    form
        .transform((data) => ({
            ...data,
            nro_vale: Number(data.nro_vale),
            id_vehiculo: data.id_vehiculo?.id ?? data.id_vehiculo,
            id_conductor: data.id_conductor?.id ?? data.id_conductor,
            id_grifo: data.id_grifo?.id ?? data.id_grifo,
            id_tipo_combustible: data.id_tipo_combustible?.id ?? data.id_tipo_combustible,
        }))
        .post(route('vales.store'))
}


const vehiculoSeleccionado = (vehiculo) => {


    form.id_tipo_combustible = vehiculo?.meta.id_tipo_combustible ?? null

    paramsConductor.value = {
        id_vehiculo: vehiculo?.id ?? null,
    }


}
</script>

<template>

    <Head title="Nuevo Vale" />

    <Maindashboard>
        <!-- Breadcrumb -->
        <div class="d-flex align-items-center justify-content-between page-header-breadcrumb flex-wrap gap-2 mb-4">
            <div>
                <nav>
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item">
                            <Link :href="route('dashboard')">Inicio</Link>
                        </li>
                        <li class="breadcrumb-item">
                            <Link :href="route('vales.index')">Vales</Link>
                        </li>
                        <li class="breadcrumb-item active">Nuevo</li>
                    </ol>
                </nav>
                <h1 class="page-title fw-medium fs-18 mb-0">Registrar Vale</h1>
            </div>
            <Link :href="route('vales.index')" class="btn btn-outline-secondary btn-wave">
                <i class="ri-arrow-left-line me-1"></i> Volver
            </Link>
        </div>

        <form @submit.prevent="submit">
            <div class="row g-4">

                <!-- Relaciones con búsqueda -->
                <div class="col-xl-6">
                    <div class="card custom-card h-100">
                        <div class="card-header">
                            <div class="card-title">Asignación</div>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">

                                <!-- Vehículo -->
                                <div class="col-12">
                                    <label class="form-label fw-medium">
                                        Vehículo <span class="text-danger">*</span>
                                    </label>
                                    <SearchSelect
                                    @selected="vehiculoSeleccionado"
                                    :object="true"
                                    v-model="form.id_vehiculo" :search-url="route('search.vehiculos')"
                                        placeholder="Buscar por placa o marca (mín. 2 caracteres)..."
                                        :invalid="!!form.errors.id_vehiculo" />
                                    <div v-if="form.errors.id_vehiculo" class="text-danger small mt-1">{{
                                        form.errors.id_vehiculo }}</div>
                                </div>

                                <!-- Conductor -->
                                <div class="col-12">
                                    <label class="form-label fw-medium">
                                        Conductor <span class="text-danger">*</span>
                                    </label>
                                    <SearchSelect v-model="form.id_conductor" :search-url="route('search.conductores')"
                                        placeholder="Buscar por CI, nombre o apellido (mín. 2 caracteres)..."
                                        :params="paramsConductor"
                                        :invalid="!!form.errors.id_conductor" />
                                    <div v-if="form.errors.id_conductor" class="text-danger small mt-1">{{
                                        form.errors.id_conductor }}</div>
                                </div>

                                <!-- Grifo -->
                                <div class="col-12">
                                    <label class="form-label fw-medium">
                                        Estación de servicio <span class="text-danger">*</span>
                                    </label>
                                    <SearchSelect v-model="form.id_grifo" :search-url="route('search.grifos')"
                                        placeholder="Buscar por razón social, NIT o ciudad (mín. 2 caracteres)..."
                                        :invalid="!!form.errors.id_grifo" />
                                    <div v-if="form.errors.id_grifo" class="text-danger small mt-1">{{
                                        form.errors.id_grifo }}</div>
                                </div>



                            </div>
                        </div>
                    </div>
                </div>
                <!-- Datos del vale -->
                <div class="col-xl-6">
                    <div class="card custom-card h-100">
                        <div class="card-header">
                            <div class="card-title">Datos del Vale</div>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">

                                <!-- Nro. Vale -->
                                <div class="col-sm-6">
                                    <label class="form-label fw-medium">
                                        Nro. Vale <span class="text-danger">*</span>
                                    </label>
                                    <input v-model="form.nro_vale" type="number" class="form-control"
                                        :class="{ 'is-invalid': form.errors.nro_vale }" min="1" />
                                    <div v-if="form.errors.nro_vale" class="invalid-feedback">{{ form.errors.nro_vale }}
                                    </div>
                                </div>

                                <!-- Fecha Emisión -->
                                <div class="col-sm-6">
                                    <label class="form-label fw-medium">
                                        Fecha de Emisión <span class="text-danger">*</span>
                                    </label>
                                    <input v-model="form.fecha_emision" type="date" class="form-control"
                                        :class="{ 'is-invalid': form.errors.fecha_emision }" />
                                    <div v-if="form.errors.fecha_emision" class="invalid-feedback">{{
                                        form.errors.fecha_emision }}</div>
                                </div>

                                <!-- Litros -->
                                <div class="col-sm-6">
                                    <label class="form-label fw-medium">
                                        Litros <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <input v-model="form.litros" type="number" step="0.01" min="0.01"
                                            class="form-control" :class="{ 'is-invalid': form.errors.litros }"
                                            placeholder="0.00" />
                                        <span class="input-group-text">Lt</span>
                                        <div v-if="form.errors.litros" class="invalid-feedback">{{ form.errors.litros }}
                                        </div>
                                    </div>
                                </div>

                                <!-- Precio -->
                                <div class="col-sm-6">
                                    <label class="form-label fw-medium">
                                        Precio (Bs) <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text">Bs</span>
                                        <input v-model="form.precio" type="number" step="0.01" min="0.01"
                                            class="form-control" :class="{ 'is-invalid': form.errors.precio }"
                                            placeholder="0.00" />
                                        <div v-if="form.errors.precio" class="invalid-feedback">{{ form.errors.precio }}
                                        </div>
                                    </div>
                                </div>

                                <!-- Estado -->
                                <div class="col-md-6">
                                    <label class="form-label fw-medium">
                                        Tipo de Combustible <span class="text-danger">*</span>
                                    </label>
                                    <select v-model="form.id_tipo_combustible" class="form-select"
                                        :class="{ 'is-invalid': form.errors.id_tipo_combustible }">
                                        <option v-for="tipo in tiposCombustible" :key="tipo.id" :value="tipo.id">{{
                                            tipo.label }}</option>
                                    </select>
                                    <div v-if="form.errors.id_tipo_combustible" class="invalid-feedback">{{
                                        form.errors.id_tipo_combustible }}</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-medium">
                                        Estado <span class="text-danger">*</span>
                                    </label>
                                    <select v-model="form.estado_vale" class="form-select"
                                        :class="{ 'is-invalid': form.errors.estado_vale }">
                                        <option value="PENDIENTE">PENDIENTE</option>
                                        <option value="USADO">USADO</option>
                                        <option value="ANULADO">ANULADO</option>
                                    </select>
                                    <div v-if="form.errors.estado_vale" class="invalid-feedback">{{
                                        form.errors.estado_vale }}</div>
                                </div>
                                <!-- Resumen precio/litros -->
                                <div v-if="form.litros && form.precio" class="col-12">
                                    <div class="alert alert-info py-2 mb-0">
                                        <i class="ri-information-line me-1"></i>
                                        Total estimado:
                                        <strong>Bs {{ (Number(form.litros) * Number(form.precio)).toFixed(2) }}</strong>
                                        ({{ form.litros }} Lt × Bs {{ form.precio }})
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>



            </div>

            <!-- Botones -->
            <div class="d-flex justify-content-end gap-2 mt-4">
                <Link :href="route('vales.index')" class="btn btn-outline-secondary btn-wave">
                    Cancelar
                </Link>
                <button type="submit" class="btn btn-primary btn-wave" :disabled="form.processing">
                    <span v-if="form.processing" class="spinner-border spinner-border-sm me-1" role="status"></span>
                    <i v-else class="ri-save-line me-1"></i>
                    {{ form.processing ? 'Guardando...' : 'Guardar Vale' }}
                </button>
            </div>
        </form>
    </Maindashboard>
</template>
