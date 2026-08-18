<script setup>
import { computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import Maindashboard from '@/Layouts/Maindashboard.vue'
defineOptions({ layout: Maindashboard })

const props = defineProps({
    orden: Object,
    tiposMantenimiento: Array,
    repuestos: Array,
})

// ── Formulario principal ──────────────────────────────────────────────────────
const form = useForm({
    fecha_ejecucion:    props.orden.fecha_ejecucion?.substring(0, 10) ?? '',
    fecha_culminacion:  '',
    kilometraje_actual: props.orden.kilometraje_actual ?? '',
    horometro_actual:   props.orden.horometro_actual ?? '',
    observacion:        props.orden.observacion ?? '',
    detalles: [],
})

// ── Ítem en blanco para la tabla de detalle ───────────────────────────────────
const itemVacio = () => ({
    id_tipo_mantenimiento: '',
    id_repuesto:           '',
    detalle:                '',
    cantidad:                1,
    costo_unitario:         '',
})

function agregarItem() {
    form.detalles.push(itemVacio())
}

function eliminarItem(idx) {
    form.detalles.splice(idx, 1)
}

// Al seleccionar un repuesto del catálogo, limpiar el detalle libre
function onRepuestoChange(item) {
    if (item.id_repuesto) {
        item.detalle = ''
    }
}

const subtotal = (item) =>
    ((parseFloat(item.cantidad) || 0) * (parseFloat(item.costo_unitario) || 0)).toFixed(2)

// Costo total de los ítems
const totalGeneral = computed(() =>
    form.detalles.reduce((acc, i) => acc + parseFloat(subtotal(i)), 0).toFixed(2)
)

function submit() {
    form.post(route('mantenimiento.ordenes.ejecucion.store', props.orden.id))
}

// Agregar primer ítem automáticamente
agregarItem()
</script>

<template>
    <Head :title="`Registrar Ejecución – Orden #${orden.nro}`" />
        <!-- Breadcrumb -->
        <div class="d-flex align-items-center justify-content-between page-header-breadcrumb flex-wrap gap-2 mb-4">
            <div>
                <nav>
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item"><Link :href="route('dashboard')">Inicio</Link></li>
                        <li class="breadcrumb-item">
                            <Link :href="route('mantenimiento.ordenes.index')">Órdenes</Link>
                        </li>
                        <li class="breadcrumb-item">
                            <Link :href="route('mantenimiento.ordenes.show', orden.id)">Orden #{{ orden.nro }}</Link>
                        </li>
                        <li class="breadcrumb-item active">Registrar Ejecución</li>
                    </ol>
                </nav>
                <h1 class="page-title fw-medium fs-18 mb-0">
                    Paso 3 – Registro de Ejecución
                </h1>
            </div>
            <Link :href="route('mantenimiento.ordenes.show', orden.id)" class="btn btn-outline-secondary btn-wave">
                <i class="ri-arrow-left-line me-1"></i> Volver
            </Link>
        </div>

        <!-- Resumen de la orden -->
        <div class="alert alert-primary d-flex align-items-start gap-2 mb-4">
            <i class="ri-file-list-3-line fs-20 mt-1"></i>
            <div>
                <strong>Orden #{{ orden.nro }}</strong> —
                Vehículo: <strong>{{ orden.vehiculo?.nro_placa }} {{ orden.vehiculo?.marca }}</strong> —
                <span class="badge bg-info-transparent text-info">{{ orden.tipo_mantenimiento }}</span>
                <span v-if="orden.taller"> — Taller: {{ orden.taller.razon_social }}</span>
            </div>
        </div>

        <form @submit.prevent="submit">
            <!-- Datos de la ejecución -->
            <div class="card custom-card mb-4">
                <div class="card-header">
                    <div class="card-title">
                        <i class="ri-tools-line me-2 text-success"></i>
                        Fechas y Lecturas
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <!-- Fecha ejecución -->
                        <div class="col-sm-6 col-xl-3">
                            <label class="form-label fw-medium">Fecha Inicio Ejecución</label>
                            <input v-model="form.fecha_ejecucion" type="date"
                                class="form-control" :class="{ 'is-invalid': form.errors.fecha_ejecucion }" />
                            <div v-if="form.errors.fecha_ejecucion" class="invalid-feedback">
                                {{ form.errors.fecha_ejecucion }}
                            </div>
                        </div>

                        <!-- Fecha culminación -->
                        <div class="col-sm-6 col-xl-3">
                            <label class="form-label fw-medium">
                                Fecha Culminación <span class="text-danger">*</span>
                            </label>
                            <input v-model="form.fecha_culminacion" type="date"
                                class="form-control" :class="{ 'is-invalid': form.errors.fecha_culminacion }" />
                            <div v-if="form.errors.fecha_culminacion" class="invalid-feedback">
                                {{ form.errors.fecha_culminacion }}
                            </div>
                        </div>

                        <!-- Km al mantenimiento -->
                        <div class="col-sm-6 col-xl-3">
                            <label class="form-label fw-medium">Kilometraje</label>
                            <div class="input-group">
                                <input v-model="form.kilometraje_actual" type="number" min="0"
                                    class="form-control" placeholder="Ej: 87500" />
                                <span class="input-group-text">km</span>
                            </div>
                        </div>

                        <!-- Horómetro -->
                        <div class="col-sm-6 col-xl-3">
                            <label class="form-label fw-medium">Horómetro</label>
                            <div class="input-group">
                                <input v-model="form.horometro_actual" type="number" min="0"
                                    class="form-control" placeholder="Ej: 1200" />
                                <span class="input-group-text">h</span>
                            </div>
                        </div>

                        <!-- Observación -->
                        <div class="col-12">
                            <label class="form-label fw-medium">Observaciones</label>
                            <textarea v-model="form.observacion" rows="2"
                                class="form-control" placeholder="Observaciones adicionales..."></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla de detalle -->
            <div class="card custom-card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div class="card-title">
                        <i class="ri-archive-line me-2"></i>
                        Detalle del Trabajo Realizado
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary btn-wave" @click="agregarItem">
                        <i class="ri-add-line me-1"></i> Agregar Ítem
                    </button>
                </div>
                <div v-if="form.errors.detalles" class="alert alert-danger mx-3 mt-3 mb-0 py-2">
                    {{ form.errors.detalles }}
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="min-width:200px">Tipo de Mantenimiento</th>
                                    <th style="min-width:200px">Repuesto (catálogo)</th>
                                    <th style="min-width:180px">Detalle / Descripción</th>
                                    <th style="min-width:100px">Cantidad</th>
                                    <th style="min-width:120px">Costo Unit.</th>
                                    <th style="min-width:110px">Subtotal</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="form.detalles.length === 0">
                                    <td colspan="7" class="text-center py-3 text-muted">
                                        No hay ítems. Haga clic en "Agregar Ítem".
                                    </td>
                                </tr>
                                <tr v-for="(item, idx) in form.detalles" :key="idx">
                                    <!-- Tipo de mantenimiento -->
                                    <td>
                                        <select v-model="item.id_tipo_mantenimiento" class="form-select form-select-sm">
                                            <option value="">— Seleccione —</option>
                                            <option v-for="t in tiposMantenimiento" :key="t.id" :value="t.id">
                                                {{ t.tipo_mantenimiento }}
                                            </option>
                                        </select>
                                    </td>
                                    <!-- Repuesto del catálogo -->
                                    <td>
                                        <select v-model="item.id_repuesto" class="form-select form-select-sm"
                                            @change="onRepuestoChange(item)">
                                            <option value="">— Libre —</option>
                                            <option v-for="r in repuestos" :key="r.id" :value="r.id">
                                                {{ r.nombre_repuesto }} ({{ r.unidad_medida }})
                                            </option>
                                        </select>
                                    </td>
                                    <!-- Detalle libre -->
                                    <td>
                                        <input v-model="item.detalle" type="text"
                                            class="form-control form-control-sm"
                                            :disabled="!!item.id_repuesto"
                                            placeholder="Descripción del ítem" />
                                    </td>
                                    <!-- Cantidad -->
                                    <td>
                                        <input v-model="item.cantidad" type="number" min="1" step="1"
                                            class="form-control form-control-sm text-end" />
                                    </td>
                                    <!-- Costo unitario -->
                                    <td>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">Bs</span>
                                            <input v-model="item.costo_unitario" type="number" min="0" step="0.01"
                                                class="form-control text-end" />
                                        </div>
                                    </td>
                                    <!-- Subtotal (calculado) -->
                                    <td class="text-end fw-medium">Bs {{ subtotal(item) }}</td>
                                    <!-- Eliminar -->
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-outline-danger"
                                            @click="eliminarItem(idx)" title="Eliminar fila">
                                            <i class="ri-delete-bin-line"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot v-if="form.detalles.length > 0" class="table-light">
                                <tr>
                                    <td colspan="5" class="text-end fw-bold">Total General:</td>
                                    <td class="text-end fw-bold text-primary">Bs {{ totalGeneral }}</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <Link :href="route('mantenimiento.ordenes.show', orden.id)"
                    class="btn btn-outline-secondary btn-wave">Cancelar</Link>
                <button type="submit" class="btn btn-success btn-wave" :disabled="form.processing">
                    <span v-if="form.processing" class="spinner-border spinner-border-sm me-1"></span>
                    <i v-else class="ri-checkbox-circle-line me-1"></i>
                    Registrar Culminación
                </button>
            </div>
        </form>
</template>
