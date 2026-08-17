<script setup>
import { computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import Maindashboard from '@/Layouts/Maindashboard.vue'
defineOptions({ layout: Maindashboard })
import InputError from '@/Components/InputError.vue'
import SearchSelect from '@/Components/SearchSelect.vue'

const props = defineProps({
    areas: Array,
})

const today = new Date(new Date().getTime() - (new Date().getTimezoneOffset() * 60000)).toISOString().slice(0, 10)

const form = useForm({
    id_persona: null,
    email: '',
    estado_usuario: 'ACTIVO',

    tipo: '',

    estado_conductor: 'ACTIVO',
    id_vehiculo: null,
    fecha_asignacion: today,

    id_area: '',
    tipo_encargo: 'TITULAR',
    fecha_inicio_encargo: today,
    motivo_encargo: '',
})

const esConductor = computed(() => form.tipo === 'conductor')
const esJefeArea = computed(() => form.tipo === 'jefe-area')

function submit() {
    form
        .transform((data) => ({
            ...data,
            id_persona: data.id_persona?.id ?? data.id_persona,
            id_vehiculo: data.id_vehiculo?.id ?? data.id_vehiculo,
        }))
        .post(route('usuarios.store'))
}
</script>

<template>
    <Head title="Nuevo Usuario" />

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
            <!-- Persona -->
            <div class="col-12">
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
                            </div>

                            <div class="col-12">
                                <div class="alert alert-info mb-0 py-2">
                                    <i class="ri-information-line me-1"></i>
                                    La contraseña se generará automáticamente a partir del C.I. (formato: <code>CI#Plusmetals</code>).
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

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

                            <div class="col-sm-6 col-xl-4">
                                <label class="form-label fw-medium">Fecha de Asignación</label>
                                <input v-model="form.fecha_asignacion" type="date" class="form-control" :class="{ 'is-invalid': form.errors.fecha_asignacion }" />
                                <InputError :message="form.errors.fecha_asignacion" class="mt-1" />
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-medium">Vehículo a Asignar</label>
                                <SearchSelect v-model="form.id_vehiculo" :object="true" :search-url="route('search.vehiculos')"
                                    placeholder="Buscar por placa, código o marca (mín. 2 caracteres)..."
                                    :invalid="!!form.errors.id_vehiculo" />
                                <InputError :message="form.errors.id_vehiculo" class="mt-1" />
                                <small class="text-muted">Opcional. Puede dejarse sin asignar y hacerlo luego.</small>
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
</template>
