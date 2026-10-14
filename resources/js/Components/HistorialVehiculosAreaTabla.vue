<script setup>
/**
 * Historial completo de asignaciones de vehículos a un área (forma de
 * ListHistorialVehiculosAreaAction, incluye REASIGNADO/CULMINADO). La usan
 * la ficha del área y el perfil del jefe de área.
 *
 * Uso:
 *   <HistorialVehiculosAreaTabla :historial="historialVehiculos" />
 */
import { estadoAsignacionBadge, nombreVehiculo, codigoPlaca } from '@/Utils/asignacionVehiculoUtil'

defineProps({
    historial: { type: Array, default: () => [] },
    mensajeVacio: { type: String, default: 'No hay historial de asignaciones de vehículos para esta área.' },
})
</script>

<template>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width: 48px">#</th>
                    <th>Vehículo</th>
                    <th>Estado</th>
                    <th>Asignación</th>
                    <th>Reasignación</th>
                    <th>Culminación</th>
                    <th>Motivo</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(h, index) in historial" :key="h.id">
                    <td class="text-muted">{{ index + 1 }}</td>
                    <td style="min-width: 180px">
                        <span class="fw-semibold d-block">{{ nombreVehiculo(h) }}</span>
                        <small class="text-muted">{{ codigoPlaca(h) }}</small>
                    </td>
                    <td>
                        <span class="badge" :class="estadoAsignacionBadge(h.estado_asignacion)">
                            {{ h.estado_asignacion }}
                        </span>
                    </td>
                    <td>{{ h.fecha_asignacion ?? '—' }}</td>
                    <td>{{ h.fecha_reasignacion ?? '—' }}</td>
                    <td>{{ h.fecha_culminacion ?? '—' }}</td>
                    <td style="min-width: 200px">{{ h.motivo_asignacion || '—' }}</td>
                </tr>

                <tr v-if="historial.length === 0">
                    <td colspan="7" class="p-5 text-center text-muted">{{ mensajeVacio }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
