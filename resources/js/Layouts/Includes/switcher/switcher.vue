<script setup>
import { ref, reactive, onMounted, onUnmounted } from 'vue';
import { Tooltip } from 'bootstrap';
import bg_img1 from "@/Assets/images/menu-bg-images/bg-img1.jpg"
import bg_img2 from "@/Assets/images/menu-bg-images/bg-img2.jpg"
import bg_img3 from "@/Assets/images/menu-bg-images/bg-img3.jpg"
import bg_img4 from "@/Assets/images/menu-bg-images/bg-img4.jpg"
import bg_img5 from "@/Assets/images/menu-bg-images/bg-img5.jpg"
import { switcherStore } from '@/stores/switcher';
import { MENUITEMS } from '@/Data/sidebar/nav';

// Reactive state and refs
const MENU = reactive(MENUITEMS);
const switcher = reactive(switcherStore());
const dynamicPrimaryColor = ref('black');
const dynamicBackgroundColor = ref('black');

// Tooltip
let tooltipInstance = null;

onMounted(() => {
    tooltipInstance = new Tooltip(document.body, {
        selector: '[data-bs-toggle="tooltip"]',
    });
});

onUnmounted(() => {
    const tooltips = document.getElementsByClassName('tooltip');
    Array.from(tooltips).forEach(el => el.remove());
});

// Utility functions
function convertRgbToIndividual(value) {
    const numericValues = value.match(/\d+/g);
    return numericValues.join(', ');
}

// Theme functions
function primaryColorFn(color) {
    const rgb = convertRgbToIndividual(color);
    themePrimaryFn(rgb);
}

function dynamicBackgroundColorFn(color) {
    const bgRgb = convertRgbToIndividual(color).split(', ');
    const bgRgb2 = [...bgRgb].map(v => +v + 14);
    themeBackgroundFn(bgRgb.join(', '), bgRgb2.join(', '));
}

function themePrimaryFn(value) {
    switcher.themePrimaryFn(value);
}

function themeBackgroundFn(val1, val2) {
    switcher.themeBackgroundFn(val1, val2);
    localStorage.removeItem('vyzorHeader');
    localStorage.removeItem('vyzorMenu');
}

// Layout and style switchers
function colorthemeFn(value) {
    switcher.colorthemeFn(value);
    localStorage.setItem("vyzorcolortheme", value);
    localStorage.removeItem('vyzorbodylightRGB');
    localStorage.removeItem('vyzorbodyBgRGB');

    if (value === 'dark') {
        localStorage.setItem("vyzorMenu", 'transparent');
        localStorage.setItem("vyzorHeader", 'transparent');
    } else {
        localStorage.removeItem("vyzorHeader");
        localStorage.removeItem("vyzorMenu");
    }
}

function directionFn(value) {
    switcher.directionFn(value);
    localStorage.setItem('vyzordirection', value);
}

function navigationStylesFn(value) {
    switcher.navigationStylesFn(value);
    localStorage.setItem('vyzornavstyles', value);
}

function layoutStylesFn(value) {
    switcher.layoutStylesFn(value);
    localStorage.setItem("vyzorverticalstyles", value);
    if (value === 'horizontal') closeMenuFn();
}

function pageStylesFn(value) {
    switcher.pageStylesFn(value);
    localStorage.setItem("vyzorpageStyle", value);
}

function widthStylesFn(value) {
    switcher.widthStylesFn(value);
    localStorage.setItem("vyzorwidthStyles", value);
}

function menuPositionFn(value) {
    switcher.menuPositionFn(value);
    localStorage.setItem("vyzormenuposition", value);
}

function headerPositionFn(value) {
    switcher.headerPositionFn(value);
    localStorage.setItem("vyzorheaderposition", value);
}

function menuColorFn(value) {
    switcher.menuColorFn(value);
    localStorage.setItem("vyzorMenu", value);
}

function headerColorFn(value) {
    switcher.headerColorFn(value);
    localStorage.setItem("vyzorHeader", value);
}

function menuStylesFn(value) {
    switcher.menuStylesFn(value);
    localStorage.setItem("vyzormenuStyles", value);
    if (['menu-hover', 'icon-hover'].includes(value)) closeMenuFn();
}

