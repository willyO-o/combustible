<script setup>
import { ref } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import Maindashboard from '@/Layouts/Maindashboard.vue'
defineOptions({ layout: Maindashboard })

const props = defineProps({
    parametrosEmpresa: Object,
})

// Numeración estándar de PHP: 1 = enero ... 12 = diciembre.
const meses = [
    { value: 1, label: 'Enero' },
    { value: 2, label: 'Febrero' },
    { value: 3, label: 'Marzo' },
    { value: 4, label: 'Abril' },
    { value: 5, label: 'Mayo' },
    { value: 6, label: 'Junio' },
    { value: 7, label: 'Julio' },
    { value: 8, label: 'Agosto' },
    { value: 9, label: 'Septiembre' },
    { value: 10, label: 'Octubre' },
    { value: 11, label: 'Noviembre' },
    { value: 12, label: 'Diciembre' },
]

const form = useForm({
    _method: 'PUT',
    nombre_empresa: props.parametrosEmpresa?.nombre_empresa ?? '',
    direccion_empresa: props.parametrosEmpresa?.direccion_empresa ?? '',
    telefono_empresa: props.parametrosEmpresa?.telefono_empresa ?? '',
    correo_empresa: props.parametrosEmpresa?.correo_empresa ?? '',
    nit_empresa: props.parametrosEmpresa?.nit_empresa ?? '',
    logo_empresa: null,
    parametros_vale: {
        tiempo_expiracion: props.parametrosEmpresa?.parametros_vale?.tiempo_expiracion ?? 1,
        // Por defecto diciembre (12): el ciclo contable coincide con el año
        // calendario, sin adelanto de gestión.
        mes_ciclo_contable: props.parametrosEmpresa?.parametros_vale?.mes_ciclo_contable ?? 12,
        // Cantidad de dígitos para el relleno con ceros de las series
        // (nro_vale, nro_orden, nro_solicitud...), ej. 6 = 000003.
        digitos_serie: props.parametrosEmpresa?.parametros_vale?.digitos_serie ?? 6,
    },
    estado: props.parametrosEmpresa?.estado ?? 'ACTIVO',
})

const logoPreview = ref(props.parametrosEmpresa?.logo_empresa ? `/storage/${props.parametrosEmpresa.logo_empresa}` : null)

function onLogoChange(e) {
    const file = e.target.files[0]
    if (!file) return
    form.logo_empresa = file
    const reader = new FileReader()
    reader.onload = (ev) => (logoPreview.value = ev.target.result)
    reader.readAsDataURL(file)
}

function submit() {
    form.post(route('parametros-empresa.update'), {
        forceFormData: true,
        preserveScroll: true,
    })
}
</script>

