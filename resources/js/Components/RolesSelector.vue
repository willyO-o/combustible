<script setup>
/**
 * Casillas para elegir uno o varios roles de una persona/usuario.
 *
 * `roles` es el catálogo que manda el backend ([{ id, name }], todos los
 * roles salvo los ocultos — ver config/acl.php `roles_ocultos`), así que un
 * rol nuevo creado desde el módulo de Roles aparece solo, sin tocar esto.
 * Sin ninguno marcado, la persona queda como "personal".
 *
 * Uso:
 *   <RolesSelector v-model="form.roles" :roles="props.roles" :error="form.errors.roles" />
 */
import { computed } from 'vue'
import { etiquetaRol } from '@/Utils/rolUtil'

const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    roles: { type: Array, default: () => [] },
    error: { type: String, default: '' },
    // Errores por casilla ("roles.0", "roles.1", ...) tal como los devuelve
    // el servidor, para mostrarlos debajo del grupo.
    errores: { type: Object, default: () => ({}) },
    idPrefix: { type: String, default: 'rol' },
})

const emit = defineEmits(['update:modelValue'])

const seleccionados = computed({
    get: () => props.modelValue,
    set: (valor) => emit('update:modelValue', valor),
})

const erroresPorRol = computed(() =>
    Object.entries(props.errores)
        .filter(([clave]) => clave.startsWith('roles.'))
        .map(([, mensaje]) => mensaje),
)
</script>

<template>
    <div>
        <div class="d-flex flex-wrap gap-4">
            <div v-for="rol in roles" :key="rol.id" class="form-check">
                <input v-model="seleccionados" class="form-check-input" type="checkbox" :value="rol.name"
                    :id="`${idPrefix}-${rol.id}`" />
                <label class="form-check-label" :for="`${idPrefix}-${rol.id}`">{{ etiquetaRol(rol.name) }}</label>
            </div>
        </div>
        <div v-if="error" class="text-danger small mt-1">{{ error }}</div>
        <div v-for="(mensaje, idx) in erroresPorRol" :key="idx" class="text-danger small mt-1">{{ mensaje }}</div>
    </div>
</template>
