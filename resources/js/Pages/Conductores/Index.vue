<script setup>
import { ref, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import Maindashboard from '@/Layouts/Maindashboard.vue'
defineOptions({ layout: Maindashboard })

import { showToast, confirm , showError} from '@/Utils/alertUtil.js'

const props = defineProps({
    conductores: Object,
    filters: Object,
    flash: Object,
})

// Filtros reactivos inicializados con los valores que llegan del servidor
const filters = ref({
    ci: props.filters?.ci ?? '',
    nombres: props.filters?.nombres ?? '',
    paterno: props.filters?.paterno ?? '',
    celular: props.filters?.celular ?? '',
})

// Debounce para no hacer petición en cada tecla
let debounceTimer = null
watch(
    filters,
    (val) => {
        clearTimeout(debounceTimer)
        debounceTimer = setTimeout(() => {
            router.get(
                route('conductores.index'),
                {
                    ci: val.ci || undefined,
                    nombres: val.nombres || undefined,
                    paterno: val.paterno || undefined,
                    celular: val.celular || undefined,
                },
                { preserveState: true, replace: true },
            )
        }, 350)
    },
    { deep: true },
)

function clearFilters() {
    filters.value = { ci: '', nombres: '', paterno: '', celular: '' }
}

// Confirmación de borrado
const confirmDelete = async (conductor) => {
    const confirmar = await confirm(`¿Eliminar al conductor ${conductor.nombres} ${conductor.paterno ?? ''}?`, "Confirmación", "Si, eliminar");
    if (!confirmar) return;

    router.delete(route('conductores.destroy', conductor.id),
        {
            _method: 'DELETE',
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                showToast('Conductor eliminado')
            },
        },
    )

}

const estadoBadge = (estado) => {
    const map = {
        ACTIVO: 'bg-success-transparent text-success',
        INACTIVO: 'bg-warning-transparent text-warning',
        RETIRADO: 'bg-danger-transparent text-danger',
    }
    return map[estado] ?? 'bg-secondary-transparent text-secondary'
}

const fotoUrl = (foto) =>
    foto ? `/storage/${foto}` : '/images/faces/1.jpg'


const cambiarEstado = async (conductorId, nuevoEstado) => {
    const confirmar = await confirm(`¿Desea cambiar el estado del conductor a <b>${nuevoEstado}</b>?`, "Confirmación", "Si, cambiar");
    if (!confirmar) return;

    router.put(route('conductores.update', conductorId),
        {
            _method: 'PUT',
            estado_conductor: nuevoEstado
        },
        {
            //prevenir scroll,
            preserveScroll: true,
            onSuccess: () => {
                showToast('Estado del conductor cambiado', 'success')
            },
            onError: (error)  => {
                showError(error);
            }
        })


}



</script>

