<script setup>
import { computed } from 'vue'
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import Multiselect from '@vueform/multiselect'
import Maindashboard from '@/Layouts/Maindashboard.vue'
import DateTimeInput from '@/Components/DateTimeInput.vue'
import { confirm } from '@/Utils/alertUtil'

defineOptions({ layout: Maindashboard })

const props = defineProps({
    // { estricto, estricto_desde: 'YYYY-MM-DD'|null, hay_parametros }
    modo: { type: Object, required: true },
    // [{ id, estado, usuario, email, desde, hasta, vigente_hasta (ISO), motivo, otorgada_por, otorgada_at, revocada_at, revocada_por }]
    vigentes: { type: Array, default: () => [] },
    historial: { type: Array, default: () => [] },
    // [{ id, label, email }] usuarios activos que pueden registrar operaciones
    usuarios: { type: Array, default: () => [] },
    vigencias: { type: Array, default: () => [] },
    maxDiasVigencia: { type: Number, default: 30 },
})

const page = usePage()
const errorFlash = computed(() => page.props.flash?.error)

/* ------------------------------------------------------------------ */
/*  Fechas (día calendario en hora local, sin corrimiento UTC)         */
/* ------------------------------------------------------------------ */
const isoLocal = (fecha) => {
    const d = new Date(fecha)
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}
const diasAtras = (n) => {
    const d = new Date()
    d.setDate(d.getDate() - n)
    return isoLocal(d)
}
const hoy = isoLocal(new Date())
const ayer = diasAtras(1)
const manana = diasAtras(-1)

// 'YYYY-MM-DD' -> 'dd/mm/aaaa'
const fechaCorta = (iso) => (iso ? iso.slice(0, 10).split('-').reverse().join('/') : '—')
const fechaHora = (iso) => {
    if (!iso) return '—'
    const d = new Date(iso)
    return `${fechaCorta(isoLocal(d))} ${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`
}
const relativo = new Intl.RelativeTimeFormat('es', { numeric: 'auto' })
const tiempoRestante = (iso) => {
    const minutos = Math.round((new Date(iso) - new Date()) / 60000)
    if (minutos < 60) return relativo.format(Math.max(minutos, 1), 'minute')
    if (minutos < 48 * 60) return relativo.format(Math.round(minutos / 60), 'hour')
    return relativo.format(Math.round(minutos / 1440), 'day')
}
const rangoDias = (desde, hasta) => (desde === hasta ? fechaCorta(desde) : `${fechaCorta(desde)} al ${fechaCorta(hasta)}`)

/* ------------------------------------------------------------------ */
/*  Modo transición / estricto                                         */
/* ------------------------------------------------------------------ */
const programado = computed(() => !props.modo.estricto && !!props.modo.estricto_desde)
const diasParaEstricto = computed(() => {
    if (!programado.value) return null
    return Math.round((new Date(`${props.modo.estricto_desde}T00:00:00`) - new Date(`${hoy}T00:00:00`)) / 86400000)
})

const modoForm = useForm({ accion: '', fecha: programado.value ? props.modo.estricto_desde : '' })

async function cambiarModo(accion) {
    const mensajes = {
        activar: ['Desde hoy todos registrarán sólo el día actual (o el turno noche de ayer). Los días anteriores necesitarán tu habilitación.', 'Activar modo estricto', 'Sí, activar'],
        desactivar: [programado.value
            ? 'Se cancela el modo estricto programado.'
            : 'Los jefes de área y administradores volverán a registrar cualquier fecha.', 'Volver al modo transición', 'Sí, volver'],
    }

    if (mensajes[accion] && !(await confirm(...mensajes[accion]))) return

    modoForm.accion = accion
    modoForm.put(route('operacion-diaria.regularizacion.modo'), { preserveScroll: true })
}

/* ------------------------------------------------------------------ */
/*  Habilitar a un usuario                                             */
/* ------------------------------------------------------------------ */
const ETIQUETAS_VIGENCIA = {
    fin_del_dia: 'Hasta hoy 23:59',
    '24h': '24 horas',
    '48h': '48 horas',
    '72h': '72 horas',
    personalizada: 'Otra fecha',
}

const ATAJOS_DIAS = [
    { etiqueta: 'Ayer', desde: 1, hasta: 1 },
    { etiqueta: 'Últimos 3 días', desde: 3, hasta: 1 },
    { etiqueta: 'Últimos 7 días', desde: 7, hasta: 1 },
]

