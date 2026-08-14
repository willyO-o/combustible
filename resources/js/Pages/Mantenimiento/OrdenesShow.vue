<script setup>
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import Maindashboard from '@/Layouts/Maindashboard.vue'
defineOptions({ layout: Maindashboard })
import { showToast, confirm } from '@/Utils/alertUtil.js'

const props = defineProps({
    orden: Object,
    repuestos: Array,
    flash: Object,
})

const estadoBadge = (estado) => {
    const map = {
        BORRADOR:   'bg-secondary-transparent text-secondary',
        PENDIENTE:  'bg-warning-transparent text-warning',
        EN_PROCESO: 'bg-info-transparent text-info',
        COMPLETADO: 'bg-success-transparent text-success',
        VENCIDO:    'bg-danger-transparent text-danger',
        ANULADO:    'bg-dark text-white',
    }
    return map[estado] ?? 'bg-light text-dark'
}

const tipoBadge = (tipo) =>
    tipo === 'PREVENTIVO' ? 'bg-info-transparent text-info' : 'bg-danger-transparent text-danger'

const ordenBadge = (tipo) =>
    tipo === 'INTERNO' ? 'bg-primary-transparent text-primary' : 'bg-warning-transparent text-warning'

// Cambiar estado rápido
async function cambiarEstado(nuevoEstado) {
    const confirmar = await confirm(`¿Cambiar estado a <b>${nuevoEstado}</b>?`, 'Confirmación', 'Sí, cambiar')
    if (!confirmar) return

    router.patch(route('mantenimiento.ordenes.estado', props.orden.id),
        { estado_plan: nuevoEstado },
        { preserveScroll: true, onSuccess: () => showToast('Estado actualizado') }
    )
}

const tipoItemLabel = (tipo) => {
    const map = { REPUESTO: 'Repuesto', ACEITE: 'Aceite', LLANTA: 'Llanta', INSUMO: 'Insumo', OTRO: 'Otro' }
    return map[tipo] ?? tipo
}

const costoTotalRepuestos = () => {
    return (props.orden.repuestos ?? []).reduce((acc, r) => acc + parseFloat(r.subtotal ?? 0), 0).toFixed(2)
}
</script>

