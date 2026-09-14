<script setup>
import { computed, ref, watch } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import Maindashboard from '@/Layouts/Maindashboard.vue'
import UnidadCapacidadSelect from '@/Components/UnidadCapacidadSelect.vue'
defineOptions({ layout: Maindashboard })

const props = defineProps({
    vehiculo:         Object,
    tiposCombustible: Array,
    tiposVehiculo:    Array,
})

const isEditing = computed(() => !!props.vehiculo)

const form = useForm({
    ...(isEditing.value ? { _method: 'PUT' } : {}),
    nro_placa:           props.vehiculo?.nro_placa ?? '',
    codigo:              props.vehiculo?.codigo ?? '',
    anio:                props.vehiculo?.anio ?? '',
    marca:               props.vehiculo?.marca ?? '',
    modelo:              props.vehiculo?.modelo ?? '',
    estado_vehiculo:     props.vehiculo?.estado_vehiculo ?? 'ACTIVO',
    tipo_medicion:       props.vehiculo?.tipo_medicion ?? 'kilometraje',
    id_tipo_combustible: props.vehiculo?.id_tipo_combustible ?? '',
    id_tipo_vehiculo:    props.vehiculo?.id_tipo_vehiculo ?? '',
    fotografia:          null,
    capacidad:           props.vehiculo?.capacidad ?? '',
    capacidad_unidad:    props.vehiculo?.capacidad_unidad ?? '',
})

// Al elegir (o cambiar) el tipo de vehículo, si todavía no hay una unidad de
// capacidad cargada se precarga con la sugerida de ese tipo (tipo_vehiculo.
// unidad_capacidad_sugerida) — sólo una sugerencia, el usuario la puede
// cambiar o dejar en blanco.
watch(
    () => form.id_tipo_vehiculo,
    (idTipoVehiculo) => {
        if (form.capacidad_unidad) return
        const tipo = props.tiposVehiculo.find((t) => t.id === idTipoVehiculo)
        if (tipo?.unidad_capacidad_sugerida) {
            form.capacidad_unidad = tipo.unidad_capacidad_sugerida
        }
    },
)

const fotoPreview = ref(
    props.vehiculo?.fotografia ? `/storage/${props.vehiculo.fotografia}` : null,
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
    if (isEditing.value) {
        form.post(route('vehiculos.update', props.vehiculo.id), { forceFormData: true })
    } else {
        form.post(route('vehiculos.store'), { forceFormData: true })
    }
}
</script>

