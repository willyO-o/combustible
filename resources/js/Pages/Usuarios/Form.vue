<script setup>
import { computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import Maindashboard from '@/Layouts/Maindashboard.vue'
defineOptions({ layout: Maindashboard })
import InputError from '@/Components/InputError.vue'
import SearchSelect from '@/Components/SearchSelect.vue'

const props = defineProps({
    usuario: Object, // presente solo al editar
    areas: Array,
    roles: Array,
    rolesAsignados: Array, // presente solo al editar
    vehiculoActual: Object,
    areaActual: Object,
})

const esEdicion = computed(() => !!props.usuario)
const persona = computed(() => props.usuario?.persona ?? null)

const today = new Date(new Date().getTime() - (new Date().getTimezoneOffset() * 60000)).toISOString().slice(0, 10)

const vehiculoLabel = (v) =>
    v ? `${v.codigo ?? ''} — ${v.nro_placa ?? ''}${v.marca ? ' — ' + v.marca : ''}${v.anio ? ' (' + v.anio + ')' : ''}` : ''

const form = useForm({
    ...(esEdicion.value ? { _method: 'PUT' } : { id_persona: null }),
    email: props.usuario?.email ?? '',
    estado_usuario: props.usuario?.estado_usuario ?? 'ACTIVO',

    roles: esEdicion.value ? [...(props.rolesAsignados ?? [])] : [],

    estado_conductor: persona.value?.conductor?.estado_conductor ?? 'ACTIVO',
    id_vehiculo: props.vehiculoActual ? { id: props.vehiculoActual.id, label: vehiculoLabel(props.vehiculoActual) } : null,
    fecha_asignacion: today,

    id_area: props.areaActual?.id ?? '',
    tipo_encargo: props.areaActual?.pivot?.tipo_encargo ?? 'TITULAR',
    fecha_inicio_encargo: props.areaActual?.pivot?.fecha_inicio ? props.areaActual.pivot.fecha_inicio.substring(0, 10) : today,
    motivo_encargo: props.areaActual?.pivot?.motivo ?? '',
})

// En edición, una cuenta de sistema sin persona vinculada no gestiona roles
// desde aquí (solo sus datos de acceso). En registro siempre hay persona.
const mostrarRoles = computed(() => !esEdicion.value || !!persona.value)

const esConductor = computed(() => form.roles.includes('conductor'))
const esJefeArea = computed(() => form.roles.includes('jefe-area'))

const rolLabel = (rol) => ({
    administrador: 'Administrador',
    conductor: 'Conductor',
    'jefe-area': 'Jefe de Área',
    'tecnico-mantenimiento': 'Técnico de Mantenimiento',
}[rol] ?? rol)

function submit() {
    form
        .transform((data) => ({
            ...data,
            id_persona: data.id_persona?.id ?? data.id_persona,
            id_vehiculo: data.id_vehiculo?.id ?? data.id_vehiculo,
        }))
        .post(esEdicion.value ? route('usuarios.update', props.usuario.id) : route('usuarios.store'))
}
</script>

<template>
    <Head :title="esEdicion ? 'Editar Usuario' : 'Nuevo Usuario'" />

    <!-- Breadcrumb -->
    <div class="d-flex align-items-center justify-content-between page-header-breadcrumb flex-wrap gap-2 mb-4">
        <div>
            <nav>
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><Link :href="route('dashboard')">Inicio</Link></li>
                    <li class="breadcrumb-item"><Link :href="route('usuarios.index')">Usuarios</Link></li>
                    <li class="breadcrumb-item active">{{ esEdicion ? 'Editar' : 'Nuevo' }}</li>
                </ol>
            </nav>
            <h1 class="page-title fw-medium fs-18 mb-0">
                <template v-if="esEdicion">Editar Usuario: <span class="text-primary">{{ usuario.name }}</span></template>
                <template v-else>Registrar Usuario</template>
            </h1>
        </div>
        <div class="d-flex gap-2">
            <Link v-if="esEdicion" :href="route('usuarios.edit-password', usuario.id)" class="btn btn-warning btn-wave">
                <i class="ri-lock-password-line me-1"></i> Cambiar contraseña
            </Link>
            <Link :href="route('usuarios.index')" class="btn btn-outline-secondary btn-wave">
                <i class="ri-arrow-left-line me-1"></i> Volver
            </Link>
        </div>
    </div>

    <form @submit.prevent="submit">
        <div class="row g-4">
            <!-- Persona -->
            <div class="col-12" v-if="!esEdicion">
                <div class="card custom-card">
                    <div class="card-header"><div class="card-title"><i class="ri-user-line me-2"></i>Persona</div></div>
                    <div class="card-body">
                        <label class="form-label fw-medium">Persona <span class="text-danger">*</span></label>
                        <SearchSelect v-model="form.id_persona" :object="true" :search-url="route('search.personas-sin-usuario')"
                            placeholder="Buscar por CI o nombre (mín. 2 caracteres)..." :invalid="!!form.errors.id_persona" />
                        <InputError :message="form.errors.id_persona" class="mt-1" />
                        <small class="text-muted">
                            Solo aparecen personas que aún no tienen un usuario de acceso. Si la persona no existe,
                            regístrala primero en el módulo de
                            <Link :href="route('personas.create')">Personas</Link>.
                        </small>
                    </div>
                </div>
            </div>
            <template v-else>
                <div v-if="persona" class="col-12">
                    <div class="card custom-card">
                        <div class="card-header"><div class="card-title"><i class="ri-user-line me-2"></i>Persona</div></div>
                        <div class="card-body">
                            <p class="mb-0">
                                <strong>{{ persona.nombre_completo }}</strong>
                                <span class="text-muted"> — CI: {{ persona.ci }}</span>
                            </p>
                            <small class="text-muted">
                                Los datos personales se editan desde el módulo de
                                <Link :href="route('personas.edit', persona.id)">Personas</Link>.
                            </small>
                        </div>
                    </div>
                </div>
                <div v-else class="col-12">
                    <div class="alert alert-warning mb-0">
                        <i class="ri-error-warning-line me-1"></i>
                        Esta cuenta no está vinculada a ninguna persona (cuenta de sistema). Solo se pueden editar sus
                        datos de acceso.
                    </div>
                </div>
            </template>

            <!-- Datos del usuario -->
            <div class="col-12">
                <div class="card custom-card">
                    <div class="card-header"><div class="card-title"><i class="ri-mail-line me-2"></i>Datos de Acceso</div></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-sm-6 col-xl-4">
                                <label class="form-label fw-medium">Correo electrónico <span class="text-danger">*</span></label>
                                <input v-model="form.email" type="email" class="form-control" :class="{ 'is-invalid': form.errors.email }"
                                    placeholder="correo@ejemplo.com" maxlength="255" />
                                <InputError :message="form.errors.email" class="mt-1" />
                            </div>

                            <div class="col-sm-6 col-xl-4">
                                <label class="form-label fw-medium">Estado <span class="text-danger">*</span></label>
                                <select v-model="form.estado_usuario" class="form-select" :class="{ 'is-invalid': form.errors.estado_usuario }">
                                    <option value="ACTIVO">ACTIVO</option>
                                    <option value="INACTIVO">INACTIVO</option>
                                </select>
                                <InputError :message="form.errors.estado_usuario" class="mt-1" />
                                <small v-if="esEdicion" class="text-muted">Un usuario INACTIVO no puede iniciar sesión.</small>
                            </div>

                            <div v-if="!esEdicion" class="col-12">
                                <div class="alert alert-info mb-0 py-2">
                                    <i class="ri-information-line me-1"></i>
                                    La contraseña se generará automáticamente a partir del C.I. (formato: <code>CI#Plusmetals</code>).
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <template v-if="mostrarRoles">
                <!-- Roles -->
                <div class="col-12">
                    <div class="card custom-card">
                        <div class="card-header"><div class="card-title"><i class="ri-shield-user-line me-2"></i>Roles</div></div>
                        <div class="card-body">
                            <div class="d-flex flex-wrap gap-4">
                                <div class="form-check" v-for="rol in props.roles" :key="rol.id">
                                    <input v-model="form.roles" class="form-check-input" type="checkbox" :value="rol.name" :id="`rol-${rol.id}`" />
                                    <label class="form-check-label" :for="`rol-${rol.id}`">{{ rolLabel(rol.name) }}</label>
                                </div>
                            </div>
                            <InputError :message="form.errors.roles" class="mt-1" />
                            <small class="text-muted d-block mt-1">
                                Puede seleccionar varios roles. Sin ninguno seleccionado, el usuario queda como
                                personal (con acceso, sin rol operativo). Los roles <strong>Conductor</strong> y
                                <strong>Jefe de Área</strong> habilitan las secciones adicionales de abajo.
                            </small>
                            <small v-if="esEdicion && (esConductor !== props.rolesAsignados.includes('conductor') || esJefeArea !== props.rolesAsignados.includes('jefe-area'))"
                                class="text-warning d-block mt-1">
                                <i class="ri-error-warning-line"></i>
                                Quitar el rol Conductor o Jefe de Área cerrará sus registros activos (asignación de
                                vehículo o encargo de área).
                            </small>
                        </div>
                    </div>
                </div>

                <!-- Datos de Conductor -->
                <div v-if="esConductor" class="col-12">
                    <div class="card custom-card">
                        <div class="card-header"><div class="card-title">Datos de Conductor</div></div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-sm-6 col-xl-4">
                                    <label class="form-label fw-medium">Estado del Conductor <span class="text-danger">*</span></label>
                                    <select v-model="form.estado_conductor" class="form-select" :class="{ 'is-invalid': form.errors.estado_conductor }">
                                        <option value="ACTIVO">ACTIVO</option>
                                        <option value="INACTIVO">INACTIVO</option>
                                        <option value="RETIRADO">RETIRADO</option>
                                    </select>
                                    <InputError :message="form.errors.estado_conductor" class="mt-1" />
                                </div>

                                <div class="col-sm-6 col-xl-4" v-if="!esEdicion">
                                    <label class="form-label fw-medium">Fecha de Asignación</label>
                                    <input v-model="form.fecha_asignacion" type="date" class="form-control" :class="{ 'is-invalid': form.errors.fecha_asignacion }" />
                                    <InputError :message="form.errors.fecha_asignacion" class="mt-1" />
                                </div>

                                <div class="col-12">
                                    <label class="form-label fw-medium">{{ esEdicion ? 'Vehículo Asignado' : 'Vehículo a Asignar' }}</label>
                                    <SearchSelect v-model="form.id_vehiculo" :object="true" :search-url="route('search.vehiculos')"
                                        placeholder="Buscar por placa, código o marca (mín. 2 caracteres)..."
                                        :invalid="!!form.errors.id_vehiculo" />
                                    <InputError :message="form.errors.id_vehiculo" class="mt-1" />
                                    <small class="text-muted">
                                        <template v-if="esEdicion">
                                            Cambiar el vehículo cierra la asignación actual (queda como REASIGNADO) y crea una nueva.
                                        </template>
                                        <template v-else>Opcional. Puede dejarse sin asignar y hacerlo luego.</template>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Datos de Jefe de Área -->
                <div v-if="esJefeArea" class="col-12">
                    <div class="card custom-card">
                        <div class="card-header"><div class="card-title">Datos de Jefe de Área</div></div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-sm-6 col-xl-4">
                                    <label class="form-label fw-medium">Área <span class="text-danger">*</span></label>
                                    <select v-model="form.id_area" class="form-select" :class="{ 'is-invalid': form.errors.id_area }">
                                        <option value="">— Seleccionar —</option>
                                        <option v-for="area in props.areas" :key="area.id" :value="area.id">{{ area.nombre_area }}</option>
                                    </select>
                                    <InputError :message="form.errors.id_area" class="mt-1" />
                                    <small v-if="esEdicion" class="text-muted">Cambiar el área cierra el encargo actual y crea uno nuevo.</small>
                                </div>

                                <div class="col-sm-6 col-xl-4">
                                    <label class="form-label fw-medium">Tipo de Encargo <span class="text-danger">*</span></label>
                                    <select v-model="form.tipo_encargo" class="form-select" :class="{ 'is-invalid': form.errors.tipo_encargo }">
                                        <option value="TITULAR">TITULAR</option>
                                        <option value="SUPLENTE">SUPLENTE</option>
                                    </select>
                                    <InputError :message="form.errors.tipo_encargo" class="mt-1" />
                                </div>

                                <div class="col-sm-6 col-xl-4">
                                    <label class="form-label fw-medium">Fecha de Inicio</label>
                                    <input v-model="form.fecha_inicio_encargo" type="date" class="form-control" :class="{ 'is-invalid': form.errors.fecha_inicio_encargo }" />
                                    <InputError :message="form.errors.fecha_inicio_encargo" class="mt-1" />
                                </div>

                                <div class="col-12">
                                    <label class="form-label fw-medium">Motivo</label>
                                    <input v-model="form.motivo_encargo" type="text" class="form-control" :class="{ 'is-invalid': form.errors.motivo_encargo }"
                                        placeholder="Ej: nombramiento, vacaciones..." maxlength="255" />
                                    <InputError :message="form.errors.motivo_encargo" class="mt-1" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <!-- Botones -->
        <div class="d-flex justify-content-end gap-2 mt-4">
            <Link :href="route('usuarios.index')" class="btn btn-outline-secondary btn-wave">Cancelar</Link>
            <button type="submit" class="btn btn-primary btn-wave" :disabled="form.processing">
                <span v-if="form.processing" class="spinner-border spinner-border-sm me-1" role="status"></span>
                <i v-else class="ri-save-line me-1"></i>
                {{ form.processing ? (esEdicion ? 'Actualizando...' : 'Guardando...') : (esEdicion ? 'Actualizar Usuario' : 'Guardar Usuario') }}
            </button>
        </div>
    </form>
</template>
