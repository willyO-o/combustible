<script setup>
import { computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import Maindashboard from '@/Layouts/Maindashboard.vue'
defineOptions({ layout: Maindashboard })
import InputError from '@/Components/InputError.vue'
import SearchSelect from '@/Components/SearchSelect.vue'

const props = defineProps({
    usuario: Object,
    areas: Array,
    vehiculoActual: Object,
    areaActual: Object,
})

const persona = computed(() => props.usuario.persona)

const today = new Date(new Date().getTime() - (new Date().getTimezoneOffset() * 60000)).toISOString().slice(0, 10)

const vehiculoLabel = (v) =>
    v ? `${v.codigo ?? ''} — ${v.nro_placa ?? ''}${v.marca ? ' — ' + v.marca : ''}${v.anio ? ' (' + v.anio + ')' : ''}` : ''

const form = useForm({
    _method: 'PUT',
    email: props.usuario.email,
    estado_usuario: props.usuario.estado_usuario,

    tipo: persona.value?.tipo_actual ?? '',

    estado_conductor: persona.value?.conductor?.estado_conductor ?? 'ACTIVO',
    id_vehiculo: props.vehiculoActual ? { id: props.vehiculoActual.id, label: vehiculoLabel(props.vehiculoActual) } : null,
    fecha_asignacion: today,

    id_area: props.areaActual?.id ?? '',
    tipo_encargo: props.areaActual?.pivot?.tipo_encargo ?? 'TITULAR',
    fecha_inicio_encargo: props.areaActual?.pivot?.fecha_inicio ? props.areaActual.pivot.fecha_inicio.substring(0, 10) : today,
    motivo_encargo: props.areaActual?.pivot?.motivo ?? '',
})

const esConductor = computed(() => form.tipo === 'conductor')
const esJefeArea = computed(() => form.tipo === 'jefe-area')

function submit() {
    form
        .transform((data) => ({
            ...data,
            id_vehiculo: data.id_vehiculo?.id ?? data.id_vehiculo,
        }))
        .post(route('usuarios.update', props.usuario.id))
}
</script>

<template>
    <Head title="Editar Usuario" />

    <!-- Breadcrumb -->
    <div class="d-flex align-items-center justify-content-between page-header-breadcrumb flex-wrap gap-2 mb-4">
        <div>
            <nav>
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><Link :href="route('dashboard')">Inicio</Link></li>
                    <li class="breadcrumb-item"><Link :href="route('usuarios.index')">Usuarios</Link></li>
                    <li class="breadcrumb-item active">Editar</li>
                </ol>
            </nav>
            <h1 class="page-title fw-medium fs-18 mb-0">
                Editar Usuario: <span class="text-primary">{{ usuario.name }}</span>
            </h1>
        </div>
        <div class="d-flex gap-2">
            <Link :href="route('usuarios.edit-password', usuario.id)" class="btn btn-warning btn-wave">
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
                                <small class="text-muted">Un usuario INACTIVO no puede iniciar sesión.</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <template v-if="persona">
                <!-- Tipo de registro -->
                <div class="col-12">
                    <div class="card custom-card">
                        <div class="card-header"><div class="card-title"><i class="ri-shield-user-line me-2"></i>Tipo de Registro</div></div>
                        <div class="card-body">
                            <div class="d-flex flex-wrap gap-4">
                                <div class="form-check">
                                    <input v-model="form.tipo" class="form-check-input" type="radio" value="conductor" id="tipoConductor" />
                                    <label class="form-check-label" for="tipoConductor">Conductor</label>
                                </div>
                                <div class="form-check">
                                    <input v-model="form.tipo" class="form-check-input" type="radio" value="jefe-area" id="tipoJefeArea" />
                                    <label class="form-check-label" for="tipoJefeArea">Jefe de Área</label>
                                </div>
                                <div class="form-check">
                                    <input v-model="form.tipo" class="form-check-input" type="radio" value="personal" id="tipoPersonal" />
                                    <label class="form-check-label" for="tipoPersonal">Personal</label>
                                </div>
                            </div>
                            <InputError :message="form.errors.tipo" class="mt-1" />
                            <small v-if="persona.tipo_actual && persona.tipo_actual !== form.tipo" class="text-warning d-block mt-1">
                                <i class="ri-error-warning-line"></i>
                                Cambiar el tipo cerrará los registros activos del tipo anterior.
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

                                <div class="col-12">
                                    <label class="form-label fw-medium">Vehículo Asignado</label>
                                    <SearchSelect v-model="form.id_vehiculo" :object="true" :search-url="route('search.vehiculos')"
                                        placeholder="Buscar por placa, código o marca (mín. 2 caracteres)..."
                                        :invalid="!!form.errors.id_vehiculo" />
                                    <InputError :message="form.errors.id_vehiculo" class="mt-1" />
                                    <small class="text-muted">
                                        Cambiar el vehículo cierra la asignación actual (queda como REASIGNADO) y crea una nueva.
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
                                    <small class="text-muted">Cambiar el área cierra el encargo actual y crea uno nuevo.</small>
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
                {{ form.processing ? 'Actualizando...' : 'Actualizar Usuario' }}
            </button>
        </div>
    </form>
</template>
