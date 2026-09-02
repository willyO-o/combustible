<script setup>
import { ref } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import Maindashboard from '@/Layouts/Maindashboard.vue'


defineOptions({ layout: Maindashboard })

const form = useForm({
    ci:               '',
    nombres:          '',
    paterno:          '',
    materno:          '',
    foto:             null,
    celular:          '',
    direccion:        '',
    fecha_nacimiento: '',
    estado_conductor: 'ACTIVO',
    documentos:       [],
})

const fotoPreview = ref(null)

function onFotoChange(e) {
    const file = e.target.files[0]
    if (!file) return
    form.foto = file
    const reader = new FileReader()
    reader.onload = (ev) => (fotoPreview.value = ev.target.result)
    reader.readAsDataURL(file)
}

const documentoVacio = () => ({
    tipo_documento:    'LICENCIA_DE_CONDUCIR',
    numero_documento:  '',
    categoria:         '',
    fecha_emision:     '',
    fecha_vencimiento: '',
    archivo:           null,
    estado_documento:  'VIGENTE',
    observacion:       '',
})

function agregarDocumento() {
    form.documentos.push(documentoVacio())
}

function eliminarDocumento(idx) {
    form.documentos.splice(idx, 1)
}

function onDocumentoArchivoChange(e, documento) {
    documento.archivo = e.target.files[0] ?? null
}

function submit() {
    form.post(route('conductores.store'), {
        forceFormData: true,
    })
}
</script>