<template>
    <Head title="Parámetros de la Empresa" />

    <!-- Breadcrumb -->
    <div class="d-flex align-items-center justify-content-between page-header-breadcrumb flex-wrap gap-2 mb-4">
        <div>
            <nav>
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><Link :href="route('dashboard')">Inicio</Link></li>
                    <li class="breadcrumb-item active">Parámetros de la Empresa</li>
                </ol>
            </nav>
            <h1 class="page-title fw-medium fs-18 mb-0">Parámetros de la Empresa</h1>
        </div>
    </div>

    <form @submit.prevent="submit" enctype="multipart/form-data">
        <div class="row g-4">
            <!-- Logo -->
            <div class="col-xl-3">
                <div class="card custom-card h-100">
                    <div class="card-header"><div class="card-title">Logo</div></div>
                    <div class="card-body d-flex flex-column align-items-center justify-content-center gap-3">
                        <div class="border rounded-3 overflow-hidden d-flex align-items-center justify-content-center" style="width:150px;height:150px;background:#f8f9fa;">
                            <img v-if="logoPreview" :src="logoPreview" alt="Logo de la empresa" class="w-100 h-100" style="object-fit:contain;" />
                            <div v-else class="w-100 h-100 d-flex align-items-center justify-content-center text-muted">
                                <i class="ri-building-4-line" style="font-size:4rem;"></i>
                            </div>
                        </div>
                        <div class="w-100">
                            <label class="form-label fw-medium">Cambiar logo</label>
                            <input type="file" class="form-control" :class="{ 'is-invalid': form.errors.logo_empresa }"
                                accept="image/jpeg,image/png,image/webp" @change="onLogoChange" />
                            <div v-if="form.errors.logo_empresa" class="invalid-feedback">{{ form.errors.logo_empresa }}</div>
                            <small class="text-muted">Dejar vacío para conservar el logo actual.</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Datos de la Empresa -->
            <div class="col-xl-9">
                <div class="card custom-card">
                    <div class="card-header"><div class="card-title">Datos de la Empresa</div></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-sm-12">
                                <label class="form-label fw-medium">Nombre de la Empresa <span class="text-danger">*</span></label>
                                <input v-model="form.nombre_empresa" type="text" class="form-control" :class="{ 'is-invalid': form.errors.nombre_empresa }"
                                    placeholder="Razón social de la empresa" maxlength="255" />
                                <div v-if="form.errors.nombre_empresa" class="invalid-feedback">{{ form.errors.nombre_empresa }}</div>
                            </div>

                            <div class="col-sm-4">
                                <label class="form-label fw-medium">NIT <span class="text-danger">*</span></label>
                                <input v-model="form.nit_empresa" type="text" class="form-control" :class="{ 'is-invalid': form.errors.nit_empresa }"
                                    placeholder="Ej: 1234567890" maxlength="30" />
                                <div v-if="form.errors.nit_empresa" class="invalid-feedback">{{ form.errors.nit_empresa }}</div>
                            </div>

                            <div class="col-sm-4">
                                <label class="form-label fw-medium">Correo Electrónico <span class="text-danger">*</span></label>
                                <input v-model="form.correo_empresa" type="email" class="form-control" :class="{ 'is-invalid': form.errors.correo_empresa }"
                                    placeholder="correo@empresa.com" maxlength="255" />
                                <div v-if="form.errors.correo_empresa" class="invalid-feedback">{{ form.errors.correo_empresa }}</div>
                            </div>

                            <div class="col-sm-4">
                                <label class="form-label fw-medium">Teléfono <span class="text-danger">*</span></label>
                                <input v-model="form.telefono_empresa" type="text" class="form-control" :class="{ 'is-invalid': form.errors.telefono_empresa }"
                                    placeholder="Ej: 2123456" maxlength="20" />
                                <div v-if="form.errors.telefono_empresa" class="invalid-feedback">{{ form.errors.telefono_empresa }}</div>
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-medium">Dirección <span class="text-danger">*</span></label>
                                <textarea v-model="form.direccion_empresa" class="form-control" :class="{ 'is-invalid': form.errors.direccion_empresa }"
                                    rows="4" placeholder="Dirección de la empresa" maxlength="3000"></textarea>
                                <div v-if="form.errors.direccion_empresa" class="invalid-feedback">{{ form.errors.direccion_empresa }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Configuración de Vales -->
            <div class="col-12">
                <div class="card custom-card">
                    <div class="card-header"><div class="card-title">Configuración de Vales</div></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-sm-4 col-xl-3">
                                <label class="form-label fw-medium">Tiempo de Expiración del Vale (días) <span class="text-danger">*</span></label>
                                <input v-model.number="form.parametros_vale.tiempo_expiracion" type="text" v-entero="1"
                                    class="form-control" :class="{ 'is-invalid': form.errors['parametros_vale.tiempo_expiracion'] }" />
                                <div v-if="form.errors['parametros_vale.tiempo_expiracion']" class="invalid-feedback">
                                    {{ form.errors['parametros_vale.tiempo_expiracion'] }}
                                </div>
                                <small class="text-muted">Días que un vale permanece válido antes de expirar.</small>
                            </div>

                            <div class="col-sm-6 col-xl-4">
                                <label class="form-label fw-medium">Mes de Inicio del Ciclo Contable <span class="text-danger">*</span></label>
                                <select v-model.number="form.parametros_vale.mes_ciclo_contable" class="form-select"
                                    :class="{ 'is-invalid': form.errors['parametros_vale.mes_ciclo_contable'] }">
                                    <option v-for="mes in meses" :key="mes.value" :value="mes.value">{{ mes.label }}</option>
                                </select>
                                <div v-if="form.errors['parametros_vale.mes_ciclo_contable']" class="invalid-feedback">
                                    {{ form.errors['parametros_vale.mes_ciclo_contable'] }}
                                </div>
                                <small class="text-muted">
                                    A partir de este mes, las series numeradas (vales, órdenes, solicitudes...)
                                    comienzan a corresponder a la siguiente gestión. Ej: si es Noviembre, la
                                    numeración vuelve a 1 desde noviembre en vez de esperar a enero.
                                </small>
                            </div>

                            <div class="col-sm-4 col-xl-3">
                                <label class="form-label fw-medium">Dígitos de la Serie <span class="text-danger">*</span></label>
                                <input v-model.number="form.parametros_vale.digitos_serie" type="text" v-entero="3"
                                    class="form-control" :class="{ 'is-invalid': form.errors['parametros_vale.digitos_serie'] }" />
                                <div v-if="form.errors['parametros_vale.digitos_serie']" class="invalid-feedback">
                                    {{ form.errors['parametros_vale.digitos_serie'] }}
                                </div>
                                <small class="text-muted">
                                    Cantidad de dígitos con la que se rellenan con ceros los números de serie.
                                    Ej: 6 dígitos = 000003.
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Botones -->
        <div class="d-flex justify-content-end gap-2 mt-4">
            <button type="submit" class="btn btn-primary btn-wave" :disabled="form.processing">
                <span v-if="form.processing" class="spinner-border spinner-border-sm me-1" role="status"></span>
                <i v-else class="ri-save-line me-1"></i>
                {{ form.processing ? 'Guardando...' : 'Guardar Parámetros' }}
            </button>
        </div>
    </form>
</template>
