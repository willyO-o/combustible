<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import Maindashboard from '@/Layouts/Maindashboard.vue'
import InputError from '@/Components/InputError.vue'

const props = defineProps({
    roles: Array,
})

const form = useForm({
    name:                  '',
    email:                 '',
    password:              '',
    password_confirmation: '',
    roles:                 [],
})

function toggleRol(id) {
    const idx = form.roles.indexOf(id)
    idx === -1 ? form.roles.push(id) : form.roles.splice(idx, 1)
}

function submit() {
    form.post(route('usuarios.store'))
}
</script>

<template>
    <Head title="Nuevo Usuario" />

    <Maindashboard>
        <!-- Breadcrumb -->
        <div class="d-flex align-items-center justify-content-between page-header-breadcrumb flex-wrap gap-2 mb-4">
            <div>
                <nav>
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item"><Link :href="route('dashboard')">Inicio</Link></li>
                        <li class="breadcrumb-item"><Link :href="route('usuarios.index')">Usuarios</Link></li>
                        <li class="breadcrumb-item active">Nuevo</li>
                    </ol>
                </nav>
                <h1 class="page-title fw-medium fs-18 mb-0">Registrar Usuario</h1>
            </div>
            <Link :href="route('usuarios.index')" class="btn btn-outline-secondary btn-wave">
                <i class="ri-arrow-left-line me-1"></i> Volver
            </Link>
        </div>

        <form @submit.prevent="submit">
            <div class="row g-4">

                <!-- Datos del usuario -->
                <div class="col-xl-8">
                    <div class="card custom-card">
                        <div class="card-header">
                            <div class="card-title"><i class="ri-user-line me-2"></i>Datos del Usuario</div>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">

                                <div class="col-sm-6">
                                    <label for="name" class="form-label fw-medium">
                                        Nombre completo <span class="text-danger">*</span>
                                    </label>
                                    <input
                                        id="name"
                                        v-model="form.name"
                                        type="text"
                                        class="form-control"
                                        :class="{ 'is-invalid': form.errors.name }"
                                        placeholder="Nombre del usuario"
                                        maxlength="255"
                                        autofocus
                                    />
                                    <InputError :message="form.errors.name" class="mt-1" />
                                </div>

                                <div class="col-sm-6">
                                    <label for="email" class="form-label fw-medium">
                                        Correo electrónico <span class="text-danger">*</span>
                                    </label>
                                    <input
                                        id="email"
                                        v-model="form.email"
                                        type="email"
                                        class="form-control"
                                        :class="{ 'is-invalid': form.errors.email }"
                                        placeholder="correo@ejemplo.com"
                                        maxlength="255"
                                    />
                                    <InputError :message="form.errors.email" class="mt-1" />
                                </div>

                                <div class="col-sm-6">
                                    <label for="password" class="form-label fw-medium">
                                        Contraseña <span class="text-danger">*</span>
                                    </label>
                                    <input
                                        id="password"
                                        v-model="form.password"
                                        type="password"
                                        class="form-control"
                                        :class="{ 'is-invalid': form.errors.password }"
                                        placeholder="Mín. 8 caracteres"
                                    />
                                    <InputError :message="form.errors.password" class="mt-1" />
                                </div>

                                <div class="col-sm-6">
                                    <label for="password_confirmation" class="form-label fw-medium">
                                        Confirmar contraseña <span class="text-danger">*</span>
                                    </label>
                                    <input
                                        id="password_confirmation"
                                        v-model="form.password_confirmation"
                                        type="password"
                                        class="form-control"
                                        :class="{ 'is-invalid': form.errors.password_confirmation }"
                                        placeholder="Repite la contraseña"
                                    />
                                    <InputError :message="form.errors.password_confirmation" class="mt-1" />
                                </div>

                            </div>
                        </div>
                    </div>
                </div>

                <!-- Roles -->
                <div class="col-xl-4">
                    <div class="card custom-card h-100">
                        <div class="card-header">
                            <div class="card-title"><i class="ri-shield-user-line me-2"></i>Roles</div>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small mb-3">Selecciona los roles que tendrá el usuario:</p>

                            <div v-if="roles.length === 0" class="text-muted small">
                                No hay roles disponibles.
                            </div>

                            <div v-for="rol in roles" :key="rol.id" class="form-check mb-2">
                                <input
                                    :id="`rol-${rol.id}`"
                                    type="checkbox"
                                    class="form-check-input"
                                    :value="rol.id"
                                    :checked="form.roles.includes(rol.id)"
                                    @change="toggleRol(rol.id)"
                                />
                                <label :for="`rol-${rol.id}`" class="form-check-label">
                                    <span class="badge bg-primary-transparent text-primary">{{ rol.rol }}</span>
                                </label>
                            </div>

                            <InputError :message="form.errors.roles" class="mt-2" />
                        </div>
                    </div>
                </div>

            </div>

            <!-- Botones -->
            <div class="d-flex justify-content-end gap-2 mt-4">
                <Link :href="route('usuarios.index')" class="btn btn-outline-secondary btn-wave">Cancelar</Link>
                <button type="submit" class="btn btn-primary btn-wave" :disabled="form.processing">
                    <span v-if="form.processing" class="spinner-border spinner-border-sm me-1" role="status"></span>
                    <i v-else class="ri-save-line me-1"></i>
                    {{ form.processing ? 'Guardando...' : 'Guardar Usuario' }}
                </button>
            </div>
        </form>
    </Maindashboard>
</template>
