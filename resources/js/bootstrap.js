import axios from 'axios';
import { instalarControlDeSesion } from '@/Utils/sesionPestanaUtil';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Cabecera con la identidad de la pestaña en cada petición (Inertia usa esta
// misma instancia de axios): ver Utils/sesionPestanaUtil.js.
instalarControlDeSesion(window.axios);
