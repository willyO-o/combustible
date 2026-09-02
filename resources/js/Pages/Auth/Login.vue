<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const showPassword = ref(false);

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Iniciar sesión" />

    <div class="auth-split">
        <aside class="auth-split__brand">
            <div class="auth-split__brandTop">
                <div class="auth-split__mark">
                    <svg class="auth-split__markIcon" viewBox="0 0 64 40" aria-hidden="true">
                        <path d="M2 34 L14 20 L20 25 L31 6 L41 22 L49 14 L62 30" />
                    </svg>
                    <span class="auth-split__markText">Plus Metals<small>Ltda</small></span>
                </div>
            </div>

            <div class="auth-split__brandBody">
                <h1 class="auth-split__title">Sistema de Control<br />de Combustible</h1>
                <p class="auth-split__lead">
                    Cargas, operación diaria y rendimiento de la flota,
                    reunidos en un solo lugar.
                </p>
            </div>

            <p class="auth-split__brandFoot">Acceso restringido a personal autorizado.</p>

            <svg class="auth-split__ridge" viewBox="0 0 1200 320" preserveAspectRatio="none" aria-hidden="true">
                <path class="auth-split__ridge--back" d="M0 232 L150 150 L260 196 L400 88 L540 172 L680 74 L840 168 L1000 104 L1130 188 L1200 150 L1200 320 L0 320 Z" />
                <path class="auth-split__ridge--mid" d="M0 268 L170 210 L320 252 L470 158 L620 240 L780 150 L940 232 L1090 182 L1200 244 L1200 320 L0 320 Z" />
                <path class="auth-split__ridge--front" d="M0 320 L0 292 L220 262 L430 300 L640 268 L900 304 L1120 276 L1200 300 L1200 320 Z" />
            </svg>
        </aside>

        <main class="auth-split__panel">
            <div class="auth-split__form">
                <div class="auth-split__mark auth-split__mark--ink">
                    <svg class="auth-split__markIcon" viewBox="0 0 64 40" aria-hidden="true">
                        <path d="M2 34 L14 20 L20 25 L31 6 L41 22 L49 14 L62 30" />
                    </svg>
                    <span class="auth-split__markText">Plus Metals<small>Ltda</small></span>
                </div>

                <header class="auth-split__head">
                    <h2>Iniciar sesión</h2>
                    <p>Ingresa con tu correo y contraseña para continuar.</p>
                </header>

                <p v-if="status" class="auth-split__status" role="status">
                    <i class="ri-checkbox-circle-line" aria-hidden="true"></i>
                    {{ status }}
                </p>

                <form class="auth-split__fields" @submit.prevent="submit">
                    <div class="auth-field" :class="{ 'auth-field--error': form.errors.email }">
                        <label for="email">Correo</label>
                        <div class="auth-field__control">
                            <i class="ri-mail-line" aria-hidden="true"></i>
                            <input
                                id="email"
                                type="email"
                                v-model="form.email"
                                required
                                autofocus
                                autocomplete="username"
                                inputmode="email"
                                placeholder="nombre@empresa.com"
                                :aria-invalid="Boolean(form.errors.email)"
                            />
                        </div>
                        <p v-if="form.errors.email" class="auth-field__msg">{{ form.errors.email }}</p>
                    </div>

                    <div class="auth-field" :class="{ 'auth-field--error': form.errors.password }">
                        <label for="password">Contraseña</label>
                        <div class="auth-field__control">
                            <i class="ri-lock-2-line" aria-hidden="true"></i>
                            <input
                                id="password"
                                :type="showPassword ? 'text' : 'password'"
                                v-model="form.password"
                                required
                                autocomplete="current-password"
                                placeholder="Ingresa tu contraseña"
                                :aria-invalid="Boolean(form.errors.password)"
                            />
                            <button
                                type="button"
                                class="auth-field__toggle"
                                :aria-label="showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'"
                                :aria-pressed="showPassword"
                                @click="showPassword = !showPassword"
                            >
                                <i :class="showPassword ? 'ri-eye-off-line' : 'ri-eye-line'" aria-hidden="true"></i>
                            </button>
                        </div>
                        <p v-if="form.errors.password" class="auth-field__msg">{{ form.errors.password }}</p>
                    </div>

                    <div class="auth-split__row">
                        <label class="auth-check">
                            <input type="checkbox" v-model="form.remember" />
                            <span>Recordarme</span>
                        </label>

                        <Link
                            v-if="canResetPassword"
                            :href="route('password.request')"
                            class="auth-split__link"
                        >
                            ¿Olvidaste tu contraseña?
                        </Link>
                    </div>

                    <button type="submit" class="auth-split__submit" :disabled="form.processing">
                        <span v-if="form.processing" class="auth-split__spinner" aria-hidden="true"></span>
                        {{ form.processing ? 'Verificando…' : 'Iniciar sesión' }}
                    </button>
                </form>

                <p class="auth-split__copyright">© {{ new Date().getFullYear() }} Plus Metals Ltda</p>
            </div>
        </main>
    </div>
</template>
