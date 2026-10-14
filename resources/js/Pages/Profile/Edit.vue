<script setup>
import { computed, ref } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import Maindashboard from '@/Layouts/Maindashboard.vue'
defineOptions({ layout: Maindashboard })
import InputError from '@/Components/InputError.vue'
import { showToast } from '@/Utils/alertUtil.js'
import { etiquetaRol, claseRol } from '@/Utils/rolUtil'
import { estadoAsignacionBadge } from '@/Utils/asignacionVehiculoUtil'
import VehiculosAreaTabla from '@/Components/VehiculosAreaTabla.vue'
import HistorialVehiculosAreaTabla from '@/Components/HistorialVehiculosAreaTabla.vue'

const props = defineProps({
    usuario: Object,
    // Sólo lectura (ProfileController::ficha): roles, datos de la persona y
    // lo que implica cada rol (vehículos/licencia, áreas a cargo).
    ficha: {
        type: Object,
        default: () => ({ roles: [], persona: null, conductor: null, areas: [] }),
    },
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

// La foto se guarda con el resto del perfil: mientras no se guarde, se avisa
// junto al avatar y se ofrece guardarla o descartarla ahí mismo.
const fotoPendiente = computed(() => !!form.foto)

function descartarFoto() {
    form.foto = null
    form.clearErrors('foto')
    fotoPreview.value = props.usuario.foto_url
    if (fotoInput.value) fotoInput.value.value = ''
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
            if (fotoInput.value) fotoInput.value.value = ''
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

/* ------------------------------------------------------------------ */
/*  Ficha (sólo lectura)                                               */
/* ------------------------------------------------------------------ */
const persona = computed(() => props.ficha.persona)
const roles = computed(() => props.ficha.roles ?? [])
const tieneRol = (rol) => roles.value.includes(rol)

const nombreVisible = computed(() => persona.value?.nombre_completo || props.usuario.name)

const estadoBadge = (estado) => ({
    ACTIVO: 'bg-success-transparent text-success',
    INACTIVO: 'bg-warning-transparent text-warning',
    RETIRADO: 'bg-danger-transparent text-danger',
}[estado] ?? 'bg-secondary-transparent text-secondary')

// Celular y dirección se muestran con lo ya guardado (se editan más abajo).
const camposPersona = computed(() => {
    const p = persona.value
    if (!p) return []

    return [
        { etiqueta: 'C.I.', valor: p.ci },
        { etiqueta: 'Cargo', valor: p.cargo },
        { etiqueta: 'Nombres', valor: p.nombres },
        { etiqueta: 'Apellido paterno', valor: p.paterno },
        { etiqueta: 'Apellido materno', valor: p.materno },
        {
            etiqueta: 'Fecha de nacimiento',
            valor: p.fecha_nacimiento ? `${p.fecha_nacimiento}${p.edad !== null ? ` (${p.edad} años)` : ''}` : null,
        },
        { etiqueta: 'Celular', valor: props.usuario.persona?.celular },
        { etiqueta: 'Dirección', valor: props.usuario.persona?.direccion, ancho: true },
    ]
})

const mostrarConductor = computed(() => !!props.ficha.conductor || tieneRol('conductor'))

/* ------------------------------------------------------------------ */
/*  Jefe de área: vehículos a cargo e historial (mismas tablas que la  */
/*  ficha del área, Areas/Show.vue)                                    */
/* ------------------------------------------------------------------ */
const areas = computed(() => props.ficha.areas ?? [])
const mostrarJefeArea = computed(() => areas.value.length > 0 || tieneRol('jefe-area'))
const nombresAreas = computed(() => areas.value.map((a) => a.nombre_area).join(', '))

const areaActivaId = ref(areas.value[0]?.id ?? null)
const areaActiva = computed(
    () => areas.value.find((a) => a.id === areaActivaId.value) ?? areas.value[0] ?? null,
)
// 'vehiculos' | 'historial'
const vistaArea = ref('vehiculos')

const totalVehiculosACargo = computed(() =>
    areas.value.reduce((total, area) => total + (area.vehiculos?.length ?? 0), 0),
)
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
        <!-- Identidad: foto, nombre, roles y estado de la cuenta -->
        <div class="col-xl-4">
            <div class="card custom-card h-100">
                <div class="card-body d-flex flex-column align-items-center text-center gap-3">
                    <div class="position-relative">
                        <div class="perfil-avatar border rounded-circle overflow-hidden">
                            <img v-if="fotoPreview" :src="fotoPreview" :alt="`Foto de ${nombreVisible}`" class="w-100 h-100" />
                            <div v-else class="w-100 h-100 d-flex align-items-center justify-content-center text-muted">
                                <i class="ri-user-3-line perfil-avatar__vacio" aria-hidden="true"></i>
                            </div>
                        </div>
                        <button type="button" class="btn btn-icon btn-primary rounded-circle position-absolute perfil-avatar__boton"
                            title="Cambiar foto" aria-label="Cambiar foto" @click="fotoInput.click()">
                            <i class="ri-camera-line" aria-hidden="true"></i>
                        </button>
                        <input ref="fotoInput" type="file" class="d-none" :class="{ 'is-invalid': form.errors.foto }"
                            accept="image/jpeg,image/png,image/webp,image/avif" @change="onFotoChange" />
                    </div>

                    <InputError :message="form.errors.foto" />

                    <div v-if="fotoPendiente" class="perfil-foto-pendiente w-100" role="status">
                        <span class="fs-12"><i class="ri-information-line me-1" aria-hidden="true"></i>Foto nueva sin guardar</span>
                        <div class="d-flex gap-2 justify-content-center">
                            <button type="button" class="btn btn-sm btn-primary btn-wave" :disabled="form.processing" @click="submitPerfil">
                                <span v-if="form.processing" class="spinner-border spinner-border-sm me-1" role="status"></span>
                                {{ form.processing ? 'Guardando...' : 'Guardar foto' }}
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" :disabled="form.processing" @click="descartarFoto">
                                Descartar
                            </button>
                        </div>
                    </div>
                    <small v-else class="text-muted">Clic en la cámara para cambiar la foto</small>

                    <div class="w-100">
                        <h2 class="fs-16 fw-semibold mb-1 text-break">{{ nombreVisible }}</h2>
                        <span v-if="persona?.cargo" class="d-block fs-13 fw-medium perfil-cargo mb-1">
                            <i class="ri-briefcase-line me-1" aria-hidden="true"></i>{{ persona.cargo }}
                        </span>
                        <span class="text-muted fs-13 text-break">{{ usuario.email }}</span>
                    </div>

                    <ul class="list-unstyled d-flex flex-wrap justify-content-center gap-1 mb-0" aria-label="Roles">
                        <li v-for="rol in roles" :key="rol">
                            <span class="badge" :class="claseRol(rol)">{{ etiquetaRol(rol) }}</span>
                        </li>
                        <li v-if="!roles.length">
                            <span class="badge bg-secondary-transparent text-secondary">Personal</span>
                        </li>
                    </ul>

                    <ul class="list-unstyled mb-0 w-100 text-start perfil-datos">
                        <li v-if="areas.length">
                            <span class="text-muted fs-13">Jefe de área de</span>
                            <span class="fw-medium text-end">{{ nombresAreas }}</span>
                        </li>
                        <li v-if="areas.length">
                            <span class="text-muted fs-13">Vehículos a cargo</span>
                            <span class="badge bg-info-transparent text-info perfil-numero">{{ totalVehiculosACargo }}</span>
                        </li>
                        <li>
                            <span class="text-muted fs-13">Estado de la cuenta</span>
                            <span class="badge" :class="estadoBadge(ficha.estado_usuario)">{{ ficha.estado_usuario ?? '—' }}</span>
                        </li>
                        <li>
                            <span class="text-muted fs-13">Usuario desde</span>
                            <span class="fw-medium perfil-numero">{{ ficha.usuario_desde ?? '—' }}</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Datos de la persona y de cada rol (sólo lectura) -->
        <div class="col-xl-8">
            <div class="card custom-card h-100">
                <div class="card-header justify-content-between">
                    <div class="card-title"><i class="ri-profile-line me-2"></i>Datos de la Persona</div>
                    <span v-if="persona" class="badge" :class="estadoBadge(persona.estado_persona)">{{ persona.estado_persona }}</span>
                </div>
                <div class="card-body">
                    <template v-if="persona">
                        <dl class="row g-3 mb-0 perfil-campos">
                            <div v-for="campo in camposPersona" :key="campo.etiqueta"
                                :class="campo.ancho ? 'col-12' : 'col-sm-6 col-lg-4'">
                                <dt>{{ campo.etiqueta }}</dt>
                                <dd :class="{ 'perfil-vacio': !campo.valor }">{{ campo.valor || 'Sin registrar' }}</dd>
                            </div>
                        </dl>
                        <p class="text-muted fs-12 mb-0 mt-3">
                            <i class="ri-information-line me-1" aria-hidden="true"></i>
                            Para corregir tu C.I., nombres o fecha de nacimiento, consulta con un administrador. El celular
                            y la dirección puedes actualizarlos tú mismo más abajo.
                        </p>
                    </template>
                    <div v-else class="perfil-sin-persona text-center">
                        <i class="ri-user-unfollow-line" aria-hidden="true"></i>
                        <p class="fw-medium mb-1">Cuenta sin persona vinculada</p>
                        <p class="text-muted fs-13 mb-0">
                            Es una cuenta de sistema: no tiene datos personales, vehículos ni áreas asociadas.
                        </p>
                    </div>

                    <!-- Conductor -->
                    <section v-if="mostrarConductor" class="perfil-seccion" aria-labelledby="perfil-conductor">
                        <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
                            <h3 id="perfil-conductor" class="fs-14 fw-semibold mb-0">
                                <i class="ri-steering-2-line me-1 text-primary" aria-hidden="true"></i>Como conductor
                            </h3>
                            <span v-if="ficha.conductor" class="badge" :class="estadoBadge(ficha.conductor.estado)">
                                {{ ficha.conductor.estado }}
                            </span>
                        </div>

                        <template v-if="ficha.conductor">
                            <div class="perfil-licencia mb-3">
                                <i class="ri-bank-card-line text-muted" aria-hidden="true"></i>
                                <template v-if="ficha.conductor.licencia">
                                    <span>
                                        Licencia <span class="fw-medium perfil-numero">{{ ficha.conductor.licencia.numero ?? 'S/N' }}</span>
                                        <template v-if="ficha.conductor.licencia.categoria">
                                            · Categoría <span class="fw-medium">{{ ficha.conductor.licencia.categoria }}</span>
                                        </template>
                                        <template v-if="ficha.conductor.licencia.vencimiento">
                                            · Vence <span class="fw-medium perfil-numero">{{ ficha.conductor.licencia.vencimiento }}</span>
                                        </template>
                                    </span>
                                    <span v-if="ficha.conductor.licencia.vencida" class="badge bg-danger-transparent text-danger">Vencida</span>
                                </template>
                                <span v-else class="text-muted">Sin licencia de conducir registrada.</span>
                            </div>

                            <ul v-if="ficha.conductor.vehiculos.length" class="perfil-lista">
                                <li v-for="vehiculo in ficha.conductor.vehiculos" :key="vehiculo.id">
                                    <span class="perfil-lista__icono perfil-lista__icono--conductor" aria-hidden="true">
                                        <i class="ri-truck-line"></i>
                                    </span>
                                    <div class="flex-fill perfil-lista__texto">
                                        <span class="fw-semibold d-block">{{ vehiculo.codigo }} — {{ vehiculo.nro_placa }}</span>
                                        <span class="fs-12 text-muted">
                                            {{ vehiculo.descripcion || 'Vehículo' }}<template v-if="vehiculo.anio"> ({{ vehiculo.anio }})</template>
                                        </span>
                                    </div>
                                    <div class="text-end flex-shrink-0">
                                        <span class="badge" :class="estadoAsignacionBadge(vehiculo.estado_asignacion)">
                                            {{ vehiculo.estado_asignacion }}
                                        </span>
                                        <span v-if="vehiculo.desde" class="d-block fs-12 text-muted mt-1 perfil-numero">desde {{ vehiculo.desde }}</span>
                                    </div>
                                </li>
                            </ul>
                            <p v-else class="text-muted fs-13 mb-0">No tienes un vehículo asignado actualmente.</p>
                        </template>
                        <p v-else class="text-muted fs-13 mb-0">
                            Tu cuenta tiene el rol Conductor, pero aún no tienes un registro de conductor. Consulta con un administrador.
                        </p>
                    </section>

                    <!-- Jefe de área -->
                    <section v-if="mostrarJefeArea" class="perfil-seccion" aria-labelledby="perfil-jefe-area">
                        <h3 id="perfil-jefe-area" class="fs-14 fw-semibold mb-3">
                            <i class="ri-building-2-line me-1 text-info" aria-hidden="true"></i>Como jefe de área
                        </h3>

                        <ul v-if="ficha.areas.length" class="perfil-lista">
                            <li v-for="area in ficha.areas" :key="area.nombre_area">
                                <span class="perfil-lista__icono perfil-lista__icono--area" aria-hidden="true">
                                    <i class="ri-community-line"></i>
                                </span>
                                <div class="flex-fill perfil-lista__texto">
                                    <span class="fw-semibold d-block">{{ area.nombre_area }}</span>
                                    <span class="fs-12 text-muted perfil-numero">
                                        {{ area.vehiculos.length }} {{ area.vehiculos.length === 1 ? 'vehículo asignado' : 'vehículos asignados' }}<template v-if="area.desde"> · A cargo desde {{ area.desde }}</template>
                                    </span>
                                </div>
                                <span v-if="area.tipo_encargo" class="badge bg-info-transparent text-info flex-shrink-0">
                                    {{ area.tipo_encargo }}
                                </span>
                            </li>
                        </ul>
                        <p v-else class="text-muted fs-13 mb-0">
                            Tu cuenta tiene el rol Jefe de Área, pero no tienes un área a cargo actualmente. Consulta con un administrador.
                        </p>
                    </section>
                </div>
            </div>
        </div>

        <!-- Jefe de área: vehículos a cargo e historial de asignaciones -->
        <div v-if="areaActiva" class="col-12">
            <div class="card custom-card overflow-hidden">
                <div class="card-header justify-content-between flex-wrap gap-2">
                    <div class="card-title"><i class="ri-building-2-line me-2"></i>Vehículos a mi Cargo</div>
                    <Link v-can="'areas.ver'" :href="route('areas.show', areaActiva.id)" class="btn btn-sm btn-outline-primary btn-wave">
                        <i class="ri-external-link-line me-1" aria-hidden="true"></i>Ver ficha del área
                    </Link>
                </div>
                <div class="card-body pb-0">
                    <div v-if="areas.length > 1" class="d-flex flex-wrap gap-2 mb-3" role="group" aria-label="Áreas a cargo">
                        <button v-for="area in areas" :key="area.id" type="button" class="btn btn-sm"
                            :class="area.id === areaActiva.id ? 'btn-primary' : 'btn-light'"
                            :aria-pressed="area.id === areaActiva.id" @click="areaActivaId = area.id">
                            {{ area.nombre_area }}
                        </button>
                    </div>
                    <div v-else class="d-flex align-items-center flex-wrap gap-2 mb-3">
                        <span class="fw-semibold">{{ areaActiva.nombre_area }}</span>
                        <span v-if="areaActiva.tipo_encargo" class="badge bg-info-transparent text-info">{{ areaActiva.tipo_encargo }}</span>
                    </div>

                    <ul class="nav nav-tabs perfil-tabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button type="button" class="nav-link" role="tab" :class="{ active: vistaArea === 'vehiculos' }"
                                :aria-selected="vistaArea === 'vehiculos'" @click="vistaArea = 'vehiculos'">
                                <i class="ri-truck-line me-1" aria-hidden="true"></i>Vehículos vigentes
                                <span class="badge bg-primary-transparent text-primary ms-1 perfil-numero">{{ areaActiva.vehiculos.length }}</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button type="button" class="nav-link" role="tab" :class="{ active: vistaArea === 'historial' }"
                                :aria-selected="vistaArea === 'historial'" @click="vistaArea = 'historial'">
                                <i class="ri-history-line me-1" aria-hidden="true"></i>Historial de asignaciones
                                <span class="badge bg-secondary-transparent text-secondary ms-1 perfil-numero">{{ areaActiva.historial.length }}</span>
                            </button>
                        </li>
                    </ul>
                </div>
                <div class="card-body p-0" role="tabpanel">
                    <VehiculosAreaTabla v-if="vistaArea === 'vehiculos'" :vehiculos="areaActiva.vehiculos"
                        mensaje-vacio="Tu área no tiene vehículos asignados actualmente." />
                    <HistorialVehiculosAreaTabla v-else :historial="areaActiva.historial"
                        mensaje-vacio="Tu área todavía no tiene historial de asignaciones de vehículos." />
                </div>
            </div>
        </div>

        <!-- Datos editables -->
        <div class="col-12">
            <form @submit.prevent="submitPerfil">
                <div class="card custom-card">
                    <div class="card-header"><div class="card-title"><i class="ri-edit-line me-2"></i>Editar mis Datos</div></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label for="perfil-nombre" class="form-label fw-medium">Nombre de usuario <span class="text-danger">*</span></label>
                                <input id="perfil-nombre" v-model="form.name" type="text" class="form-control" :class="{ 'is-invalid': form.errors.name }"
                                    maxlength="255" autocomplete="name" />
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
                                <label for="perfil-celular" class="form-label fw-medium">Celular</label>
                                <input id="perfil-celular" v-model="form.celular" type="text" class="form-control" :class="{ 'is-invalid': form.errors.celular }"
                                    :disabled="!tienePersona" maxlength="20" autocomplete="tel" inputmode="tel" />
                                <InputError :message="form.errors.celular" class="mt-1" />
                            </div>

                            <div class="col-sm-6">
                                <label for="perfil-direccion" class="form-label fw-medium">Dirección</label>
                                <input id="perfil-direccion" v-model="form.direccion" type="text" class="form-control" :class="{ 'is-invalid': form.errors.direccion }"
                                    :disabled="!tienePersona" maxlength="250" autocomplete="street-address" />
                                <InputError :message="form.errors.direccion" class="mt-1" />
                            </div>

                            <div v-if="!tienePersona" class="col-12">
                                <small class="text-muted">
                                    Esta cuenta no está vinculada a una persona; el celular y la dirección no se pueden editar aquí.
                                </small>
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
