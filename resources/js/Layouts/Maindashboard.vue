<script setup>
import {
    ref,
    reactive,
    onMounted,
    onBeforeUnmount,
    computed,
    watch
} from 'vue'

import { switcherStore } from '@/stores/switcher'
import Header from '@/Layouts/Includes/header/header.vue'
import Sidebar from '@/Layouts/Includes/sidebar/sidebar.vue'
import Footer from '@/Layouts/Includes/footer/footer.vue'
import Switcher from '@/Layouts/Includes/switcher/switcher.vue'
import BackToTop from '@/Layouts/Includes/backtotop/backtotop.vue'

import { usePage } from '@inertiajs/vue3';

import { showToast, showError } from '@/Utils/alertUtil'
// Reactive store
const switcher = reactive(switcherStore())

// Computed class
const customClass = computed(() =>
    switcher.pageStyles === 'flat' ? 'main-body-container' : ''
)

const page = usePage();

// header.vue hace usePoll(30000, { only: ['notificaciones'] }) para refrescar
// las notificaciones del header en segundo plano. Cada uno de esos polls
// reasigna page.props (aunque flash no venga en la respuesta parcial). Un
// watch({ deep: true }) sobre el objeto flash completo se dispara en CADA
// reasignación de page.props sin importar si su contenido cambió (con
// deep:true Vue se salta la comparación de valor y siempre invoca el
// callback cuando se reevalúa el getter) — por eso el mismo toast volvía a
// mostrarse cada ~30s sin ninguna acción del usuario. Al observar el string
// primitivo en vez del objeto, Vue solo dispara el callback cuando el
// mensaje realmente cambia.
watch(() => page.props.flash?.success, (mensaje) => {
    if (mensaje) {
        showToast(mensaje);
    }
});


// Scroll progress logic
const progressRef = ref(null)

const handleScroll = () => {
    const scrollTop = document.documentElement.scrollTop || document.body.scrollTop
    const scrollHeight =
        document.documentElement.scrollHeight - document.documentElement.clientHeight

    if (scrollHeight === 0) return

    const scrollPercent = (scrollTop / scrollHeight) * 100

    if (progressRef.value) {
        progressRef.value.style.width = `${scrollPercent}%`
    }
}

onMounted(() => {
    window.addEventListener('scroll', handleScroll)
    switcher.retrieveFromLocalStorage()
})

onBeforeUnmount(() => {
    window.removeEventListener('scroll', handleScroll)
})
</script>

<template>
    <div ref="progressRef" class="progress-top-bar"></div>
    <Switcher />
    <div class="page">
        <Header />
        <Sidebar />

        <!-- Start::app-content -->
        <div class="main-content app-content">
            <div :class="['container-fluid', 'page-container', customClass]" class="pb-4" >

                <slot />


            </div>
        </div>
        <!-- End::app-content -->

        <Footer />
    </div>
    <BackToTop />
</template>

<style scoped lang="scss"></style>
