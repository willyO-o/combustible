<script setup>
import { computed } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import Maindashboard from '@/Layouts/Maindashboard.vue'
defineOptions({ layout: Maindashboard })
import { showToast, confirm } from '@/Utils/alertUtil.js'

const props = defineProps({
    orden: Object,
    flash: Object,
})

const page = usePage()

// La "Verificación" sólo puede realizarla el usuario que emitió la orden, y
// "Cancelar" queda reservado a la gestión (jefe de área/administrador) — un
// técnico de mantenimiento no debe ver ninguno de los dos botones.
const esGestor = computed(() => {
    const roles = page.props.auth?.roles ?? []
    return page.props.auth?.is_super_admin
        || roles.includes('administrador')
        || roles.includes('jefe-area')
})
const esEmisor = computed(() => page.props.auth?.user?.id === props.orden.usuario_emite?.id)

const puedeIniciar = computed(() => props.orden.estado_orden === 'PENDIENTE')
const puedeVerificar = computed(() => props.orden.estado_orden === 'CULMINADO' && esEmisor.value)
const puedeCancelar = computed(() =>
    ['PENDIENTE', 'EN_EJECUCION'].includes(props.orden.estado_orden) && esGestor.value
)
const mostrarPanelEstado = computed(() => puedeIniciar.value || puedeVerificar.value || puedeCancelar.value)

const estadoBadge = (estado) => {
    const map = {
        PENDIENTE:    'bg-warning-transparent text-warning',
        EN_EJECUCION: 'bg-info-transparent text-info',
        CULMINADO:    'bg-success-transparent text-success',
        CANCELADO:    'bg-dark text-white',
        VERIFICADO:   'bg-primary-transparent text-primary',
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
        { estado_orden: nuevoEstado },
        { preserveScroll: true, onSuccess: () => showToast('Estado actualizado') }
    )
}

const formatFecha = (fecha) => fecha ? fecha.substring(0, 10).split('-').reverse().join('/') : '—'

// El horómetro/kilometraje de cada ítem del detalle a mostrar depende del
// tipo de medición del vehículo (mismo criterio que Ejecucion/Create.vue).
const tipoMedicion = computed(() => props.orden.vehiculo?.tipo_medicion)
</script>

