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

/**
 * Etiqueta de un vehículo en los selectores y filtros: "marca modelo - placa
 * - (código)", sin año, omitiendo lo que falte. Ej. "Volvo FMX - 1234ABC -
 * (VOL-01)". Réplica de Vehiculo::etiquetaSeleccion() (backend) para los
 * filtros que arman la etiqueta en la vista: si cambia uno, cambiar el otro.
 */
export const etiquetaVehiculo = (v) =>
    [
        [v?.marca, v?.modelo].filter(Boolean).join(' '),
        v?.nro_placa,
        v?.codigo ? `(${v.codigo})` : null,
    ].filter(Boolean).join(' - ')