<template>

    <Head title="Conductores" />

        <!-- Page header -->
        <div class="d-flex align-items-center justify-content-between page-header-breadcrumb flex-wrap gap-2 mb-4">
            <div>
                <nav>
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item">
                            <Link :href="route('dashboard')">Inicio</Link>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">Conductores</li>
                    </ol>
                </nav>
                <h1 class="page-title fw-medium fs-18 mb-0">Gestión de Conductores</h1>
            </div>
            <div>
                <Link :href="route('conductores.create')" class="btn btn-primary btn-wave">
                    <i class="ri-add-line me-1"></i> Nuevo Conductor
                </Link>
            </div>
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
                        <label class="form-label">Carnet de Identidad</label>
                        <input v-model="filters.ci" type="text" class="form-control" placeholder="Buscar por CI..." />
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <label class="form-label">Nombres</label>
                        <input v-model="filters.nombres" type="text" class="form-control"
                            placeholder="Buscar por nombres..." />
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <label class="form-label">Apellido Paterno</label>
                        <input v-model="filters.paterno" type="text" class="form-control"
                            placeholder="Buscar por apellido..." />
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <label class="form-label">Celular</label>
                        <input v-model="filters.celular" type="text" class="form-control"
                            placeholder="Buscar por celular..." />
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
                    Conductores
                    <span class="badge bg-primary-transparent text-primary ms-2">
                        {{ conductores.total }} registros
                    </span>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover text-nowrap mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Foto</th>
                                <th>CI</th>
                                <th>Nombre Completo</th>
                                <th>Celular</th>
                                <th>Vehiculos Asignados</th>
                                <th>Estado</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="conductores.data.length === 0">
                                <td colspan="8" class="text-center py-4 text-muted">
                                    <i class="ri-user-search-line fs-3 d-block mb-2"></i>
                                    No se encontraron conductores
                                </td>
                            </tr>
                            <tr v-for="(conductor, index) in conductores.data" :key="conductor.id">
                                <td>{{ index + 1 }}</td>
                                <td>
                                    <span class="avatar avatar-lg">
                                        <img :src="fotoUrl(conductor.foto)" :alt="conductor.nombres"
                                            class="rounded-circle" style="width:36px;height:36px;object-fit:cover;" />
                                    </span>
                                </td>
                                <td><span class="fw-medium">{{ conductor.ci }}</span></td>
                                <td>{{ conductor.nombres }} {{ conductor.paterno ?? '—' }} {{ conductor.materno ?? '—'
                                }}</td>
                                <td> {{ conductor.celular ?? '—' }} </td>

                                <td>
                                    <ul>
                                        <li v-for="vehiculo in conductor.asignaciones_activas" :key="vehiculo.id">
                                            {{ vehiculo.nro_placa }} ({{ vehiculo.marca }})
                                        </li>

                                        <li v-if="conductor.asignaciones_activas.length === 0" class="text-danger">
                                            No asignado
                                        </li>
                                    </ul>
                                </td>
                                <td>
                                    <!-- <span class="badge btn " :class="estadoBadge(conductor.estado_conductor)"
                                        @click="cambiarEstado(conductor.id)">
                                        {{ conductor.estado_conductor }}
                                    </span> -->
                                    <div class="btn-list">
                                        <div class="btn-group">
                                            <button
                                                :class="`btn btn- ${estadoBadge(conductor.estado_conductor)} dropdown-toggle`"
                                                type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                {{ conductor.estado_conductor }}
                                            </button>
                                            <ul :class="`dropdown-menu `">
                                                <li v-if="conductor.estado_conductor !== 'ACTIVO'"><a
                                                        @click="cambiarEstado(conductor.id, 'ACTIVO')"
                                                        class="dropdown-item" href="javascript:void(0);">Activo</a></li>
                                                <li v-if="conductor.estado_conductor !== 'INACTIVO'"><a
                                                        @click="cambiarEstado(conductor.id, 'INACTIVO')"
                                                        class="dropdown-item" href="javascript:void(0);">Inactivo</a>
                                                </li>
                                                <li v-if="conductor.estado_conductor !== 'RETIRADO'"><a
                                                        @click="cambiarEstado(conductor.id, 'RETIRADO')"
                                                        class="dropdown-item" href="javascript:void(0);">Retirado</a>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex gap-1 justify-content-center">
                                        <Link :href="route('conductores.show', conductor.id)"
                                            class="btn btn-sm btn-icon btn-light" title="Ver">
                                            <i class="ri-eye-line"></i>
                                        </Link>
                                        <Link :href="route('conductores.edit', conductor.id)"
                                            class="btn btn-sm btn-icon btn-light" title="Editar">
                                            <i class="ri-edit-line"></i>
                                        </Link>
                                        <button type="button" class="btn btn-sm btn-icon btn-light" title="Eliminar"
                                            @click="confirmDelete(conductor)">
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
                    Mostrando {{ conductores.from ?? 0 }} - {{ conductores.to ?? 0 }}
                    de {{ conductores.total }} resultados
                </div>
                <nav v-if="conductores.last_page > 1">
                    <ul class="pagination pagination-sm mb-0">
                        <li v-for="link in conductores.links" :key="link.label" class="page-item"
                            :class="{ active: link.active, disabled: !link.url }">
                            <Link v-if="link.url" :href="link.url" class="page-link" preserve-state
                                v-html="link.label" />
                            <span v-else class="page-link" v-html="link.label" />
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
</template>
