<script setup>
import { ref } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import Maindashboard from '@/Layouts/Maindashboard.vue'
defineOptions({ layout: Maindashboard })

const props = defineProps({
    vehiculos: Array,
})

// El conductor y la fecha/hora de la solicitud se asignan automáticamente
// en el modelo (a partir del usuario autenticado y del momento del registro),
// por lo que no se piden en el formulario.
const form = useForm({
    id_vehiculo:          '',
    tipo_mantenimiento:   'PREVENTIVO',
    descripcion_problema: '',
    kilometraje_actual:   '',
    observacion:          '',
})

function submit() {
    form.post(route('mantenimiento.solicitudes.store'))
}
</script>

<template>
    <Head title="Nueva Solicitud de Mantenimiento" />
        <!-- Breadcrumb -->
        <div class="d-flex align-items-center justify-content-between page-header-breadcrumb flex-wrap gap-2 mb-4">
            <div>
                <nav>
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item"><Link :href="route('dashboard')">Inicio</Link></li>
                        <li class="breadcrumb-item">
                            <Link :href="route('mantenimiento.solicitudes.index')">Solicitudes Mantenimiento</Link>
                        </li>
                        <li class="breadcrumb-item active">Nueva Solicitud</li>
                    </ol>
                </nav>
                <h1 class="page-title fw-medium fs-18 mb-0">Registrar Solicitud de Mantenimiento</h1>
            </div>
            <Link :href="route('mantenimiento.solicitudes.index')" class="btn btn-outline-secondary btn-wave">
                <i class="ri-arrow-left-line me-1"></i> Volver
            </Link>
        </div>

        <form @submit.prevent="submit">
            <div class="card custom-card">
                <div class="card-header">
                    <div class="card-title">
                        <i class="ri-alarm-warning-line me-2 text-warning"></i>
                        Paso 1 – Registro de Solicitud / Alarma de Mantenimiento
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <!-- Vehículo -->
                        <div class="col-sm-6 col-xl-4">
                            <label class="form-label fw-medium">
                                Vehículo <span class="text-danger">*</span>
                            </label>
                            <select v-model="form.id_vehiculo" class="form-select"
                                :class="{ 'is-invalid': form.errors.id_vehiculo }">
                                <option value="">— Seleccione —</option>
                                <option v-for="v in vehiculos" :key="v.id" :value="v.id">
                                    {{ v.nro_placa }} – {{ v.marca }}
                                </option>
                            </select>
                            <div v-if="form.errors.id_vehiculo" class="invalid-feedback">
                                {{ form.errors.id_vehiculo }}
                            </div>
                        </div>

                        <!-- Tipo -->
                        <div class="col-sm-6 col-xl-4">
                            <label class="form-label fw-medium">
                                Tipo de Mantenimiento <span class="text-danger">*</span>
                            </label>
                            <select v-model="form.tipo_mantenimiento" class="form-select"
                                :class="{ 'is-invalid': form.errors.tipo_mantenimiento }">
                                <option value="PREVENTIVO">Preventivo</option>
                                <option value="CORRECTIVO">Correctivo</option>
                            </select>
                            <div v-if="form.errors.tipo_mantenimiento" class="invalid-feedback">
                                {{ form.errors.tipo_mantenimiento }}
                            </div>
                        </div>

                        <!-- Km actual -->
                        <div class="col-sm-6 col-xl-4">
                            <label class="form-label fw-medium">Kilometraje Actual</label>
                            <div class="input-group">
                                <input v-model="form.kilometraje_actual" type="number" min="0"
                                    class="form-control" :class="{ 'is-invalid': form.errors.kilometraje_actual }"
                                    placeholder="Ej: 85000" />
                                <span class="input-group-text">km</span>
                                <div v-if="form.errors.kilometraje_actual" class="invalid-feedback">
                                    {{ form.errors.kilometraje_actual }}
                                </div>
                            </div>
                        </div>

                        <!-- Descripción -->
                        <div class="col-12">
                            <label class="form-label fw-medium">
                                Descripción del Problema / Mantenimiento Preventivo
                                <span class="text-danger">*</span>
                            </label>
                            <textarea v-model="form.descripcion_problema" rows="4"
                                class="form-control" :class="{ 'is-invalid': form.errors.descripcion_problema }"
                                placeholder="Describa la falla detectada o el mantenimiento preventivo requerido...">
                            </textarea>
                            <div v-if="form.errors.descripcion_problema" class="invalid-feedback">
                                {{ form.errors.descripcion_problema }}
                            </div>
                        </div>

                        <!-- Observación -->
                        <div class="col-12">
                            <label class="form-label fw-medium">Observación adicional</label>
                            <textarea v-model="form.observacion" rows="2"
                                class="form-control" :class="{ 'is-invalid': form.errors.observacion }"
                                placeholder="Observaciones opcionales...">
                            </textarea>
                            <div v-if="form.errors.observacion" class="invalid-feedback">
                                {{ form.errors.observacion }}
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-end gap-2">
                    <Link :href="route('mantenimiento.solicitudes.index')" class="btn btn-outline-secondary btn-wave">
                        Cancelar
                    </Link>
                    <button type="submit" class="btn btn-primary btn-wave" :disabled="form.processing">
                        <span v-if="form.processing" class="spinner-border spinner-border-sm me-1"></span>
                        <i v-else class="ri-send-plane-line me-1"></i>
                        Enviar Solicitud
                    </button>
                </div>
            </div>
        </form>
</template>
