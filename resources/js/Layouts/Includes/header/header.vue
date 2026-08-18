<template>
    <header class="app-header sticky" id="header">

        <!-- Start::main-header-container -->
        <div class="main-header-container container-fluid">

            <!-- Start::header-content-left -->
            <div class="header-content-left">

                <!-- Start::header-element -->
                <div class="header-element">
                    <div class="horizontal-logo">
                        <Link href="/dashboard" class="header-logo">
                            <img src="/images/logo/logo-plus-metals.webp" alt="logo" class="desktop-logo">
                            <img src="/images/logo/logo-min.png" alt="logo" class="toggle-logo">
                            <img src="/images/logo/logo-plus-metals.webp" alt="logo" class="desktop-dark">
                            <img src="/images/logo/logo-min.png" alt="logo" class="toggle-dark">
                        </Link>
                    </div>
                </div>
                <!-- End::header-element -->

                <!-- Start::header-element -->
                <div class="header-element mx-lg-0 mx-2">
                    <a aria-label="Hide Sidebar" @click="ToggleMenu"
                        class="sidemenu-toggle header-link animated-arrow hor-toggle horizontal-navtoggle"
                        data-bs-toggle="sidebar" href="javascript:void(0);"><span></span></a>
                </div>
                <!-- End::header-element -->

                <div class="header-element header-search d-md-block d-none">
                    <div class="autoComplete_wrapper d-none">
                        <!-- Start::header-link -->
                        <input type="text" class="header-search-bar form-control bg-white" id="header-search"
                            :value="search" @input="handleToChange" placeholder="Search" spellcheck=false
                            autocomplete="off" autocapitalize="off">
                        <template v-if="showSuggestions">
                            <div
                                className="custom-card card w-100 search-result position-absolute z-index-9 search-fix border mt-1">
                                <div className="card-header">
                                    <div className="card-title mb-0 text-break">Search result of {{ search }}</div>
                                </div>
                                <div className="card-body overflow-auto">
                                    <div class="list-group custom-header" Id="autoComplete_list_1">
                                        <template v-if="uniqueSuggestions.length > 0">
                                            <li id="autoComplete_result_0" class="list-group-item li-Class"
                                                v-for="(e, index) in uniqueSuggestions.slice(0, 7)" :key="index">
                                                <Link :href="`${e.path}/`" class="search-result-item"
                                                    @click="handleSuggestionClick(e.title)">
                                                    {{ e.title }}
                                                </Link>
                                            </li>
                                        </template>
                                        <template v-else>
                                            <b class='text-danger'>There is no component with this name</b>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </template>
                        <a href="javascript:void(0);" class="header-search-icon border-0">
                            <i class="bi bi-search fs-12 mb-1"></i>
                        </a>
                        <!-- End::header-link -->
                    </div>
                </div>

            </div>
            <!-- End::header-content-left -->

            <!-- Start::header-content-right -->
            <ul class="header-content-right">

                <!-- Start::header-element -->
                <li class="header-element d-md-none d-block">
                    <a href="javascript:void(0);" class="header-link" data-bs-toggle="modal"
                        data-bs-target="#header-responsive-search">
                        <!-- Start::header-link-icon -->
                        <svg xmlns="http://www.w3.org/2000/svg" class="header-link-icon" viewBox="0 0 256 256">
                            <rect width="256" height="256" fill="none" />
                            <circle cx="112" cy="112" r="80" opacity="0.2" />
                            <circle cx="112" cy="112" r="80" fill="none" stroke="currentColor" stroke-linecap="round"
                                stroke-linejoin="round" stroke-width="16" />
                            <line x1="168.57" y1="168.57" x2="224" y2="224" fill="none" stroke="currentColor"
                                stroke-linecap="round" stroke-linejoin="round" stroke-width="16" />
                        </svg>
                        <!-- End::header-link-icon -->
                    </a>
                </li>
                <!-- End::header-element -->

                <!-- Start::header-element -->
                <li class="header-element country-selector dropdown  d-none">
                    <!-- Start::header-link|dropdown-toggle -->
                    <a href="javascript:void(0);" class="header-link dropdown-toggle" data-bs-auto-close="outside"
                        data-bs-toggle="dropdown">
                        <svg xmlns="http://www.w3.org/2000/svg" class="header-link-icon" viewBox="0 0 256 256">
                            <rect width="256" height="256" fill="none" />
                            <path
                                d="M215,168.71a96.42,96.42,0,0,1-30.54,37l-9.36-9.37a8,8,0,0,0-3.63-2.09L150,188.59a8,8,0,0,1-5.88-8.9l2.38-16.2a8,8,0,0,1,4.85-6.22l30.45-12.66a8,8,0,0,1,8.47,1.49Z"
                                opacity="0.2" />
                            <path
                                d="M184,74a8,8,0,0,1-1.94,5.22L159.89,105a8,8,0,0,1-5,2.71l-31.46,4.26a8.06,8.06,0,0,1-5.77-1.45l-19.81-13a8,8,0,0,0-11.34,2l-20.94,31.3a8.06,8.06,0,0,0-1.35,4.41L64,171.49a8,8,0,0,1-3.61,6.64l-9.92,6.52A96,96,0,0,1,184,50Z"
                                opacity="0.2" />
                            <circle cx="128" cy="128" r="96" fill="none" stroke="currentColor" stroke-linecap="round"
                                stroke-linejoin="round" stroke-width="16" />
                            <path
                                d="M184.42,205.68l-9.36-9.37a8,8,0,0,0-3.63-2.09L150,188.59a8,8,0,0,1-5.88-8.9l2.38-16.2a8,8,0,0,1,4.85-6.22l30.45-12.66a8,8,0,0,1,8.47,1.49L215,168.71"
                                fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
                                stroke-width="16" />
                            <path
                                d="M50.49,184.65l9.92-6.52A8,8,0,0,0,64,171.49l.21-36.23a8.06,8.06,0,0,1,1.35-4.41l20.94-31.3a8,8,0,0,1,11.34-2l19.81,13a8.06,8.06,0,0,0,5.77,1.45l31.46-4.26a8,8,0,0,0,5-2.71L182.06,79.2A8,8,0,0,0,184,74V50"
                                fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
                                stroke-width="16" />
                        </svg>
                    </a>
                    <!-- End::header-link|dropdown-toggle -->
                    <ul class="main-header-dropdown dropdown-menu dropdown-menu-end" data-popper-placement="none">
                        <li v-for="(lang, index) in Languages" :key="index">
                            <a class="dropdown-item d-flex align-items-center" href="javascript:void(0);">
                                <span class="avatar avatar-rounded avatar-xs lh-1 me-2">
                                    <img :src="lang.flag" alt="img">
                                </span>
                                {{ lang.name }}
                            </a>
                        </li>
                    </ul>
                </li>
                <!-- End::header-element -->

                <!-- Start::header-element -->
                <li class="header-element header-theme-mode ">
                    <!-- Start::header-link|layout-setting -->
                    <a href="javascript:void(0);" class="header-link layout-setting">
                        <span class="light-layout" @click="colorthemeFn('dark')">
                            <!-- Start::header-link-icon -->
                            <svg xmlns="http://www.w3.org/2000/svg" class="header-link-icon" viewBox="0 0 256 256">
                                <rect width="256" height="256" fill="none" />
                                <path d="M108.11,28.11A96.09,96.09,0,0,0,227.89,147.89,96,96,0,1,1,108.11,28.11Z"
                                    opacity="0.2" />
                                <path d="M108.11,28.11A96.09,96.09,0,0,0,227.89,147.89,96,96,0,1,1,108.11,28.11Z"
                                    fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
                                    stroke-width="16" />
                            </svg>
                            <!-- End::header-link-icon -->
                        </span>
                        <span class="dark-layout" @click="colorthemeFn('light')">
                            <!-- Start::header-link-icon -->
                            <svg xmlns="http://www.w3.org/2000/svg" class="header-link-icon" viewBox="0 0 256 256">
                                <rect width="256" height="256" fill="none" />
                                <circle cx="128" cy="128" r="56" opacity="0.2" />
                                <line x1="128" y1="40" x2="128" y2="32" fill="none" stroke="currentColor"
                                    stroke-linecap="round" stroke-linejoin="round" stroke-width="16" />
                                <circle cx="128" cy="128" r="56" fill="none" stroke="currentColor"
                                    stroke-linecap="round" stroke-linejoin="round" stroke-width="16" />
                                <line x1="64" y1="64" x2="56" y2="56" fill="none" stroke="currentColor"
                                    stroke-linecap="round" stroke-linejoin="round" stroke-width="16" />
                                <line x1="64" y1="192" x2="56" y2="200" fill="none" stroke="currentColor"
                                    stroke-linecap="round" stroke-linejoin="round" stroke-width="16" />
                                <line x1="192" y1="64" x2="200" y2="56" fill="none" stroke="currentColor"
                                    stroke-linecap="round" stroke-linejoin="round" stroke-width="16" />
                                <line x1="192" y1="192" x2="200" y2="200" fill="none" stroke="currentColor"
                                    stroke-linecap="round" stroke-linejoin="round" stroke-width="16" />
                                <line x1="40" y1="128" x2="32" y2="128" fill="none" stroke="currentColor"
                                    stroke-linecap="round" stroke-linejoin="round" stroke-width="16" />
                                <line x1="128" y1="216" x2="128" y2="224" fill="none" stroke="currentColor"
                                    stroke-linecap="round" stroke-linejoin="round" stroke-width="16" />
                                <line x1="216" y1="128" x2="224" y2="128" fill="none" stroke="currentColor"
                                    stroke-linecap="round" stroke-linejoin="round" stroke-width="16" />
                            </svg>
                            <!-- End::header-link-icon -->
                        </span>
                    </a>
                    <!-- End::header-link|layout-setting -->
                </li>
                <!-- End::header-element -->

                <!-- Start::header-element -->
                <li class="header-element cart-dropdown dropdown d-none">
                    <!-- Start::header-link|dropdown-toggle -->
                    <a href="javascript:void(0);" class="header-link dropdown-toggle" data-bs-auto-close="outside"
                        data-bs-toggle="dropdown">
                        <svg xmlns="http://www.w3.org/2000/svg" class="header-link-icon" viewBox="0 0 256 256">
                            <rect width="256" height="256" fill="none" />
                            <path d="M70.55,144H196.1a16,16,0,0,0,15.74-13.14L224,64H56Z" opacity="0.2" />
                            <path d="M188,184H91.17a16,16,0,0,1-15.74-13.14L48.73,24H24" fill="none"
                                stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
                                stroke-width="16" />
                            <circle cx="92" cy="204" r="20" fill="none" stroke="currentColor" stroke-linecap="round"
                                stroke-linejoin="round" stroke-width="16" />
                            <circle cx="188" cy="204" r="20" fill="none" stroke="currentColor" stroke-linecap="round"
                                stroke-linejoin="round" stroke-width="16" />
                            <path d="M70.55,144H196.1a16,16,0,0,0,15.74-13.14L224,64H56" fill="none"
                                stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
                                stroke-width="16" />
                        </svg>
                        <span class="badge bg-primary rounded-pill header-icon-badge" id="cart-icon-badge">5</span>
                    </a>
                    <!-- End::header-link|dropdown-toggle -->
                    <!-- Start::main-header-dropdown -->
                    <div class="main-header-dropdown dropdown-menu dropdown-menu-end" data-popper-placement="none">
                        <div class="p-3 bg-primary text-fixed-white">
                            <div class="d-flex align-items-center justify-content-between">
                                <p class="mb-0 fs-16">Cart Items<span class="badge bg-warning ms-1 fs-12 rounded-circle"
                                        id="cart-data">{{ notificationNotes.length }}</span></p>
                                <Link href="#!"
                                    class="text-fixed-white text-decoration-underline fs-12">Continue Shopping <i
                                        class="ti ti-arrow-narrow-right"></i></Link>
                            </div>
                        </div>
                        <div class="dropdown-divider"></div>
                        <ul class="list-unstyled mb-0">
                            <PerfectScrollbar id="header-cart-items-scroll">
                                <li :class="`dropdown-item ${item.liclass}`" v-for="(item, index) in notificationNotes"
                                    :key="index">
                                    <div class="d-flex align-items-start cart-dropdown-item gap-3">
                                        <div class="lh-1">
                                            <span class="avatar avatar-xl bg-gray-300">
                                                <img :src="item.image" alt="img">
                                            </span>
                                        </div>
                                        <div class="flex-fill w-75">
                                            <div class="d-flex align-items-start justify-content-between mb-3">
                                                <div class="fs-14 fw-medium w-75">
                                                    <div class="text-truncate">
                                                        <Link href="#!">{{ item.name }}
                                                        </Link>
                                                    </div>
                                                    <div class="fs-11 text-muted text-truncate">
                                                        <span>{{ item.description }}</span>
                                                    </div>
                                                </div>
                                                <div class="text-end">
                                                    <a href="javascript:void(0);"
                                                        class="header-cart-remove dropdown-item-close"
                                                        @click="handleCartDelete(item.id)"><i
                                                            class="ri-delete-bin-line"></i></a>
                                                </div>
                                            </div>
                                            <div class="d-flex align-items-center justify-content-between">
                                                <div class="flex-fill">
                                                    <div class="lh-1 fs-12 mb-1">
                                                        <span
                                                            class="text-muted fw-normal d-inline-block text-decoration-line-through">{{
                                                                item.oldprice }}</span><span class="text-success ms-1">{{ item.off }}
                                                            off</span>
                                                    </div>
                                                    <h6 class="fw-medium mb-0">{{ item.newprice }}</h6>
                                                </div>
                                                <div
                                                    class="d-flex rounded align-items-center flex-nowrap order-qnt gap-2">
                                                    <Quantity :defaultValue="item.quantity"
                                                        decClass="badge bg-white p-1 border text-muted fs-13 product-quantity-minus"
                                                        inputClass="form-control form-control-cart border-0 text-center w-100"
                                                        incClass="badge bg-white p-1 border text-muted fs-13 product-quantity-plus" />
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                            </PerfectScrollbar>
                        </ul>
                        <div class="p-3 empty-header-item border-top" v-if="notificationNotes.length">
                            <div class="text-center d-grid">
                                <Link href="#!" class="btn btn-primary btn-wave">
                                    Proceed to
                                    checkout</Link>
                            </div>
                        </div>
                        <div class="p-5 empty-item" v-if="!notificationNotes.length">
                            <div class="text-center">
                                <span class="avatar avatar-xl avatar-rounded bg-success-transparent">
                                    <i class="ti ti-shopping-cart fs-2"></i>
                                </span>
                                <h6 class="fw-medium mb-1 mt-3">No items in your cart yet</h6>
                                <span class="mb-3 fw-normal fs-13 d-block">Add some to enjoy a seamless checkout
                                    experience!
                                    :)</span>
                            </div>
                        </div>
                    </div>
                    <!-- End::main-header-dropdown -->
                </li>
                <!-- End::header-element -->

                <!-- Start::header-element -->
                <li class="header-element notifications-dropdown d-xl-block d-none dropdown">
                    <!-- Start::header-link|dropdown-toggle -->
                    <a href="javascript:void(0);" class="header-link dropdown-toggle" data-bs-toggle="dropdown"
                        data-bs-auto-close="outside" id="messageDropdown" aria-expanded="false">
                        <svg xmlns="http://www.w3.org/2000/svg" class="header-link-icon" viewBox="0 0 256 256">
                            <rect width="256" height="256" fill="none" />
                            <path
                                d="M56,104a72,72,0,0,1,144,0c0,35.82,8.3,64.6,14.9,76A8,8,0,0,1,208,192H48a8,8,0,0,1-6.88-12C47.71,168.6,56,139.81,56,104Z"
                                opacity="0.2" />
                            <path d="M96,192a32,32,0,0,0,64,0" fill="none" stroke="currentColor" stroke-linecap="round"
                                stroke-linejoin="round" stroke-width="16" />
                            <path
                                d="M56,104a72,72,0,0,1,144,0c0,35.82,8.3,64.6,14.9,76A8,8,0,0,1,208,192H48a8,8,0,0,1-6.88-12C47.71,168.6,56,139.81,56,104Z"
                                fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
                                stroke-width="16" />
                        </svg>
                        <span v-if="notificacionesNoLeidas > 0"
                            class="header-icon-pulse bg-danger rounded pulse pulse-secondary"></span>
                        <span v-if="notificacionesNoLeidas > 0"
                            class="badge bg-danger rounded-pill header-icon-badge">{{ notificacionesNoLeidas }}</span>
                    </a>
                    <!-- End::header-link|dropdown-toggle -->
                    <!-- Start::main-header-dropdown -->
                    <div class="main-header-dropdown dropdown-menu dropdown-menu-end" data-popper-placement="none">
                        <div class="p-3 bg-primary text-fixed-white">
                            <div class="d-flex align-items-center justify-content-between">
                                <p class="mb-0 fs-16">Notificaciones</p>
                                <a v-if="notificacionesNoLeidas > 0" href="javascript:void(0);"
                                    class="badge bg-light text-default border" @click="marcarTodasLeidas">Marcar
                                    todas leídas</a>
                            </div>
                        </div>
                        <div class="dropdown-divider"></div>
                        <template v-if="notificaciones.length">
                            <PerfectScrollbar class="list-unstyled mb-0" id="header-notification-scroll">
                                <li :class="`dropdown-item position-relative ${idx.leida ? '' : 'bg-primary-transparent'}`"
                                    v-for="(idx) in notificaciones" :key="idx.id">
                                    <a href="javascript:void(0);" class="stretched-link"
                                        @click="abrirNotificacion(idx)"></a>
                                    <div class="d-flex align-items-start gap-3">
                                        <div class="lh-1">
                                            <span class="avatar avatar-sm avatar-rounded bg-primary-transparent">
                                                <i :class="idx.icono" class="fs-16"></i>
                                            </span>
                                        </div>
                                        <div class="flex-fill">
                                            <span class="d-block fw-semibold">{{ idx.titulo }}</span>
                                            <span class="d-block text-muted fs-12">{{ idx.descripcion }}</span>
                                        </div>
                                        <div class="text-end">
                                            <span class="d-block mb-1 fs-12 text-muted text-nowrap">{{ idx.fecha }}</span>
                                            <span :class="`d-block text-primary ${idx.leida ? 'd-none' : ''} `"><i
                                                    class="ri-circle-fill fs-9"></i></span>
                                        </div>
                                    </div>
                                </li>
                            </PerfectScrollbar>
                        </template>
                        <div class="p-5 empty-item1" v-else>
                            <div class="text-center">
                                <span class="avatar avatar-xl avatar-rounded bg-secondary-transparent">
                                    <i class="ri-notification-off-line fs-2"></i>
                                </span>
                                <h6 class="fw-medium mt-3">Sin notificaciones nuevas</h6>
                            </div>
                        </div>
                    </div>
                    <!-- End::main-header-dropdown -->
                </li>
        <!-- End::header-element -->

        <!-- Start::header-element -->
        <li class="header-element header-fullscreen">
            <a @click="toggleFullScreen();" href="javascript:void(0);" class="header-link">
                <svg  v-if="!isFullScreen" xmlns="http://www.w3.org/2000/svg" class="full-screen-open header-link-icon d-block" viewBox="0 0 256 256"><rect width="256" height="256" fill="none"></rect><rect x="48" y="48" width="160" height="160" opacity="0.2"></rect><polyline points="168 48 208 48 208 88" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16"></polyline><polyline points="88 208 48 208 48 168" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16"></polyline><polyline points="208 168 208 208 168 208" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16"></polyline><polyline points="48 88 48 48 88 48" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16"></polyline></svg>
                <svg v-if="isFullScreen" xmlns="http://www.w3.org/2000/svg" class="full-screen-close header-link-icon" viewBox="0 0 256 256"><rect width="256" height="256" fill="none"></rect><rect x="32" y="32" width="192" height="192" rx="16" opacity="0.2"></rect><polyline points="160 48 208 48 208 96" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16"></polyline><line x1="144" y1="112" x2="208" y2="48" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16"></line><polyline points="96 208 48 208 48 160" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16"></polyline><line x1="112" y1="144" x2="48" y2="208" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16"></line></svg>
            </a>
            <!-- End::header-link -->
        </li>
        <!-- End::header-element -->

        <!-- Start::header-element -->
        <li class="header-element dropdown">
            <!-- Start::header-link|dropdown-toggle -->
            <a href="javascript:void(0);" class="header-link dropdown-toggle" id="mainHeaderProfile"
                data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                <div>
                    <img src="/images/faces/12.jpg" alt="img" class="header-link-icon">
                </div>
            </a>
            <!-- End::header-link|dropdown-toggle -->
            <div class="main-header-dropdown dropdown-menu pt-0 overflow-hidden header-profile-dropdown dropdown-menu-end"
                aria-labelledby="mainHeaderProfile">
                <div class="p-3 bg-primary text-fixed-white">
                    <div class="d-flex align-items-center justify-content-between">
                        <p class="mb-0 fs-16">Perfil</p>
                        <a href="javascript:void(0);" class="text-fixed-white"><i class="ti ti-settings-cog"></i></a>
                    </div>
                </div>
                <div class="dropdown-divider"></div>
                <div class="p-3">
                    <div class="d-flex align-items-start gap-2">
                        <div class="lh-1">
                            <span class="avatar avatar-sm bg-primary-transparent avatar-rounded">
                                <img src="/images/faces/12.jpg" alt="">
                            </span>
                        </div>
                        <div>
                            <span class="d-block fw-semibold lh-1">
                               {{ $page.props.auth.user.persona?.nombre_completo }}
                            </span>
                            <span class="text-muted fs-12">{{ $page.props.auth.user.email }}</span>
                        </div>
                    </div>
                </div>
                <div class="dropdown-divider"></div>
                <ul class="list-unstyled mb-0">
                    <li>
                        <ul class="list-unstyled mb-0 sub-list">
                            <li>
                                <Link class="dropdown-item d-flex align-items-center" href="#!"><i
                                        class="ti ti-user-circle me-2 fs-18"></i>Ver Perfil</Link>
                            </li>

                        </ul>
                    </li>
                    <li>
                        <ul class="list-unstyled mb-0 sub-list">
                            <li>
                                <a class="dropdown-item d-flex align-items-center" href="javascript:void(0);"><i
                                        class="ti ti-lifebuoy me-2 fs-18"></i>Ayuda</a>
                            </li>


                        </ul>
                    </li>
                    <li>
                        <Link href="#!" class="dropdown-item d-flex align-items-center" @click.prevent="logout"><i
                                class="ti ti-logout me-2 fs-18"></i>Cerrar Sesión</Link>
                    </li>
                </ul>
            </div>
        </li>
        <!-- End::header-element -->

        <!-- Start::header-element -->
        <li class="header-element">
            <!-- Start::header-link|switcher-icon -->
            <a href="javascript:void(0);" class="header-link switcher-icon" data-bs-toggle="offcanvas"
                data-bs-target="#switcher-canvas">
                <svg xmlns="http://www.w3.org/2000/svg" class="header-link-icon" viewBox="0 0 256 256">
                    <rect width="256" height="256" fill="none" />
                    <path
                        d="M207.86,123.18l16.78-21a99.14,99.14,0,0,0-10.07-24.29l-26.7-3a81,81,0,0,0-6.81-6.81l-3-26.71a99.43,99.43,0,0,0-24.3-10l-21,16.77a81.59,81.59,0,0,0-9.64,0l-21-16.78A99.14,99.14,0,0,0,77.91,41.43l-3,26.7a81,81,0,0,0-6.81,6.81l-26.71,3a99.43,99.43,0,0,0-10,24.3l16.77,21a81.59,81.59,0,0,0,0,9.64l-16.78,21a99.14,99.14,0,0,0,10.07,24.29l26.7,3a81,81,0,0,0,6.81,6.81l3,26.71a99.43,99.43,0,0,0,24.3,10l21-16.77a81.59,81.59,0,0,0,9.64,0l21,16.78a99.14,99.14,0,0,0,24.29-10.07l3-26.7a81,81,0,0,0,6.81-6.81l26.71-3a99.43,99.43,0,0,0,10-24.3l-16.77-21A81.59,81.59,0,0,0,207.86,123.18ZM128,168a40,40,0,1,1,40-40A40,40,0,0,1,128,168Z"
                        opacity="0.2" />
                    <circle cx="128" cy="128" r="40" fill="none" stroke="currentColor" stroke-linecap="round"
                        stroke-linejoin="round" stroke-width="16" />
                    <path
                        d="M41.43,178.09A99.14,99.14,0,0,1,31.36,153.8l16.78-21a81.59,81.59,0,0,1,0-9.64l-16.77-21a99.43,99.43,0,0,1,10.05-24.3l26.71-3a81,81,0,0,1,6.81-6.81l3-26.7A99.14,99.14,0,0,1,102.2,31.36l21,16.78a81.59,81.59,0,0,1,9.64,0l21-16.77a99.43,99.43,0,0,1,24.3,10.05l3,26.71a81,81,0,0,1,6.81,6.81l26.7,3a99.14,99.14,0,0,1,10.07,24.29l-16.78,21a81.59,81.59,0,0,1,0,9.64l16.77,21a99.43,99.43,0,0,1-10,24.3l-26.71,3a81,81,0,0,1-6.81,6.81l-3,26.7a99.14,99.14,0,0,1-24.29,10.07l-21-16.78a81.59,81.59,0,0,1-9.64,0l-21,16.77a99.43,99.43,0,0,1-24.3-10l-3-26.71a81,81,0,0,1-6.81-6.81Z"
                        fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
                        stroke-width="16" />
                </svg>
            </a>
            <!-- End::header-link|switcher-icon -->
        </li>
        <!-- End::header-element -->

        </ul>
        <!-- End::header-content-right -->

        </div>
        <!-- End::main-header-container -->

    </header>

    <div class="modal fade" id="header-responsive-search" tabindex="-1" aria-labelledby="header-responsive-search"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-body">
                    <div class="input-group">
                        <input type="text" class="form-control border-end-0" placeholder="Search Anything ..."
                            :value="search" @input="handleToChange" aria-label="Search Anything ..."
                            aria-describedby="button-addon2">
                        <template v-if="showSuggestions">
                            <div
                                className="custom-card card w-100 search-result position-absolute z-index-9 search-fix border mt-15">
                                <div className="card-header">
                                    <div className="card-title mb-0 text-break">Search result of
                                        <b><u>{{ search }}</u></b>
                                    </div>
                                </div>
                                <div className="card-body overflow-auto">
                                    <div class="m-2 list-group" Id="autoComplete_list_1">
                                        <template v-if="uniqueSuggestions.length > 0">
                                            <li id="autoComplete_result_0" class="list-group-item"
                                                v-for="(e, index) in uniqueSuggestions.slice(0, 7)" :key="index">
                                                <Link :href="`${e.path}/`" class="search-result-item"
                                                    @click="handleSuggestionClick('Sugestion Title')">
                                                    {{ e.title }}
                                                </Link>
                                            </li>
                                        </template>
                                        <template v-else>
                                            <b class='text-danger'>There is no component with this name</b>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </template>
                        <button class="btn btn-primary" type="button" id="button-addon2"><i
                                class="bi bi-search"></i></button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Tooltip } from 'bootstrap';
