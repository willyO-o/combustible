<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import Maindashboard from '@/Layouts/Maindashboard.vue'
import InputError from '@/Components/InputError.vue'

const form = useForm({
    tipo_combustible:        '',
    estado_tipo_combustible: 'ACTIVO',
})

function submit() {
    form.post(route('tipos-combustible.store'))
}
</script>

<template>
    <Head title="Nuevo Tipo de Combustible" />

    <Maindashboard>
        <!-- Breadcrumb -->
        <div class="d-flex align-items-center justify-content-between page-header-breadcrumb flex-wrap gap-2 mb-4">
            <div>
                <nav>
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item">
                            <Link :href="route('dashboard')">Inicio</Link>
                        </li>
                        <li class="breadcrumb-item">
                            <Link :href="route('tipos-combustible.index')">Tipos de Combustible</Link>
                        </li>
                        <li class="breadcrumb-item active">Nuevo</li>
                    </ol>
                </nav>
                <h1 class="page-title fw-medium fs-18 mb-0">Registrar Tipo de Combustible</h1>
            </div>
            <Link :href="route('tipos-combustible.index')" class="btn btn-outline-secondary btn-wave">
                <i class="ri-arrow-left-line me-1"></i> Volver
            </Link>
        </div>

        <div class="row justify-content-center">
            <div class="col-xl-6 col-lg-8">
                <div class="card custom-card">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="ri-drop-line me-2"></i>Datos del Tipo de Combustible
                        </div>
                    </div>
                    <div class="card-body">
                        <form @submit.prevent="submit">
                            <div class="row g-4">

                                <!-- Tipo de combustible -->
                                <div class="col-12">
                                    <label for="tipo_combustible" class="form-label fw-medium">
                                        Tipo de Combustible
                                        <span class="text-danger">*</span>
                                    </label>
                                    <input
                                        id="tipo_combustible"
                                        v-model="form.tipo_combustible"
                                        type="text"
                                        class="form-control"
                                        :class="{ 'is-invalid': form.errors.tipo_combustible }"
                                        placeholder="Ej: Gasolina, Diesel, GNV..."
                                        maxlength="100"
                                        autofocus
                                    />
                                    <InputError :message="form.errors.tipo_combustible" class="mt-1" />
                                </div>

                                <!-- Estado -->
                                <div class="col-12">
                                    <label for="estado_tipo_combustible" class="form-label fw-medium">
                                        Estado
                                        <span class="text-danger">*</span>
                                    </label>
                                    <div class="d-flex gap-4 mt-1">
                                        <div class="form-check">
                                            <input
                                                id="estado_activo"
                                                v-model="form.estado_tipo_combustible"
                                                class="form-check-input"
                                                type="radio"
                                                value="ACTIVO"
                                            />
                                            <label for="estado_activo" class="form-check-label">
                                                <span class="badge bg-success-transparent text-success">ACTIVO</span>
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input
                                                id="estado_inactivo"
                                                v-model="form.estado_tipo_combustible"
                                                class="form-check-input"
                                                type="radio"
                                                value="INACTIVO"
                                            />
                                            <label for="estado_inactivo" class="form-check-label">
                                                <span class="badge bg-danger-transparent text-danger">INACTIVO</span>
                                            </label>
                                        </div>
                                    </div>
                                    <InputError :message="form.errors.estado_tipo_combustible" class="mt-1" />
                                </div>

                            </div>

                            <!-- Botones -->
                            <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                                <Link
                                    :href="route('tipos-combustible.index')"
                                    class="btn btn-outline-secondary btn-wave"
                                >
                                    Cancelar
                                </Link>
                                <button
                                    type="submit"
                                    class="btn btn-primary btn-wave"
                                    :disabled="form.processing"
                                >
                                    <span
                                        v-if="form.processing"
                                        class="spinner-border spinner-border-sm me-1"
                                        role="status"
                                    ></span>
                                    <i v-else class="ri-save-line me-1"></i>
                                    {{ form.processing ? 'Guardando...' : 'Guardar' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </Maindashboard>
</template>
