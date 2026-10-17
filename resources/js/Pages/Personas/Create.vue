<script setup>
import { computed, ref } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import Maindashboard from '@/Layouts/Maindashboard.vue'
import Multiselect from '@vueform/multiselect'
import ContrasenaInicialInput from '@/Components/ContrasenaInicialInput.vue'
import CiInput from '@/Components/CiInput.vue'
import RolesSelector from '@/Components/RolesSelector.vue'
import CargoSelect from '@/Components/CargoSelect.vue'
import { etiquetaRol } from '@/Utils/rolUtil'
defineOptions({ layout: Maindashboard })

const props = defineProps({
    areas: Array,
    roles: Array, // [{ id, name }] roles asignables (todos salvo los ocultos)
    cargos: { type: Array, default: () => [] }, // [{ id, cargo, estado_cargo }] Cargo::seleccionables()
    vehiculos: { type: Array, default: () => [] }, // [{ id, label, meta }] vehículos activos precargados
})

// Vehículos activos precargados ({ id, label, meta }). Copias: Multiselect
// reescribe `label` de la opción seleccionada (ver .ai/rules/js.md).
const vehiculosOpt = computed(() => props.vehiculos.map(vehiculo => ({ ...vehiculo })))

const today = new Date(new Date().getTime() - (new Date().getTimezoneOffset() * 60000)).toISOString().slice(0, 10)

const form = useForm({
    ci: '',
    nombres: '',
    paterno: '',
    materno: '',
    foto: null,
    celular: '',
    direccion: '',
    fecha_nacimiento: '',
    estado_persona: 'ACTIVO',

    // Cargo del personal (opcional, independiente de los roles).
    id_cargo: '',

    // Uno o varios roles (nombres). Vacío = "personal".
    roles: [],

    estado_conductor: 'ACTIVO',
    id_vehiculo: null,
    fecha_asignacion: today,

    id_area: '',
    tipo_encargo: 'TITULAR',
    fecha_inicio_encargo: today,
    motivo_encargo: '',

    // Marcada por defecto: lo habitual es registrar a la persona con su
    // cuenta de acceso (se puede desmarcar, p.ej. un operario sin sistema).
    crear_usuario: true,
    email: '',
    estado_usuario: 'ACTIVO',
    // Se prellena con CI#Plusmetals en ContrasenaInicialInput (editable).
    password: '',
})

const esConductor = computed(() => form.roles.includes('conductor'))
const esJefeArea = computed(() => form.roles.includes('jefe-area'))

// Conductor y Jefe de Área crean sus propios registros aunque no haya cuenta;
// el resto de roles sólo existe en la cuenta de usuario (el servidor lo valida).
const ROLES_CON_REGISTRO_PROPIO = ['conductor', 'jefe-area']
const rolesSinUsuario = computed(() =>
    form.crear_usuario
        ? []
        : form.roles.filter((rol) => !ROLES_CON_REGISTRO_PROPIO.includes(rol)).map(etiquetaRol),
)

const fotoPreview = ref(null)

function onFotoChange(e) {
    const file = e.target.files[0]
    if (!file) return
    form.foto = file
    const reader = new FileReader()
    reader.onload = (ev) => (fotoPreview.value = ev.target.result)
    reader.readAsDataURL(file)
}

function submit() {
    form
        .transform((data) => ({
            ...data,
            id_vehiculo: data.id_vehiculo?.id ?? data.id_vehiculo,
        }))
        .post(route('personas.store'), {
            forceFormData: true,
        })
}
</script>

