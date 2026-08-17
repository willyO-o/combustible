<script setup>
import { computed, ref } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import Maindashboard from '@/Layouts/Maindashboard.vue'
import SearchSelect from '@/Components/SearchSelect.vue'
defineOptions({ layout: Maindashboard })

const props = defineProps({
    areas: Array,
})

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

    tipo: '',

    estado_conductor: 'ACTIVO',
    id_vehiculo: null,
    fecha_asignacion: today,

    id_area: '',
    tipo_encargo: 'TITULAR',
    fecha_inicio_encargo: today,
    motivo_encargo: '',

    crear_usuario: false,
    email: '',
    estado_usuario: 'ACTIVO',
})

const esConductor = computed(() => form.tipo === 'conductor')
const esJefeArea = computed(() => form.tipo === 'jefe-area')

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
                                <label class="form-label fw-medium">Carnet de Identidad <span class="text-danger">*</span></label>
                                <input v-model="form.ci" type="text" class="form-control" :class="{ 'is-invalid': form.errors.ci }"
                                    placeholder="Ej: 12345678" maxlength="20" />
                                <div v-if="form.errors.ci" class="invalid-feedback">{{ form.errors.ci }}</div>
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

            <!-- Tipo de registro -->
            <div class="col-12">
                <div class="card custom-card">
                    <div class="card-header"><div class="card-title">Tipo de Registro</div></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12">
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
                                <div v-if="form.errors.tipo" class="text-danger small mt-1">{{ form.errors.tipo }}</div>
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
                                <SearchSelect v-model="form.id_vehiculo" :object="true" :search-url="route('search.vehiculos')"
                                    placeholder="Buscar por placa, código o marca (mín. 2 caracteres)..."
                                    :invalid="!!form.errors.id_vehiculo" />
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