<template>
    <Head title="Nuevo Operario" />

        <!-- Breadcrumb -->
        <div class="d-flex align-items-center justify-content-between page-header-breadcrumb flex-wrap gap-2 mb-4">
            <div>
                <nav>
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item"><Link :href="route('dashboard')">Inicio</Link></li>
                        <li class="breadcrumb-item"><Link :href="route('conductores.index')">Operarios</Link></li>
                        <li class="breadcrumb-item active">Nuevo</li>
                    </ol>
                </nav>
                <h1 class="page-title fw-medium fs-18 mb-0">Registrar Operario</h1>
            </div>
            <Link :href="route('conductores.index')" class="btn btn-outline-secondary btn-wave">
                <i class="ri-arrow-left-line me-1"></i> Volver
            </Link>
        </div>

        <form @submit.prevent="submit" enctype="multipart/form-data">
            <div class="row g-4">
                <!-- Foto -->
                <div class="col-xl-3">
                    <div class="card custom-card h-100">
                        <div class="card-header"><div class="card-title">Foto del Operario</div></div>
                        <div class="card-body d-flex flex-column align-items-center justify-content-center gap-3">
                            <div
                                class="border rounded-3 overflow-hidden"
                                style="width:150px;height:150px;background:#f8f9fa;"
                            >
                                <img
                                    v-if="fotoPreview"
                                    :src="fotoPreview"
                                    alt="Vista previa"
                                    class="w-100 h-100"
                                    style="object-fit:cover;"
                                />
                                <div v-else class="w-100 h-100 d-flex align-items-center justify-content-center text-muted">
                                    <i class="ri-user-3-line" style="font-size:4rem;"></i>
                                </div>
                            </div>
                            <div class="w-100">
                                <label class="form-label fw-medium">
                                    Seleccionar foto <span class="text-danger">*</span>
                                </label>
                                <input
                                    type="file"
                                    class="form-control"
                                    :class="{ 'is-invalid': form.errors.foto }"
                                    accept="image/jpeg,image/png,image/webp"
                                    @change="onFotoChange"
                                />
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
                                <!-- CI -->
                                <div class="col-sm-6 col-xl-4">
                                    <label class="form-label fw-medium">
                                        Carnet de Identidad <span class="text-danger">*</span>
                                    </label>
                                    <input
                                        v-model="form.ci"
                                        type="text"
                                        class="form-control"
                                        :class="{ 'is-invalid': form.errors.ci }"
                                        placeholder="Ej: 12345678"
                                        maxlength="20"
                                    />
                                    <div v-if="form.errors.ci" class="invalid-feedback">{{ form.errors.ci }}</div>
                                </div>

                                <!-- Nombres -->
                                <div class="col-sm-6 col-xl-8">
                                    <label class="form-label fw-medium">
                                        Nombres <span class="text-danger">*</span>
                                    </label>
                                    <input
                                        v-model="form.nombres"
                                        type="text"
                                        class="form-control"
                                        :class="{ 'is-invalid': form.errors.nombres }"
                                        placeholder="Nombres completos"
                                        maxlength="150"
                                    />
                                    <div v-if="form.errors.nombres" class="invalid-feedback">{{ form.errors.nombres }}</div>
                                </div>

                                <!-- Paterno -->
                                <div class="col-sm-6">
                                    <label class="form-label fw-medium">Apellido Paterno</label>
                                    <input
                                        v-model="form.paterno"
                                        type="text"
                                        class="form-control"
                                        :class="{ 'is-invalid': form.errors.paterno }"
                                        placeholder="Apellido paterno"
                                        maxlength="150"
                                    />
                                    <div v-if="form.errors.paterno" class="invalid-feedback">{{ form.errors.paterno }}</div>
                                </div>

                                <!-- Materno -->
                                <div class="col-sm-6">
                                    <label class="form-label fw-medium">Apellido Materno</label>
                                    <input
                                        v-model="form.materno"
                                        type="text"
                                        class="form-control"
                                        :class="{ 'is-invalid': form.errors.materno }"
                                        placeholder="Apellido materno"
                                        maxlength="150"
                                    />
                                    <div v-if="form.errors.materno" class="invalid-feedback">{{ form.errors.materno }}</div>
                                </div>

                                <!-- Celular -->
                                <div class="col-sm-6 col-xl-4">
                                    <label class="form-label fw-medium">Celular</label>
                                    <input
                                        v-model="form.celular"
                                        type="text"
                                        class="form-control"
                                        :class="{ 'is-invalid': form.errors.celular }"
                                        placeholder="Ej: 70000000"
                                        maxlength="20"
                                    />
                                    <div v-if="form.errors.celular" class="invalid-feedback">{{ form.errors.celular }}</div>
                                </div>

                                <!-- Fecha nacimiento -->
                                <div class="col-sm-6 col-xl-4">
                                    <label class="form-label fw-medium">Fecha de Nacimiento</label>
                                    <input
                                        v-model="form.fecha_nacimiento"
                                        type="date"
                                        class="form-control"
                                        :class="{ 'is-invalid': form.errors.fecha_nacimiento }"
                                    />
                                    <div v-if="form.errors.fecha_nacimiento" class="invalid-feedback">{{ form.errors.fecha_nacimiento }}</div>
                                </div>

                                <!-- Estado -->
                                <div class="col-sm-6 col-xl-4">
                                    <label class="form-label fw-medium">
                                        Estado <span class="text-danger">*</span>
                                    </label>
                                    <select
                                        v-model="form.estado_conductor"
                                        class="form-select"
                                        :class="{ 'is-invalid': form.errors.estado_conductor }"
                                    >
                                        <option value="ACTIVO">ACTIVO</option>
                                        <option value="INACTIVO">INACTIVO</option>
                                        <option value="RETIRADO">RETIRADO</option>
                                    </select>
                                    <div v-if="form.errors.estado_conductor" class="invalid-feedback">{{ form.errors.estado_conductor }}</div>
                                </div>

                                <!-- Dirección -->
                                <div class="col-12">
                                    <label class="form-label fw-medium">Dirección</label>
                                    <textarea
                                        v-model="form.direccion"
                                        class="form-control"
                                        :class="{ 'is-invalid': form.errors.direccion }"
                                        rows="2"
                                        placeholder="Dirección del operario"
                                        maxlength="250"
                                    ></textarea>
                                    <div v-if="form.errors.direccion" class="invalid-feedback">{{ form.errors.direccion }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Documentos del Operario -->
            <div class="card custom-card mt-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div class="card-title">Documentos del Operario (opcional)</div>
                    <button type="button" class="btn btn-sm btn-outline-primary btn-wave" @click="agregarDocumento">
                        <i class="ri-add-line me-1"></i> Agregar Documento
                    </button>
                </div>
                <div v-if="form.documentos.length === 0" class="card-body text-center text-muted py-4">
                    No se registraron documentos adicionales. Son opcionales.
                </div>
                <div v-else class="card-body d-flex flex-column gap-3">
                    <div v-for="(documento, idx) in form.documentos" :key="idx" class="border rounded-3 p-3 position-relative">
                        <button
                            type="button"
                            class="btn btn-sm btn-icon btn-danger-light position-absolute top-0 end-0 m-2"
                            title="Quitar documento"
                            @click="eliminarDocumento(idx)"
                        >
                            <i class="ri-delete-bin-line"></i>
                        </button>
                        <div class="row g-3">
                            <div class="col-sm-6 col-xl-3">
                                <label class="form-label fw-medium">
                                    Tipo de Documento <span class="text-danger">*</span>
                                </label>
                                <select
                                    v-model="documento.tipo_documento"
                                    class="form-select"
                                    :class="{ 'is-invalid': form.errors[`documentos.${idx}.tipo_documento`] }"
                                >
                                    <option value="LICENCIA_DE_CONDUCIR">Licencia de Conducir</option>
                                    <option value="CI">Carnet de Identidad</option>
                                    <option value="CERTIFICADO_MEDICO">Certificado Médico</option>
                                    <option value="OTRO">Otro</option>
                                </select>
                                <div v-if="form.errors[`documentos.${idx}.tipo_documento`]" class="invalid-feedback">
                                    {{ form.errors[`documentos.${idx}.tipo_documento`] }}
                                </div>
                            </div>

                            <div class="col-sm-6 col-xl-3">
                                <label class="form-label fw-medium">Número de Documento</label>
                                <input
                                    v-model="documento.numero_documento"
                                    type="text"
                                    class="form-control"
                                    :class="{ 'is-invalid': form.errors[`documentos.${idx}.numero_documento`] }"
                                    maxlength="100"
                                    placeholder="Ej: LIC-12345"
                                />
                                <div v-if="form.errors[`documentos.${idx}.numero_documento`]" class="invalid-feedback">
                                    {{ form.errors[`documentos.${idx}.numero_documento`] }}
                                </div>
                            </div>

                            <div class="col-sm-6 col-xl-2">
                                <label class="form-label fw-medium">Categoría</label>
                                <input
                                    v-model="documento.categoria"
                                    type="text"
                                    class="form-control"
                                    :class="{ 'is-invalid': form.errors[`documentos.${idx}.categoria`] }"
                                    maxlength="10"
                                    placeholder="Ej: B-2"
                                />
                                <div v-if="form.errors[`documentos.${idx}.categoria`]" class="invalid-feedback">
                                    {{ form.errors[`documentos.${idx}.categoria`] }}
                                </div>
                            </div>

                            <div class="col-sm-6 col-xl-2">
                                <label class="form-label fw-medium">Estado</label>
                                <select
                                    v-model="documento.estado_documento"
                                    class="form-select"
                                    :class="{ 'is-invalid': form.errors[`documentos.${idx}.estado_documento`] }"
                                >
                                    <option value="VIGENTE">VIGENTE</option>
                                    <option value="VENCIDO">VENCIDO</option>
                                    <option value="OBSERVADO">OBSERVADO</option>
                                </select>
                                <div v-if="form.errors[`documentos.${idx}.estado_documento`]" class="invalid-feedback">
                                    {{ form.errors[`documentos.${idx}.estado_documento`] }}
                                </div>
                            </div>

                            <div class="col-sm-6 col-xl-2">
                                <label class="form-label fw-medium">Fecha de Emisión</label>
                                <input
                                    v-model="documento.fecha_emision"
                                    type="date"
                                    class="form-control"
                                    :class="{ 'is-invalid': form.errors[`documentos.${idx}.fecha_emision`] }"
                                />
                                <div v-if="form.errors[`documentos.${idx}.fecha_emision`]" class="invalid-feedback">
                                    {{ form.errors[`documentos.${idx}.fecha_emision`] }}
                                </div>
                            </div>

                            <div class="col-sm-6 col-xl-3">
                                <label class="form-label fw-medium">Fecha de Vencimiento</label>
                                <input
                                    v-model="documento.fecha_vencimiento"
                                    type="date"
                                    class="form-control"
                                    :class="{ 'is-invalid': form.errors[`documentos.${idx}.fecha_vencimiento`] }"
                                />
                                <div v-if="form.errors[`documentos.${idx}.fecha_vencimiento`]" class="invalid-feedback">
                                    {{ form.errors[`documentos.${idx}.fecha_vencimiento`] }}
                                </div>
                            </div>

                            <div class="col-sm-6 col-xl-4">
                                <label class="form-label fw-medium">Archivo</label>
                                <input
                                    type="file"
                                    class="form-control"
                                    :class="{ 'is-invalid': form.errors[`documentos.${idx}.archivo`] }"
                                    accept="image/jpeg,image/png,image/webp,application/pdf"
                                    @change="onDocumentoArchivoChange($event, documento)"
                                />
                                <div v-if="form.errors[`documentos.${idx}.archivo`]" class="invalid-feedback">
                                    {{ form.errors[`documentos.${idx}.archivo`] }}
                                </div>
                                <small class="text-muted">JPG, PNG, WEBP o PDF. Máx 4MB.</small>
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-medium">Observación</label>
                                <textarea
                                    v-model="documento.observacion"
                                    class="form-control"
                                    :class="{ 'is-invalid': form.errors[`documentos.${idx}.observacion`] }"
                                    rows="2"
                                    maxlength="1000"
                                    placeholder="Observaciones sobre el documento..."
                                ></textarea>
                                <div v-if="form.errors[`documentos.${idx}.observacion`]" class="invalid-feedback">
                                    {{ form.errors[`documentos.${idx}.observacion`] }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Botones -->
            <div class="d-flex justify-content-end gap-2 mt-4">
                <Link :href="route('conductores.index')" class="btn btn-outline-secondary btn-wave">
                    Cancelar
                </Link>
                <button
                    type="submit"
                    class="btn btn-primary btn-wave"
                    :disabled="form.processing"
                >
                    <span v-if="form.processing" class="spinner-border spinner-border-sm me-1" role="status"></span>
                    <i v-else class="ri-save-line me-1"></i>
                    {{ form.processing ? 'Guardando...' : 'Guardar Operario' }}
                </button>
            </div>
        </form>
</template>
