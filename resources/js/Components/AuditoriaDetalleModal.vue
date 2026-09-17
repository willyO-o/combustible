<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import axios from 'axios'
import { avatarColor, claseEvento, iniciales } from '@/Utils/auditoriaUtil'

const props = defineProps({
    auditoriaId: { type: Number, default: null },
})

const emit = defineEmits(['close'])

const loading = ref(false)
const error = ref(null)
const auditoria = ref(null)

// El modal es un overlay propio (no una instancia de Bootstrap), así que el
// cierre con Escape hay que atenderlo a mano.
const cerrarConEscape = (pulsacion) => {
    if (pulsacion.key === 'Escape') {
        emit('close')
    }
}

watch(
    () => props.auditoriaId,
    async (id) => {
        if (!id) {
            window.removeEventListener('keydown', cerrarConEscape)

            return
        }

        window.addEventListener('keydown', cerrarConEscape)

        loading.value = true
        error.value = null
        auditoria.value = null
        try {
            const { data } = await axios.get(route('auditoria.detalle', id))
            auditoria.value = data
        } catch (e) {
            error.value = 'No se pudo cargar el detalle de este movimiento.'
        } finally {
            loading.value = false
        }
    },
)

onBeforeUnmount(() => window.removeEventListener('keydown', cerrarConEscape))

const evento = computed(() => auditoria.value?.evento?.clave)

// Una creación sólo tiene "después" y una eliminación sólo "antes": mostrar
// dos columnas vacías en esos casos sería ruido, no información.
const muestraAntes = computed(() => evento.value !== 'created')
const muestraDespues = computed(() => evento.value !== 'deleted')

const tituloColumnaValor = computed(() => ({
    created: 'Valor registrado',
    deleted: 'Valor antes de eliminar',
    restored: 'Valor restaurado',
}[evento.value] ?? 'Después'))

const leyendaEvento = computed(() => ({
    created: 'Se creó este registro con los siguientes valores.',
    updated: 'Se modificaron los siguientes campos.',
    deleted: 'Se eliminó este registro. Estos eran sus valores.',
    restored: 'Se restauró este registro con los siguientes valores.',
}[evento.value] ?? ''))

const userAgentCorto = computed(() => {
    const ua = auditoria.value?.contexto?.user_agent
    if (!ua) return null
    return ua.length > 80 ? `${ua.slice(0, 80)}…` : ua
})
</script>