import { PerfectScrollbar } from 'vue3-perfect-scrollbar';
import 'vue3-perfect-scrollbar/style.css';
import { Languages, notificationNotes as initialNotificationNotes } from '@/Data/header';
import { switcherStore } from '@/stores/switcher';
import { MENUITEMS } from '@/Data/sidebar/nav';
import { useAuthStore } from '@/stores/auth';
import Quantity from '@/UI/quantity.vue';

import { Link, router, usePage, usePoll } from '@inertiajs/vue3';

// Stores
const switcher = switcherStore();
const { logUserOut } = useAuthStore();

// Refs
const isFullScreen = ref(false);
const search = ref('');
const showSuggestions = ref(false);
const notificationNotes = ref([...initialNotificationNotes]);

// Notificaciones del usuario (compartidas por HandleInertiaRequests)
const page = usePage();
const notificaciones = computed(() => (page.props as any).notificaciones?.items ?? []);
const notificacionesNoLeidas = computed(() => (page.props as any).notificaciones?.no_leidas ?? 0);

// Refresca las notificaciones en segundo plano sin recargar el resto de la página.
usePoll(30000, { only: ['notificaciones'] });

const abrirNotificacion = (item: { id: string; leida: boolean; url: string | null }) => {
    if (!item.leida) {
        router.post(route('notificaciones.marcar-leida', item.id), {}, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                if (item.url) router.visit(item.url);
            },
        });
        return;
    }

    if (item.url) router.visit(item.url);
};

