<script setup>
import { computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import Maindashboard from '@/Layouts/Maindashboard.vue'
defineOptions({ layout: Maindashboard })

const props = defineProps({
    orden: Object,
    vehiculos: Array,
    talleres: Array,
    usuarios: Array,
})

const form = useForm({
    id_vehiculo:                props.orden.id_vehiculo ?? '',
    id_conductor:                props.orden.id_conductor ?? '',
    id_solicitud_mantenimiento: props.orden.id_solicitud_mantenimiento ?? '',
    id_taller:                  props.orden.id_taller ?? '',
    id_usuario_ejecuta:         props.orden.id_usuario_ejecuta ?? '',
    tipo_mantenimiento:         props.orden.tipo_mantenimiento ?? 'PREVENTIVO',
    nota_emisor:                props.orden.nota_emisor ?? '',
    kilometraje_actual:         props.orden.kilometraje_actual ?? '',
    horometro_actual:           props.orden.horometro_actual ?? '',
    observacion:                props.orden.observacion ?? '',
})

const esExterno = computed(() => !!form.id_taller)

function submit() {
    form.put(route('mantenimiento.ordenes.update', props.orden.id))
}
</script>

<template>
    <Head :title="`Editar Orden #${orden.nro}`" />
        <!-- Breadcrumb -->
        <div class="d-flex align-items-center justify-content-between page-header-breadcrumb flex-wrap gap-2 mb-4">
            <div>
                <nav>
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item"><Link :href="route('dashboard')">Inicio</Link></li>
                        <li class="breadcrumb-item">
                            <Link :href="route('mantenimiento.ordenes.index')">Órdenes de Trabajo</Link>
                        </li>
                        <li class="breadcrumb-item">
                            <Link :href="route('mantenimiento.ordenes.show', orden.id)">Orden #{{ orden.nro }}</Link>
                        </li>
                        <li class="breadcrumb-item active">Editar</li>
                    </ol>
                </nav>
                <h1 class="page-title fw-medium fs-18 mb-0">Editar Orden de Trabajo #{{ orden.nro }}</h1>
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
                                    {{ v.codigo }} – {{ v.nro_placa }} – {{ v.marca }}
                                </option>
                            </select>
                            <div v-if="form.errors.id_vehiculo" class="invalid-feedback">{{ form.errors.id_vehiculo }}</div>
                        </div>

                        <!-- Responsable de ejecución -->
                        <div class="col-sm-6 col-xl-4">
                            <label class="form-label fw-medium">
                                Responsable de Ejecución <span class="text-danger">*</span>
                            </label>
                            <select v-model="form.id_usuario_ejecuta" class="form-select"
                                :class="{ 'is-invalid': form.errors.id_usuario_ejecuta }">
                                <option value="">— Seleccione —</option>
                                <option v-for="u in usuarios" :key="u.id" :value="u.id">{{ u.name }}</option>
                            </select>
                            <div v-if="form.errors.id_usuario_ejecuta" class="invalid-feedback">
                                {{ form.errors.id_usuario_ejecuta }}
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

                        <!-- Taller -->
                        <div class="col-sm-6 col-xl-4">
                            <label class="form-label fw-medium">
                                Taller Externo
                                <span class="text-muted">(vacío = orden interna)</span>
                            </label>
                            <select v-model="form.id_taller" class="form-select"
                                :class="{ 'is-invalid': form.errors.id_taller }">
                                <option value="">— Interno —</option>
                                <option v-for="t in talleres" :key="t.id" :value="t.id">
                                    {{ t.razon_social }}
                                </option>
                            </select>
                            <div v-if="form.errors.id_taller" class="invalid-feedback">{{ form.errors.id_taller }}</div>
                            <small class="text-muted">Orden {{ esExterno ? 'EXTERNA' : 'INTERNA' }}</small>
                        </div>

                        <!-- Km actual -->
                        <div class="col-sm-6 col-xl-4">
                            <label class="form-label fw-medium">Kilometraje Actual</label>
                            <div class="input-group">
                                <input v-model="form.kilometraje_actual" type="number" min="0" class="form-control" />
                                <span class="input-group-text">km</span>
                            </div>
                        </div>

                        <!-- Nota del emisor -->
                        <div class="col-12">
                            <label class="form-label fw-medium">Nota del Emisor</label>
                            <textarea v-model="form.nota_emisor" rows="3"
                                class="form-control" placeholder="Detalle el trabajo a realizar..."></textarea>
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
</template>
