<script setup>
import { ref } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import Maindashboard from '@/Layouts/Maindashboard.vue'
defineOptions({ layout: Maindashboard })

const props = defineProps({
    vehiculo:         Object,
    tiposCombustible: Array,
    tiposVehiculo:    Array,
})

const form = useForm({
    _method:             'PUT',
    nro_placa:           props.vehiculo.nro_placa,
    anio:                props.vehiculo.anio        ?? '',
    marca:               props.vehiculo.marca       ?? '',
    estado_vehiculo:     props.vehiculo.estado_vehiculo,
    id_tipo_combustible: props.vehiculo.id_tipo_combustible,
    id_tipo_vehiculo:    props.vehiculo.id_tipo_vehiculo,
    fotografia:          null,
})

const fotoPreview = ref(
    props.vehiculo.fotografia ? `/storage/${props.vehiculo.fotografia}` : null,
)

function onFotoChange(e) {
    const file = e.target.files[0]
    if (!file) return
    form.fotografia = file
    const reader = new FileReader()
    reader.onload = (ev) => (fotoPreview.value = ev.target.result)
    reader.readAsDataURL(file)
}

function submit() {
    form.post(route('vehiculos.update', props.vehiculo.id), { forceFormData: true })
}
</script>

<template>
    <Head title="Editar Vehículo" />

        <!-- Breadcrumb -->
        <div class="d-flex align-items-center justify-content-between page-header-breadcrumb flex-wrap gap-2 mb-4">
            <div>
                <nav>
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item"><Link :href="route('dashboard')">Inicio</Link></li>
                        <li class="breadcrumb-item"><Link :href="route('vehiculos.index')">Vehículos</Link></li>
                        <li class="breadcrumb-item active">Editar</li>
                    </ol>
                </nav>
                <h1 class="page-title fw-medium fs-18 mb-0">
                    Editar Vehículo:
                    <span class="text-primary">{{ vehiculo.nro_placa }}</span>
                </h1>
            </div>
            <Link :href="route('vehiculos.index')" class="btn btn-outline-secondary btn-wave">
                <i class="ri-arrow-left-line me-1"></i> Volver
            </Link>
        </div>

        <form @submit.prevent="submit" enctype="multipart/form-data">
            <div class="row g-4">

                <!-- Fotografía -->
                <div class="col-xl-3">
                    <div class="card custom-card h-100">
                        <div class="card-header"><div class="card-title">Fotografía</div></div>
                        <div class="card-body d-flex flex-column align-items-center justify-content-center gap-3">
                            <div
                                class="border rounded-3 overflow-hidden"
                                style="width:160px;height:120px;background:#f8f9fa;"
                            >
                                <img
                                    v-if="fotoPreview"
                                    :src="fotoPreview"
                                    alt="Foto del vehículo"
                                    class="w-100 h-100"
                                    style="object-fit:cover;"
                                />
                                <div v-else class="w-100 h-100 d-flex align-items-center justify-content-center text-muted">
                                    <i class="ri-car-line" style="font-size:4rem;"></i>
                                </div>
                            </div>
                            <div class="w-100">
                                <label class="form-label fw-medium">Cambiar foto</label>
                                <input
                                    type="file"
                                    class="form-control"
                                    :class="{ 'is-invalid': form.errors.fotografia }"
                                    accept=".jpg,.jpeg,.png,.webp,.avif"
                                    @change="onFotoChange"
                                />
                                <div v-if="form.errors.fotografia" class="invalid-feedback">{{ form.errors.fotografia }}</div>
                                <small class="text-muted">Dejar vacío para conservar la foto actual.</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Datos del vehículo -->
                <div class="col-xl-9">
                    <div class="card custom-card">
                        <div class="card-header"><div class="card-title">Datos del Vehículo</div></div>
                        <div class="card-body">
                            <div class="row g-3">

                                <!-- Nro. Placa -->
                                <div class="col-sm-4">
                                    <label class="form-label fw-medium">
                                        Nro. Placa <span class="text-danger">*</span>
                                    </label>
                                    <input
                                        v-model="form.nro_placa"
                                        type="text"
                                        class="form-control"
                                        :class="{ 'is-invalid': form.errors.nro_placa }"
                                        placeholder="Ej: 1234ABC"
                                        maxlength="20"
                                        style="text-transform:uppercase;"
                                        @input="form.nro_placa = form.nro_placa.toUpperCase()"
                                    />
                                    <div v-if="form.errors.nro_placa" class="invalid-feedback">{{ form.errors.nro_placa }}</div>
                                </div>

                                <!-- Marca -->
                                <div class="col-sm-4">
                                    <label class="form-label fw-medium">Marca</label>
                                    <input
                                        v-model="form.marca"
                                        type="text"
                                        class="form-control"
                                        :class="{ 'is-invalid': form.errors.marca }"
                                        placeholder="Ej: Toyota, Hyundai..."
                                        maxlength="50"
                                    />
                                    <div v-if="form.errors.marca" class="invalid-feedback">{{ form.errors.marca }}</div>
                                </div>

                                <!-- Año -->
                                <div class="col-sm-4">
                                    <label class="form-label fw-medium">Año</label>
                                    <input
                                        v-model="form.anio"
                                        type="text"
                                        class="form-control"
                                        :class="{ 'is-invalid': form.errors.anio }"
                                        placeholder="Ej: 2020"
                                        maxlength="4"
                                    />
                                    <div v-if="form.errors.anio" class="invalid-feedback">{{ form.errors.anio }}</div>
                                </div>

                                <!-- Tipo Vehículo -->
                                <div class="col-sm-4">
                                    <label class="form-label fw-medium">
                                        Tipo de Vehículo <span class="text-danger">*</span>
                                    </label>
                                    <select
                                        v-model="form.id_tipo_vehiculo"
                                        class="form-select"
                                        :class="{ 'is-invalid': form.errors.id_tipo_vehiculo }"
                                    >
                                        <option value="">— Seleccionar —</option>
                                        <option v-for="tv in tiposVehiculo" :key="tv.id" :value="tv.id">
                                            {{ tv.tipo_vehiculo }}
                                        </option>
                                    </select>
                                    <div v-if="form.errors.id_tipo_vehiculo" class="invalid-feedback">{{ form.errors.id_tipo_vehiculo }}</div>
                                </div>

                                <!-- Tipo Combustible -->
                                <div class="col-sm-4">
                                    <label class="form-label fw-medium">
                                        Tipo de Combustible <span class="text-danger">*</span>
                                    </label>
                                    <select
                                        v-model="form.id_tipo_combustible"
                                        class="form-select"
                                        :class="{ 'is-invalid': form.errors.id_tipo_combustible }"
                                    >
                                        <option value="">— Seleccionar —</option>
                                        <option v-for="tc in tiposCombustible" :key="tc.id" :value="tc.id">
                                            {{ tc.tipo_combustible }}
                                        </option>
                                    </select>
                                    <div v-if="form.errors.id_tipo_combustible" class="invalid-feedback">{{ form.errors.id_tipo_combustible }}</div>
                                </div>

                                <!-- Estado -->
                                <div class="col-sm-4">
                                    <label class="form-label fw-medium">
                                        Estado <span class="text-danger">*</span>
                                    </label>
                                    <select
                                        v-model="form.estado_vehiculo"
                                        class="form-select"
                                        :class="{ 'is-invalid': form.errors.estado_vehiculo }"
                                    >
                                        <option value="ACTIVO">ACTIVO</option>
                                        <option value="RETIRADO">RETIRADO</option>
                                        <option value="VENDIDO">VENDIDO</option>
                                    </select>
                                    <div v-if="form.errors.estado_vehiculo" class="invalid-feedback">{{ form.errors.estado_vehiculo }}</div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Botones -->
            <div class="d-flex justify-content-end gap-2 mt-4">
                <Link :href="route('vehiculos.index')" class="btn btn-outline-secondary btn-wave">
                    Cancelar
                </Link>
                <button
                    type="submit"
                    class="btn btn-primary btn-wave"
                    :disabled="form.processing"
                >
                    <span v-if="form.processing" class="spinner-border spinner-border-sm me-1" role="status"></span>
                    <i v-else class="ri-save-line me-1"></i>
                    {{ form.processing ? 'Actualizando...' : 'Actualizar Vehículo' }}
                </button>
            </div>
        </form>
</template>
