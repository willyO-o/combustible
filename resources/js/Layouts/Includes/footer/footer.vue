<template>
<footer class="footer mt-auto py-3 text-center">
    <div class="container">
        <span class="text-muted"> Copyright © <span id="year">{{currentYear}}</span> <a href="javascript:void(0);" class="text-dark fw-medium">
            Plus Metals L.T.D.A
        </a>.
            Elaborado  por <a href="javascript:void(0);">
                <span class="fw-medium text-primary">Soglotec</span>
            </a>
            Todos los derechos reservados.
        </span>
    </div>
</footer>
</template>

<script setup>
let currentYear = new Date().getFullYear()
</script>

<style lang="">

</style>

<style lang="">

</style>
