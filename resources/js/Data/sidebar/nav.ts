

import * as Svgicons from "../sidebar/menusvg-icons";

interface MenuItemBase {
  title: string;
  icon?: any;
  permission?: string|string[];
  type?: string;
  active?: boolean;
  selected?: boolean;
  dirchange?: boolean;
  badgetxt?: string;
}

interface LinkMenuItem extends MenuItemBase {
  type: 'link';
  path: string;
}

interface SubMenuItem extends MenuItemBase {
  type: 'sub';
  children: MenuItem[];
}

type MenuItem = LinkMenuItem | SubMenuItem;


export const MENUITEMS: (MenuItem | { menutitle: string })[] = [

  {
    menutitle: 'Principal', permission: 'dashboard.ver'
  },
  {
    title: 'Dashboard', icon: Svgicons.Dashboardicon, type: 'link', path: '/dashboard', active: true, selected: true, dirchange: false, permission: 'dashboard.ver',
  },
  {
    menutitle: 'GESTIÓN', permission: ['dashboard.ver', 'vales.ver', 'cargas.ver', 'mantenimiento.solicitudes.ver', 'mantenimiento.ordenes.ver']
  },

  {
    title: 'Vales', icon: Svgicons.Valeicon, type: 'link', path: '/vales', active: false, selected: false, dirchange: false, permission: 'vales.ver',
  },
  {
    title: 'Cargas Combustible', icon: Svgicons.CargaIcon, type: 'link', path: '/cargas', active: false, selected: false, dirchange: false, permission: 'cargas.ver',
  },

  {
    title: 'Mantenimiento', icon: Svgicons.MantenimientoIcon, type: 'sub', active: false, selected: false, dirchange: false,
    children: [
      { path: '/mantenimiento/solicitudes', icon: Svgicons.SolicitudMantenimientoIcon, type: 'link', active: false, selected: false, dirchange: false, title: 'Solicitudes', permission: 'mantenimiento.solicitudes.ver' },
      { path: '/mantenimiento/ordenes',    icon: Svgicons.OrdenMantenimientoIcon,      type: 'link', active: false, selected: false, dirchange: false, title: 'Órdenes de Trabajo', permission: 'mantenimiento.ordenes.ver' },
      { path: '/mantenimiento/ordenes',    icon: Svgicons.OrdenMantenimientoIcon,      type: 'link', active: false, selected: false, dirchange: false, title: 'Órdenes de Trabajo', permission: 'mantenimiento.ver' },
    ],
  },
  {
    menutitle: 'CATALOGOS', permission: ['conductores.ver', 'vehiculos.ver', 'grifos.ver', 'tipos-combustible.ver', 'tipos-mantenimiento.ver', 'tipos-vehiculo.ver']
  },
  {
    title: 'Conductores', icon: Svgicons.Conductoricon, type: 'link', path: '/conductores', active: false, selected: false, dirchange: false, permission: 'conductores.ver'
  },

  {
    title: 'Vehículos', icon: Svgicons.Vehiculoicon, type: 'link', path: '/vehiculos', active: false, selected: false, dirchange: false, permission: 'vehiculos.ver'
  },
  {
    title: 'Surtidores', icon: Svgicons.Grifoicon, type: 'link', path: '/grifos', active: false, selected: false, dirchange: false, permission: 'grifos.ver'
  },
  {
    title: 'Tipos de Combustible', icon: Svgicons.TipoCombustibleIcon, type: 'link', path: '/tipos-combustible', active: false, selected: false, dirchange: false, permission: 'tipos-combustible.ver'
  },
  {
    title: 'Tipos de Mantenimiento', icon: Svgicons.TipoMantenimientoIcon, type: 'link', path: '/tipos-mantenimiento', active: false, selected: false, dirchange: false, permission: 'tipos-mantenimiento.ver'
  },
  {
    title: 'Tipos de Vehículo', icon: Svgicons.TipoVehiculoIcon, type: 'link', path: '/tipos-vehiculo', active: false, selected: false, dirchange: false, permission: 'tipos-vehiculo.ver'
  },
  {
    menutitle: 'ADMINISTRACIÓN', permission: ['usuarios.ver', 'roles.ver', 'permisos.ver']
  },
  {
    title: 'Usuarios', icon: Svgicons.UsuarioIcon, type: 'link', path: '/usuarios', active: false, selected: false, dirchange: false, permission: 'usuarios.ver'
  },
//   {
//     title: "Dashboards", icon: Svgicons.Dashboardicon, type: "sub", active: false, dirchange: false, children: [

//       { path: "/dashboards/sales", icon: Svgicons.Salesicon, type: "link", active: true, selected: false, dirchange: false, title: "Sales" },

//     ]
//   },

//   {
//     menutitle: 'WEB APPS'
//   },



//   {
//     title: "Nested Menu", icon: Svgicons.Nestedmenuicon, selected: false, active: false, dirchange: false, type: "sub", children: [

//       { path: "", title: "Nested-1", icon: Svgicons.Nested1icon, type: "link", active: false, selected: false, dirchange: false },
//       {
//         title: "Nested-2", icon: Svgicons.Nested2icon, type: "sub", active: false, selected: false, dirchange: false, children: [

//           { path: "", type: "empty", active: false, selected: false, dirchange: false, title: "Nested-2-1" },
//           { path: "", type: "empty", ctive: false, selected: false, dirchange: false, title: "Nested-2-2" },
//           { path: "", type: "empty", active: false, selected: false, dirchange: false, title: "Nested-2-3" },

//         ],
//       },

//     ],
//   },

//   {
//     menutitle: 'PAGES'
//   },

//   {
//     icon: Svgicons.Pagesicon, title: "Pages", type: "sub", active: false, dirchange: false, children: [

//       {
//         icon: Svgicons.Erroricon, title: "Error", type: "sub", active: false, selected: false, dirchange: false, children: [
//           { path: "/pages/error/404-error", type: "link", active: false, selected: false, dirchange: false, title: "404-Error" },
//         ]
//       },
//     ]
//   },
]