const form = useForm({
    id_usuario: null,
    fecha_desde: ayer,
    fecha_hasta: ayer,
    vigencia: '24h',
    vigente_hasta: '',
    motivo: '',
})

const aplicarAtajo = (atajo) => {
    form.fecha_desde = diasAtras(atajo.desde)
    form.fecha_hasta = diasAtras(atajo.hasta)
}
const atajoActivo = (atajo) => form.fecha_desde === diasAtras(atajo.desde) && form.fecha_hasta === diasAtras(atajo.hasta)

const ahora = new Date()
const maxVigencia = new Date(ahora.getTime() + props.maxDiasVigencia * 86400000)

// Fin de la vigencia elegida, sólo para el resumen (el servidor la calcula).
const finVigencia = computed(() => {
    const base = new Date()
    switch (form.vigencia) {
        case 'fin_del_dia': return new Date(new Date().setHours(23, 59, 0, 0))
        case '24h': return new Date(base.getTime() + 24 * 3600000)
        case '48h': return new Date(base.getTime() + 48 * 3600000)
        case '72h': return new Date(base.getTime() + 72 * 3600000)
        default: return form.vigente_hasta ? new Date(form.vigente_hasta) : null
    }
})

const usuarioElegido = computed(() => props.usuarios.find((u) => u.id === form.id_usuario) ?? null)
const resumen = computed(() => {
    if (!usuarioElegido.value || !form.fecha_desde || !form.fecha_hasta || !finVigencia.value || isNaN(finVigencia.value)) return null
    return {
        usuario: usuarioElegido.value.label,
        dias: rangoDias(form.fecha_desde, form.fecha_hasta),
        hasta: fechaHora(finVigencia.value.toISOString()),
    }
})

function habilitar() {
    form
        .transform((data) => ({ ...data, vigente_hasta: data.vigencia === 'personalizada' ? data.vigente_hasta : null }))
        .post(route('operacion-diaria.regularizacion.habilitaciones.store'), {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        })
}

async function revocar(habilitacion) {
    const confirmado = await confirm(
        `<strong>${habilitacion.usuario}</strong> dejará de poder registrar operaciones del ${rangoDias(habilitacion.desde, habilitacion.hasta)}.`,
        'Revocar habilitación',
        'Sí, revocar',
    )

    if (!confirmado) return

    router.patch(route('operacion-diaria.regularizacion.habilitaciones.revocar', habilitacion.id), {}, { preserveScroll: true })
}

const estadoBadge = (estado) => (estado === 'REVOCADA' ? 'bg-danger-transparent text-danger' : 'bg-light text-muted')
</script>

