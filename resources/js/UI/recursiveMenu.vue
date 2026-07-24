<template>
    <a v-can="menuData.permission" href="javascript:void(0)" class="side-menu__item"  :class="`${menuData?.selected ? 'active' : ''}`"
        @click="toggleSubmenu($event, menuData, undefined, level > 1)"
        @mouseover="HoverToggleInnerMenuFn($event, menuData)">
        <span v-if='menuData?.icon' v-html="menuData.icon">
        </span>
        <span class="side-menu__label" v-if="level == 1">{{ menuData.title }}
            <span v-if="menuData.badge" :class="`badge ${menuData?.badgeColor} ms-1`">{{ menuData.badge
                }}</span></span>
        <span v-if="level > 1">{{ menuData.title }}
            <span v-if="menuData.badgetxt" v-html="menuData.badgetxt"></span>
        </span>
        <i class="ri-arrow-right-s-line side-menu__angle"></i>
    </a>
    <ul class="slide-menu "
        :class="`${menuData.active ? 'double-menu-active' : ''} child${level} ${menuData?.dirchange ? 'force-left' : ''}`"
        :style="menuData.active ? 'display : block' : ''">
        <li v-if="level <= 1" class="slide side-menu__label1">
            <a href="javascript:void(0)">{{ menuData.title }} </a>
        </li>

        <template v-for="(firstLevelMenuItem, subIndex) in menuData.children" :key="subIndex">
            <li v-if="firstLevelMenuItem?.type === 'link'" v-can="firstLevelMenuItem.permission"
                :class="`slide ${firstLevelMenuItem?.active ? 'open' : ''} ${firstLevelMenuItem?.selected ? 'active' : ''}`">
                <Link :href="firstLevelMenuItem?.path" class="side-menu__item"
                    :class="`${firstLevelMenuItem?.selected ? 'active' : ''}`">
                    <span v-html="firstLevelMenuItem.icon"></span> {{ firstLevelMenuItem.title }}
                </Link>
            </li>
            <li v-else-if="firstLevelMenuItem?.type === 'empty'" v-can="firstLevelMenuItem.permission" class="slide">
                <a to="javascript:;" class="side-menu__item">{{ firstLevelMenuItem.title }}</a>
            </li>
            <li v-else-if="firstLevelMenuItem?.type === 'sub'"
                :class="`slide has-sub ${firstLevelMenuItem?.active ? 'open' : ''}`">
                <RecursiveMenu :menuData="firstLevelMenuItem" :toggleSubmenu="toggleSubmenu"
                    :HoverToggleInnerMenuFn="HoverToggleInnerMenuFn" :level="level + 1" />
            </li>
        </template>
    </ul>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
    menuData: {
        type: Object,
        required: true,
    },
    toggleSubmenu: {
        type: Function,
        required: true,
    },
    HoverToggleInnerMenuFn: {
        type: Function,
        required: true,
    },
    level: {
        type: Number,
        required: true,
    },
})
</script>

<style lang="">

</style>