function backgroundImageFn(value) {
    switcher.backgroundImageFn(value);
    localStorage.setItem("vyzorbgimg", value);
}

function closeMenuFn() {
    const closeMenuRecursively = (items) => {
        items?.forEach(item => {
            item.active = false;
            closeMenuRecursively(item.children);
        });
    };
    closeMenuRecursively(MENU);
}

function reset() {
    switcher.$reset();
    switcher.reset();
}

function retrieveFromLocalStorage() {
    switcher.retrieveFromLocalStorage();
}
</script>


<template>
    <!-- Start Switcher -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="switcher-canvas" aria-labelledby="offcanvasRightLabel">
        <div class="offcanvas-header border-bottom d-block p-0">
            <div class="d-flex align-items-center justify-content-between p-3">
                <h5 class="offcanvas-title text-default" id="offcanvasRightLabel">Switcher</h5>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
            </div>
            <nav class="border-top border-block-start-dashed">
                <div class="nav nav-tabs nav-justified" id="switcher-main-tab" role="tablist">
                    <button class="nav-link active" id="switcher-home-tab" data-bs-toggle="tab"
                        data-bs-target="#switcher-home" type="button" role="tab" aria-controls="switcher-home"
                        aria-selected="true">Theme Styles</button>
                    <button class="nav-link" id="switcher-profile-tab" data-bs-toggle="tab"
                        data-bs-target="#switcher-profile" type="button" role="tab" aria-controls="switcher-profile"
                        aria-selected="false">Theme Colors</button>
                </div>
            </nav>
        </div>
        <div class="offcanvas-body">
            <div class="tab-content" id="nav-tabContent">
                <div class="tab-pane fade show active border-0" id="switcher-home" role="tabpanel"
                    aria-labelledby="switcher-home-tab" tabindex="0">
                    <div class="">
                        <p class="switcher-style-head">Theme Color Mode:</p>
                        <div class="row switcher-style gx-0">
                            <div class="col-4">
                                <div class="form-check switch-select">
                                    <label class="form-check-label" for="switcher-light-theme">
                                        Light
                                    </label>
                                    <input @click="colorthemeFn('light')" class="form-check-input" type="radio"
                                        name="theme-style" id="switcher-light-theme"
                                        :checked="switcher.colortheme == 'light' ? true : false">
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="form-check switch-select">
                                    <label class="form-check-label" for="switcher-dark-theme">
                                        Dark
                                    </label>
                                    <input @click="colorthemeFn('dark')" class="form-check-input" type="radio"
                                        name="theme-style" id="switcher-dark-theme"
                                        :checked="switcher.colortheme == 'dark' ? true : false">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="">
                        <p class="switcher-style-head">Directions:</p>
                        <div class="row switcher-style gx-0">
                            <div class="col-4">
                                <div class="form-check switch-select">
                                    <label class="form-check-label" for="switcher-ltr">
                                        LTR
                                    </label>
                                    <input @click="directionFn('ltr')" class="form-check-input" type="radio"
                                        name="direction" id="switcher-ltr"
                                        :checked="switcher.direction == 'ltr' ? true : false">
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="form-check switch-select">
                                    <label class="form-check-label" for="switcher-rtl">
                                        RTL
                                    </label>
                                    <input @click="directionFn('rtl')" class="form-check-input" type="radio"
                                        name="direction" id="switcher-rtl"
                                        :checked="switcher.direction == 'rtl' ? true : false">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="">
                        <p class="switcher-style-head">Navigation Styles:</p>
                        <div class="row switcher-style gx-0">
                            <div class="col-4">
                                <div class="form-check switch-select">
                                    <label class="form-check-label" for="switcher-vertical">
                                        Vertical
                                    </label>
                                    <input @click="navigationStylesFn('vertical')" class="form-check-input" type="radio"
                                        name="navigation-style" id="switcher-vertical"
                                        :checked="switcher.navigationStyles == 'vertical' ? true : false">
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="form-check switch-select">
                                    <label class="form-check-label" for="switcher-horizontal">
                                        Horizontal
                                    </label>
                                    <input @click="navigationStylesFn('horizontal')" class="form-check-input"
                                        type="radio" name="navigation-style" id="switcher-horizontal"
                                        :checked="switcher.navigationStyles == 'horizontal' ? true : false">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="navigation-menu-styles">
                        <p class="switcher-style-head">Vertical & Horizontal Menu Styles:</p>
                        <div class="row switcher-style gx-0 pb-2 gy-2">
                            <div class="col-4">
                                <div class="form-check switch-select">
                                    <label class="form-check-label" for="switcher-menu-click">
                                        Menu Click
                                    </label>
                                    <input @click="menuStylesFn('menu-click')" class="form-check-input" type="radio"
                                        name="navigation-menu-styles" id="switcher-menu-click"
                                        :checked="switcher.menuStyles == 'menu-click' ? true : false">
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="form-check switch-select">
                                    <label class="form-check-label" for="switcher-menu-hover">
                                        Menu Hover
                                    </label>
                                    <input @click="menuStylesFn('menu-hover')" class="form-check-input" type="radio"
                                        name="navigation-menu-styles" id="switcher-menu-hover"
                                        :checked="switcher.menuStyles == 'menu-hover' ? true : false">
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="form-check switch-select">
                                    <label class="form-check-label" for="switcher-icon-click">
                                        Icon Click
                                    </label>
                                    <input @click="menuStylesFn('icon-click')" class="form-check-input" type="radio"
                                        name="navigation-menu-styles" id="switcher-icon-click"
                                        :checked="switcher.menuStyles == 'icon-click' ? true : false">
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="form-check switch-select">
                                    <label class="form-check-label" for="switcher-icon-hover">
                                        Icon Hover
                                    </label>
                                    <input @click="menuStylesFn('icon-hover')" class="form-check-input" type="radio"
                                        name="navigation-menu-styles" id="switcher-icon-hover"
                                        :checked="switcher.menuStyles == 'icon-hover' ? true : false">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="sidemenu-layout-styles">
                        <p class="switcher-style-head">Sidemenu Layout Styles:</p>
                        <div class="row switcher-style gx-0 pb-2 gy-2">
                            <div class="col-sm-6">
                                <div class="form-check switch-select">
                                    <label class="form-check-label" for="switcher-default-menu">
                                        Default Menu
                                    </label>
                                    <input @click="layoutStylesFn('default-menu')" class="form-check-input" type="radio"
                                        name="sidemenu-layout-styles" id="switcher-default-menu"
                                        :checked="switcher.layoutStyles == 'default-menu' ? true : false">
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="form-check switch-select">
                                    <label class="form-check-label" for="switcher-closed-menu">
                                        Closed Menu
                                    </label>
                                    <input @click="layoutStylesFn('closed-menu')" class="form-check-input" type="radio"
                                        name="sidemenu-layout-styles" id="switcher-closed-menu"
                                        :checked="switcher.layoutStyles == 'closed-menu' ? true : false">
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="form-check switch-select">
                                    <label class="form-check-label" for="switcher-icontext-menu">
                                        Icon Text
                                    </label>
                                    <input @click="layoutStylesFn('icontext-menu')" class="form-check-input"
                                        type="radio" name="sidemenu-layout-styles" id="switcher-icontext-menu"
                                        :checked="switcher.layoutStyles == 'icontext-menu' ? true : false">
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="form-check switch-select">
                                    <label class="form-check-label" for="switcher-icon-overlay">
                                        Icon Overlay
                                    </label>
                                    <input @click="layoutStylesFn('icon-overlay')" class="form-check-input" type="radio"
                                        name="sidemenu-layout-styles" id="switcher-icon-overlay"
                                        :checked="switcher.layoutStyles == 'icon-overlay' ? true : false">
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="form-check switch-select">
                                    <label class="form-check-label" for="switcher-detached">
                                        Detached
                                    </label>
                                    <input @click="layoutStylesFn('detached')" class="form-check-input" type="radio"
                                        name="sidemenu-layout-styles" id="switcher-detached"
                                        :checked="switcher.layoutStyles == 'detached' ? true : false">
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="form-check switch-select">
                                    <label class="form-check-label" for="switcher-double-menu">
                                        Double Menu
                                    </label>
                                    <input @click="layoutStylesFn('double-menu')" class="form-check-input" type="radio"
                                        name="sidemenu-layout-styles" id="switcher-double-menu"
                                        :checked="switcher.layoutStyles == 'double-menu' ? true : false">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="">
                        <p class="switcher-style-head">Page Styles:</p>
                        <div class="row switcher-style gx-0">
                            <div class="col-xl-3 col-6">
                                <div class="form-check switch-select">
                                    <label class="form-check-label" for="switcher-regular">
                                        Regular
                                    </label>
                                    <input @click="pageStylesFn('regular')" class="form-check-input" type="radio"
                                        name="page-styles" id="switcher-regular"
                                        :checked="switcher.pageStyles == 'regular' ? true : false">
                                </div>
                            </div>
                            <div class="col-xl-3 col-6">
                                <div class="form-check switch-select">
                                    <label class="form-check-label" for="switcher-classic">
                                        Classic
                                    </label>
                                    <input @click="pageStylesFn('classic')" class="form-check-input" type="radio"
                                        name="page-styles" id="switcher-classic"
                                        :checked="switcher.pageStyles == 'classic' ? true : false">
                                </div>
                            </div>
                            <div class="col-xl-3 col-6">
                                <div class="form-check switch-select">
                                    <label class="form-check-label" for="switcher-modern">
                                        Modern
                                    </label>
                                    <input @click="pageStylesFn('modern')" class="form-check-input" type="radio"
                                        name="page-styles" id="switcher-modern"
                                        :checked="switcher.pageStyles == 'modern' ? true : false">
                                </div>
                            </div>
                            <div class="col-xl-3 col-6">
                                <div class="form-check switch-select">
                                    <label class="form-check-label" for="switcher-flat">
                                        Flat
                                    </label>
                                    <input @click="pageStylesFn('flat')" class="form-check-input" type="radio"
                                        name="page-styles" id="switcher-modern"
                                        :checked="switcher.pageStyles == 'flat' ? true : false">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="">
                        <p class="switcher-style-head">Layout Width Styles:</p>
                        <div class="row switcher-style gx-0">
                            <div class="col-4">
                                <div class="form-check switch-select">
                                    <label class="form-check-label" for="switcher-default-width">
                                        Default
                                    </label>
                                    <input @click="widthStylesFn('default')" class="form-check-input" type="radio"
                                        name="layout-width" id="switcher-full-width"
                                        :checked="switcher.widthStyles == 'default' ? true : false">
                                </div>
                            </div>
                            <div class="col-5">
                                <div class="form-check switch-select">
                                    <label class="form-check-label" for="switcher-full-width">
                                        Full Width
                                    </label>
                                    <input @click="widthStylesFn('full-width')" class="form-check-input" type="radio"
                                        name="layout-width" id="switcher-full-width"
                                        :checked="switcher.widthStyles == 'fullwidth' ? true : false">
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="form-check switch-select">
                                    <label class="form-check-label" for="switcher-boxed">
                                        Boxed
                                    </label>
                                    <input @click="widthStylesFn('boxed')" class="form-check-input" type="radio"
                                        name="layout-width" id="switcher-boxed"
                                        :checked="switcher.widthStyles == 'boxed' ? true : false">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="">
                        <p class="switcher-style-head">Menu Positions:</p>
                        <div class="row switcher-style gx-0">
                            <div class="col-4">
                                <div class="form-check switch-select">
                                    <label class="form-check-label" for="switcher-menu-fixed">
                                        Fixed
                                    </label>
                                    <input @click="menuPositionFn('fixed')" class="form-check-input" type="radio"
                                        name="menu-positions" id="switcher-menu-fixed"
                                        :checked="switcher.menuPosition == 'fixed' ? true : false">
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="form-check switch-select">
                                    <label class="form-check-label" for="switcher-menu-scroll">
                                        Scrollable
                                    </label>
                                    <input @click="menuPositionFn('scrollable')" class="form-check-input" type="radio"
                                        name="menu-positions" id="switcher-menu-scroll"
                                        :checked="switcher.menuPosition == 'scrollable' ? true : false">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="">
                        <p class="switcher-style-head">Header Positions:</p>
                        <div class="row switcher-style gx-0">
                            <div class="col-4">
                                <div class="form-check switch-select">
                                    <label class="form-check-label" for="switcher-header-fixed">
                                        Fixed
                                    </label>
                                    <input @click="headerPositionFn('fixed')" class="form-check-input" type="radio"
                                        name="header-positions" id="switcher-header-fixed"
                                        :checked="switcher.headerPosition == 'fixed' ? true : false">
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="form-check switch-select">
                                    <label class="form-check-label" for="switcher-header-scroll">
                                        Scrollable
                                    </label>
                                    <input @click="headerPositionFn('scrollable')" class="form-check-input" type="radio"
                                        name="header-positions" id="switcher-header-scroll"
                                        :checked="switcher.headerPosition == 'scrollable' ? true : false">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="tab-pane fade border-0" id="switcher-profile" role="tabpanel"
                    aria-labelledby="switcher-profile-tab" tabindex="0">
                    <div>
                        <div class="theme-colors">
                            <p class="switcher-style-head">Menu Colors:</p>
                            <div class="d-flex switcher-style pb-2">
                                <div class="form-check switch-select me-3">
                                    <input class="form-check-input color-input color-white" data-bs-toggle="tooltip"
                                        data-bs-placement="top" title="Light Menu" type="radio"
                                        @click="menuColorFn('light')" name="menu-colors" id="switcher-menu-light"
                                        :checked="switcher.menuColor == 'light' ? true : false">
                                </div>
                                <div class="form-check switch-select me-3">
                                    <input @click="menuColorFn('dark')" class="form-check-input color-input color-dark"
                                        data-bs-toggle="tooltip" data-bs-placement="top" title="Dark Menu" type="radio"
                                        name="menu-colors" id="switcher-menu-dark"
                                        :checked="switcher.menuColor == 'dark' ? true : false">
                                </div>
                                <div class="form-check switch-select me-3">
                                    <input @click="menuColorFn('color')"
                                        class="form-check-input color-input color-primary" data-bs-toggle="tooltip"
                                        data-bs-placement="top" title="Color Menu" type="radio" name="menu-colors"
                                        id="switcher-menu-primary"
                                        :checked="switcher.menuColor == 'color' ? true : false">
                                </div>
                                <div class="form-check switch-select me-3">
                                    <input @click="menuColorFn('gradient')"
                                        class="form-check-input color-input color-gradient" data-bs-toggle="tooltip"
                                        data-bs-placement="top" title="Gradient Menu" type="radio" name="menu-colors"
                                        id="switcher-menu-gradient"
                                        :checked="switcher.menuColor == 'gradient' ? true : false">
                                </div>
                                <div class="form-check switch-select me-3">
                                    <input @click="menuColorFn('transparent')"
                                        class="form-check-input color-input color-transparent" data-bs-toggle="tooltip"
                                        data-bs-placement="top" title="Transparent Menu" type="radio" name="menu-colors"
                                        id="switcher-menu-transparent"
                                        :checked="switcher.menuColor == 'transparent' ? true : false">
                                </div>
                            </div>
                            <div class="px-4 pb-3 text-muted fs-11">Note:If you want to change color Menu dynamically
                                change
                                from below Theme Primary color picker</div>
                        </div>
                        <div class="theme-colors">
                            <p class="switcher-style-head">Header Colors:</p>
                            <div class="d-flex switcher-style pb-2">
                                <div class="form-check switch-select me-3">
                                    <input @click="headerColorFn('light')"
                                        class="form-check-input color-input color-white" data-bs-toggle="tooltip"
                                        data-bs-placement="top" title="Light Header" type="radio" name="header-colors"
                                        id="switcher-header-light"
                                        :checked="switcher.headerColor == 'light' ? true : false">
                                </div>
                                <div class="form-check switch-select me-3">
                                    <input @click="headerColorFn('dark')"
                                        class="form-check-input color-input color-dark" data-bs-toggle="tooltip"
                                        data-bs-placement="top" title="Dark Header" type="radio" name="header-colors"
                                        id="switcher-header-dark"
                                        :checked="switcher.headerColor == 'dark' ? true : false">
                                </div>
                                <div class="form-check switch-select me-3">
                                    <input @click="headerColorFn('color')"
                                        class="form-check-input color-input color-primary" data-bs-toggle="tooltip"
                                        data-bs-placement="top" title="Color Header" type="radio" name="header-colors"
                                        id="switcher-header-primary"
                                        :checked="switcher.headerColor == 'color' ? true : false">
                                </div>
                                <div class="form-check switch-select me-3">
                                    <input @click="headerColorFn('gradient')"
                                        class="form-check-input color-input color-gradient" data-bs-toggle="tooltip"
                                        data-bs-placement="top" title="Gradient Header" type="radio"
                                        name="header-colors" id="switcher-header-gradient"
                                        :checked="switcher.headerColor == 'gradient' ? true : false">
                                </div>
                                <div class="form-check switch-select me-3">
                                    <input @click="headerColorFn('transparent')"
                                        class="form-check-input color-input color-transparent" data-bs-toggle="tooltip"
                                        data-bs-placement="top" title="Transparent Header" type="radio"
                                        name="header-colors" id="switcher-header-transparent"
                                        :checked="switcher.headerColor == 'transparent' ? true : false">
                                </div>
                            </div>
                            <div class="px-4 pb-3 text-muted fs-11">Note:If you want to change color Header dynamically
                                change from below Theme Primary color picker</div>
                        </div>
                        <div class="theme-colors">
                            <p class="switcher-style-head">Theme Primary:</p>
                            <div class="d-flex flex-wrap align-items-center switcher-style">
                                <div class="form-check switch-select me-3">
                                    <input @click="themePrimaryFn('42 ,16, 164')"
                                        class="form-check-input color-input color-primary-1" type="radio"
                                        name="theme-primary" id="switcher-primary"
                                        :checked="switcher.themePrimary == '42 ,16, 164' ? true : false">
                                </div>
                                <div class="form-check switch-select me-3">
                                    <input @click="themePrimaryFn('125 ,0, 189')"
                                        class="form-check-input color-input color-primary-2" type="radio"
                                        name="theme-primary" id="switcher-primary1"
                                        :checked="switcher.themePrimary == '125 ,0, 189' ? true : false">
                                </div>
                                <div class="form-check switch-select me-3">
                                    <input @click="themePrimaryFn('4, 118, 141')"
                                        class="form-check-input color-input color-primary-3" type="radio"
                                        name="theme-primary" id="switcher-primary2"
                                        :checked="switcher.themePrimary == '4, 118, 141' ? true : false">
                                </div>
                                <div class="form-check switch-select me-3">
                                    <input @click="themePrimaryFn('138, 0, 32')"
                                        class="form-check-input color-input color-primary-4" type="radio"
                                        name="theme-primary" id="switcher-primary3"
                                        :checked="switcher.themePrimary == '138, 0, 32' ? true : false">
                                </div>
                                <div class="form-check switch-select me-3">
                                    <input @click="themePrimaryFn('9 ,124, 103')"
                                        class="form-check-input color-input color-primary-5" type="radio"
                                        name="theme-primary" id="switcher-primary4"
                                        :checked="switcher.themePrimary == '9 ,124, 103' ? true : false">
                                </div>
                                <div class="form-check switch-select ps-0 mt-1 color-primary-light">
                                    <color-picker @update:pureColor="primaryColorFn"
                                        v-model:dynamicPrimaryColor="dynamicPrimaryColor" shape="circle" format="rgb"
                                        pickerType="chrome" useType="pure" :disableAlpha="true" />
                                </div>
                            </div>
                        </div>
                        <div class="theme-colors">
                            <p class="switcher-style-head">Theme Background:</p>
                            <div class="d-flex flex-wrap align-items-center switcher-style">
                                <div class="form-check switch-select me-3">
                                    <input @click="themeBackgroundFn('0,8,52', '14,22,66')"
                                        class="form-check-input color-input color-bg-1" type="radio"
                                        name="theme-background" id="switcher-background"
                                        :checked="switcher.themeBackground == `0,8,52, 14,22,66` ? true : false">
                                </div>
                                <div class="form-check switch-select me-3">
                                    <input @click="themeBackgroundFn('58, 0, 109', '72 ,14, 123')"
                                        class="form-check-input color-input color-bg-2" type="radio"
                                        :checked="switcher.themeBackground == `58, 0, 109, 72 ,14, 123` ? true : false"
                                        name="theme-background" id="switcher-background1">
                                </div>
                                <div class="form-check switch-select me-3">
                                    <input @click="themeBackgroundFn('0 ,59, 70', '14, 73, 84')"
                                        class="form-check-input color-input color-bg-3" type="radio"
                                        :checked="switcher.themeBackground == `0 ,59, 70, 14, 73, 84` ? true : false"
                                        name="theme-background" id="switcher-background2">
                                </div>
                                <div class="form-check switch-select me-3">
                                    <input @click="themeBackgroundFn('65, 0, 0', '79 ,14, 14')"
                                        class="form-check-input color-input color-bg-4" type="radio"
                                        :checked="switcher.themeBackground == `65, 0, 0, 79 ,14, 14` ? true : false"
                                        name="theme-background" id="switcher-background3">
                                </div>
                                <div class="form-check switch-select me-3">
                                    <input @click="themeBackgroundFn('1 ,77, 46', '15 ,91, 60')"
                                        class="form-check-input color-input color-bg-5" type="radio"
                                        :checked="switcher.themeBackground == `1 ,77, 46, 15 ,91, 60` ? true : false"
                                        name="theme-background" id="switcher-background4">
                                </div>
                                <div
                                    class="form-check switch-select ps-0 mt-1 tooltip-static-demo color-bg-transparent">
                                    <color-picker @update:pureColor="dynamicBackgroundColorFn"
                                        v-model="dynamicBackgroundColor" shape="circle" format="rgb" pickerType="chrome"
                                        useType="pure" :disableAlpha="true"><i class="icon">ICON_HERE</i></color-picker>
                                </div>
                            </div>
                        </div>
                        <div class="menu-image mb-3">
                            <p class="switcher-style-head">Menu With Background Image:</p>
                            <div class="d-flex flex-wrap align-items-center switcher-style">
                                <div class="form-check switch-select menu-img-select m-2">
                                    <input @click="backgroundImageFn('bgimg1')"
                                        class="form-check-input bgimage-input bg-img1" type="radio"
                                        name="theme-background" id="switcher-bg-img"
                                        :checked="switcher.backgroundImage == 'bgimg1' ? true : false">
                                    <div class="bg-img-container">
                                        <img :src="bg_img1" alt="">
                                    </div>
                                </div>
                                <div class="form-check switch-select menu-img-select m-2">
                                    <input @click="backgroundImageFn('bgimg2')"
                                        class="form-check-input bgimage-input bg-img2" type="radio"
                                        name="theme-background" id="switcher-bg-img1"
                                        :checked="switcher.backgroundImage == 'bgimg2' ? true : false">
                                    <div class="bg-img-container">
                                        <img :src="bg_img2" alt="">
                                    </div>
                                </div>
                                <div class="form-check switch-select menu-img-select m-2">
                                    <input @click="backgroundImageFn('bgimg3')"
                                        class="form-check-input bgimage-input bg-img3" type="radio"
                                        name="theme-background" id="switcher-bg-img2"
                                        :checked="switcher.backgroundImage == 'bgimg3' ? true : false">
                                    <div class="bg-img-container">
                                        <img :src="bg_img3" alt="">
                                    </div>
                                </div>
                                <div class="form-check switch-select menu-img-select m-2">
                                    <input @click="backgroundImageFn('bgimg4')"
                                        class="form-check-input bgimage-input bg-img4" type="radio"
                                        name="theme-background" id="switcher-bg-img3"
                                        :checked="switcher.backgroundImage == 'bgimg4' ? true : false">
                                    <div class="bg-img-container">
                                        <img :src="bg_img4" alt="">
                                    </div>
                                </div>
                                <div class="form-check switch-select menu-img-select m-2">
                                    <input @click="backgroundImageFn('bgimg5')"
                                        class="form-check-input bgimage-input bg-img5" type="radio"
                                        name="theme-background" id="switcher-bg-img4"
                                        :checked="switcher.backgroundImage == 'bgimg5' ? true : false">
                                    <div class="bg-img-container">
                                        <img :src="bg_img5" alt="">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="d-flex justify-content-between canvas-footer flex-nowrap">
                    <a href="javascript:void(0);" id="reset-all" class="btn btn-danger m-1 w-100" @click="reset()">Reset</a>
                </div>
            </div>
        </div>
    </div>
    <!-- End Switcher -->
</template>
