---
paths:
  - 'app/Http/Controllers/TipoMantenimientoController.php,app/Http/Requests/TipoMantenimientoRequest.php,resources/js/Pages/TiposMantenimiento/**'
---

# Tipos Mantenimiento

## Tipos de Mantenimiento: dos tableros (taller / operación diaria) sobre un solo index
tipo_mantenimiento tiene ambito enum('taller','operacion_diaria') default taller, más tipo_valor enum('cantidad','booleano') y unidad_medida — sólo aplican a operacion_diaria. Un único index/create/Index.vue sirve a los 2 "tableros": el ambito viaja como query param (?ambito=), el controlador lo valida con ambitoDesde() (default taller) y filtra ->where('ambito', $ambito); Index.vue muestra pestañas (tab-style-8) + columnas extra (tipo_valor, unidad_medida) sólo si ambito==operacion_diaria. El campo ambito NUNCA se muestra/edita: en create viene del query param, en edit se conserva el del registro (form.ambito oculto). store/update: normalizarCampos() pone tipo_valor/unidad_medida a null si ambito!=operacion_diaria, y unidad_medida a null si tipo_valor!=cantidad. Request: tipo_valor required si ambito=operacion_diaria; unidad_medida required si además tipo_valor=cantidad. La unicidad de tipo_mantenimiento se acota al ambito (mismo nombre puede existir en los 2 tableros). Los redirects de store/update/destroy llevan ?ambito= para volver al tablero correcto. OJO: se corrigió el bug pre-existente de .ai/rules/requests.md — TipoMantenimientoRequest usaba $this->route('tipo_mantenimiento') (null) en vez de la clave real del binding 'tipoMantenimiento', lo que rompía update() al conservar el nombre.
