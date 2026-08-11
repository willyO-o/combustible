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
watch(() => page.props.flash, (flash) => {

    if (flash.success) {
        showToast(flash.success);
    }
}, { deep: true });


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