<template>
    <Head :title="`Orden de Trabajo #${orden.nro}`" />
        <!-- Breadcrumb -->
        <div class="d-flex align-items-center justify-content-between page-header-breadcrumb flex-wrap gap-2 mb-4">
            <div>
                <nav>
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item"><Link :href="route('dashboard')">Inicio</Link></li>
                        <li class="breadcrumb-item">
                            <Link :href="route('mantenimiento.ordenes.index')">Órdenes de Trabajo</Link>
                        </li>
                        <li class="breadcrumb-item active">Orden #{{ orden.nro }}</li>
                    </ol>
                </nav>
                <h1 class="page-title fw-medium fs-18 mb-0">Orden de Trabajo #{{ orden.nro }}</h1>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <Link :href="route('mantenimiento.ordenes.index')" class="btn btn-outline-secondary btn-wave">
                    <i class="ri-arrow-left-line me-1"></i> Volver
                </Link>
                <Link v-can="'mantenimiento.ordenes.editar'" v-if="orden.estado_orden === 'PENDIENTE'"
                    :href="route('mantenimiento.ordenes.edit', orden.id)"
                    class="btn btn-outline-secondary btn-wave">
                    <i class="ri-pencil-line me-1"></i> Editar
                </Link>
                <Link v-can="'mantenimiento.ordenes.ejecucion.registrar'"
                    v-if="['PENDIENTE','EN_EJECUCION'].includes(orden.estado_orden)"
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
                        <span class="badge fs-13" :class="estadoBadge(orden.estado_orden)">{{ orden.estado_orden }}</span>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label text-muted mb-0">Vehículo</label>
                                <p class="fw-bold fs-15">{{ orden.vehiculo?.codigo ?? '—' }} — {{ orden.vehiculo?.nro_placa ?? '—' }} — {{ orden.vehiculo?.marca ?? '' }}</p>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label text-muted mb-0">Tipo de Mantenimiento</label>
                                <p>
                                    <span class="badge me-1" :class="tipoBadge(orden.tipo_mantenimiento)">{{ orden.tipo_mantenimiento }}</span>
                                    <span class="badge" :class="ordenBadge(orden.tipo_orden)">{{ orden.tipo_orden }}</span>
                                </p>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label text-muted mb-0">Fecha de Emisión</label>
                                <p>{{ orden.fecha_emision?.substring(0, 16).replace('T', ' ') ?? '—' }}</p>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label text-muted mb-0">Km / Horómetro Actual</label>
                                <p>
                                    {{ orden.kilometraje_actual != null ? orden.kilometraje_actual.toLocaleString() + ' km' : '—' }}
                                    <span v-if="orden.horometro_actual != null"> / {{ orden.horometro_actual }} h</span>
                                </p>
                            </div>
                            <div v-if="orden.nota_emisor" class="col-12">
                                <label class="form-label text-muted mb-0">Nota del Emisor</label>
                                <p style="white-space:pre-wrap;">{{ orden.nota_emisor }}</p>
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
                <div v-if="['CULMINADO','VERIFICADO'].includes(orden.estado_orden)" class="card custom-card">
                    <div class="card-header bg-success-transparent">
                        <div class="card-title text-success">
                            <i class="ri-checkbox-circle-line me-2"></i>
                            Ejecución
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label text-muted mb-0">Fecha Ejecución</label>
                                <p>{{ orden.fecha_ejecucion?.substring(0, 16).replace('T', ' ') ?? '—' }}</p>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label text-muted mb-0">Fecha Culminación</label>
                                <p>{{ orden.fecha_culminacion?.substring(0, 16).replace('T', ' ') ?? '—' }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Detalle de repuestos / insumos / mano de obra -->
                <div v-if="orden.detalles && orden.detalles.length > 0" class="card custom-card mt-4">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="ri-archive-line me-2"></i>
                            Detalle del Trabajo Realizado
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Fecha</th>
                                        <th>Tipo de Mantenimiento</th>
                                        <th>Repuesto</th>
                                        <th class="text-end">Cantidad</th>
                                        <th class="text-end">{{ tipoMedicion === 'kilometraje' ? 'Kilometraje' : 'Horómetro' }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(d, idx) in orden.detalles" :key="d.id">
                                        <td>{{ idx + 1 }}</td>
                                        <td>{{ formatFecha(d.fecha) }}</td>
                                        <td>{{ d.tipo_mantenimiento?.tipo_mantenimiento ?? '—' }}</td>
                                        <td>
                                            <span v-if="d.repuesto">{{ d.repuesto.codigo_repuesto }} – {{ d.repuesto.nombre_repuesto }}</span>
                                            <span v-else class="text-muted">— Mano de obra —</span>
                                        </td>
                                        <td class="text-end">{{ d.cantidad }}</td>
                                        <td class="text-end">
                                            <template v-if="tipoMedicion === 'kilometraje'">
                                                {{ d.kilometraje != null ? Number(d.kilometraje).toFixed(2) + ' km' : '—' }}
                                            </template>
                                            <template v-else>
                                                {{ d.horometro != null ? Number(d.horometro).toFixed(2) + ' h' : '—' }}
                                            </template>
                                        </td>
                                    </tr>
                                </tbody>
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
                        <p class="mb-1 fw-medium">#{{ orden.solicitud_mantenimiento.nro }}</p>
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
                        <p class="mb-1 text-muted small">Emitido por</p>
                        <p class="fw-medium mb-3">{{ orden.usuario_emite?.name ?? '—' }}</p>
                        <p class="mb-1 text-muted small">Responsable de Ejecución</p>
                        <p class="fw-medium mb-0">{{ orden.usuario_ejecuta?.name ?? '—' }}</p>
                    </div>
                </div>

                <!-- Cambiar estado -->
                <div v-can="'mantenimiento.ordenes.estado.cambiar'" v-if="mostrarPanelEstado" class="card custom-card">
                    <div class="card-header"><div class="card-title">Cambiar Estado</div></div>
                    <div class="card-body d-flex flex-column gap-2">
                        <button v-if="puedeIniciar"
                            class="btn btn-info btn-wave w-100"
                            @click="cambiarEstado('EN_EJECUCION')">
                            Iniciar → En Ejecución
                        </button>
                        <button v-if="puedeVerificar"
                            class="btn btn-primary btn-wave w-100"
                            @click="cambiarEstado('VERIFICADO')">
                            Verificar Orden
                        </button>
                        <button v-if="puedeCancelar"
                            class="btn btn-outline-danger btn-wave w-100"
                            @click="cambiarEstado('CANCELADO')">
                            Cancelar Orden
                        </button>
                    </div>
                </div>
            </div>
        </div>
</template>
