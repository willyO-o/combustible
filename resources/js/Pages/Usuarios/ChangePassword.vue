<script setup>
import { ref, computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import Maindashboard from '@/Layouts/Maindashboard.vue'
defineOptions({ layout: Maindashboard })
import InputError from '@/Components/InputError.vue'

const props = defineProps({
    usuario: Object,
})

const form = useForm({
    current_password:          '',
    new_password:              '',
    new_password_confirmation: '',
})

const showCurrent = ref(false)
const showNew     = ref(false)
const showConfirm = ref(false)

const passwordStrength = computed(() => {
    const p = form.new_password
    if (!p) return 0
    let score = 0
    if (p.length >= 8)            score++
    if (/[A-Z]/.test(p))          score++
    if (/[0-9]/.test(p))          score++
    if (/[^A-Za-z0-9]/.test(p))  score++
    return score
})
const strengthColor     = computed(() => ['', 'bg-danger', 'bg-warning', 'bg-info', 'bg-success'][passwordStrength.value] ?? 'bg-secondary')
const strengthTextColor = computed(() => ['', 'text-danger', 'text-warning', 'text-info', 'text-success'][passwordStrength.value] ?? 'text-muted')
const strengthLabel     = computed(() => ['', 'Muy débil', 'Débil', 'Aceptable', 'Fuerte'][passwordStrength.value] ?? '')

function submit() {
    form.put(route('usuarios.update-password', props.usuario.id), {
        onSuccess: () => form.reset(),
    })
}
</script>

<template>
    <Head title="Cambiar Contraseña" />

        <!-- Breadcrumb -->
        <div class="d-flex align-items-center justify-content-between page-header-breadcrumb flex-wrap gap-2 mb-4">
            <div>
                <nav>
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item"><Link :href="route('dashboard')">Inicio</Link></li>
                        <li class="breadcrumb-item"><Link :href="route('usuarios.index')">Usuarios</Link></li>
                        <li class="breadcrumb-item">
                            <Link :href="route('usuarios.edit', usuario.id)">{{ usuario.name }}</Link>
                        </li>
                        <li class="breadcrumb-item active">Cambiar contraseña</li>
                    </ol>
                </nav>
                <h1 class="page-title fw-medium fs-18 mb-0">
                    Cambiar contraseña:
                    <span class="text-primary">{{ usuario.name }}</span>
                </h1>
            </div>
            <Link :href="route('usuarios.edit', usuario.id)" class="btn btn-outline-secondary btn-wave">
                <i class="ri-arrow-left-line me-1"></i> Volver
            </Link>
        </div>

        <div class="row justify-content-center">
            <div class="col-xl-5 col-lg-7">

                <!-- Info del usuario -->
                <div class="card custom-card mb-4">
                    <div class="card-body py-3">
                        <div class="d-flex align-items-center gap-3">
                            <span class="avatar avatar-md rounded-circle bg-primary-transparent text-primary fw-semibold d-flex align-items-center justify-content-center fs-18">
                                {{ usuario.name.charAt(0).toUpperCase() }}
                            </span>
                            <div>
                                <div class="fw-semibold">{{ usuario.name }}</div>
                                <div class="text-muted small">{{ usuario.email }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Formulario -->
                <div class="card custom-card">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="ri-lock-password-line me-2"></i>Actualizar Contraseña
                        </div>
                    </div>
                    <div class="card-body">
                        <form @submit.prevent="submit">
                            <div class="row g-4">

                                <!-- Contraseña actual -->
                                <div class="col-12">
                                    <label for="current_password" class="form-label fw-medium">
                                        Contraseña actual <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <input
                                            id="current_password"
                                            v-model="form.current_password"
                                            :type="showCurrent ? 'text' : 'password'"
                                            class="form-control"
                                            :class="{ 'is-invalid': form.errors.current_password }"
                                            placeholder="Contraseña actual"
                                            autocomplete="current-password"
                                        />
                                        <button
                                            type="button"
                                            class="btn btn-outline-secondary"
                                            @click="showCurrent = !showCurrent"
                                            tabindex="-1"
                                        >
                                            <i :class="showCurrent ? 'ri-eye-off-line' : 'ri-eye-line'"></i>
                                        </button>
                                        <div v-if="form.errors.current_password" class="invalid-feedback">
                                            {{ form.errors.current_password }}
                                        </div>
                                    </div>
                                    <InputError :message="form.errors.current_password" class="mt-1" />
                                </div>

                                <!-- Nueva contraseña -->
                                <div class="col-12">
                                    <label for="new_password" class="form-label fw-medium">
                                        Nueva contraseña <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <input
                                            id="new_password"
                                            v-model="form.new_password"
                                            :type="showNew ? 'text' : 'password'"
                                            class="form-control"
                                            :class="{ 'is-invalid': form.errors.new_password }"
                                            placeholder="Mín. 8 caracteres con letras y números"
                                            autocomplete="new-password"
                                        />
                                        <button
                                            type="button"
                                            class="btn btn-outline-secondary"
                                            @click="showNew = !showNew"
                                            tabindex="-1"
                                        >
                                            <i :class="showNew ? 'ri-eye-off-line' : 'ri-eye-line'"></i>
                                        </button>
                                        <div v-if="form.errors.new_password" class="invalid-feedback">
                                            {{ form.errors.new_password }}
                                        </div>
                                    </div>
                                    <InputError :message="form.errors.new_password" class="mt-1" />

                                    <!-- Barra de fortaleza -->
                                    <div v-if="form.new_password" class="mt-2">
                                        <div class="d-flex gap-1 mb-1">
                                            <div v-for="i in 4" :key="i" class="flex-fill rounded" style="height:5px;transition:background .3s;" :class="passwordStrength >= i ? strengthColor : 'bg-light'"></div>
                                        </div>
                                        <small :class="strengthTextColor" class="fw-medium">{{ strengthLabel }}</small>
                                    </div>
                                </div>

                                <!-- Confirmar nueva contraseña -->
                                <div class="col-12">
                                    <label for="new_password_confirmation" class="form-label fw-medium">
                                        Confirmar nueva contraseña <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <input
                                            id="new_password_confirmation"
                                            v-model="form.new_password_confirmation"
                                            :type="showConfirm ? 'text' : 'password'"
                                            class="form-control"
                                            :class="{ 'is-invalid': form.errors.new_password_confirmation }"
                                            placeholder="Repite la nueva contraseña"
                                            autocomplete="new-password"
                                        />
                                        <button
                                            type="button"
                                            class="btn btn-outline-secondary"
                                            @click="showConfirm = !showConfirm"
                                            tabindex="-1"
                                        >
                                            <i :class="showConfirm ? 'ri-eye-off-line' : 'ri-eye-line'"></i>
                                        </button>
                                        <div v-if="form.errors.new_password_confirmation" class="invalid-feedback">
                                            {{ form.errors.new_password_confirmation }}
                                        </div>
                                    </div>
                                    <InputError :message="form.errors.new_password_confirmation" class="mt-1" />

                                    <!-- Match check -->
                                    <div v-if="form.new_password && form.new_password_confirmation" class="mt-1">
                                        <small v-if="form.new_password === form.new_password_confirmation" class="text-success">
                                            <i class="ri-checkbox-circle-line me-1"></i>Las contraseñas coinciden
                                        </small>
                                        <small v-else class="text-danger">
                                            <i class="ri-close-circle-line me-1"></i>Las contraseñas no coinciden
                                        </small>
                                    </div>
                                </div>

                            </div>

                            <!-- Botones -->
                            <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                                <Link :href="route('usuarios.edit', usuario.id)" class="btn btn-outline-secondary btn-wave">
                                    Cancelar
                                </Link>
                                <button
                                    type="submit"
                                    class="btn btn-warning btn-wave"
                                    :disabled="form.processing"
                                >
                                    <span v-if="form.processing" class="spinner-border spinner-border-sm me-1" role="status"></span>
                                    <i v-else class="ri-lock-password-line me-1"></i>
                                    {{ form.processing ? 'Actualizando...' : 'Actualizar Contraseña' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>
</template>
