<script setup>
import { computed, ref } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import Maindashboard from '@/Layouts/Maindashboard.vue'
defineOptions({ layout: Maindashboard })
import InputError from '@/Components/InputError.vue'
import { showToast } from '@/Utils/alertUtil.js'

const props = defineProps({
    usuario: Object,
})

const tienePersona = !!props.usuario.persona

const fotoPreview = ref(props.usuario.foto_url)
const fotoInput = ref(null)

const form = useForm({
    _method: 'PATCH',
    name: props.usuario.name,
    email: props.usuario.email,
    // Sólo se pide (y se envía con valor) cuando cambia el correo: es la
    // credencial de acceso, ver ProfileUpdateRequest.
    current_password: '',
    celular: props.usuario.persona?.celular ?? '',
    direccion: props.usuario.persona?.direccion ?? '',
    foto: null,
})

// Mismo criterio que el servidor: sin espacios y sin distinguir mayúsculas.
// Compara contra props.usuario, que se refresca tras guardar.
const correoCambiado = computed(
    () => form.email.trim().toLowerCase() !== (props.usuario.email ?? '').toLowerCase(),
)

function onFotoChange(e) {
    const file = e.target.files[0]
    if (!file) return
    form.foto = file
    const reader = new FileReader()
    reader.onload = (ev) => (fotoPreview.value = ev.target.result)
    reader.readAsDataURL(file)
}

const currentPasswordEmailInput = ref(null)

function submitPerfil() {
    form.transform((data) => ({
        ...data,
        current_password: correoCambiado.value ? data.current_password : '',
    })).post(route('profile.update'), {
        preserveScroll: true,
        onSuccess: () => {
            form.foto = null
            form.reset('current_password')
            form.email = props.usuario.email
        },
        onError: () => {
            if (form.errors.current_password) {
                form.reset('current_password')
                currentPasswordEmailInput.value?.focus()
            }
        },
    })
}

const currentPasswordInput = ref(null)
const passwordInput = ref(null)

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
})

function submitPassword() {
    passwordForm.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => {
            passwordForm.reset()
            showToast('Contraseña actualizada exitosamente')
        },
        onError: () => {
            if (passwordForm.errors.password) {
                passwordForm.reset('password', 'password_confirmation')
                passwordInput.value?.focus()
            }
            if (passwordForm.errors.current_password) {
                passwordForm.reset('current_password')
                currentPasswordInput.value?.focus()
            }
        },
    })
}
</script>