<template>
    <Head :title="`Orden de Mantenimiento #${orden.id}`" />
        <!-- Breadcrumb -->
        <div class="d-flex align-items-center justify-content-between page-header-breadcrumb flex-wrap gap-2 mb-4">
            <div>
                <nav>
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item"><Link :href="route('dashboard')">Inicio</Link></li>
                        <li class="breadcrumb-item">
                            <Link :href="route('mantenimiento.ordenes.index')">Órdenes Mantenimiento</Link>
                        </li>
                        <li class="breadcrumb-item active">Orden #{{ orden.id }}</li>
                    </ol>
                </nav>
                <h1 class="page-title fw-medium fs-18 mb-0">Orden de Trabajo #{{ orden.id }}</h1>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <Link :href="route('mantenimiento.ordenes.index')" class="btn btn-outline-secondary btn-wave">
                    <i class="ri-arrow-left-line me-1"></i> Volver
                </Link>
                <Link v-if="['BORRADOR','PENDIENTE'].includes(orden.estado_plan)"
                    :href="route('mantenimiento.ordenes.edit', orden.id)"
                    class="btn btn-outline-secondary btn-wave">
                    <i class="ri-pencil-line me-1"></i> Editar
                </Link>
                <Link v-if="['PENDIENTE','EN_PROCESO'].includes(orden.estado_plan)"
                    :href="route('mantenimiento.ordenes.ejecucion.create', orden.id)"
                    class="btn btn-success btn-wave">
                    <i class="ri-tools-line me-1"></i> Registrar Ejecución
                </Link>
            </div>
        </div>

        <!-- Flash -->
        <div v-if="flash?.success" class="alert alert-success alert-dismissible fade show">
            <i class="ri-checkbox-circle-line me-2"></i>{{ flash.success }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>

        <div class="row g-4">
            <!-- Datos principales -->
            <div class="col-xl-8">
                <div class="card custom-card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div class="card-title">
                            <i class="ri-file-list-3-line me-2 text-primary"></i>
                            Datos de la Orden
                        </div>
                        <span class="badge fs-13" :class="estadoBadge(orden.estado_plan)">{{ orden.estado_plan }}</span>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label text-muted mb-0">Vehículo</label>
                                <p class="fw-bold fs-15">{{ orden.vehiculo?.nro_placa ?? '—' }} — {{ orden.vehiculo?.marca ?? '' }}</p>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label text-muted mb-0">Tipo de Mantenimiento</label>
                                <p>
                                    <span class="badge me-1" :class="tipoBadge(orden.tipo_mantenimiento)">{{ orden.tipo_mantenimiento.tipo_mantenimiento }}</span>
                                    <span class="badge" :class="ordenBadge(orden.tipo_orden)">{{ orden.tipo_orden }}</span>
                                </p>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label text-muted mb-0">Categoría de Mantenimiento</label>
                                <p>{{ orden.tipo_mantenimiento?.tipo_mantenimiento ?? orden.tipo_mantenimiento }}</p>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label text-muted mb-0">Fecha de Orden</label>
                                <p>{{ orden.fecha_orden ?? '—' }}</p>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label text-muted mb-0">Fecha Programada</label>
                                <p>{{ orden.fecha_programada ?? '—' }}</p>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label text-muted mb-0">Km Programado</label>
                                <p>{{ orden.kilometraje_programado != null ? orden.kilometraje_programado.toLocaleString() + ' km' : '—' }}</p>
                            </div>
                            <div v-if="orden.descripcion_trabajo_ordenado" class="col-12">
                                <label class="form-label text-muted mb-0">Trabajo Ordenado</label>
                                <p style="white-space:pre-wrap;">{{ orden.descripcion_trabajo_ordenado }}</p>
                            </div>
                            <div v-if="orden.taller" class="col-sm-6">
                                <label class="form-label text-muted mb-0">Taller</label>
                                <p>{{ orden.taller.razon_social }}</p>
                            </div>
                            <div v-if="orden.observacion" class="col-12">
                                <label class="form-label text-muted mb-0">Observación</label>
                                <p style="white-space:pre-wrap;">{{ orden.observacion }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Ejecución (Paso 3) -->
                <div v-if="orden.estado_plan === 'COMPLETADO'" class="card custom-card">
                    <div class="card-header bg-success-transparent">
                        <div class="card-title text-success">
                            <i class="ri-checkbox-circle-line me-2"></i>
                            Registro de Ejecución
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label text-muted mb-0">Fecha Inicio</label>
                                <p>{{ orden.fecha_inicio ?? '—' }}</p>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label text-muted mb-0">Fecha Fin</label>
                                <p>{{ orden.fecha_fin ?? '—' }}</p>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label text-muted mb-0">Km al Mantenimiento</label>
                                <p>{{ orden.kilometraje_al_mantenimiento != null ? orden.kilometraje_al_mantenimiento.toLocaleString() + ' km' : '—' }}</p>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label text-muted mb-0">Ejecutado por</label>
                                <p>{{ orden.usuario_ejecuta?.name ?? '—' }}</p>
                            </div>
                            <div class="col-12">
                                <label class="form-label text-muted mb-0">Trabajo Realizado</label>
                                <p style="white-space:pre-wrap;">{{ orden.trabajo_realizado }}</p>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label text-muted mb-0">Costo Mano de Obra</label>
                                <p class="fw-medium">Bs {{ Number(orden.costo_mano_obra ?? 0).toFixed(2) }}</p>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label text-muted mb-0">Costo Total</label>
                                <p class="fw-bold text-success fs-15">Bs {{ Number(orden.costo_total ?? 0).toFixed(2) }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Materiales utilizados -->
                <div v-if="orden.repuestos && orden.repuestos.length > 0" class="card custom-card mt-4">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="ri-archive-line me-2"></i>
                            Repuestos / Insumos Utilizados
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Tipo</th>
                                        <th>Ítem</th>
                                        <th>Unidad</th>
                                        <th class="text-end">Cantidad</th>
                                        <th class="text-end">Costo Unit.</th>
                                        <th class="text-end">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(r, idx) in orden.repuestos" :key="r.id">
                                        <td>{{ idx + 1 }}</td>
                                        <td><span class="badge bg-secondary-transparent text-secondary">{{ tipoItemLabel(r.tipo_item) }}</span></td>
                                        <td>{{ r.repuesto?.nombre_repuesto ?? r.nombre_item ?? '—' }}</td>
                                        <td>{{ r.unidad_medida }}</td>
                                        <td class="text-end">{{ r.cantidad_utilizada }}</td>
                                        <td class="text-end">Bs {{ Number(r.costo_unitario).toFixed(2) }}</td>
                                        <td class="text-end fw-medium">Bs {{ Number(r.subtotal).toFixed(2) }}</td>
                                    </tr>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <td colspan="6" class="text-end fw-bold">Total Repuestos / Insumos:</td>
                                        <td class="text-end fw-bold text-primary">Bs {{ costoTotalRepuestos() }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Panel lateral -->
            <div class="col-xl-4">
                <!-- Solicitud origen -->
                <div v-if="orden.solicitud_mantenimiento" class="card custom-card mb-3">
                    <div class="card-header">
                        <div class="card-title"><i class="ri-alarm-warning-line me-2 text-warning"></i>Solicitud Origen</div>
                    </div>
                    <div class="card-body">
                        <p class="mb-1 fw-medium">#{{ orden.solicitud_mantenimiento.id }}</p>
                        <p class="mb-1 text-muted small">{{ orden.solicitud_mantenimiento.descripcion_problema?.substring(0, 100) }}</p>
                        <Link :href="route('mantenimiento.solicitudes.show', orden.solicitud_mantenimiento.id)"
                            class="btn btn-sm btn-outline-info btn-wave">
                            <i class="ri-eye-line me-1"></i> Ver Solicitud
                        </Link>
                    </div>
                </div>

                <!-- Responsables -->
                <div class="card custom-card mb-3">
                    <div class="card-header"><div class="card-title"><i class="ri-user-settings-line me-2"></i>Responsables</div></div>
                    <div class="card-body">
                        <p class="mb-1 text-muted small">Jefe de Transportes</p>
                        <p class="fw-medium mb-3">{{ orden.usuario_jefe?.name ?? '—' }}</p>
                        <p v-if="orden.usuario_ejecuta" class="mb-1 text-muted small">Ejecutado por</p>
                        <p v-if="orden.usuario_ejecuta" class="fw-medium">{{ orden.usuario_ejecuta?.name ?? '—' }}</p>
                    </div>
                </div>

                <!-- Cambiar estado -->
                <div v-if="orden.estado_plan !== 'COMPLETADO'" class="card custom-card">
                    <div class="card-header"><div class="card-title">Cambiar Estado</div></div>
                    <div class="card-body d-flex flex-column gap-2">
                        <button v-if="orden.estado_plan === 'BORRADOR'"
                            class="btn btn-warning btn-wave w-100"
                            @click="cambiarEstado('PENDIENTE')">
                            Aprobar → Pendiente
                        </button>
                        <button v-if="orden.estado_plan === 'PENDIENTE'"
                            class="btn btn-info btn-wave w-100"
                            @click="cambiarEstado('EN_PROCESO')">
                            Iniciar → En Proceso
                        </button>
                        <button v-if="['BORRADOR','PENDIENTE','EN_PROCESO'].includes(orden.estado_plan)"
                            class="btn btn-outline-danger btn-wave w-100"
                            @click="cambiarEstado('ANULADO')">
                            Anular Orden
                        </button>
                    </div>
                </div>
            </div>
        </div>
</template>
