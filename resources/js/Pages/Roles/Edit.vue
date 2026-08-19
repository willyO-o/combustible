<script setup>
import { computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import Maindashboard from '@/Layouts/Maindashboard.vue'
defineOptions({ layout: Maindashboard })

const props = defineProps({
    role:              Object,
    permisosAsignados: Array,
    modulos:           Array,
    esProtegido:       Boolean,
})

const form = useForm({
    permisos: [...props.permisosAsignados],
})

// En un rol protegido, los permisos que ya tenía asignados no se pueden
// quitar desde aquí: quedan marcados y deshabilitados. Sólo se pueden
// agregar permisos nuevos.
function esBase(nombrePermiso) {
    return props.esProtegido && props.permisosAsignados.includes(nombrePermiso)
}

function nombresDelModulo(modulo) {
    return modulo.permisos.map((p) => p.name)
}

function todoSeleccionado(modulo) {
    return nombresDelModulo(modulo).every((name) => form.permisos.includes(name))
}

function alternarModulo(modulo) {
    const nombres = nombresDelModulo(modulo)
    if (todoSeleccionado(modulo)) {
        form.permisos = form.permisos.filter((name) => !nombres.includes(name) || esBase(name))
    } else {
        form.permisos = [...new Set([...form.permisos, ...nombres])]
    }
}

const totalSeleccionados = computed(() => form.permisos.length)

function submit() {
    form.put(route('roles.update', props.role.id))
}
</script>

<template>
    <Head title="Editar Rol" />

        <!-- Breadcrumb -->
        <div class="d-flex align-items-center justify-content-between page-header-breadcrumb flex-wrap gap-2 mb-4">
            <div>
                <nav>
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item"><Link :href="route('dashboard')">Inicio</Link></li>
                        <li class="breadcrumb-item"><Link :href="route('roles.index')">Roles y Permisos</Link></li>
                        <li class="breadcrumb-item active">Editar</li>
                    </ol>
                </nav>
                <h1 class="page-title fw-medium fs-18 mb-0">
                    Permisos del Rol:
                    <span class="text-primary">{{ role.name }}</span>
                    <span v-if="esProtegido" class="badge bg-warning-transparent text-warning ms-2 align-middle">
                        Protegido
                    </span>
                </h1>
            </div>
            <Link :href="route('roles.index')" class="btn btn-outline-secondary btn-wave">
                <i class="ri-arrow-left-line me-1"></i> Volver
            </Link>
        </div>

        <div v-if="esProtegido" class="alert alert-warning-transparent d-flex align-items-start gap-2 mb-4">
            <i class="ri-lock-line fs-20 mt-1"></i>
            <div>
                Este es un rol protegido: sus permisos base (marcados con
                <i class="ri-lock-line"></i>) no se pueden quitar, sólo se le pueden agregar permisos adicionales.
            </div>
        </div>

        <form @submit.prevent="submit">
            <div class="card custom-card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <div class="card-title">Permisos</div>
                    <span class="badge bg-primary-transparent text-primary">
                        {{ totalSeleccionados }} seleccionados
                    </span>
                </div>
                <div v-if="form.errors.permisos" class="alert alert-danger mx-3 mt-3 mb-0 py-2">
                    {{ form.errors.permisos }}
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div v-for="modulo in modulos" :key="modulo.label" class="col-md-6 col-xl-4">
                            <div class="card custom-card border h-100">
                                <div class="card-header py-2 d-flex align-items-center justify-content-between">
                                    <span class="fw-medium fs-13">{{ modulo.label }}</span>
                                    <div class="form-check form-switch mb-0">
                                        <input
                                            type="checkbox"
                                            class="form-check-input"
                                            :checked="todoSeleccionado(modulo)"
                                            @change="alternarModulo(modulo)"
                                            :title="todoSeleccionado(modulo) ? 'Quitar todos' : 'Seleccionar todos'"
                                        />
                                    </div>
                                </div>
                                <div class="card-body py-2">
                                    <div v-for="permiso in modulo.permisos" :key="permiso.name" class="form-check">
                                        <input
                                            :id="`permiso-${permiso.name}`"
                                            v-model="form.permisos"
                                            :value="permiso.name"
                                            type="checkbox"
                                            class="form-check-input"
                                            :disabled="esBase(permiso.name)"
                                        />
                                        <label :for="`permiso-${permiso.name}`" class="form-check-label fs-13">
                                            {{ permiso.label }}
                                            <i v-if="esBase(permiso.name)" class="ri-lock-line text-warning ms-1" title="Permiso base: no se puede quitar"></i>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Botones -->
            <div class="d-flex justify-content-end gap-2 mt-4">
                <Link :href="route('roles.index')" class="btn btn-outline-secondary btn-wave">
                    Cancelar
                </Link>
                <button type="submit" class="btn btn-primary btn-wave" :disabled="form.processing">
                    <span v-if="form.processing" class="spinner-border spinner-border-sm me-1" role="status"></span>
                    <i v-else class="ri-save-line me-1"></i>
                    {{ form.processing ? 'Guardando...' : 'Guardar Permisos' }}
                </button>
            </div>
        </form>
</template>