const marcarTodasLeidas = () => {
    router.post(route('notificaciones.marcar-todas-leidas'), {}, {
        preserveScroll: true,
        preserveState: true,
    });
};

// Functions
const colorthemeFn = (value: string) => {
    localStorage.setItem('vyzorcolortheme', value);
    localStorage.removeItem('vyzorbodyBgRGB'); // ❌ Fix: removeItem takes only one argument
    switcher.colorthemeFn(value);
};



const ToggleMenu = () => {
    const html = document.documentElement;

    if (window.innerWidth <= 992) {
        const dataToggled = html.getAttribute('data-toggled');
        html.setAttribute('data-toggled', dataToggled === 'open' ? 'close' : 'open');
    } else {
        const menuNavLayoutType = html.getAttribute('data-nav-style');
        const verticalStyleType = html.getAttribute('data-vertical-style');
        const dataToggled = html.getAttribute('data-toggled');

        if (menuNavLayoutType) {
            if (dataToggled) {
                html.removeAttribute('data-toggled');
            } else {
                html.setAttribute('data-toggled', `${menuNavLayoutType}-closed`);
            }
        } else if (verticalStyleType) {
            if (verticalStyleType === 'doublemenu') {
                if (dataToggled === 'double-menu-open' && document.querySelector('.double-menu-active')) {
                    html.setAttribute('data-toggled', 'double-menu-close');
                } else if (document.querySelector('.double-menu-active')) {
                    html.setAttribute('data-toggled', 'double-menu-open');
                }
            } else {
                if (dataToggled) {
                    html.removeAttribute('data-toggled');
                } else {
                    const map: Record<string, string> = {
                        closed: 'close-menu-close',
                        icontext: 'icon-text-close',
                        overlay: 'icon-overlay-close',
                        detached: 'detached-close',
                    };
                    html.setAttribute('data-toggled', map[verticalStyleType] || '');
                }
            }
        }
    }
};

