<?php

use App\Models\Actividad;
use App\Models\ActividadRealizada;
use App\Models\Area;
use App\Models\Asignacion;
use App\Models\CargaCombustible;
use App\Models\CargaMaterial;
use App\Models\Conductor;
use App\Models\DetalleMantenimiento;
use App\Models\Dispositivo;
use App\Models\DocumentoConductor;
use App\Models\EncargadoArea;
use App\Models\Grifo;
use App\Models\GrupoVehiculo;
use App\Models\IntervaloMantenimientoTipo;
use App\Models\MantenimientoOperacionDiaria;
use App\Models\Material;
use App\Models\OperacionDiaria;
use App\Models\OrdenTrabajo;
use App\Models\ParametrosEmpresa;
use App\Models\Persona;
use App\Models\Repuesto;
use App\Models\RespaldoDigital;
use App\Models\SolicitudMantenimiento;
use App\Models\Taller;
use App\Models\TipoCombustible;
use App\Models\TipoMantenimiento;
use App\Models\TipoVehiculo;
use App\Models\User;
use App\Models\Vale;
use App\Models\Vehiculo;
use App\Models\VehiculoArea;
use App\Models\VehiculoExterno;
use App\Models\Viaje;

return [

    /*
    |--------------------------------------------------------------------------
    | Presentación de la bitácora de auditoría
    |--------------------------------------------------------------------------
    |
    | Traduce lo que guarda owen-it/laravel-auditing (nombres de clase, nombres
    | de columna y llaves foráneas en crudo) a algo legible en la pantalla de
    | Auditoría. Todo son arrays planos — sin closures — para que el archivo
    | siga siendo cacheable con `php artisan config:cache`.
    |
    */

    /**
     * Eventos de Eloquent auditados (config/audit.php -> 'events').
     */
    'eventos' => [
        'created' => ['label' => 'Creación', 'icono' => 'ri-add-circle-line', 'color' => 'success'],
        'updated' => ['label' => 'Modificación', 'icono' => 'ri-edit-2-line', 'color' => 'info'],
        'deleted' => ['label' => 'Eliminación', 'icono' => 'ri-delete-bin-6-line', 'color' => 'danger'],
        'restored' => ['label' => 'Restauración', 'icono' => 'ri-arrow-go-back-line', 'color' => 'warning'],
    ],

    /**
     * Un módulo por modelo auditable.
     *
     * - label: nombre del módulo tal como lo conoce el usuario (el mismo que
     *   usa el menú lateral cuando existe: "Operarios", "Surtidores", …).
     * - icono: clase de RemixIcon (la familia de íconos del template).
     * - descriptor: columnas que identifican al registro en lenguaje humano.
     *   Aceptan notación de punto para relaciones ('persona.nombre_completo')
     *   y llaves foráneas declaradas en 'relaciones', que se resuelven a su
     *   propia etiqueta (así una asignación se describe como
     *   "V-01 — 1234ABC · Juan Pérez" en vez de "id_vehiculo: 3").
     *
     * @var array<class-string, array{label: string, icono: string, descriptor: array<int, string>}>
     */
    'modelos' => [
        Vale::class => ['label' => 'Vales', 'icono' => 'ri-coupon-3-line', 'descriptor' => ['nro_vale', 'gestion'], 'formato' => 'Vale N° {nro_vale}/{gestion}'],
        CargaCombustible::class => ['label' => 'Cargas de combustible', 'icono' => 'ri-gas-station-line', 'descriptor' => ['nro_carga', 'id_vehiculo'], 'formato' => 'Carga N° {nro_carga} - {id_vehiculo}'],
        OperacionDiaria::class => ['label' => 'Operación diaria', 'icono' => 'ri-calendar-check-line', 'descriptor' => ['nro_operacion', 'id_vehiculo'], 'formato' => 'Operación N° {nro_operacion} - {id_vehiculo}'],
        ActividadRealizada::class => ['label' => 'Actividades realizadas', 'icono' => 'ri-list-check-2', 'descriptor' => ['id_actividad', 'lugar']],
        Actividad::class => ['label' => 'Actividades', 'icono' => 'ri-list-check-2', 'descriptor' => ['nombre_actividad']],
        MantenimientoOperacionDiaria::class => ['label' => 'Mantenimiento en operación', 'icono' => 'ri-tools-line', 'descriptor' => ['id_tipo_mantenimiento']],
        SolicitudMantenimiento::class => ['label' => 'Solicitudes de mantenimiento', 'icono' => 'ri-file-list-3-line', 'descriptor' => ['nro_solicitud', 'gestion'], 'formato' => 'Solicitud N° {nro_solicitud}/{gestion}'],
        OrdenTrabajo::class => ['label' => 'Órdenes de trabajo', 'icono' => 'ri-clipboard-line', 'descriptor' => ['nro_orden', 'gestion'], 'formato' => 'Orden N° {nro_orden}/{gestion}'],
        DetalleMantenimiento::class => ['label' => 'Detalle de mantenimiento', 'icono' => 'ri-tools-fill', 'descriptor' => ['id_orden_trabajo', 'id_repuesto']],
        IntervaloMantenimientoTipo::class => ['label' => 'Intervalos de mantenimiento', 'icono' => 'ri-timer-line', 'descriptor' => ['id_tipo_vehiculo', 'id_tipo_mantenimiento']],
        CargaMaterial::class => ['label' => 'Volteos y viajes', 'icono' => 'ri-truck-line', 'descriptor' => ['nro_carga', 'id_vehiculo_externo'], 'formato' => 'Flete N° {nro_carga} - {id_vehiculo_externo}'],
        Viaje::class => ['label' => 'Viajes', 'icono' => 'ri-route-line', 'descriptor' => ['id_carga_material', 'id_material']],
        Material::class => ['label' => 'Materiales', 'icono' => 'ri-box-3-line', 'descriptor' => ['material']],
        VehiculoExterno::class => ['label' => 'Vehículos externos', 'icono' => 'ri-truck-fill', 'descriptor' => ['nro_placa', 'propietario']],
        Vehiculo::class => ['label' => 'Vehículos', 'icono' => 'ri-roadster-line', 'descriptor' => ['codigo', 'nro_placa']],
        VehiculoArea::class => ['label' => 'Vehículos por área', 'icono' => 'ri-map-pin-line', 'descriptor' => ['id_vehiculo', 'id_area']],
        Asignacion::class => ['label' => 'Asignaciones de operario', 'icono' => 'ri-links-line', 'descriptor' => ['id_vehiculo', 'id_conductor']],
        Conductor::class => ['label' => 'Operarios', 'icono' => 'ri-steering-2-line', 'descriptor' => ['persona.nombre_completo']],
        DocumentoConductor::class => ['label' => 'Documentos de operario', 'icono' => 'ri-file-text-line', 'descriptor' => ['tipo_documento', 'numero_documento']],
        Persona::class => ['label' => 'Personas', 'icono' => 'ri-user-line', 'descriptor' => ['nombre_completo', 'ci']],
        User::class => ['label' => 'Usuarios', 'icono' => 'ri-shield-user-line', 'descriptor' => ['name', 'email']],
        Area::class => ['label' => 'Áreas', 'icono' => 'ri-community-line', 'descriptor' => ['nombre_area']],
        EncargadoArea::class => ['label' => 'Encargados de área', 'icono' => 'ri-user-star-line', 'descriptor' => ['id_persona', 'id_area']],
        Grifo::class => ['label' => 'Surtidores', 'icono' => 'ri-store-2-line', 'descriptor' => ['razon_social']],
        Taller::class => ['label' => 'Talleres', 'icono' => 'ri-home-gear-line', 'descriptor' => ['razon_social']],
        Repuesto::class => ['label' => 'Repuestos', 'icono' => 'ri-hammer-line', 'descriptor' => ['nombre_repuesto', 'codigo_repuesto']],
        TipoCombustible::class => ['label' => 'Tipos de combustible', 'icono' => 'ri-drop-line', 'descriptor' => ['tipo_combustible']],
        TipoMantenimiento::class => ['label' => 'Tipos de mantenimiento', 'icono' => 'ri-settings-4-line', 'descriptor' => ['tipo_mantenimiento']],
        TipoVehiculo::class => ['label' => 'Tipos de vehículo', 'icono' => 'ri-car-line', 'descriptor' => ['tipo_vehiculo']],
        GrupoVehiculo::class => ['label' => 'Grupos de vehículo', 'icono' => 'ri-stack-line', 'descriptor' => ['grupo_vehiculo']],
        RespaldoDigital::class => ['label' => 'Respaldos digitales', 'icono' => 'ri-image-line', 'descriptor' => ['tipo_respaldo', 'tipo_archivo']],
        Dispositivo::class => ['label' => 'Dispositivos', 'icono' => 'ri-smartphone-line', 'descriptor' => ['plataforma', 'id_usuario']],
        ParametrosEmpresa::class => ['label' => 'Parámetros de la empresa', 'icono' => 'ri-settings-3-line', 'descriptor' => ['nombre_empresa']],
    ],

    /**
     * Llaves foráneas que se muestran como el registro al que apuntan, no como
     * un número. 'campos' admite notación de punto; se cargan con with() y se
     * unen con " — ".
     *
     * @var array<string, array{modelo: class-string, campos: array<int, string>}>
     */
    'relaciones' => [
        'id_vehiculo' => ['modelo' => Vehiculo::class, 'campos' => ['codigo', 'nro_placa']],
        'id_vehiculo_externo' => ['modelo' => VehiculoExterno::class, 'campos' => ['nro_placa', 'propietario']],
        'id_conductor' => ['modelo' => Conductor::class, 'campos' => ['persona.nombre_completo']],
        'id_persona' => ['modelo' => Persona::class, 'campos' => ['nombre_completo']],
        'id_verificador' => ['modelo' => Persona::class, 'campos' => ['nombre_completo']],
        'id_user' => ['modelo' => User::class, 'campos' => ['name']],
        'id_usuario' => ['modelo' => User::class, 'campos' => ['name']],
        'id_usuario_apertura' => ['modelo' => User::class, 'campos' => ['name']],
        'id_usuario_cierre' => ['modelo' => User::class, 'campos' => ['name']],
        'id_usuario_emite' => ['modelo' => User::class, 'campos' => ['name']],
        'id_usuario_ejecuta' => ['modelo' => User::class, 'campos' => ['name']],
        'id_usuario_registra' => ['modelo' => User::class, 'campos' => ['name']],
        'id_usuario_registro' => ['modelo' => User::class, 'campos' => ['name']],
        'id_area' => ['modelo' => Area::class, 'campos' => ['nombre_area']],
        'id_grifo' => ['modelo' => Grifo::class, 'campos' => ['razon_social']],
        'id_taller' => ['modelo' => Taller::class, 'campos' => ['razon_social']],
        'id_material' => ['modelo' => Material::class, 'campos' => ['material']],
        'id_actividad' => ['modelo' => Actividad::class, 'campos' => ['nombre_actividad']],
        'id_repuesto' => ['modelo' => Repuesto::class, 'campos' => ['nombre_repuesto']],
        'id_vale' => ['modelo' => Vale::class, 'campos' => ['nro_vale', 'gestion'], 'formato' => 'Vale N° {nro_vale}/{gestion}'],
        'id_carga_material' => ['modelo' => CargaMaterial::class, 'campos' => ['nro_carga', 'gestion'], 'formato' => 'Flete N° {nro_carga}/{gestion}'],
        'id_carga_combustible' => ['modelo' => CargaCombustible::class, 'campos' => ['nro_carga', 'gestion'], 'formato' => 'Carga N° {nro_carga}/{gestion}'],
        'id_operacion_diaria' => ['modelo' => OperacionDiaria::class, 'campos' => ['nro_operacion'], 'formato' => 'Operación N° {nro_operacion}'],
        'id_orden_trabajo' => ['modelo' => OrdenTrabajo::class, 'campos' => ['nro_orden', 'gestion'], 'formato' => 'Orden N° {nro_orden}/{gestion}'],
        'id_solicitud_mantenimiento' => ['modelo' => SolicitudMantenimiento::class, 'campos' => ['nro_solicitud', 'gestion'], 'formato' => 'Solicitud N° {nro_solicitud}/{gestion}'],
        'id_tipo_combustible' => ['modelo' => TipoCombustible::class, 'campos' => ['tipo_combustible']],
        'id_tipo_mantenimiento' => ['modelo' => TipoMantenimiento::class, 'campos' => ['tipo_mantenimiento']],
        'id_tipo_vehiculo' => ['modelo' => TipoVehiculo::class, 'campos' => ['tipo_vehiculo']],
        'id_grupo_vehiculo' => ['modelo' => GrupoVehiculo::class, 'campos' => ['grupo_vehiculo']],
    ],

    /**
     * Nombre humano de cada columna. Lo que no esté acá cae en un
     * Str::headline() del nombre de la columna.
     *
     * @var array<string, string>
     */
    'atributos' => [
        'ambito' => 'Ámbito',
        'anio' => 'Año',
        'archivo' => 'Archivo',
        'cantidad' => 'Cantidad',
        'capacidad' => 'Capacidad',
        'capacidad_unidad' => 'Unidad de capacidad',
        'categoria' => 'Categoría',
        'celular' => 'Celular',
        'ci' => 'CI',
        'ciudad' => 'Ciudad',
        'codigo' => 'Código',
        'codigo_repuesto' => 'Código de repuesto',
        'concepto' => 'Concepto',
        'contacto' => 'Contacto',
        'correo_empresa' => 'Correo de la empresa',
        'deleted_at' => 'Fecha de eliminación',
        'descripcion_area' => 'Descripción del área',
        'descripcion_problema' => 'Descripción del problema',
        'descripcion_repuesto' => 'Descripción del repuesto',
        'destino' => 'Destino',
        'detalle' => 'Detalle',
        'detalles' => 'Detalles',
        'direccion' => 'Dirección',
        'direccion_empresa' => 'Dirección de la empresa',
        'email' => 'Correo electrónico',
        'es_al_exterior' => 'Viaje al exterior',
        'es_principal' => 'Es principal',
        'estado' => 'Estado',
        'estado_actividad' => 'Estado',
        'estado_area' => 'Estado',
        'estado_asignacion' => 'Estado de la asignación',
        'estado_carga' => 'Estado de la carga',
        'estado_conductor' => 'Estado del operario',
        'estado_documento' => 'Estado del documento',
        'estado_encargo' => 'Estado del encargo',
        'estado_grifo' => 'Estado del surtidor',
        'estado_grupo_vehiculo' => 'Estado',
        'estado_orden' => 'Estado de la orden',
        'estado_persona' => 'Estado de la persona',
        'estado_repuesto' => 'Estado del repuesto',
        'estado_taller' => 'Estado del taller',
        'estado_tipo_combustible' => 'Estado',
        'estado_tipo_mantenimiento' => 'Estado',
        'estado_tipo_vehiculo' => 'Estado',
        'estado_usuario' => 'Estado del usuario',
        'estado_vale' => 'Estado del vale',
        'estado_vehiculo' => 'Estado del vehículo',
        'evidencia' => 'Evidencia',
        'fecha' => 'Fecha',
        'fecha_apertura' => 'Fecha de apertura',
        'fecha_asignacion' => 'Fecha de asignación',
        'fecha_carga' => 'Fecha de carga',
        'fecha_cierre' => 'Fecha de cierre',
        'fecha_culminacion' => 'Fecha de culminación',
        'fecha_ejecucion' => 'Fecha de ejecución',
        'fecha_emision' => 'Fecha de emisión',
        'fecha_fin' => 'Fecha de fin',
        'fecha_hora_carga' => 'Fecha y hora de carga',
        'fecha_inicio' => 'Fecha de inicio',
        'fecha_nacimiento' => 'Fecha de nacimiento',
        'fecha_pago' => 'Fecha de pago',
        'fecha_reasignacion' => 'Fecha de reasignación',
        'fecha_solicitud' => 'Fecha de solicitud',
        'fecha_vencimiento' => 'Fecha de vencimiento',
        'fotografia' => 'Fotografía',
        'foto' => 'Foto',
        'frecuencia' => 'Frecuencia',
        'gestion' => 'Gestión',
        'grupo_vehiculo' => 'Grupo de vehículo',
        'hora_fin' => 'Hora de fin',
        'hora_inicio' => 'Hora de inicio',
        'horas_trabajadas' => 'Horas trabajadas',
        'horometro' => 'Horómetro',
        'horometro_actual' => 'Horómetro actual',
        'horometro_fin' => 'Horómetro final',
        'horometro_inicial' => 'Horómetro inicial',
        'horometro_inicio' => 'Horómetro inicial',
        'id_actividad' => 'Actividad',
        'id_area' => 'Área',
        'id_carga_combustible' => 'Carga de combustible',
        'id_carga_material' => 'Carga de material',
        'id_conductor' => 'Operario',
        'id_grifo' => 'Surtidor',
        'id_grupo_vehiculo' => 'Grupo de vehículo',
        'id_material' => 'Material',
        'id_operacion_diaria' => 'Operación diaria',
        'id_orden_trabajo' => 'Orden de trabajo',
        'id_persona' => 'Persona',
        'id_repuesto' => 'Repuesto',
        'id_solicitud_mantenimiento' => 'Solicitud de mantenimiento',
        'id_taller' => 'Taller',
        'id_tipo_combustible' => 'Tipo de combustible',
        'id_tipo_mantenimiento' => 'Tipo de mantenimiento',
        'id_tipo_vehiculo' => 'Tipo de vehículo',
        'id_user' => 'Usuario',
        'id_usuario' => 'Usuario',
        'id_usuario_apertura' => 'Usuario que abrió',
        'id_usuario_cierre' => 'Usuario que cerró',
        'id_usuario_ejecuta' => 'Usuario que ejecuta',
        'id_usuario_emite' => 'Usuario que emite',
        'id_usuario_registra' => 'Usuario que registra',
        'id_usuario_registro' => 'Usuario que registra',
        'id_vale' => 'Vale',
        'id_vehiculo' => 'Vehículo',
        'id_vehiculo_externo' => 'Vehículo externo',
        'id_verificador' => 'Verificador',
        'kilometraje' => 'Kilometraje',
        'kilometraje_actual' => 'Kilometraje actual',
        'kilometraje_fin' => 'Kilometraje final',
        'kilometraje_inicial' => 'Kilometraje inicial',
        'kilometraje_inicio' => 'Kilometraje inicial',
        'litros' => 'Litros',
        'logo_empresa' => 'Logo de la empresa',
        'lugar' => 'Lugar',
        'marca' => 'Marca',
        'material' => 'Material',
        'materno' => 'Apellido materno',
        'modelo' => 'Modelo',
        'monto_pago' => 'Monto del pago',
        'motivo' => 'Motivo',
        'motivo_asignacion' => 'Motivo de la asignación',
        'name' => 'Nombre',
        'nit' => 'NIT',
        'nit_empresa' => 'NIT de la empresa',
        'nombres' => 'Nombres',
        'nombre_actividad' => 'Actividad',
        'nombre_area' => 'Nombre del área',
        'nombre_conductor' => 'Nombre del conductor',
        'nombre_empresa' => 'Nombre de la empresa',
        'nombre_repuesto' => 'Nombre del repuesto',
        'nota_emisor' => 'Nota del emisor',
        'notificado_vencimiento_at' => 'Aviso de vencimiento enviado',
        'nro_carga' => 'Nro. de carga',
        'nro_factura' => 'Nro. de factura',
        'nro_operacion' => 'Nro. de operación',
        'nro_orden' => 'Nro. de orden',
        'nro_placa' => 'Nro. de placa',
        'nro_solicitud' => 'Nro. de solicitud',
        'nro_vale' => 'Nro. de vale',
        'numero_documento' => 'Nro. de documento',
        'observacion' => 'Observación',
        'observaciones' => 'Observaciones',
        'origen' => 'Origen',
        'pais' => 'País',
        'parametros_vale' => 'Parámetros del vale',
        'paterno' => 'Apellido paterno',
        'plataforma' => 'Plataforma',
        'precio' => 'Precio unitario',
        'propietario' => 'Propietario',
        'razon_social' => 'Razón social',
        'realizado' => 'Realizado',
        'ruta_respaldo' => 'Ruta del respaldo',
        'stock_actual' => 'Stock actual',
        'telefono' => 'Teléfono',
        'telefono_empresa' => 'Teléfono de la empresa',
        'tipo_archivo' => 'Tipo de archivo',
        'tipo_carga' => 'Tipo de carga',
        'tipo_combustible' => 'Tipo de combustible',
        'tipo_documento' => 'Tipo de documento',
        'tipo_encargo' => 'Tipo de encargo',
        'tipo_mantenimiento' => 'Tipo de mantenimiento',
        'tipo_medicion' => 'Tipo de medición',
        'tipo_respaldo' => 'Tipo de respaldo',
        'tipo_valor' => 'Tipo de valor',
        'tipo_vehiculo' => 'Tipo de vehículo',
        'turno' => 'Turno',
        'ultima_actividad' => 'Última actividad',
        'ultimo_uso' => 'Último uso',
        'unidad_capacidad_sugerida' => 'Unidad de capacidad sugerida',
        'unidad_medida' => 'Unidad de medida',
        'usos' => 'Usos',
        'uuid' => 'UUID',
        'valor' => 'Valor',
    ],

    /**
     * Columnas guardadas como 0/1 que se muestran como Sí/No.
     *
     * @var array<int, string>
     */
    'booleanos' => [
        'es_principal',
        'es_al_exterior',
        'realizado',
    ],

    /**
     * Columnas que nunca se muestran en el detalle, aunque queden registradas.
     * Refuerzo de config/audit.php -> 'exclude' (que evita guardarlas).
     *
     * @var array<int, string>
     */
    'ocultos' => [
        'password',
        'remember_token',
        'token',
    ],
];
