<script setup>
import { computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import Maindashboard from '@/Layouts/Maindashboard.vue'

const props = defineProps({
    orden: Object,
    vehiculos: Array,
    tiposMantenimiento: Array,
    talleres: Array,
})

const form = useForm({
    id_vehiculo:                  props.orden.id_vehiculo ?? '',
    id_tipo_mantenimiento:        props.orden.id_tipo_mantenimiento ?? '',
    id_solicitud_mantenimiento:   props.orden.id_solicitud_mantenimiento ?? '',
    id_taller:                    props.orden.id_taller ?? '',
    tipo_mantenimiento:           props.orden.tipo_mantenimiento ?? 'PREVENTIVO',
    tipo_orden:                   props.orden.tipo_orden ?? 'INTERNO',
    descripcion_trabajo_ordenado: props.orden.descripcion_trabajo_ordenado ?? '',
    kilometraje_programado:       props.orden.kilometraje_programado ?? '',
    fecha_programada:             props.orden.fecha_programada ?? '',
    frecuencia_km:                props.orden.frecuencia_km ?? '',
    frecuencia_mes:               props.orden.frecuencia_mes ?? '',
    observacion:                  props.orden.observacion ?? '',
})

const requiereTaller = computed(() => form.tipo_orden === 'EXTERNO')

function submit() {
    form.put(route('mantenimiento.ordenes.update', props.orden.id))
}
</script>

<template>
    <Head :title="`Editar Orden #${orden.id}`" />
    <Maindashboard>
        <!-- Breadcrumb -->
        <div class="d-flex align-items-center justify-content-between page-header-breadcrumb flex-wrap gap-2 mb-4">
            <div>
                <nav>
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item"><Link :href="route('dashboard')">Inicio</Link></li>
                        <li class="breadcrumb-item">
                            <Link :href="route('mantenimiento.ordenes.index')">Órdenes Mantenimiento</Link>
                        </li>
                        <li class="breadcrumb-item">
                            <Link :href="route('mantenimiento.ordenes.show', orden.id)">Orden #{{ orden.id }}</Link>
                        </li>
                        <li class="breadcrumb-item active">Editar</li>
                    </ol>
                </nav>
                <h1 class="page-title fw-medium fs-18 mb-0">Editar Orden de Trabajo #{{ orden.id }}</h1>
            </div>
            <Link :href="route('mantenimiento.ordenes.show', orden.id)" class="btn btn-outline-secondary btn-wave">
                <i class="ri-arrow-left-line me-1"></i> Volver
            </Link>
        </div>

        <form @submit.prevent="submit">
            <div class="card custom-card">
                <div class="card-header">
                    <div class="card-title">
                        <i class="ri-file-list-3-line me-2 text-primary"></i>
                        Editar Orden de Trabajo
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
                            <div v-if="form.errors.id_vehiculo" class="invalid-feedback">{{ form.errors.id_vehiculo }}</div>
                        </div>

                        <!-- Tipo mantenimiento catálogo -->
                        <div class="col-sm-6 col-xl-4">
                            <label class="form-label fw-medium">
                                Tipo de Mantenimiento <span class="text-danger">*</span>
                            </label>
                            <select v-model="form.id_tipo_mantenimiento" class="form-select"
                                :class="{ 'is-invalid': form.errors.id_tipo_mantenimiento }">
                                <option value="">— Seleccione —</option>
                                <option v-for="t in tiposMantenimiento" :key="t.id" :value="t.id">
                                    {{ t.nombre_tipo }}
                                </option>
                            </select>
                            <div v-if="form.errors.id_tipo_mantenimiento" class="invalid-feedback">
                                {{ form.errors.id_tipo_mantenimiento }}
                            </div>
                        </div>

                        <!-- Categoría -->
                        <div class="col-sm-6 col-xl-4">
                            <label class="form-label fw-medium">Categoría <span class="text-danger">*</span></label>
                            <select v-model="form.tipo_mantenimiento" class="form-select">
                                <option value="PREVENTIVO">Preventivo</option>
                                <option value="CORRECTIVO">Correctivo</option>
                            </select>
                        </div>

                        <!-- Tipo orden -->
                        <div class="col-sm-6 col-xl-4">
                            <label class="form-label fw-medium">Tipo de Orden <span class="text-danger">*</span></label>
                            <select v-model="form.tipo_orden" class="form-select">
                                <option value="INTERNO">Interno</option>
                                <option value="EXTERNO">Externo (Taller)</option>
                            </select>
                        </div>

                        <!-- Taller -->
                        <div class="col-sm-6 col-xl-4">
                            <label class="form-label fw-medium">
                                Taller
                                <span v-if="requiereTaller" class="text-danger">*</span>
                                <span v-else class="text-muted">(sólo externo)</span>
                            </label>
                            <select v-model="form.id_taller" class="form-select" :disabled="!requiereTaller"
                                :class="{ 'is-invalid': form.errors.id_taller }">
                                <option value="">— Seleccione taller —</option>
                                <option v-for="t in talleres" :key="t.id" :value="t.id">
                                    {{ t.razon_social }}
                                </option>
                            </select>
                            <div v-if="form.errors.id_taller" class="invalid-feedback">{{ form.errors.id_taller }}</div>
                        </div>

                        <!-- Descripción -->
                        <div class="col-12">
                            <label class="form-label fw-medium">Descripción del Trabajo</label>
                            <textarea v-model="form.descripcion_trabajo_ordenado" rows="3"
                                class="form-control" placeholder="Detalle el trabajo a realizar..."></textarea>
                        </div>

                        <div class="col-12"><hr class="my-1" /><p class="text-muted mb-0 small">Programación</p></div>

                        <div class="col-sm-6 col-xl-3">
                            <label class="form-label fw-medium">Km Programado</label>
                            <div class="input-group">
                                <input v-model="form.kilometraje_programado" type="number" min="0" class="form-control" />
                                <span class="input-group-text">km</span>
                            </div>
                        </div>
                        <div class="col-sm-6 col-xl-3">
                            <label class="form-label fw-medium">Fecha Programada</label>
                            <input v-model="form.fecha_programada" type="date" class="form-control" />
                        </div>
                        <div class="col-sm-6 col-xl-3">
                            <label class="form-label fw-medium">Frecuencia (km)</label>
                            <div class="input-group">
                                <input v-model="form.frecuencia_km" type="number" min="0" class="form-control" />
                                <span class="input-group-text">km</span>
                            </div>
                        </div>
                        <div class="col-sm-6 col-xl-3">
                            <label class="form-label fw-medium">Frecuencia (meses)</label>
                            <div class="input-group">
                                <input v-model="form.frecuencia_mes" type="number" min="0" class="form-control" />
                                <span class="input-group-text">meses</span>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-medium">Observación</label>
                            <textarea v-model="form.observacion" rows="2" class="form-control"></textarea>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-end gap-2">
                    <Link :href="route('mantenimiento.ordenes.show', orden.id)"
                        class="btn btn-outline-secondary btn-wave">Cancelar</Link>
                    <button type="submit" class="btn btn-primary btn-wave" :disabled="form.processing">
                        <span v-if="form.processing" class="spinner-border spinner-border-sm me-1"></span>
                        <i v-else class="ri-save-line me-1"></i>
                        Guardar Cambios
                    </button>
                </div>
            </div>
        </form>
    </Maindashboard>
</template>