<template>
    <Head title="Nueva Persona" />

    <!-- Breadcrumb -->
    <div class="d-flex align-items-center justify-content-between page-header-breadcrumb flex-wrap gap-2 mb-4">
        <div>
            <nav>
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><Link :href="route('dashboard')">Inicio</Link></li>
                    <li class="breadcrumb-item"><Link :href="route('personas.index')">Personas</Link></li>
                    <li class="breadcrumb-item active">Nueva</li>
                </ol>
            </nav>
            <h1 class="page-title fw-medium fs-18 mb-0">Registrar Persona</h1>
        </div>
        <Link :href="route('personas.index')" class="btn btn-outline-secondary btn-wave">
            <i class="ri-arrow-left-line me-1"></i> Volver
        </Link>
    </div>

    <form @submit.prevent="submit" enctype="multipart/form-data">
        <div class="row g-4">
            <!-- Foto -->
            <div class="col-xl-3">
                <div class="card custom-card h-100">
                    <div class="card-header"><div class="card-title">Foto</div></div>
                    <div class="card-body d-flex flex-column align-items-center justify-content-center gap-3">
                        <div class="border rounded-3 overflow-hidden" style="width:150px;height:150px;background:#f8f9fa;">
                            <img v-if="fotoPreview" :src="fotoPreview" alt="Vista previa" class="w-100 h-100" style="object-fit:cover;" />
                            <div v-else class="w-100 h-100 d-flex align-items-center justify-content-center text-muted">
                                <i class="ri-user-3-line" style="font-size:4rem;"></i>
                            </div>
                        </div>
                        <div class="w-100">
                            <label class="form-label fw-medium">Seleccionar foto</label>
                            <input type="file" class="form-control" :class="{ 'is-invalid': form.errors.foto }"
                                accept="image/jpeg,image/png,image/webp" @change="onFotoChange" />
                            <div v-if="form.errors.foto" class="invalid-feedback">{{ form.errors.foto }}</div>
                            <small class="text-muted">JPG, PNG o WEBP. Máx 2MB.</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Datos personales -->
            <div class="col-xl-9">
                <div class="card custom-card">
                    <div class="card-header"><div class="card-title">Datos Personales</div></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-sm-6 col-xl-4">
                                <CiInput v-model="form.ci" :error="form.errors.ci" />
                            </div>

                            <div class="col-sm-6 col-xl-8">
                                <label class="form-label fw-medium">Nombres <span class="text-danger">*</span></label>
                                <input v-model="form.nombres" type="text" class="form-control" :class="{ 'is-invalid': form.errors.nombres }"
                                    placeholder="Nombres completos" maxlength="150" />
                                <div v-if="form.errors.nombres" class="invalid-feedback">{{ form.errors.nombres }}</div>
                            </div>

                            <div class="col-sm-6">
                                <label class="form-label fw-medium">Apellido Paterno</label>
                                <input v-model="form.paterno" type="text" class="form-control" :class="{ 'is-invalid': form.errors.paterno }"
                                    placeholder="Apellido paterno" maxlength="150" />
                                <div v-if="form.errors.paterno" class="invalid-feedback">{{ form.errors.paterno }}</div>
                            </div>

                            <div class="col-sm-6">
                                <label class="form-label fw-medium">Apellido Materno</label>
                                <input v-model="form.materno" type="text" class="form-control" :class="{ 'is-invalid': form.errors.materno }"
                                    placeholder="Apellido materno" maxlength="150" />
                                <div v-if="form.errors.materno" class="invalid-feedback">{{ form.errors.materno }}</div>
                            </div>

                            <div class="col-sm-6 col-xl-4">
                                <label class="form-label fw-medium">Celular</label>
                                <input v-model="form.celular" type="text" class="form-control" :class="{ 'is-invalid': form.errors.celular }"
                                    placeholder="Ej: 70000000" maxlength="20" />
                                <div v-if="form.errors.celular" class="invalid-feedback">{{ form.errors.celular }}</div>
                            </div>

                            <div class="col-sm-6 col-xl-4">
                                <label class="form-label fw-medium">Fecha de Nacimiento</label>
                                <input v-model="form.fecha_nacimiento" type="date" class="form-control" :class="{ 'is-invalid': form.errors.fecha_nacimiento }" />
                                <div v-if="form.errors.fecha_nacimiento" class="invalid-feedback">{{ form.errors.fecha_nacimiento }}</div>
                            </div>

                            <div class="col-sm-6 col-xl-4">
                                <label class="form-label fw-medium">Estado <span class="text-danger">*</span></label>
                                <select v-model="form.estado_persona" class="form-select" :class="{ 'is-invalid': form.errors.estado_persona }">
                                    <option value="ACTIVO">ACTIVO</option>
                                    <option value="INACTIVO">INACTIVO</option>
                                    <option value="RETIRADO">RETIRADO</option>
                                </select>
                                <div v-if="form.errors.estado_persona" class="invalid-feedback">{{ form.errors.estado_persona }}</div>
                            </div>

                            <div class="col-sm-6 col-xl-4">
                                <CargoSelect v-model="form.id_cargo" :cargos="props.cargos" :error="form.errors.id_cargo" />
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-medium">Dirección</label>
                                <textarea v-model="form.direccion" class="form-control" :class="{ 'is-invalid': form.errors.direccion }"
                                    rows="2" placeholder="Dirección" maxlength="250"></textarea>
                                <div v-if="form.errors.direccion" class="invalid-feedback">{{ form.errors.direccion }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Roles -->
            <div class="col-12">
                <div class="card custom-card">
                    <div class="card-header"><div class="card-title"><i class="ri-shield-user-line me-2"></i>Roles</div></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <RolesSelector v-model="form.roles" :roles="props.roles" :error="form.errors.roles"
                                    :errores="form.errors" />
                                <small class="text-muted d-block mt-1">
                                    Puede seleccionar uno o varios roles. Sin ninguno, la persona queda como
                                    <strong>Personal</strong>. <strong>Conductor</strong> y <strong>Jefe de Área</strong>
                                    habilitan las secciones de abajo; los demás roles requieren crear el usuario de acceso.
                                </small>
                                <small v-if="rolesSinUsuario.length" class="text-warning d-block mt-1">
                                    <i class="ri-error-warning-line"></i>
                                    {{ rolesSinUsuario.join(', ') }}: marque "Crear usuario de acceso al sistema" para asignar
                                    {{ rolesSinUsuario.length === 1 ? 'este rol' : 'estos roles' }}.
                                </small>
                            </div>
                        </div>
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
                                <div v-if="form.errors.estado_conductor" class="invalid-feedback">{{ form.errors.estado_conductor }}</div>
                            </div>

                            <div class="col-sm-6 col-xl-4">
                                <label class="form-label fw-medium">Fecha de Asignación</label>
                                <input v-model="form.fecha_asignacion" type="date" class="form-control" :class="{ 'is-invalid': form.errors.fecha_asignacion }" />
                                <div v-if="form.errors.fecha_asignacion" class="invalid-feedback">{{ form.errors.fecha_asignacion }}</div>
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-medium">Vehículo a Asignar</label>
<Multiselect v-model="form.id_vehiculo" :options="vehiculosOpt" value-prop="id" label="label"
                                    :searchable="true" :filter-results="true"
                                    placeholder="Selecciona o busca por código, placa o marca..."
                                    no-options-text="No hay vehículos activos" no-results-text="Sin resultados"
                                    :class="{ 'is-invalid-multiselect': form.errors.id_vehiculo }" />
                                <div v-if="form.errors.id_vehiculo" class="text-danger small mt-1">{{ form.errors.id_vehiculo }}</div>
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
                                <div v-if="form.errors.id_area" class="invalid-feedback">{{ form.errors.id_area }}</div>
                            </div>

                            <div class="col-sm-6 col-xl-4">
                                <label class="form-label fw-medium">Tipo de Encargo <span class="text-danger">*</span></label>
                                <select v-model="form.tipo_encargo" class="form-select" :class="{ 'is-invalid': form.errors.tipo_encargo }">
                                    <option value="TITULAR">TITULAR</option>
                                    <option value="SUPLENTE">SUPLENTE</option>
                                </select>
                                <div v-if="form.errors.tipo_encargo" class="invalid-feedback">{{ form.errors.tipo_encargo }}</div>
                            </div>

                            <div class="col-sm-6 col-xl-4">
                                <label class="form-label fw-medium">Fecha de Inicio</label>
                                <input v-model="form.fecha_inicio_encargo" type="date" class="form-control" :class="{ 'is-invalid': form.errors.fecha_inicio_encargo }" />
                                <div v-if="form.errors.fecha_inicio_encargo" class="invalid-feedback">{{ form.errors.fecha_inicio_encargo }}</div>
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-medium">Motivo</label>
                                <input v-model="form.motivo_encargo" type="text" class="form-control" :class="{ 'is-invalid': form.errors.motivo_encargo }"
                                    placeholder="Ej: nombramiento, vacaciones..." maxlength="255" />
                                <div v-if="form.errors.motivo_encargo" class="invalid-feedback">{{ form.errors.motivo_encargo }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cuenta de usuario -->
            <div class="col-12">
                <div class="card custom-card">
                    <div class="card-header"><div class="card-title">Cuenta de Usuario</div></div>
                    <div class="card-body">
                        <div class="form-check mb-3">
                            <input v-model="form.crear_usuario" class="form-check-input" type="checkbox" id="crearUsuario" />
                            <label class="form-check-label" for="crearUsuario">
                                Crear usuario de acceso al sistema
                            </label>
                        </div>

                        <div v-if="form.crear_usuario" class="row g-3">
                            <div class="col-sm-6 col-xl-4">
                                <label class="form-label fw-medium">Correo Electrónico <span class="text-danger">*</span></label>
                                <input v-model="form.email" type="email" class="form-control" :class="{ 'is-invalid': form.errors.email }"
                                    placeholder="correo@ejemplo.com" maxlength="255" />
                                <div v-if="form.errors.email" class="invalid-feedback">{{ form.errors.email }}</div>
                            </div>

                            <div class="col-sm-6 col-xl-4">
                                <label class="form-label fw-medium">Estado del Usuario <span class="text-danger">*</span></label>
                                <select v-model="form.estado_usuario" class="form-select" :class="{ 'is-invalid': form.errors.estado_usuario }">
                                    <option value="ACTIVO">ACTIVO</option>
                                    <option value="INACTIVO">INACTIVO</option>
                                </select>
                                <div v-if="form.errors.estado_usuario" class="invalid-feedback">{{ form.errors.estado_usuario }}</div>
                            </div>

                            <div class="col-sm-6 col-xl-4">
                                <ContrasenaInicialInput v-model="form.password" :ci="form.ci" :error="form.errors.password" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Botones -->
        <div class="d-flex justify-content-end gap-2 mt-4">
            <Link :href="route('personas.index')" class="btn btn-outline-secondary btn-wave">
                Cancelar
            </Link>
            <button type="submit" class="btn btn-primary btn-wave" :disabled="form.processing">
                <span v-if="form.processing" class="spinner-border spinner-border-sm me-1" role="status"></span>
                <i v-else class="ri-save-line me-1"></i>
                {{ form.processing ? 'Guardando...' : 'Guardar Persona' }}
            </button>
        </div>
    </form>
</template>
