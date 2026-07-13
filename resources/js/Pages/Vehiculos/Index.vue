<script setup>
import { onMounted, ref, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import Maindashboard from '@/Layouts/Maindashboard.vue'

const props = defineProps({
    vehiculos: Object,
    tiposVehiculo: Array,
    filters: Object,
    flash: Object,
})

const filters = ref({
    nro_placa: props.filters?.nro_placa ?? '',
    marca: props.filters?.marca ?? '',
    estado_vehiculo: props.filters?.estado_vehiculo ?? '',
    id_tipo_vehiculo: props.filters?.id_tipo_vehiculo ?? '',
})

let debounceTimer = null
watch(
    filters,
    (val) => {
        clearTimeout(debounceTimer)
        debounceTimer = setTimeout(() => {
            router.get(
                route('vehiculos.index'),
                {
                    nro_placa: val.nro_placa || undefined,
                    marca: val.marca || undefined,
                    estado_vehiculo: val.estado_vehiculo || undefined,
                    id_tipo_vehiculo: val.id_tipo_vehiculo || undefined,
                },
                { preserveState: true, replace: true },
            )
        }, 350)
    },
    { deep: true },
)

function clearFilters() {
    filters.value = { nro_placa: '', marca: '', estado_vehiculo: '', id_tipo_vehiculo: '' }
}

function confirmDelete(vehiculo) {
    if (confirm(`¿Eliminar el vehículo con placa "${vehiculo.nro_placa}"?`)) {
        router.delete(route('vehiculos.destroy', vehiculo.id))
    }
}

const estadoBadge = (estado) => {
    const map = {
        ACTIVO: 'bg-success-transparent text-success',
        RETIRADO: 'bg-warning-transparent text-warning',
        VENDIDO: 'bg-secondary-transparent text-secondary',
    }
    return map[estado] ?? 'bg-secondary-transparent text-secondary'
}

const fotoUrl = (foto) => foto ? `/storage/${foto}` : null


onMounted(() => {
    console.log(props.vehiculos);
})
</script>

<template>

    <Head title="Vehículos" />

    <Maindashboard>
        <!-- Page header -->
        <div class="d-flex align-items-center justify-content-between page-header-breadcrumb flex-wrap gap-2 mb-4">
            <div>
                <nav>
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item">
                            <Link :href="route('dashboard')">Inicio</Link>
                        </li>
                        <li class="breadcrumb-item active">Vehículos</li>
                    </ol>
                </nav>
                <h1 class="page-title fw-medium fs-18 mb-0">Gestión de Vehículos</h1>
            </div>
            <Link :href="route('vehiculos.create')" class="btn btn-primary btn-wave">
                <i class="ri-add-line me-1"></i> Nuevo Vehículo
            </Link>
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

        <!-- Filtros -->
        <div class="card custom-card mb-4">
            <div class="card-header">
                <div class="card-title">Filtros de búsqueda</div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-sm-6 col-xl-3">
                        <label class="form-label">Nro. Placa</label>
                        <input v-model="filters.nro_placa" type="text" class="form-control"
                            placeholder="Buscar por placa..." />
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <label class="form-label">Marca</label>
                        <input v-model="filters.marca" type="text" class="form-control"
                            placeholder="Buscar por marca..." />
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <label class="form-label">Tipo de Vehículo</label>
                        <select v-model="filters.id_tipo_vehiculo" class="form-select">
                            <option value="">Todos</option>
                            <option v-for="tv in tiposVehiculo" :key="tv.id" :value="tv.id">
                                {{ tv.tipo_vehiculo }}
                            </option>
                        </select>
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <label class="form-label">Estado</label>
                        <select v-model="filters.estado_vehiculo" class="form-select">
                            <option value="">Todos</option>
                            <option value="ACTIVO">ACTIVO</option>
                            <option value="RETIRADO">RETIRADO</option>
                            <option value="VENDIDO">VENDIDO</option>
                        </select>
                    </div>
                </div>
                <div class="mt-3 d-flex justify-content-end">
                    <button type="button" class="btn btn-outline-secondary btn-wave" @click="clearFilters">
                        <i class="ri-refresh-line me-1"></i> Limpiar filtros
                    </button>
                </div>
            </div>
        </div>

        <!-- Tabla -->
        <div class="card custom-card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <div class="card-title">
                    Vehículos
                    <span class="badge bg-primary-transparent text-primary ms-2">
                        {{ vehiculos.total }} registros
                    </span>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover text-nowrap mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Vehiculo / Placa / Modelo</th>
                                <th>Año</th>
                                <th>Tipo Vehículo</th>
                                <th>Tipo Combustible</th>
                                <th>Estado</th>
                                <th>Asignado</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="vehiculos.data.length === 0">
                                <td colspan="8" class="text-center py-4 text-muted">
                                    <i class="ri-car-line fs-3 d-block mb-2"></i>
                                    No se encontraron vehículos
                                </td>
                            </tr>
                            <tr v-for="vehiculo in vehiculos.data" :key="vehiculo.id">
                                <td>
                                    <div class="d-flex align-items-center gap-3 position-relative">
                                        <div class="lh-1">
                                            <span class="avatar avatar-lg bg-light">
                                                <img v-if="fotoUrl(vehiculo.fotografia)"
                                                    :src="fotoUrl(vehiculo.fotografia)" :alt="vehiculo.nro_placa"
                                                    class="rounded" style="width:36px;height:36px;object-fit:cover;" />
                                                <span v-else
                                                    class="avatar avatar-sm avatar-rounded bg-light text-muted d-flex align-items-center justify-content-center">
                                                    <i class="ri-car-line"></i>
                                                </span>
                                            </span>
                                        </div>
                                        <div>
                                            <span class="d-block fw-semibold">{{ vehiculo.nro_placa }}</span>
                                            <span class="text-muted fs-13"> {{ vehiculo.marca ?? '—' }} </span>
                                        </div>
                                    </div>

                                </td>
                                <td>{{ vehiculo.anio ?? '—' }}</td>
                                <td>
                                    <span class="badge bg-info-transparent text-info">
                                        {{ vehiculo.tipo_vehiculo?.tipo_vehiculo ?? '—' }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-warning-transparent text-warning">
                                        {{ vehiculo.tipo_combustible?.tipo_combustible ?? '—' }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge" :class="estadoBadge(vehiculo.estado_vehiculo)">
                                        {{ vehiculo.estado_vehiculo }}
                                    </span>
                                </td>
                                <td>
                                    <span v-if="vehiculo.conductor_asignado" class="fw-semibold">
                                        <i class="ri-user-line me-1"></i>
                                        {{ vehiculo.conductor_asignado.nombres }} {{ vehiculo.conductor_asignado.paterno
                                        }}
                                        {{ vehiculo.conductor_asignado.materno }}

                                    </span>
                                    <span v-else class="text-muted">
                                        N/A
                                    </span>
                                </td>

                                <td class="text-center">
                                    <div class="d-flex gap-1 justify-content-center">
                                        <Link :href="route('vehiculos.show', vehiculo.id)"
                                            class="btn btn-sm btn-icon btn-primary-light" title="Editar">
                                            <i class="ri-eye-line"></i>
                                        </Link>
                                        <Link :href="route('vehiculos.edit', vehiculo.id)"
                                            class="btn btn-sm btn-icon btn-info-light" title="Editar">
                                            <i class="ri-edit-line"></i>
                                        </Link>
                                        <button type="button" class="btn btn-sm btn-icon btn-danger-light"
                                            title="Eliminar" @click="confirmDelete(vehiculo)">
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
                    Mostrando {{ vehiculos.from ?? 0 }} - {{ vehiculos.to ?? 0 }}
                    de {{ vehiculos.total }} resultados
                </div>
                <nav v-if="vehiculos.last_page > 1">
                    <ul class="pagination pagination-sm mb-0">
                        <li v-for="link in vehiculos.links" :key="link.label" class="page-item"
                            :class="{ active: link.active, disabled: !link.url }">
                            <Link v-if="link.url" :href="link.url" class="page-link" preserve-state
                                v-html="link.label" />
                            <span v-else class="page-link" v-html="link.label" />
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
    </Maindashboard>
</template>
