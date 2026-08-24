<script setup>
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import Maindashboard from '@/Layouts/Maindashboard.vue'
defineOptions({ layout: Maindashboard })
import VehiculoExternoFormModal from '@/Components/VehiculoExternoFormModal.vue'
import { confirm as confirmSwal } from '@/Utils/alertUtil.js'

const props = defineProps({
    vehiculosExternos: Object,
    flash: Object,
})

const modalRef = ref(null)

function abrirCrear() {
    modalRef.value?.open()
}

function abrirEditar(vehiculoExterno) {
    modalRef.value?.open(vehiculoExterno)
}

// El modal guarda por AJAX (para poder reutilizarse desde otros formularios
// sin navegar), así que aquí solo recargamos el listado con los datos frescos.
function recargarListado() {
    router.reload({ only: ['vehiculosExternos'], preserveScroll: true })
}

async function confirmDelete(vehiculoExterno) {
    const confirmado = await confirmSwal(
        `¿Eliminar el vehículo <strong>${vehiculoExterno.nro_placa}</strong>?`,
        'Eliminar Vehículo Externo',
        'Sí, eliminar',
    )

    if (!confirmado) {
        return
    }

    router.delete(route('vehiculos-externos.destroy', vehiculoExterno.id))
}
</script>

<template>
    <Head title="Vehículos Externos" />

    <!-- Page header -->
    <div class="d-flex align-items-center justify-content-between page-header-breadcrumb flex-wrap gap-2 mb-4">
        <div>
            <nav>
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item">
                        <Link :href="route('dashboard')">Inicio</Link>
                    </li>
                    <li class="breadcrumb-item active">Vehículos Externos</li>
                </ol>
            </nav>
            <h1 class="page-title fw-medium fs-18 mb-0">Vehículos Externos</h1>
        </div>
        <button v-can="'vehiculos-externos.crear'" type="button" class="btn btn-primary btn-wave" @click="abrirCrear">
            <i class="ri-add-line me-1"></i> Nuevo Vehículo Externo
        </button>
    </div>

    <!-- Flash messages -->
    <div v-if="flash?.success" class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="ri-checkbox-circle-line me-2"></i>{{ flash.success }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <div v-if="flash?.error" class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="ri-error-warning-line me-2"></i>{{ flash.error }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>

    <!-- Tabla -->
    <div class="card custom-card">
        <div class="card-header d-flex align-items-center justify-content-between">
            <div class="card-title">
                Listado
                <span class="badge bg-primary-transparent text-primary ms-2">
                    {{ vehiculosExternos.total }} registros
                </span>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:60px">#</th>
                            <th>Placa</th>
                            <th>Propietario</th>
                            <th style="width:150px" class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="vehiculosExternos.data.length === 0">
                            <td colspan="4" class="text-center py-5 text-muted">
                                <i class="ri-truck-line fs-3 d-block mb-2"></i>
                                No se encontraron vehículos externos
                            </td>
                        </tr>
                        <tr v-for="(vehiculoExterno, idx) in vehiculosExternos.data" :key="vehiculoExterno.id">
                            <td class="text-muted small">
                                {{ (vehiculosExternos.current_page - 1) * vehiculosExternos.per_page + idx + 1 }}
                            </td>
                            <td class="fw-medium">{{ vehiculoExterno.nro_placa }}</td>
                            <td>
                                <small class="text-muted">{{ vehiculoExterno.propietario || '—' }}</small>
                            </td>
                            <td class="text-center">
                                <div class="d-flex gap-1 justify-content-center">
                                    <button
                                        v-can="'vehiculos-externos.editar'"
                                        type="button"
                                        class="btn btn-sm btn-icon btn-info-light"
                                        title="Editar"
                                        @click="abrirEditar(vehiculoExterno)"
                                    >
                                        <i class="ri-edit-line"></i>
                                    </button>
                                    <button
                                        v-can="'vehiculos-externos.eliminar'"
                                        type="button"
                                        class="btn btn-sm btn-icon btn-danger-light"
                                        title="Eliminar"
                                        @click="confirmDelete(vehiculoExterno)"
                                    >
                                        <i class="ri-delete-bin-line"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Paginador -->
        <div class="card-footer d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="text-muted small">
                Mostrando
                <strong>{{ vehiculosExternos.from ?? 0 }}</strong> -
                <strong>{{ vehiculosExternos.to ?? 0 }}</strong>
                de <strong>{{ vehiculosExternos.total }}</strong> resultados
            </div>
            <nav v-if="vehiculosExternos.last_page > 1" aria-label="Paginación">
                <ul class="pagination pagination-sm mb-0">
                    <li
                        v-for="link in vehiculosExternos.links"
                        :key="link.label"
                        class="page-item"
                        :class="{ active: link.active, disabled: !link.url }"
                    >
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            class="page-link"
                            preserve-state
                            v-html="link.label"
                        />
                        <span v-else class="page-link" v-html="link.label" />
                    </li>
                </ul>
            </nav>
        </div>
    </div>

    <!-- Modal: crear / editar vehículo externo -->
    <VehiculoExternoFormModal ref="modalRef" @created="recargarListado" @updated="recargarListado" />
</template>