const toggleFullScreen = () => {
    const element = document.documentElement;
    if (document.fullscreenElement) {
        document.exitFullscreen();
    } else {
        element.requestFullscreen();
    }
};

const fullscreenChanged = () => {
    isFullScreen.value = !!document.fullscreenElement;
};

const handleCartDelete = (id: number) => {
    notificationNotes.value = notificationNotes.value.filter(item => item.id !== id);
};

const dec = (event: Event) => {
    event.preventDefault();
    const input = (event.currentTarget as HTMLElement).parentElement?.querySelector('input') as HTMLInputElement;
    if (input) {
        const unit = Number(input.value);
        if (unit > 1) {
            input.value = String(unit - 1);
        }
    }
};

const inc = (event: Event) => {
    event.preventDefault();
    const input = (event.currentTarget as HTMLElement).parentElement?.querySelector('input') as HTMLInputElement;
    if (input) {
        input.value = String(Number(input.value) + 1);
    }
};

const handleClickOutside = () => {
    showSuggestions.value = false;
};

const handleToChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    search.value = target.value;
    showSuggestions.value = search.value.length > 0;
};

const handleSuggestionClick = (suggestionTitle: string) => {
    search.value = '';
    showSuggestions.value = false;
};

// Computed
const filterSuggestions = computed(() => {
    const getTitlesWithPaths = (menuItems: any[]): { title: string; path: string }[] => {
        const titles: { title: string; path: string }[] = [];
        menuItems.forEach(item => {
            if (item?.path) {
                titles.push({ title: item.title, path: item.path });
            }
            if (Array.isArray(item?.children)) {
                titles.push(...getTitlesWithPaths(item.children));
            }
        });
        return titles;
    };
    return getTitlesWithPaths(MENUITEMS);
});

const uniqueSuggestions = computed(() => {
    const searchLower = search.value.toLowerCase();
    const suggestions = filterSuggestions.value.filter(item =>
        item.title.toLowerCase().includes(searchLower)
    );
    const uniqueTitles = Array.from(new Set(suggestions.map(item => item.title.toLowerCase())));
    return uniqueTitles.map(title =>
        suggestions.find(item => item.title.toLowerCase() === title)!
    );
});

let pop = null;



const logout = () => {
    //cerrar sesion en router post logoutd de laravel bezee

    router.post('/logout', {}, {
        onSuccess: () => {
            logUserOut();
        },
    })
};
onMounted(() => {
    document.addEventListener('fullscreenchange', fullscreenChanged, { passive: true });
    document.body.addEventListener('click', handleClickOutside, { passive: true });
    pop = new Tooltip(document.body, {
        selector: '[data-bs-toggle="tooltip"]',
    });
});

onUnmounted(() => {
    document.body.removeEventListener('click', handleClickOutside);
    document.removeEventListener('fullscreenchange', fullscreenChanged);
    if (pop) pop.dispose();

    const popovers = document.getElementsByClassName('tooltip');
    Array.from(popovers).forEach(el => el.remove());
});
</script>