<template>
    <teleport to="body">
        <div v-if="auditoriaId" class="modal fade show d-block auditoria-modal" tabindex="-1" role="dialog"
            aria-modal="true" aria-labelledby="auditoria-modal-titulo" style="background: rgba(0, 0, 0, .5);"
            @click.self="emit('close')">
            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content border-0 shadow-lg">

                    <!-- El encabezado identifica el REGISTRO auditado; la acción
                         va como distintivo en el cuerpo, donde el color del
                         tema tiene contraste suficiente sobre fondo claro. -->
                    <div class="modal-header bg-primary text-fixed-white">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fs-4" :class="auditoria?.modelo?.icono ?? 'ri-history-line'"></i>
                            <div>
                                <h5 id="auditoria-modal-titulo" class="modal-title mb-0 fw-semibold">
                                    {{ auditoria ? auditoria.modelo.label : 'Detalle del movimiento' }}
                                </h5>
                                <small v-if="auditoria" class="opacity-75">
                                    {{ auditoria.registro.descriptor ?? 'Registro' }}
                                    <span class="auditoria-cifra">#{{ auditoria.registro.id }}</span>
                                </small>
                            </div>
                        </div>
                        <button type="button" class="btn-close btn-close-white" aria-label="Cerrar"
                            @click="emit('close')"></button>
                    </div>

                    <div class="modal-body p-4">

                        <!-- Carga: esqueleto del contenido real, no un spinner suelto -->
                        <div v-if="loading" class="auditoria-skeleton" aria-live="polite" aria-busy="true">
                            <span class="visually-hidden">Cargando detalle…</span>
                            <div class="auditoria-skeleton-bloque mb-4"></div>
                            <div v-for="n in 4" :key="n" class="auditoria-skeleton-fila mb-2"></div>
                        </div>

                        <div v-else-if="error" class="alert alert-danger mb-0">
                            <i class="ri-error-warning-line me-2"></i>{{ error }}
                        </div>

                        <template v-else-if="auditoria">

                            <!-- Qué acción fue -->
                            <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
                                <span class="badge fs-13 px-3 py-2" :class="claseEvento(auditoria.evento.clave)">
                                    <i :class="auditoria.evento.icono" class="me-1"></i>{{ auditoria.evento.label }}
                                </span>
                                <span class="text-muted fs-13">{{ leyendaEvento }}</span>
                            </div>

                            <!-- Quién, cuándo y desde dónde -->
                            <div class="card border mb-4">
                                <div class="card-body p-3">
                                    <div class="row g-3 align-items-start">
                                        <div class="col-md-5">
                                            <p class="text-muted fs-11 text-uppercase fw-semibold mb-2">
                                                <i class="ri-user-settings-line me-1"></i>Realizado por
                                            </p>
                                            <div v-if="auditoria.usuario" class="d-flex align-items-center gap-2">
                                                <img v-if="auditoria.usuario.foto_url" :src="auditoria.usuario.foto_url"
                                                    alt="" class="avatar avatar-md avatar-rounded" />
                                                <span v-else
                                                    class="avatar avatar-md rounded-circle fw-semibold d-flex align-items-center justify-content-center"
                                                    :class="avatarColor(auditoria.usuario.nombre)">
                                                    {{ iniciales(auditoria.usuario.nombre) }}
                                                </span>
                                                <div class="min-w-0">
                                                    <p class="fw-semibold mb-0">{{ auditoria.usuario.nombre }}</p>
                                                    <p class="text-muted fs-12 mb-1 text-truncate">
                                                        {{ auditoria.usuario.email }}
                                                    </p>
                                                    <span v-for="rol in auditoria.usuario.roles" :key="rol"
                                                        class="badge bg-primary-transparent text-primary fw-normal me-1">
                                                        {{ rol }}
                                                    </span>
                                                </div>
                                            </div>
                                            <div v-else class="d-flex align-items-center gap-2">
                                                <span
                                                    class="avatar avatar-md rounded-circle bg-secondary-transparent text-secondary d-flex align-items-center justify-content-center">
                                                    <i class="ri-terminal-box-line"></i>
                                                </span>
                                                <div>
                                                    <p class="fw-semibold mb-0">Sistema</p>
                                                    <p class="text-muted fs-12 mb-0">
                                                        Cambio sin sesión de usuario (consola o proceso automático)
                                                    </p>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-md-3">
                                            <p class="text-muted fs-11 text-uppercase fw-semibold mb-2">
                                                <i class="ri-time-line me-1"></i>Cuándo
                                            </p>
                                            <p class="fw-semibold mb-0 auditoria-cifra">{{ auditoria.contexto.fecha }}</p>
                                            <p class="text-muted fs-12 mb-0">{{ auditoria.contexto.fecha_humana }}</p>
                                        </div>

                                        <div class="col-md-4">
                                            <p class="text-muted fs-11 text-uppercase fw-semibold mb-2">
                                                <i class="ri-global-line me-1"></i>Origen
                                            </p>
                                            <p class="mb-1 fs-13">
                                                <span class="text-muted">IP:</span>
                                                <span class="fw-medium auditoria-cifra">{{ auditoria.contexto.ip ?? '—' }}</span>
                                            </p>
                                            <p class="mb-1 fs-13 text-break">
                                                <span class="text-muted">Ruta:</span>
                                                <span class="fw-medium">{{ auditoria.contexto.url ?? '—' }}</span>
                                            </p>
                                            <p v-if="userAgentCorto" class="mb-0 fs-12 text-muted text-break"
                                                :title="auditoria.contexto.user_agent">
                                                {{ userAgentCorto }}
                                            </p>
                                        </div>
                                    </div>

                                    <div v-if="!auditoria.registro.existe"
                                        class="alert alert-warning-transparent mt-3 mb-0 py-2 fs-12">
                                        <i class="ri-information-line me-1"></i>
                                        El registro auditado ya no existe en el sistema; esta bitácora conserva sus datos.
                                    </div>
                                </div>
                            </div>

                            <!-- Antes / Después -->
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                                <h6 class="fw-semibold mb-0">
                                    <i class="ri-git-commit-line me-1"></i>Cambios registrados
                                    <span class="badge bg-primary-transparent text-primary ms-1 fw-normal">
                                        {{ auditoria.cambios.length }}
                                    </span>
                                </h6>
                            </div>

                            <div v-if="auditoria.cambios.length === 0" class="alert alert-info-transparent mb-0">
                                <i class="ri-information-line me-2"></i>
                                Este movimiento no dejó campos comparables (sólo se registró la acción).
                            </div>

                            <div v-else class="auditoria-diff">
                                <div class="auditoria-diff-encabezado" :class="{ 'auditoria-diff--doble': muestraAntes && muestraDespues }">
                                    <span>Campo</span>
                                    <span v-if="muestraAntes">Antes</span>
                                    <span v-if="muestraDespues">{{ tituloColumnaValor }}</span>
                                </div>

                                <div v-for="cambio in auditoria.cambios" :key="cambio.campo" class="auditoria-diff-fila"
                                    :class="{ 'auditoria-diff--doble': muestraAntes && muestraDespues }">
                                    <div class="auditoria-diff-campo">
                                        <span class="fw-medium d-block">{{ cambio.label }}</span>
                                        <small class="text-muted auditoria-cifra">{{ cambio.campo }}</small>
                                    </div>

                                    <div v-if="muestraAntes" class="auditoria-diff-celda" data-titulo="Antes">
                                        <div class="auditoria-valor"
                                            :class="cambio.modificado ? 'auditoria-valor--antes' : ''">
                                            <pre v-if="cambio.tipo === 'json' && cambio.antes">{{ cambio.antes }}</pre>
                                            <span v-else-if="cambio.antes">{{ cambio.antes }}</span>
                                            <span v-else class="text-muted fst-italic">vacío</span>
                                        </div>
                                    </div>

                                    <div v-if="muestraDespues" class="auditoria-diff-celda" :data-titulo="tituloColumnaValor">
                                        <div class="auditoria-valor"
                                            :class="cambio.modificado ? 'auditoria-valor--despues' : ''">
                                            <pre v-if="cambio.tipo === 'json' && cambio.despues">{{ cambio.despues }}</pre>
                                            <span v-else-if="cambio.despues">{{ cambio.despues }}</span>
                                            <span v-else class="text-muted fst-italic">vacío</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Historial del mismo registro -->
                            <div v-if="auditoria.historial.length > 1" class="mt-4">
                                <h6 class="fw-semibold mb-3">
                                    <i class="ri-history-line me-1"></i>Historial de este registro
                                </h6>
                                <ul class="auditoria-timeline list-unstyled mb-0">
                                    <li v-for="entrada in auditoria.historial" :key="entrada.id"
                                        class="auditoria-timeline-item"
                                        :class="{ 'auditoria-timeline-item--actual': entrada.es_actual }">
                                        <span class="auditoria-timeline-punto"
                                            :class="claseEvento(entrada.evento.clave, 'solido')"></span>
                                        <div class="d-flex flex-wrap align-items-center gap-2">
                                            <span class="badge" :class="claseEvento(entrada.evento.clave)">
                                                {{ entrada.evento.label }}
                                            </span>
                                            <span class="fs-13">{{ entrada.usuario ?? 'Sistema' }}</span>
                                            <span class="text-muted fs-12 auditoria-cifra">{{ entrada.fecha }}</span>
                                            <span v-if="entrada.es_actual"
                                                class="badge bg-primary-transparent text-primary fw-normal">
                                                Estás viendo este
                                            </span>
                                        </div>
                                    </li>
                                </ul>
                            </div>

                        </template>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-wave" @click="emit('close')">
                            <i class="ri-close-line me-1"></i> Cerrar
                        </button>
                    </div>

                </div>
            </div>
        </div>
    </teleport>
</template>
