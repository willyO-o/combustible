

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
    menutitle: 'GESTIÓN', permission: ['dashboard.ver', 'vales.ver', 'cargas-combustible.ver', 'cargas-combustible.reporte', 'mantenimiento.solicitudes.ver', 'mantenimiento.ordenes.ver']
  },

  {
    title: 'Vales', icon: Svgicons.Valeicon, type: 'link', path: '/vales', active: false, selected: false, dirchange: false, permission: 'vales.ver',
  },
  {
    title: 'Cargas Combustible', icon: Svgicons.CargaIcon, type: 'link', path: '/cargas', active: false, selected: false, dirchange: false, permission: 'cargas-combustible.ver',
  },
  {
    title: 'Reportes Combustible', icon: Svgicons.Chartsicon, type: 'link', path: '/reportes/cargas-combustible', active: false, selected: false, dirchange: false, permission: 'cargas-combustible.reporte',
  },
  {
    title: 'Rendimiento Combustible', icon: Svgicons.Chartsicon, type: 'link', path: '/reportes/cargas-combustible/rendimiento', active: false, selected: false, dirchange: false, permission: 'cargas-combustible.reporte',
  },

  {
    title: 'Operación Diaria', icon: Svgicons.MantenimientoIcon, type: 'sub', active: false, selected: false, dirchange: false, permission: ['operacion-diaria.ver', 'operacion-diaria.crear', 'operacion-diaria.informe'],
    children: [
      { path: '/operacion-diaria', icon: Svgicons.SolicitudMantenimientoIcon, type: 'link', active: false, selected: false, dirchange: false, title: 'Actividades', permission: 'operacion-diaria.ver' },
      { path: '/operacion-diaria/create', icon: Svgicons.SolicitudMantenimientoIcon, type: 'link', active: false, selected: false, dirchange: false, title: 'Registrar Actividad', permission: 'operacion-diaria.crear' },
    ],
  },
  {
    title: 'Mantenimiento', icon: Svgicons.MantenimientoIcon, type: 'sub', active: false, selected: false, dirchange: false, permission: ['mantenimiento.solicitudes.ver', 'mantenimiento.ordenes.ver', 'mantenimiento.ver','mantenimiento.solicitudes.crear'],
    children: [
      { path: '/mantenimiento/solicitudes', icon: Svgicons.SolicitudMantenimientoIcon, type: 'link', active: false, selected: false, dirchange: false, title: 'Solicitudes', permission: 'mantenimiento.solicitudes.ver' },
      { path: '/mantenimiento/solicitudes/crear', icon: Svgicons.SolicitudMantenimientoIcon, type: 'link', active: false, selected: false, dirchange: false, title: 'Crear Solicitud', permission: 'mantenimiento.solicitudes.crear' },
      { path: '/mantenimiento/ordenes',    icon: Svgicons.OrdenMantenimientoIcon,      type: 'link', active: false, selected: false, dirchange: false, title: 'Órdenes de Trabajo', permission: 'mantenimiento.ordenes.ver' },
    ],
  },
  {
    menutitle: 'CATALOGOS', permission: ['conductores.ver', 'vehiculos.ver', 'grifos.ver', 'tipos-combustible.ver', 'tipos-mantenimiento.ver', 'tipos-vehiculo.ver', 'grupos-vehiculo.ver', 'repuestos.ver']
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
    title: 'Grupos de Vehículo', icon: Svgicons.GrupoVehiculoIcon, type: 'link', path: '/grupos-vehiculo', active: false, selected: false, dirchange: false, permission: 'grupos-vehiculo.ver'
  },
  {
    title: 'Repuestos', icon: Svgicons.RepuestoIcon, type: 'link', path: '/repuestos', active: false, selected: false, dirchange: false, permission: 'repuestos.ver'
  },
  {
    menutitle: 'ADMINISTRACIÓN', permission: ['usuarios.ver', 'personas.ver', 'areas.ver', 'roles.ver', 'permisos.ver', 'parametros-empresa.ver']
  },
  {
    title: 'Personas', icon: Svgicons.UsuarioIcon, type: 'link', path: '/personas', active: false, selected: false, dirchange: false, permission: 'personas.ver'
  },
  {
    title: 'Áreas', icon: Svgicons.AreaIcon, type: 'link', path: '/areas', active: false, selected: false, dirchange: false, permission: 'areas.ver'
  },
  {
    title: 'Usuarios', icon: Svgicons.UsuarioIcon, type: 'link', path: '/usuarios', active: false, selected: false, dirchange: false, permission: 'usuarios.ver'
  },
  {
    title: 'Roles y Permisos', icon: Svgicons.RolIcon, type: 'link', path: '/roles', active: false, selected: false, dirchange: false, permission: 'roles.ver'
  },
  {
    title: 'Parámetros de la Empresa', icon: Svgicons.Generalicon, type: 'link', path: '/parametros-empresa', active: false, selected: false, dirchange: false, permission: 'parametros-empresa.ver'
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
