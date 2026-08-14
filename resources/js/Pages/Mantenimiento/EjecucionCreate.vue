<script setup>
import { ref, computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import Maindashboard from '@/Layouts/Maindashboard.vue'
defineOptions({ layout: Maindashboard })

const props = defineProps({
    orden: Object,
    repuestos: Array,
})

// ── Formulario principal ──────────────────────────────────────────────────────
const form = useForm({
    fecha_inicio:                 '',
    fecha_fin:                    '',
    kilometraje_al_mantenimiento: '',
    trabajo_realizado:            '',
    costo_mano_obra:              '',
    observacion:                  '',
    items: [],
})

// ── Ítem en blanco para la tabla de insumos ───────────────────────────────────
const itemVacio = () => ({
    tipo_item:          'REPUESTO',
    id_repuesto:        '',
    nombre_item:        '',
    unidad_medida:      'UNIDAD',
    cantidad_utilizada: '',
    costo_unitario:     '',
    subtotal:           '',
    observacion:        '',
})

function agregarItem() {
    form.items.push(itemVacio())
}

function eliminarItem(idx) {
    form.items.splice(idx, 1)
}

// Calcular subtotal automáticamente cuando cambia cantidad o costo
function recalcularSubtotal(item) {
    const qty   = parseFloat(item.cantidad_utilizada) || 0
    const price = parseFloat(item.costo_unitario)     || 0
    item.subtotal = (qty * price).toFixed(2)
}

// Al seleccionar un repuesto del catálogo, copiar unidad de medida
function onRepuestoChange(item) {
    const rep = props.repuestos.find(r => r.id == item.id_repuesto)
    if (rep) {
        item.unidad_medida = rep.unidad_medida
        item.nombre_item   = ''
    }
}

// Mostrar campo nombre libre sólo cuando no hay repuesto seleccionado
const necesitaNombreLibre = (item) =>
    ['ACEITE', 'LLANTA', 'INSUMO', 'OTRO'].includes(item.tipo_item) || !item.id_repuesto

// Costo total de materiales
const totalMateriales = computed(() =>
    form.items.reduce((acc, i) => acc + (parseFloat(i.subtotal) || 0), 0).toFixed(2)
)

// Costo total = mano de obra + materiales
const costoTotal = computed(() =>
    (parseFloat(form.costo_mano_obra || 0) + parseFloat(totalMateriales.value)).toFixed(2)
)

function submit() {
    // Adjuntar costo total calculado
    const payload = {
        ...form.data(),
        costo_total: costoTotal.value,
    }
    form.transform(() => payload).post(
        route('mantenimiento.ordenes.ejecucion.store', props.orden.id)
    )
}

// Agregar primer ítem automáticamente
agregarItem()
</script>

<template>
    <Head :title="`Registrar Ejecución – Orden #${orden.id}`" />
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
                            <Link :href="route('mantenimiento.ordenes.show', orden.id)">Orden #{{ orden.id }}</Link>
                        </li>
                        <li class="breadcrumb-item active">Registrar Ejecución</li>
                    </ol>
                </nav>
                <h1 class="page-title fw-medium fs-18 mb-0">
                    Paso 3 – Registro de Trabajo Realizado
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
                <strong>Orden #{{ orden.id }}</strong> —
                Vehículo: <strong>{{ orden.vehiculo?.nro_placa }} {{ orden.vehiculo?.marca }}</strong> —
                <span class="badge bg-info-transparent text-info">{{ orden.tipo_mantenimiento.tipo_mantenimiento }}</span>
                <span class="badge bg-primary-transparent text-primary ms-1">{{ orden.tipo_orden }}</span>
                <span v-if="orden.taller"> — Taller: {{ orden.taller.razon_social }}</span>
            </div>
        </div>

        <form @submit.prevent="submit">
            <!-- Datos de la ejecución -->
            <div class="card custom-card mb-4">
                <div class="card-header">
                    <div class="card-title">
                        <i class="ri-tools-line me-2 text-success"></i>
                        Datos del Trabajo Realizado
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <!-- Fecha inicio -->
                        <div class="col-sm-6 col-xl-3">
                            <label class="form-label fw-medium">
                                Fecha Inicio <span class="text-danger">*</span>
                            </label>
                            <input v-model="form.fecha_inicio" type="date"
                                class="form-control" :class="{ 'is-invalid': form.errors.fecha_inicio }" />
                            <div v-if="form.errors.fecha_inicio" class="invalid-feedback">
                                {{ form.errors.fecha_inicio }}
                            </div>
                        </div>

                        <!-- Fecha fin -->
                        <div class="col-sm-6 col-xl-3">
                            <label class="form-label fw-medium">
                                Fecha Fin <span class="text-danger">*</span>
                            </label>
                            <input v-model="form.fecha_fin" type="date"
                                class="form-control" :class="{ 'is-invalid': form.errors.fecha_fin }" />
                            <div v-if="form.errors.fecha_fin" class="invalid-feedback">
                                {{ form.errors.fecha_fin }}
                            </div>
                        </div>

                        <!-- Km al mantenimiento -->
                        <div class="col-sm-6 col-xl-3">
                            <label class="form-label fw-medium">Kilometraje al Mantenimiento</label>
                            <div class="input-group">
                                <input v-model="form.kilometraje_al_mantenimiento" type="number" min="0"
                                    class="form-control" placeholder="Ej: 87500" />
                                <span class="input-group-text">km</span>
                            </div>
                        </div>

                        <!-- Costo mano de obra -->
                        <div class="col-sm-6 col-xl-3">
                            <label class="form-label fw-medium">Costo Mano de Obra (Bs)</label>
                            <div class="input-group">
                                <span class="input-group-text">Bs</span>
                                <input v-model="form.costo_mano_obra" type="number" min="0" step="0.01"
                                    class="form-control" placeholder="0.00" />
                            </div>
                        </div>

                        <!-- Trabajo realizado -->
                        <div class="col-12">
                            <label class="form-label fw-medium">
                                Descripción del Trabajo Realizado <span class="text-danger">*</span>
                            </label>
                            <textarea v-model="form.trabajo_realizado" rows="4"
                                class="form-control" :class="{ 'is-invalid': form.errors.trabajo_realizado }"
                                placeholder="Describa detalladamente el trabajo que se realizó...">
                            </textarea>
                            <div v-if="form.errors.trabajo_realizado" class="invalid-feedback">
                                {{ form.errors.trabajo_realizado }}
                            </div>
                        </div>

                        <!-- Observación -->
                        <div class="col-12">
                            <label class="form-label fw-medium">Observaciones</label>
                            <textarea v-model="form.observacion" rows="2"
                                class="form-control" placeholder="Observaciones adicionales...">
                            </textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla de repuestos / insumos utilizados -->
            <div class="card custom-card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div class="card-title">
                        <i class="ri-archive-line me-2"></i>
                        Repuestos / Aceites / Llantas / Insumos Utilizados
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary btn-wave" @click="agregarItem">
                        <i class="ri-add-line me-1"></i> Agregar Ítem
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="min-width:110px">Tipo</th>
                                    <th style="min-width:200px">Repuesto (catálogo)</th>
                                    <th style="min-width:160px">Nombre / Descripción</th>
                                    <th style="min-width:120px">Unidad</th>
                                    <th style="min-width:100px">Cantidad</th>
                                    <th style="min-width:110px">Costo Unit.</th>
                                    <th style="min-width:110px">Subtotal</th>
                                    <th style="min-width:130px">Observación</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="form.items.length === 0">
                                    <td colspan="9" class="text-center py-3 text-muted">
                                        No hay ítems. Haga clic en "Agregar Ítem".
                                    </td>
                                </tr>
                                <tr v-for="(item, idx) in form.items" :key="idx">
                                    <!-- Tipo -->
                                    <td>
                                        <select v-model="item.tipo_item" class="form-select form-select-sm">
                                            <option value="REPUESTO">Repuesto</option>
                                            <option value="ACEITE">Aceite</option>
                                            <option value="LLANTA">Llanta</option>
                                            <option value="INSUMO">Insumo</option>
                                            <option value="OTRO">Otro</option>
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
                                    <!-- Nombre libre -->
                                    <td>
                                        <input v-model="item.nombre_item" type="text"
                                            class="form-control form-control-sm"
                                            :disabled="!!item.id_repuesto"
                                            placeholder="Nombre del ítem" />
                                    </td>
                                    <!-- Unidad -->
                                    <td>
                                        <select v-model="item.unidad_medida" class="form-select form-select-sm">
                                            <option value="UNIDAD">Unidad</option>
                                            <option value="LITRO">Litro</option>
                                            <option value="KILOGRAMO">Kilogramo</option>
                                            <option value="METRO">Metro</option>
                                            <option value="JUEGO">Juego</option>
                                            <option value="CAJA">Caja</option>
                                            <option value="BOLSA">Bolsa</option>
                                            <option value="PAQUETE">Paquete</option>
                                        </select>
                                    </td>
                                    <!-- Cantidad -->
                                    <td>
                                        <input v-model="item.cantidad_utilizada" type="number" min="0.01" step="0.01"
                                            class="form-control form-control-sm text-end"
                                            @input="recalcularSubtotal(item)" />
                                    </td>
                                    <!-- Costo unitario -->
                                    <td>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">Bs</span>
                                            <input v-model="item.costo_unitario" type="number" min="0" step="0.01"
                                                class="form-control text-end"
                                                @input="recalcularSubtotal(item)" />
                                        </div>
                                    </td>
                                    <!-- Subtotal (calculado) -->
                                    <td>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">Bs</span>
                                            <input v-model="item.subtotal" type="number" min="0" step="0.01"
                                                class="form-control text-end fw-medium" readonly />
                                        </div>
                                    </td>
                                    <!-- Observación del ítem -->
                                    <td>
                                        <input v-model="item.observacion" type="text"
                                            class="form-control form-control-sm"
                                            placeholder="Obs." />
                                    </td>
                                    <!-- Eliminar -->
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-outline-danger"
                                            @click="eliminarItem(idx)" title="Eliminar fila">
                                            <i class="ri-delete-bin-line"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot v-if="form.items.length > 0" class="table-light">
                                <tr>
                                    <td colspan="6" class="text-end fw-bold">Total Materiales:</td>
                                    <td class="text-end fw-bold text-primary">Bs {{ totalMateriales }}</td>
                                    <td colspan="2"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Resumen de costos -->
            <div class="card custom-card mb-4 border border-success">
                <div class="card-header bg-success-transparent">
                    <div class="card-title text-success">
                        <i class="ri-money-dollar-circle-line me-2"></i> Resumen de Costos
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-sm-4 text-center">
                            <p class="text-muted mb-1">Mano de Obra</p>
                            <p class="fw-bold fs-18">Bs {{ Number(form.costo_mano_obra || 0).toFixed(2) }}</p>
                        </div>
                        <div class="col-sm-4 text-center">
                            <p class="text-muted mb-1">Repuestos / Insumos</p>
                            <p class="fw-bold fs-18">Bs {{ totalMateriales }}</p>
                        </div>
                        <div class="col-sm-4 text-center border-start">
                            <p class="text-muted mb-1">TOTAL GENERAL</p>
                            <p class="fw-bold fs-22 text-success">Bs {{ costoTotal }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <Link :href="route('mantenimiento.ordenes.show', orden.id)"
                    class="btn btn-outline-secondary btn-wave">Cancelar</Link>
                <button type="submit" class="btn btn-success btn-wave" :disabled="form.processing">
                    <span v-if="form.processing" class="spinner-border spinner-border-sm me-1"></span>
                    <i v-else class="ri-checkbox-circle-line me-1"></i>
                    Registrar Mantenimiento Completado
                </button>
            </div>
        </form>
</template>
