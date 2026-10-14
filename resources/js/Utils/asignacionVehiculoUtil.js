/**
 * Helpers de presentación de vehículos y sus asignaciones (a un área o a un
 * conductor). Compartidos por Areas/Show.vue, VehiculosAreaTabla.vue,
 * HistorialVehiculosAreaTabla.vue y Profile/Edit.vue.
 */
export const estadoVehiculoBadge = (estado) =>
    ({
        ACTIVO: 'bg-success-transparent text-success',
        RETIRADO: 'bg-warning-transparent text-warning',
        VENDIDO: 'bg-secondary-transparent text-secondary',
    })[estado] ?? 'bg-secondary-transparent text-secondary'

export const estadoAsignacionBadge = (estado) =>
    ({
        ACTIVO: 'bg-success-transparent text-success',
        PROVISIONAL: 'bg-info-transparent text-info',
        REASIGNADO: 'bg-warning-transparent text-warning',
        CULMINADO: 'bg-secondary-transparent text-secondary',
    })[estado] ?? 'bg-secondary-transparent text-secondary'

export const nombreVehiculo = (v) => [v.marca, v.modelo].filter(Boolean).join(' ') || '—'

export const codigoPlaca = (v) => [v.codigo, v.nro_placa].filter(Boolean).join(' · ') || '—'