<template>
    <Head title="Regularización de operaciones" />

    <div class="reg-page">
        <div class="d-flex align-items-center justify-content-between page-header-breadcrumb flex-wrap gap-2 mb-4">
            <div>
                <nav>
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item"><Link :href="route('dashboard')">Inicio</Link></li>
                        <li class="breadcrumb-item"><Link :href="route('operacion-diaria.index')">Actividades</Link></li>
                        <li class="breadcrumb-item active">Regularización</li>
                    </ol>
                </nav>
                <h1 class="page-title fw-medium fs-18 mb-0">Regularización de operaciones diarias</h1>
            </div>
        </div>

        <div v-if="errorFlash" class="alert alert-danger d-flex align-items-start gap-2" role="alert">
            <i class="ri-error-warning-line fs-18" aria-hidden="true"></i>
            <div>{{ errorFlash }}</div>
        </div>

        <!-- ====== MODO ====== -->
        <section class="card custom-card" aria-labelledby="reg-modo-titulo">
            <div class="card-body">
                <div class="mb-3">
                    <div>
                        <h2 id="reg-modo-titulo" class="fs-15 fw-semibold mb-1">
                            Modo de registro:
                            <span :class="modo.estricto ? 'text-primary' : 'text-warning'">{{ modo.estricto ? 'estricto' : 'transición' }}</span>
                        </h2>
                        <p class="text-muted mb-0">
                            <template v-if="modo.estricto">Rige desde el {{ fechaCorta(modo.estricto_desde) }}. Para un día anterior, el usuario necesita tu habilitación.</template>
                            <template v-else-if="programado">
                                El modo estricto empieza el <strong class="text-body">{{ fechaCorta(modo.estricto_desde) }}</strong>
                                ({{ diasParaEstricto === 1 ? 'mañana' : `en ${diasParaEstricto} días` }}). Hasta entonces se permite regularizar como hasta ahora.
                            </template>
                            <template v-else>Se permite regularizar como hasta ahora. Activa el modo estricto cuando termine la transición.</template>
                        </p>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <div class="reg-modo reg-modo--transicion h-100" :class="{ 'reg-modo--activo': !modo.estricto }">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <span class="fw-semibold">Transición</span>
                                <span v-if="!modo.estricto" class="badge bg-warning-transparent text-warning">Actual</span>
                            </div>
                            <p class="mb-0 text-muted fs-13">
                                Jefes de área y administradores registran cualquier fecha. El resto, hoy o ayer.
                            </p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="reg-modo h-100" :class="{ 'reg-modo--activo': modo.estricto }">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <span class="fw-semibold">Estricto</span>
                                <span v-if="modo.estricto" class="badge bg-primary-transparent text-primary">Actual</span>
                                <span v-else-if="programado" class="badge bg-light text-muted">Desde {{ fechaCorta(modo.estricto_desde) }}</span>
                            </div>
                            <p class="mb-0 text-muted fs-13">
                                Todos registran sólo hoy, o el turno noche de ayer. Días anteriores, sólo con tu habilitación.
                            </p>
                        </div>
                    </div>
                </div>

                <div v-if="!modo.hay_parametros" class="alert alert-warning mb-0">
                    Registra primero los parámetros de la empresa: ahí se guarda el modo.
                </div>

                <div v-else class="d-flex flex-wrap align-items-end gap-2">
                    <template v-if="modo.estricto">
                        <button type="button" class="btn btn-outline-warning btn-wave" :disabled="modoForm.processing"
                            @click="cambiarModo('desactivar')">
                            <i class="ri-arrow-go-back-line me-1"></i> Volver al modo transición
                        </button>
                    </template>
                    <template v-else>
                        <button type="button" class="btn btn-primary btn-wave" :disabled="modoForm.processing"
                            @click="cambiarModo('activar')">
                            <i class="ri-lock-line me-1"></i> Activar modo estricto ahora
                        </button>

                        <span class="text-muted px-1 align-self-center">o</span>

                        <div>
                            <label for="reg-fecha-programada" class="form-label fs-13 mb-1">Programar desde</label>
                            <div class="input-group">
                                <input id="reg-fecha-programada" v-model="modoForm.fecha" type="date" class="form-control"
                                    :min="manana" :class="{ 'is-invalid': modoForm.errors.fecha }" />
                                <button type="button" class="btn btn-outline-primary" :disabled="modoForm.processing || !modoForm.fecha"
                                    @click="cambiarModo('programar')">
                                    {{ programado ? 'Reprogramar' : 'Programar' }}
                                </button>
                            </div>
                        </div>

                        <button v-if="programado" type="button" class="btn btn-link text-danger" :disabled="modoForm.processing"
                            @click="cambiarModo('desactivar')">
                            Cancelar programación
                        </button>
                    </template>
                </div>
                <div v-if="modoForm.errors.fecha" class="text-danger small mt-1">{{ modoForm.errors.fecha }}</div>
            </div>
        </section>

        <div class="row g-4">
            <!-- ====== HABILITAR ====== -->
            <div class="col-xl-5">
                <section class="card custom-card h-100" aria-labelledby="reg-form-titulo">
                    <div class="card-header">
                        <h2 id="reg-form-titulo" class="card-title mb-0">Habilitar a un usuario</h2>
                    </div>
                    <form class="card-body d-flex flex-column gap-3" @submit.prevent="habilitar">
                        <div>
                            <label class="form-label fw-medium">Usuario <span class="text-danger">*</span></label>
                            <Multiselect v-model="form.id_usuario" :options="usuarios" value-prop="id" label="label"
                                :searchable="true" :filter-results="true" placeholder="Buscar por nombre..."
                                no-options-text="No hay usuarios que registren operaciones" no-results-text="Sin resultados"
                                :class="{ 'is-invalid-multiselect': form.errors.id_usuario }">
                                <template #option="{ option }">
                                    <span>{{ option.label }}</span>
                                    <span class="text-muted fs-12 ms-2">{{ option.email }}</span>
                                </template>
                            </Multiselect>
                            <div v-if="form.errors.id_usuario" class="text-danger small mt-1">{{ form.errors.id_usuario }}</div>
                        </div>

                        <fieldset>
                            <legend class="form-label fw-medium fs-14 mb-1">Días que podrá registrar <span class="text-danger">*</span></legend>
                            <div class="d-flex flex-wrap gap-2 mb-2">
                                <button v-for="atajo in ATAJOS_DIAS" :key="atajo.etiqueta" type="button"
                                    class="btn btn-sm rounded-pill"
                                    :class="atajoActivo(atajo) ? 'btn-primary' : 'btn-outline-light text-body'"
                                    :aria-pressed="atajoActivo(atajo)" @click="aplicarAtajo(atajo)">
                                    {{ atajo.etiqueta }}
                                </button>
                            </div>
                            <div class="row g-2">
                                <div class="col-6">
                                    <label for="reg-desde" class="form-label fs-13 text-muted mb-1">Desde</label>
                                    <input id="reg-desde" v-model="form.fecha_desde" type="date" class="form-control" :max="form.fecha_hasta || ayer"
                                        :class="{ 'is-invalid': form.errors.fecha_desde }" />
                                </div>
                                <div class="col-6">
                                    <label for="reg-hasta" class="form-label fs-13 text-muted mb-1">Hasta</label>
                                    <input id="reg-hasta" v-model="form.fecha_hasta" type="date" class="form-control" :min="form.fecha_desde" :max="ayer"
                                        :class="{ 'is-invalid': form.errors.fecha_hasta }" />
                                </div>
                            </div>
                            <div v-if="form.errors.fecha_desde || form.errors.fecha_hasta" class="text-danger small mt-1">
                                {{ form.errors.fecha_desde || form.errors.fecha_hasta }}
                            </div>
                            <small v-else class="text-muted">Sólo días pasados: hoy y la noche de ayer no necesitan habilitación.</small>
                        </fieldset>

                        <fieldset>
                            <legend class="form-label fw-medium fs-14 mb-1">Vigencia <span class="text-danger">*</span></legend>
                            <div class="d-flex flex-wrap gap-2" role="radiogroup">
                                <template v-for="vigencia in vigencias" :key="vigencia">
                                    <input :id="`reg-vigencia-${vigencia}`" v-model="form.vigencia" type="radio" class="btn-check"
                                        name="reg-vigencia" :value="vigencia" autocomplete="off" />
                                    <label class="btn btn-sm btn-outline-primary rounded-pill" :for="`reg-vigencia-${vigencia}`">
                                        {{ ETIQUETAS_VIGENCIA[vigencia] ?? vigencia }}
                                    </label>
                                </template>
                            </div>
                            <div v-if="form.vigencia === 'personalizada'" class="mt-2">
                                <DateTimeInput v-model="form.vigente_hasta" :invalid="!!form.errors.vigente_hasta"
                                    :min-date="ahora" :max-date="maxVigencia" hora-sugerida="18:00" />
                            </div>
                            <div v-if="form.errors.vigencia || form.errors.vigente_hasta" class="text-danger small mt-1">
                                {{ form.errors.vigencia || form.errors.vigente_hasta }}
                            </div>
                        </fieldset>

                        <div>
                            <label for="reg-motivo" class="form-label fw-medium">Motivo <span class="text-danger">*</span></label>
                            <textarea id="reg-motivo" v-model="form.motivo" class="form-control" rows="3" maxlength="500"
                                :class="{ 'is-invalid': form.errors.motivo }"
                                placeholder="Ej: el operario olvidó registrar el turno del 04/11 por falta de señal."></textarea>
                            <div class="d-flex justify-content-between mt-1">
                                <span class="text-danger small">{{ form.errors.motivo }}</span>
                                <span class="text-muted fs-12 reg-num">{{ form.motivo.length }}/500</span>
                            </div>
                        </div>

                        <p v-if="resumen" class="reg-resumen mb-0">
                            <strong>{{ resumen.usuario }}</strong> podrá registrar operaciones del
                            <strong>{{ resumen.dias }}</strong> hasta el <strong>{{ resumen.hasta }}</strong>. Le llegará un aviso.
                        </p>

                        <div class="mt-auto d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary btn-wave" :disabled="form.processing">
                                <span v-if="form.processing" class="spinner-border spinner-border-sm me-1" role="status"></span>
                                <i v-else class="ri-calendar-check-line me-1"></i>
                                {{ form.processing ? 'Habilitando...' : 'Habilitar y avisar' }}
                            </button>
                        </div>
                    </form>
                </section>
            </div>

            <!-- ====== VIGENTES ====== -->
            <div class="col-xl-7">
                <section class="card custom-card h-100" aria-labelledby="reg-vigentes-titulo">
                    <div class="card-header justify-content-between">
                        <h2 id="reg-vigentes-titulo" class="card-title mb-0">Habilitaciones vigentes</h2>
                        <span class="badge reg-num" :class="vigentes.length ? 'bg-primary-transparent text-primary' : 'bg-light text-muted'">
                            {{ vigentes.length }}
                        </span>
                    </div>
                    <div class="card-body p-0">
                        <div v-if="!vigentes.length" class="reg-vacio">
                            <i class="ri-calendar-line" aria-hidden="true"></i>
                            <p class="mb-0">Nadie tiene una habilitación vigente.</p>
                            <p class="mb-0 text-muted fs-13">Cuando habilites a alguien aparecerá aquí, con su vencimiento y la opción de revocarla.</p>
                        </div>
                        <div v-else class="table-responsive">
                            <table class="table table-hover table-sm mb-0 reg-tabla">
                                <thead class="table-light">
                                    <tr>
                                        <th scope="col">Usuario</th>
                                        <th scope="col">Días</th>
                                        <th scope="col">Vence</th>
                                        <th scope="col">Motivo</th>
                                        <th scope="col" class="text-end"><span class="visually-hidden">Acciones</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="h in vigentes" :key="h.id">
                                        <td>
                                            <span class="d-block fw-medium">{{ h.usuario }}</span>
                                            <span class="text-muted fs-12">{{ h.email }}</span>
                                        </td>
                                        <td class="reg-num text-nowrap">{{ rangoDias(h.desde, h.hasta) }}</td>
                                        <td class="text-nowrap">
                                            <span class="d-block reg-num">{{ fechaHora(h.vigente_hasta) }}</span>
                                            <span class="text-muted fs-12">{{ tiempoRestante(h.vigente_hasta) }}</span>
                                        </td>
                                        <td class="reg-motivo">
                                            <span class="d-block">{{ h.motivo }}</span>
                                            <span class="text-muted fs-12">Por {{ h.otorgada_por }}</span>
                                        </td>
                                        <td class="text-end">
                                            <button type="button" class="btn btn-sm btn-outline-danger" @click="revocar(h)">
                                                Revocar
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            </div>

            <!-- ====== HISTORIAL ====== -->
            <div v-if="historial.length" class="col-12">
                <section class="card custom-card" aria-labelledby="reg-historial-titulo">
                    <div class="card-header">
                        <h2 id="reg-historial-titulo" class="card-title mb-0">Historial reciente</h2>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0 reg-tabla">
                                <thead class="table-light">
                                    <tr>
                                        <th scope="col">Usuario</th>
                                        <th scope="col">Días</th>
                                        <th scope="col">Estado</th>
                                        <th scope="col">Terminó</th>
                                        <th scope="col">Motivo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="h in historial" :key="h.id">
                                        <td>
                                            <span class="d-block fw-medium">{{ h.usuario }}</span>
                                            <span class="text-muted fs-12">Por {{ h.otorgada_por }}</span>
                                        </td>
                                        <td class="reg-num text-nowrap">{{ rangoDias(h.desde, h.hasta) }}</td>
                                        <td>
                                            <span class="badge" :class="estadoBadge(h.estado)">{{ h.estado === 'REVOCADA' ? 'Revocada' : 'Vencida' }}</span>
                                        </td>
                                        <td class="text-nowrap">
                                            <span class="d-block reg-num">{{ fechaHora(h.revocada_at ?? h.vigente_hasta) }}</span>
                                            <span v-if="h.revocada_por" class="text-muted fs-12">Revocó {{ h.revocada_por }}</span>
                                        </td>
                                        <td class="reg-motivo">{{ h.motivo }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
</template>
