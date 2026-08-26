<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import Maindashboard from '@/Layouts/Maindashboard.vue'
defineOptions({ layout: Maindashboard })

const form = useForm({
    nombre_repuesto:       '',
    codigo_repuesto:       '',
    descripcion_repuesto:  '',
    unidad_medida:         'UNIDAD',
    stock_actual:          0,
    estado_repuesto:       'ACTIVO',
})

function submit() {
    form.post(route('repuestos.store'))
}
</script>

<template>
    <Head title="Nuevo Repuesto" />

        <!-- Breadcrumb -->
        <div class="d-flex align-items-center justify-content-between page-header-breadcrumb flex-wrap gap-2 mb-4">
            <div>
                <nav>
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item"><Link :href="route('dashboard')">Inicio</Link></li>
                        <li class="breadcrumb-item"><Link :href="route('repuestos.index')">Repuestos</Link></li>
                        <li class="breadcrumb-item active">Nuevo</li>
                    </ol>
                </nav>
                <h1 class="page-title fw-medium fs-18 mb-0">Registrar Repuesto</h1>
            </div>
            <Link :href="route('repuestos.index')" class="btn btn-outline-secondary btn-wave">
                <i class="ri-arrow-left-line me-1"></i> Volver
            </Link>
        </div>

        <form @submit.prevent="submit">
            <div class="card custom-card">
                <div class="card-header">
                    <div class="card-title">Datos del Repuesto</div>
                </div>
                <div class="card-body">
                    <div class="row g-3">

                        <!-- Nombre -->
                        <div class="col-sm-8">
                            <label class="form-label fw-medium">
                                Nombre <span class="text-danger">*</span>
                            </label>
                            <input
                                v-model="form.nombre_repuesto"
                                type="text"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.nombre_repuesto }"
                                placeholder="Ej: Filtro de aceite"
                                maxlength="200"
                            />
                            <div v-if="form.errors.nombre_repuesto" class="invalid-feedback">
                                {{ form.errors.nombre_repuesto }}
                            </div>
                        </div>

                        <!-- Código -->
                        <div class="col-sm-4">
                            <label class="form-label fw-medium">
                                Código <span class="text-danger">*</span>
                            </label>
                            <input
                                v-model="form.codigo_repuesto"
                                type="text"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.codigo_repuesto }"
                                placeholder="Ej: REP-0001"
                                maxlength="100"
                            />
                            <div v-if="form.errors.codigo_repuesto" class="invalid-feedback">
                                {{ form.errors.codigo_repuesto }}
                            </div>
                        </div>

                        <!-- Unidad de medida -->
                        <div class="col-sm-4">
                            <label class="form-label fw-medium">
                                Unidad de Medida <span class="text-danger">*</span>
                            </label>
                            <select
                                v-model="form.unidad_medida"
                                class="form-select"
                                :class="{ 'is-invalid': form.errors.unidad_medida }"
                            >
                                <option value="UNIDAD">UNIDAD</option>
                                <option value="LITRO">LITRO</option>
                                <option value="KILOGRAMO">KILOGRAMO</option>
                                <option value="METRO">METRO</option>
                                <option value="JUEGO">JUEGO</option>
                                <option value="CAJA">CAJA</option>
                                <option value="BOLSA">BOLSA</option>
                                <option value="PAQUETE">PAQUETE</option>
                                <option value="OTRO">OTRO</option>
                            </select>
                            <div v-if="form.errors.unidad_medida" class="invalid-feedback">
                                {{ form.errors.unidad_medida }}
                            </div>
                        </div>

                        <!-- Stock actual -->
                        <div class="col-sm-4">
                            <label class="form-label fw-medium">
                                Stock Actual <span class="text-danger">*</span>
                            </label>
                            <input
                                v-model="form.stock_actual"
                                type="number"
                                min="0"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.stock_actual }"
                            />
                            <div v-if="form.errors.stock_actual" class="invalid-feedback">
                                {{ form.errors.stock_actual }}
                            </div>
                        </div>

                        <!-- Estado -->
                        <div class="col-sm-4">
                            <label class="form-label fw-medium">
                                Estado <span class="text-danger">*</span>
                            </label>
                            <select
                                v-model="form.estado_repuesto"
                                class="form-select"
                                :class="{ 'is-invalid': form.errors.estado_repuesto }"
                            >
                                <option value="ACTIVO">ACTIVO</option>
                                <option value="INACTIVO">INACTIVO</option>
                                <option value="AGOTADO">AGOTADO</option>
                            </select>
                            <div v-if="form.errors.estado_repuesto" class="invalid-feedback">
                                {{ form.errors.estado_repuesto }}
                            </div>
                        </div>

                        <!-- Descripción -->
                        <div class="col-12">
                            <label class="form-label fw-medium">Descripción</label>
                            <textarea
                                v-model="form.descripcion_repuesto"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.descripcion_repuesto }"
                                rows="2"
                                placeholder="Descripción o detalle adicional del repuesto"
                                maxlength="1000"
                            ></textarea>
                            <div v-if="form.errors.descripcion_repuesto" class="invalid-feedback">
                                {{ form.errors.descripcion_repuesto }}
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Botones -->
            <div class="d-flex justify-content-end gap-2 mt-4">
                <Link :href="route('repuestos.index')" class="btn btn-outline-secondary btn-wave">
                    Cancelar
                </Link>
                <button
                    type="submit"
                    class="btn btn-primary btn-wave"
                    :disabled="form.processing"
                >
                    <span v-if="form.processing" class="spinner-border spinner-border-sm me-1" role="status"></span>
                    <i v-else class="ri-save-line me-1"></i>
                    {{ form.processing ? 'Guardando...' : 'Guardar Repuesto' }}
                </button>
            </div>
        </form>
</template>