<template>
    <Head title="Mi Perfil" />

    <!-- Breadcrumb -->
    <div class="d-flex align-items-center justify-content-between page-header-breadcrumb flex-wrap gap-2 mb-4">
        <div>
            <nav>
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><Link :href="route('dashboard')">Inicio</Link></li>
                    <li class="breadcrumb-item active">Mi Perfil</li>
                </ol>
            </nav>
            <h1 class="page-title fw-medium fs-18 mb-0">Mi Perfil</h1>
        </div>
    </div>

    <div class="row g-4">
        <!-- Datos personales -->
        <div class="col-12">
            <form @submit.prevent="submitPerfil">
                <div class="card custom-card">
                    <div class="card-header"><div class="card-title"><i class="ri-user-line me-2"></i>Datos Personales</div></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12 col-md-3 d-flex flex-column align-items-center gap-2">
                                <div class="position-relative">
                                    <div class="border rounded-circle overflow-hidden" style="width:120px;height:120px;background:#f8f9fa;">
                                        <img v-if="fotoPreview" :src="fotoPreview" alt="Foto" class="w-100 h-100" style="object-fit:cover;" />
                                        <div v-else class="w-100 h-100 d-flex align-items-center justify-content-center text-muted">
                                            <i class="ri-user-3-line" style="font-size:3rem;"></i>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-icon btn-primary rounded-circle position-absolute"
                                        style="width:34px;height:34px;bottom:0;right:0;" title="Cambiar foto" @click="fotoInput.click()">
                                        <i class="ri-camera-line"></i>
                                    </button>
                                    <input ref="fotoInput" type="file" class="d-none" :class="{ 'is-invalid': form.errors.foto }"
                                        accept="image/jpeg,image/png,image/webp,image/avif" @change="onFotoChange" />
                                </div>
                                <InputError :message="form.errors.foto" class="mt-1" />
                                <small class="text-muted text-center">Clic en la cámara para cambiar la foto</small>
                            </div>

                            <div class="col-12 col-md-9">
                                <div class="row g-3">
                                    <div class="col-sm-6">
                                        <label class="form-label fw-medium">Nombre <span class="text-danger">*</span></label>
                                        <input v-model="form.name" type="text" class="form-control" :class="{ 'is-invalid': form.errors.name }"
                                            maxlength="255" />
                                        <InputError :message="form.errors.name" class="mt-1" />
                                    </div>

                                    <div class="col-sm-6">
                                        <label for="perfil-email" class="form-label fw-medium">Correo electrónico <span class="text-danger">*</span></label>
                                        <input id="perfil-email" v-model="form.email" type="email" class="form-control"
                                            :class="{ 'is-invalid': form.errors.email }" maxlength="255"
                                            autocomplete="email" inputmode="email" />
                                        <InputError :message="form.errors.email" class="mt-1" />
                                        <small v-if="!form.errors.email" class="text-muted">Es el usuario con el que inicias sesión.</small>
                                    </div>

                                    <!-- Sólo al cambiar el correo: confirma la identidad antes de
                                         cambiar la credencial de acceso. -->
                                    <div v-if="correoCambiado" class="col-sm-6">
                                        <label for="perfil-email-password" class="form-label fw-medium">
                                            Contraseña actual <span class="text-danger">*</span>
                                        </label>
                                        <input id="perfil-email-password" ref="currentPasswordEmailInput"
                                            v-model="form.current_password" type="password" class="form-control"
                                            :class="{ 'is-invalid': form.errors.current_password }"
                                            autocomplete="current-password" />
                                        <InputError :message="form.errors.current_password" class="mt-1" />
                                        <small v-if="!form.errors.current_password" class="text-muted">
                                            Para cambiar tu correo confirma tu contraseña.
                                        </small>
                                    </div>

                                    <div class="col-sm-6">
                                        <label class="form-label fw-medium">Celular</label>
                                        <input v-model="form.celular" type="text" class="form-control" :class="{ 'is-invalid': form.errors.celular }"
                                            :disabled="!tienePersona" maxlength="20" />
                                        <InputError :message="form.errors.celular" class="mt-1" />
                                    </div>

                                    <div class="col-sm-6">
                                        <label class="form-label fw-medium">Dirección</label>
                                        <input v-model="form.direccion" type="text" class="form-control" :class="{ 'is-invalid': form.errors.direccion }"
                                            :disabled="!tienePersona" maxlength="250" />
                                        <InputError :message="form.errors.direccion" class="mt-1" />
                                    </div>

                                    <div v-if="!tienePersona" class="col-12">
                                        <small class="text-muted">
                                            Esta cuenta no está vinculada a una persona; el celular y la dirección no se pueden editar aquí.
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary btn-wave" :disabled="form.processing">
                            <span v-if="form.processing" class="spinner-border spinner-border-sm me-1" role="status"></span>
                            <i v-else class="ri-save-line me-1"></i>
                            {{ form.processing ? 'Guardando...' : 'Guardar Cambios' }}
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Cambiar contraseña -->
        <div class="col-12">
            <form @submit.prevent="submitPassword">
                <div class="card custom-card">
                    <div class="card-header"><div class="card-title"><i class="ri-lock-password-line me-2"></i>Cambiar Contraseña</div></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-sm-6 col-xl-4">
                                <label class="form-label fw-medium">Contraseña actual <span class="text-danger">*</span></label>
                                <input ref="currentPasswordInput" v-model="passwordForm.current_password" type="password" class="form-control"
                                    :class="{ 'is-invalid': passwordForm.errors.current_password }" autocomplete="current-password" />
                                <InputError :message="passwordForm.errors.current_password" class="mt-1" />
                            </div>

                            <div class="col-sm-6 col-xl-4">
                                <label class="form-label fw-medium">Nueva contraseña <span class="text-danger">*</span></label>
                                <input ref="passwordInput" v-model="passwordForm.password" type="password" class="form-control"
                                    :class="{ 'is-invalid': passwordForm.errors.password }" autocomplete="new-password" />
                                <InputError :message="passwordForm.errors.password" class="mt-1" />
                            </div>

                            <div class="col-sm-6 col-xl-4">
                                <label class="form-label fw-medium">Confirmar nueva contraseña <span class="text-danger">*</span></label>
                                <input v-model="passwordForm.password_confirmation" type="password" class="form-control"
                                    :class="{ 'is-invalid': passwordForm.errors.password_confirmation }" autocomplete="new-password" />
                                <InputError :message="passwordForm.errors.password_confirmation" class="mt-1" />
                            </div>
                        </div>
                    </div>
                    <div class="card-footer d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary btn-wave" :disabled="passwordForm.processing">
                            <span v-if="passwordForm.processing" class="spinner-border spinner-border-sm me-1" role="status"></span>
                            <i v-else class="ri-save-line me-1"></i>
                            {{ passwordForm.processing ? 'Actualizando...' : 'Actualizar Contraseña' }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</template>