<template>
    <Head :title="isEditing ? 'Editar Vehículo' : 'Nuevo Vehículo'" />

        <!-- Breadcrumb -->
        <div class="d-flex align-items-center justify-content-between page-header-breadcrumb flex-wrap gap-2 mb-4">
            <div>
                <nav>
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item"><Link :href="route('dashboard')">Inicio</Link></li>
                        <li class="breadcrumb-item"><Link :href="route('vehiculos.index')">Vehículos</Link></li>
                        <li class="breadcrumb-item active">{{ isEditing ? 'Editar' : 'Nuevo' }}</li>
                    </ol>
                </nav>
                <h1 class="page-title fw-medium fs-18 mb-0">
                    <template v-if="isEditing">
                        Editar Vehículo: <span class="text-primary">{{ vehiculo.nro_placa }}</span>
                    </template>
                    <template v-else>Registrar Vehículo</template>
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
                                    alt="Vista previa"
                                    class="w-100 h-100"
                                    style="object-fit:cover;"
                                />
                                <div v-else class="w-100 h-100 d-flex align-items-center justify-content-center text-muted">
                                    <i class="ri-car-line" style="font-size:4rem;"></i>
                                </div>
                            </div>
                            <div class="w-100">
                                <label class="form-label fw-medium">
                                    {{ isEditing ? 'Cambiar foto' : 'Seleccionar foto' }}
                                    <span v-if="!isEditing" class="text-danger">*</span>
                                </label>
                                <input
                                    type="file"
                                    class="form-control"
                                    :class="{ 'is-invalid': form.errors.fotografia }"
                                    accept=".jpg,.jpeg,.png,.webp,.avif"
                                    @change="onFotoChange"
                                />
                                <div v-if="form.errors.fotografia" class="invalid-feedback">{{ form.errors.fotografia }}</div>
                                <small class="text-muted">
                                    {{ isEditing ? 'Dejar vacío para conservar la foto actual.' : 'JPG, PNG o WEBP. Máx 2MB.' }}
                                </small>
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
                                        Nro. Placa
                                    </label>
                                    <input
                                        v-model="form.nro_placa"
                                        type="text"
                                        class="form-control"
                                        :class="{ 'is-invalid': form.errors.nro_placa }"
                                        placeholder="Ej: 1234ABC (opcional)"
                                        maxlength="20"
                                        style="text-transform:uppercase;"
                                        @input="form.nro_placa = form.nro_placa.toUpperCase()"
                                    />
                                    <div v-if="form.errors.nro_placa" class="invalid-feedback">{{ form.errors.nro_placa }}</div>
                                </div>

                                <!-- Código contable -->
                                <div class="col-sm-4">
                                    <label class="form-label fw-medium">Código Contable</label>
                                    <input
                                        v-model="form.codigo"
                                        type="text"
                                        class="form-control"
                                        :class="{ 'is-invalid': form.errors.codigo }"
                                        placeholder="Ej: ACT-0001"
                                        maxlength="50"
                                    />
                                    <div v-if="form.errors.codigo" class="invalid-feedback">{{ form.errors.codigo }}</div>
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

                                <!-- Modelo -->
                                <div class="col-sm-4">
                                    <label class="form-label fw-medium">Modelo</label>
                                    <input
                                        v-model="form.modelo"
                                        type="text"
                                        class="form-control"
                                        :class="{ 'is-invalid': form.errors.modelo }"
                                        placeholder="Ej: Hilux, Corolla..."
                                        maxlength="50"
                                    />
                                    <div v-if="form.errors.modelo" class="invalid-feedback">{{ form.errors.modelo }}</div>
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

                                <!-- Tipo de Medición -->
                                <div class="col-sm-4">
                                    <label class="form-label fw-medium">
                                        Tipo de Medición <span class="text-danger">*</span>
                                    </label>
                                    <select
                                        v-model="form.tipo_medicion"
                                        class="form-select"
                                        :class="{ 'is-invalid': form.errors.tipo_medicion }"
                                    >
                                        <option value="kilometraje">Kilometraje</option>
                                        <option value="horometro">Horómetro</option>
                                    </select>
                                    <div v-if="form.errors.tipo_medicion" class="invalid-feedback">{{ form.errors.tipo_medicion }}</div>
                                </div>

                                <!-- Capacidad -->
                                <div class="col-sm-4">
                                    <label class="form-label fw-medium">Capacidad</label>
                                    <input
                                        v-model="form.capacidad"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="form-control"
                                        :class="{ 'is-invalid': form.errors.capacidad }"
                                        placeholder="Ej: 3.00"
                                    />
                                    <div v-if="form.errors.capacidad" class="invalid-feedback">{{ form.errors.capacidad }}</div>
                                </div>

                                <!-- Unidad de Capacidad -->
                                <div class="col-sm-4">
                                    <label class="form-label fw-medium">Unidad de Capacidad</label>
                                    <UnidadCapacidadSelect
                                        v-model="form.capacidad_unidad"
                                        :invalid="!!form.errors.capacidad_unidad"
                                    />
                                    <div v-if="form.errors.capacidad_unidad" class="invalid-feedback d-block">{{ form.errors.capacidad_unidad }}</div>
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
                    <template v-if="isEditing">{{ form.processing ? 'Actualizando...' : 'Actualizar Vehículo' }}</template>
                    <template v-else>{{ form.processing ? 'Guardando...' : 'Guardar Vehículo' }}</template>
                </button>
            </div>
        </form>
</template>
