<script setup>
/**
 * Tabla de vehículos con asignación vigente a un área (forma de
 * ListVehiculosVigentesAreaAction). La usan la ficha del área y el perfil
 * del jefe de área.
 *
 * `permitirCambiarAsignacion` muestra el botón "Cambiar asignación" (sujeto
 * además a v-can vehiculos.asignar-area); el padre abre su propio modal al
 * recibir `cambiar-asignacion`.
 *
 * Uso:
 *   <VehiculosAreaTabla :vehiculos="vehiculos" permitir-cambiar-asignacion
 *       @cambiar-asignacion="abrirCambiarAsignacion" />
 */
import { Link } from '@inertiajs/vue3'
import {
    estadoVehiculoBadge,
    estadoAsignacionBadge,
    nombreVehiculo,
    codigoPlaca,
} from '@/Utils/asignacionVehiculoUtil'

defineProps({
    vehiculos: { type: Array, default: () => [] },
    permitirCambiarAsignacion: { type: Boolean, default: false },
    mensajeVacio: { type: String, default: 'Esta área no tiene vehículos asignados actualmente.' },
})

defineEmits(['cambiar-asignacion'])
</script>

<template>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width: 48px">#</th>
                    <th>Vehículo</th>
                    <th>Tipo</th>
                    <th>Combustible</th>
                    <th>Conductor Actual</th>
                    <th>Asignación</th>
                    <th>Desde</th>
                    <th>Culminación</th>
                    <th class="text-center" style="width: 96px">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(v, index) in vehiculos" :key="v.id">
                    <td class="text-muted">{{ index + 1 }}</td>
                    <td style="min-width: 200px">
                        <div class="d-flex align-items-center gap-2">
                            <span class="avatar avatar-sm rounded d-flex align-items-center justify-content-center"
                                style="background: #f8f9fa">
                                <img v-if="v.url_fotografia" :src="v.url_fotografia" :alt="v.nro_placa"
                                    class="rounded" style="width: 100%; height: 100%; object-fit: cover" />
                                <i v-else class="ri-car-line text-muted"></i>
                            </span>
                            <div>
                                <span class="fw-semibold d-block">{{ nombreVehiculo(v) }}</span>
                                <small class="text-muted d-block">{{ codigoPlaca(v) }}</small>
                                <span class="badge" :class="estadoVehiculoBadge(v.estado_vehiculo)">
                                    {{ v.estado_vehiculo }}
                                </span>
                            </div>
                        </div>
                    </td>
                    <td>{{ v.tipo_vehiculo ?? '—' }}</td>
                    <td>
                        <span class="badge bg-info-transparent text-info">{{ v.tipo_combustible ?? '—' }}</span>
                    </td>
                    <td>
                        <span v-if="v.conductor" class="fw-medium">
                            <i class="ri-user-line me-1"></i>{{ v.conductor.nombre_completo }}
                        </span>
                        <span v-else class="text-muted">Sin conductor</span>
                    </td>
                    <td>
                        <span class="badge" :class="estadoAsignacionBadge(v.estado_asignacion)">
                            {{ v.estado_asignacion }}
                        </span>
                    </td>
                    <td>{{ v.fecha_asignacion ?? '—' }}</td>
                    <td>{{ v.fecha_culminacion ?? 'Indefinida' }}</td>
                    <td>
                        <div class="d-flex gap-1 justify-content-center">
                            <Link :href="route('vehiculos.show', v.id)"
                                class="btn btn-sm btn-icon btn-primary-light" title="Ver vehículo">
                                <i class="ri-eye-line"></i>
                            </Link>
                            <button v-if="permitirCambiarAsignacion" v-can="'vehiculos.asignar-area'" type="button"
                                class="btn btn-sm btn-icon btn-light" title="Cambiar asignación"
                                @click="$emit('cambiar-asignacion', v)">
                                <i class="ri-exchange-line"></i>
                            </button>
                        </div>
                    </td>
                </tr>

                <tr v-if="vehiculos.length === 0">
                    <td colspan="9" class="p-5 text-center text-muted">
                        <i class="ri-car-line fs-3 d-block mb-2"></i>
                        {{ mensajeVacio }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
