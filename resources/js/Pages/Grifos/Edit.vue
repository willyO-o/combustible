<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import Maindashboard from '@/Layouts/Maindashboard.vue'

const props = defineProps({
    grifo: Object,
})

const form = useForm({
    _method:       'PUT',
    razon_social:  props.grifo.razon_social,
    nit:           props.grifo.nit,
    direccion:     props.grifo.direccion  ?? '',
    ciudad:        props.grifo.ciudad     ?? '',
    telefono:      props.grifo.telefono   ?? '',
    estado_grifo:  props.grifo.estado_grifo,
})

function submit() {
    form.post(route('grifos.update', props.grifo.id))
}
</script>

<template>
    <Head title="Editar Surtidor" />

    <Maindashboard>
        <!-- Breadcrumb -->
        <div class="d-flex align-items-center justify-content-between page-header-breadcrumb flex-wrap gap-2 mb-4">
            <div>
                <nav>
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item"><Link :href="route('dashboard')">Inicio</Link></li>
                        <li class="breadcrumb-item"><Link :href="route('grifos.index')">Surtidores</Link></li>
                        <li class="breadcrumb-item active">Editar</li>
                    </ol>
                </nav>
                <h1 class="page-title fw-medium fs-18 mb-0">
                    Editar Surtidor:
                    <span class="text-primary">{{ grifo.razon_social }}</span>
                </h1>
            </div>
            <Link :href="route('grifos.index')" class="btn btn-outline-secondary btn-wave">
                <i class="ri-arrow-left-line me-1"></i> Volver
            </Link>
        </div>

        <form @submit.prevent="submit">
            <div class="card custom-card">
                <div class="card-header">
                    <div class="card-title">Datos del Surtidor</div>
                </div>
                <div class="card-body">
                    <div class="row g-3">

                        <!-- Razón Social -->
                        <div class="col-sm-8">
                            <label class="form-label fw-medium">
                                Razón Social <span class="text-danger">*</span>
                            </label>
                            <input
                                v-model="form.razon_social"
                                type="text"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.razon_social }"
                                placeholder="Nombre o razón social del grifo"
                                maxlength="255"
                            />
                            <div v-if="form.errors.razon_social" class="invalid-feedback">
                                {{ form.errors.razon_social }}
                            </div>
                        </div>

                        <!-- NIT -->
                        <div class="col-sm-4">
                            <label class="form-label fw-medium">
                                NIT <span class="text-danger">*</span>
                            </label>
                            <input
                                v-model="form.nit"
                                type="text"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.nit }"
                                placeholder="Ej: 1234567890"
                                maxlength="30"
                            />
                            <div v-if="form.errors.nit" class="invalid-feedback">
                                {{ form.errors.nit }}
                            </div>
                        </div>

                        <!-- Ciudad -->
                        <div class="col-sm-4">
                            <label class="form-label fw-medium">Ciudad</label>
                            <input
                                v-model="form.ciudad"
                                type="text"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.ciudad }"
                                placeholder="Ej: La Paz"
                                maxlength="100"
                            />
                            <div v-if="form.errors.ciudad" class="invalid-feedback">
                                {{ form.errors.ciudad }}
                            </div>
                        </div>

                        <!-- Teléfono -->
                        <div class="col-sm-4">
                            <label class="form-label fw-medium">Teléfono</label>
                            <input
                                v-model="form.telefono"
                                type="text"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.telefono }"
                                placeholder="Ej: 2123456"
                                maxlength="20"
                            />
                            <div v-if="form.errors.telefono" class="invalid-feedback">
                                {{ form.errors.telefono }}
                            </div>
                        </div>

                        <!-- Estado -->
                        <div class="col-sm-4">
                            <label class="form-label fw-medium">
                                Estado <span class="text-danger">*</span>
                            </label>
                            <select
                                v-model="form.estado_grifo"
                                class="form-select"
                                :class="{ 'is-invalid': form.errors.estado_grifo }"
                            >
                                <option value="ACTIVO">ACTIVO</option>
                                <option value="INACTIVO">INACTIVO</option>
                            </select>
                            <div v-if="form.errors.estado_grifo" class="invalid-feedback">
                                {{ form.errors.estado_grifo }}
                            </div>
                        </div>

                        <!-- Dirección -->
                        <div class="col-12">
                            <label class="form-label fw-medium">Dirección</label>
                            <textarea
                                v-model="form.direccion"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.direccion }"
                                rows="2"
                                placeholder="Dirección del grifo"
                                maxlength="250"
                            ></textarea>
                            <div v-if="form.errors.direccion" class="invalid-feedback">
                                {{ form.errors.direccion }}
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Botones -->
            <div class="d-flex justify-content-end gap-2 mt-4">
                <Link :href="route('grifos.index')" class="btn btn-outline-secondary btn-wave">
                    Cancelar
                </Link>
                <button
                    type="submit"
                    class="btn btn-primary btn-wave"
                    :disabled="form.processing"
                >
                    <span v-if="form.processing" class="spinner-border spinner-border-sm me-1" role="status"></span>
                    <i v-else class="ri-save-line me-1"></i>
                    {{ form.processing ? 'Actualizando...' : 'Actualizar Surtidor' }}
                </button>
            </div>
        </form>
    </Maindashboard>
</template>
