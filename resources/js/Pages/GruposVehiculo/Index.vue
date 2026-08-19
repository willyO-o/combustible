<script setup>
import { ref, nextTick } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { Modal } from 'bootstrap'
import Maindashboard from '@/Layouts/Maindashboard.vue'
defineOptions({ layout: Maindashboard })
import InputError from '@/Components/InputError.vue'

const props = defineProps({
    grupos: Object,
    flash:  Object,
})

const modalEl = ref(null)
let modalInstance = null

// null = creando un grupo nuevo; objeto = editando ese grupo.
const editando = ref(null)

const form = useForm({
    grupo_vehiculo:        '',
    estado_grupo_vehiculo: 'ACTIVO',
})

function abrirModal() {
    nextTick(() => {
        if (!modalInstance) {
            modalInstance = new Modal(modalEl.value)
        }
        modalInstance.show()
    })
}

function abrirCrear() {
    editando.value = null
    form.reset()
    form.clearErrors()
    abrirModal()
}

function abrirEditar(grupo) {
    editando.value = grupo
    form.grupo_vehiculo = grupo.grupo_vehiculo
    form.estado_grupo_vehiculo = grupo.estado_grupo_vehiculo
    form.clearErrors()
    abrirModal()
}

function submit() {
    const opciones = {
        preserveScroll: true,
        onSuccess: () => {
            modalInstance?.hide()
            form.reset()
        },
    }

    if (editando.value) {
        form.put(route('grupos-vehiculo.update', editando.value.id), opciones)
    } else {
        form.post(route('grupos-vehiculo.store'), opciones)
    }
}

function confirmDelete(grupo) {
    if (confirm(`¿Eliminar el grupo de vehículo "${grupo.grupo_vehiculo}"?`)) {
        router.delete(route('grupos-vehiculo.destroy', grupo.id))
    }
}

const estadoBadge = (estado) =>
    estado === 'ACTIVO'
        ? 'bg-success-transparent text-success'
        : 'bg-danger-transparent text-danger'
</script>

<template>
    <Head title="Grupos de Vehículo" />

        <!-- Page header -->
        <div class="d-flex align-items-center justify-content-between page-header-breadcrumb flex-wrap gap-2 mb-4">
            <div>
                <nav>
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item">
                            <Link :href="route('dashboard')">Inicio</Link>
                        </li>
                        <li class="breadcrumb-item active">Grupos de Vehículo</li>
                    </ol>
                </nav>
                <h1 class="page-title fw-medium fs-18 mb-0">Grupos de Vehículo</h1>
            </div>
            <button type="button" class="btn btn-primary btn-wave" @click="abrirCrear">
                <i class="ri-add-line me-1"></i> Nuevo Grupo
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
                        {{ grupos.total }} registros
                    </span>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:60px">#</th>
                                <th>Grupo de Vehículo</th>
                                <th style="width:130px" class="text-center">Tipos asignados</th>
                                <th style="width:140px">Estado</th>
                                <th style="width:120px" class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="grupos.data.length === 0">
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="ri-stack-line fs-3 d-block mb-2"></i>
                                    No se encontraron grupos de vehículo
                                </td>
                            </tr>
                            <tr v-for="(grupo, idx) in grupos.data" :key="grupo.id">
                                <td class="text-muted small">
                                    {{ (grupos.current_page - 1) * grupos.per_page + idx + 1 }}
                                </td>
                                <td class="fw-medium">{{ grupo.grupo_vehiculo }}</td>
                                <td class="text-center">
                                    <span class="badge bg-primary-transparent text-primary">
                                        {{ grupo.tipos_vehiculos_count }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge" :class="estadoBadge(grupo.estado_grupo_vehiculo)">
                                        {{ grupo.estado_grupo_vehiculo }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex gap-1 justify-content-center">
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-icon btn-info-light"
                                            title="Editar"
                                            @click="abrirEditar(grupo)"
                                        >
                                            <i class="ri-edit-line"></i>
                                        </button>
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-icon btn-danger-light"
                                            title="Eliminar"
                                            @click="confirmDelete(grupo)"
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
                    <strong>{{ grupos.from ?? 0 }}</strong> -
                    <strong>{{ grupos.to ?? 0 }}</strong>
                    de <strong>{{ grupos.total }}</strong> resultados
                </div>
                <nav v-if="grupos.last_page > 1" aria-label="Paginación">
                    <ul class="pagination pagination-sm mb-0">
                        <li
                            v-for="link in grupos.links"
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

        <!-- Modal: crear / editar grupo -->
        <div ref="modalEl" class="modal fade" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form @submit.prevent="submit">
                        <div class="modal-header">
                            <h5 class="modal-title fw-medium">
                                {{ editando ? 'Editar Grupo de Vehículo' : 'Nuevo Grupo de Vehículo' }}
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="grupo_vehiculo" class="form-label fw-medium">
                                    Nombre del Grupo <span class="text-danger">*</span>
                                </label>
                                <input
                                    id="grupo_vehiculo"
                                    v-model="form.grupo_vehiculo"
                                    type="text"
                                    class="form-control"
                                    :class="{ 'is-invalid': form.errors.grupo_vehiculo }"
                                    placeholder="Ej: Vehículos Livianos y Transporte de Personal"
                                    maxlength="150"
                                    autofocus
                                />
                                <InputError :message="form.errors.grupo_vehiculo" class="mt-1" />
                            </div>

                            <div>
                                <label class="form-label fw-medium">
                                    Estado <span class="text-danger">*</span>
                                </label>
                                <div class="d-flex gap-4 mt-1">
                                    <div class="form-check">
                                        <input
                                            id="grupo_estado_activo"
                                            v-model="form.estado_grupo_vehiculo"
                                            class="form-check-input"
                                            type="radio"
                                            value="ACTIVO"
                                        />
                                        <label for="grupo_estado_activo" class="form-check-label">
                                            <span class="badge bg-success-transparent text-success">ACTIVO</span>
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input
                                            id="grupo_estado_inactivo"
                                            v-model="form.estado_grupo_vehiculo"
                                            class="form-check-input"
                                            type="radio"
                                            value="INACTIVO"
                                        />
                                        <label for="grupo_estado_inactivo" class="form-check-label">
                                            <span class="badge bg-danger-transparent text-danger">INACTIVO</span>
                                        </label>
                                    </div>
                                </div>
                                <InputError :message="form.errors.estado_grupo_vehiculo" class="mt-1" />
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary btn-wave" data-bs-dismiss="modal">
                                Cancelar
                            </button>
                            <button type="submit" class="btn btn-primary btn-wave" :disabled="form.processing">
                                <span v-if="form.processing" class="spinner-border spinner-border-sm me-1" role="status"></span>
                                <i v-else class="ri-save-line me-1"></i>
                                {{ form.processing ? 'Guardando...' : 'Guardar' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
</template>
