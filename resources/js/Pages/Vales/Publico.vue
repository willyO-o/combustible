<script setup>
import { computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import { APP_NAME, APP_LONG_NAME } from '@/Data/app'

const props = defineProps({
    vale: Object,
    empresa: Object,
})

// Un solo estado "visual" que resume estado_vale + vencimiento, para no
// repetir esa lógica en cada parte del template.
const estado = computed(() => {
    if (props.vale.estado_vale === 'ANULADO') {
        return {
            clave: 'ANULADO',
            icono: 'ri-close-circle-fill',
            color: 'danger',
            titulo: 'Vale Anulado',
            mensaje: 'Este vale fue anulado y no es válido para ser utilizado.',
        }
    }
    if (props.vale.estado_vale === 'USADO') {
        return {
            clave: 'USADO',
            icono: 'ri-checkbox-circle-fill',
            color: 'info',
            titulo: 'Vale Utilizado',
            mensaje: 'Este vale ya fue utilizado, no puede volver a usarse.',
        }
    }
    if (props.vale.vencido) {
        return {
            clave: 'VENCIDO',
            icono: 'ri-time-line',
            color: 'warning',
            titulo: 'Vale Vencido',
            mensaje: 'La fecha de vencimiento de este vale ya pasó, no puede utilizarse.',
        }
    }
    return {
        clave: 'PENDIENTE',
        icono: 'ri-shield-check-fill',
        color: 'success',
        titulo: 'Vale Válido',
        mensaje: 'Este vale está pendiente de uso y es válido.',
    }
})

const numero = (v) => Number(v ?? 0).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
</script>

<template>
    <Head :title="`Vale #${vale.nro}`" />

    <div class="vale-publico-page">
        <div class="vale-publico-container">
            <!-- Marca de la empresa -->
            <div class="vale-publico-brand">
                <img v-if="empresa?.logo_url" :src="empresa.logo_url" :alt="empresa?.nombre" class="vale-publico-logo" />
                <i v-else class="ri-gas-station-fill vale-publico-logo-fallback"></i>
                <span class="vale-publico-brand-name">{{ empresa?.nombre ?? APP_LONG_NAME }}</span>
            </div>

            <!-- Estado del vale -->
            <div class="card custom-card vale-publico-estado" :class="`estado-${estado.color}`">
                <div class="card-body text-center py-4">
                    <i :class="estado.icono" class="vale-publico-estado-icono"></i>
                    <h3 class="fw-bold mb-1 mt-2">{{ estado.titulo }}</h3>
                    <p class="mb-0">{{ estado.mensaje }}</p>
                </div>
            </div>

            <!-- Datos del vale -->
            <div class="card custom-card mt-3">
                <div class="card-header justify-content-between">
                    <div class="card-title">Vale #{{ vale.nro }}</div>
                    <span class="badge" :class="`bg-${estado.color}-transparent text-${estado.color}`">
                        {{ vale.estado_vale }}
                    </span>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0 vale-publico-datos">
                        <li>
                            <span class="text-muted">Fecha de Emisión</span>
                            <span class="fw-medium">{{ vale.fecha_emision }}</span>
                        </li>
                        <li>
                            <span class="text-muted">Fecha de Vencimiento</span>
                            <span class="fw-medium">{{ vale.fecha_vencimiento }}</span>
                        </li>
                        <li>
                            <span class="text-muted">Tipo de Combustible</span>
                            <span class="badge bg-info-transparent text-info">{{ vale.tipo_combustible ?? 'N/A' }}</span>
                        </li>
                        <li>
                            <span class="text-muted">Litros Autorizados</span>
                            <span class="fw-medium">{{ numero(vale.litros) }} L</span>
                        </li>
                        <li>
                            <span class="text-muted">Precio por Litro</span>
                            <span class="fw-medium">Bs. {{ numero(vale.precio) }}</span>
                        </li>
                        <li>
                            <span class="text-muted">Total</span>
                            <span class="fw-bold">Bs. {{ numero(vale.total) }}</span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Vehículo -->
            <div v-if="vale.vehiculo" class="card custom-card mt-3">
                <div class="card-header">
                    <div class="card-title"><i class="ri-car-line me-1"></i> Vehículo Autorizado</div>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0 vale-publico-datos">
                        <li>
                            <span class="text-muted">Placa</span>
                            <span class="fw-medium">{{ vale.vehiculo.nro_placa ?? 'N/A' }}</span>
                        </li>
                        <li>
                            <span class="text-muted">Marca / Modelo</span>
                            <span class="fw-medium">{{ vale.vehiculo.marca }} {{ vale.vehiculo.modelo ?? '' }}</span>
                        </li>
                        <li v-if="vale.vehiculo.anio">
                            <span class="text-muted">Año</span>
                            <span class="fw-medium">{{ vale.vehiculo.anio }}</span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Conductor -->
            <div v-if="vale.conductor" class="card custom-card mt-3">
                <div class="card-header">
                    <div class="card-title"><i class="ri-user-line me-1"></i> Conductor Autorizado</div>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0 vale-publico-datos">
                        <li>
                            <span class="text-muted">Nombre</span>
                            <span class="fw-medium">{{ vale.conductor.nombre_completo }}</span>
                        </li>
                        <li>
                            <span class="text-muted">C.I.</span>
                            <span class="fw-medium">{{ vale.conductor.ci ?? 'N/A' }}</span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Surtidor autorizado -->
            <div v-if="vale.grifo" class="card custom-card mt-3">
                <div class="card-header">
                    <div class="card-title"><i class="ri-gas-station-line me-1"></i> Surtidor Autorizado</div>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0 vale-publico-datos">
                        <li>
                            <span class="text-muted">Razón Social</span>
                            <span class="fw-medium">{{ vale.grifo.razon_social }}</span>
                        </li>
                        <li v-if="vale.grifo.direccion">
                            <span class="text-muted">Dirección</span>
                            <span class="fw-medium">{{ vale.grifo.direccion }}</span>
                        </li>
                        <li v-if="vale.grifo.ciudad">
                            <span class="text-muted">Ciudad</span>
                            <span class="fw-medium">{{ vale.grifo.ciudad }}</span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Detalle de uso -->
            <div v-if="vale.usado_en" class="card custom-card mt-3 border-info">
                <div class="card-header">
                    <div class="card-title"><i class="ri-checkbox-circle-line me-1"></i> Detalle de Uso</div>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0 vale-publico-datos">
                        <li>
                            <span class="text-muted">Fecha de Carga</span>
                            <span class="fw-medium">{{ vale.usado_en.fecha_carga ?? 'N/A' }}</span>
                        </li>
                        <li>
                            <span class="text-muted">Litros Cargados</span>
                            <span class="fw-medium">{{ numero(vale.usado_en.litros) }} L</span>
                        </li>
                        <li v-if="vale.usado_en.grifo">
                            <span class="text-muted">Surtidor</span>
                            <span class="fw-medium">{{ vale.usado_en.grifo }}</span>
                        </li>
                    </ul>
                </div>
            </div>

            <p class="vale-publico-footer">
                <i class="ri-shield-check-line me-1"></i>
                Verificación pública de vale — {{ APP_NAME }}
            </p>
        </div>
    </div>
</template>

<style scoped>
.vale-publico-page {
    min-height: 100vh;
    display: flex;
    justify-content: center;
    padding: 2.5rem 1rem;
    background: rgb(var(--body-bg-rgb));
}

.vale-publico-container {
    width: 100%;
    max-width: 30rem;
}

.vale-publico-brand {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
}

.vale-publico-logo {
    height: 3.5rem;
    max-width: 12rem;
    object-fit: contain;
}

.vale-publico-logo-fallback {
    font-size: 2.75rem;
    color: var(--primary-color);
}

.vale-publico-brand-name {
    font-weight: 600;
    font-size: 1rem;
    text-align: center;
    color: var(--default-text-color);
}

.vale-publico-estado-icono {
    font-size: 3rem;
}

.estado-success {
    color: rgb(var(--success-rgb));
}

.estado-danger {
    color: rgb(var(--danger-rgb));
}

.estado-warning {
    color: rgb(var(--warning-rgb));
}

.estado-info {
    color: rgb(var(--info-rgb));
}

.vale-publico-datos li {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.4rem 0;
    border-bottom: 1px solid var(--default-border);
}

.vale-publico-datos li:last-child {
    border-bottom: none;
}

.vale-publico-footer {
    text-align: center;
    color: var(--text-muted);
    font-size: 0.75rem;
    margin-top: 1.5rem;
    margin-bottom: 0;
}
</style>
