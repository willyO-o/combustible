---
paths:
  - 'app/Http/Requests/**/*.php,resources/js/Pages/**/*.vue,resources/js/Utils/storageUtil.js,app/Http/Middleware/HandleInertiaRequests.php'
---

# Middleware

## Archivos: opcional al editar = "nullable" (no "sometimes"); URLs vía storageUrl()/accessor, nunca "/storage/" fijo
(1) Inertia con forceFormData SIEMPRE envía las claves del form; un File null llega como "" (objectToFormData → append(key, "")) y ConvertEmptyStringsToNull lo vuelve null. Con `sometimes|image` el campo está presente → "debe ser una imagen". Para un archivo opcional al editar usar `nullable|image` (VehiculoRequest.fotografia: POST ? required : nullable, corregido 2026-10-13; el controller conserva la actual con hasFile()/unset). Testear con el payload real: post(update, ['_method' => 'PUT', 'archivo' => '']).
(2) Imágenes del disco public: NO escribir `/storage/${ruta}` en Vue (apunta a la raíz del dominio y falla si el sistema se publica en subcarpeta). Usar el accessor del modelo si existe (Vehiculo::url_fotografia, Persona/User::foto_url — asset() usa host/subcarpeta reales de la petición, no APP_URL) o `storageUrl(ruta)` de resources/js/Utils/storageUtil.js, que arma la URL con la prop compartida `storage_url` (= rtrim(asset('storage'),'/') en HandleInertiaRequests). Si las imágenes no cargan en un servidor, causa típica: falta `php artisan storage:link` (ver `php artisan about` → "public/storage: NOT LINKED") o el servidor web no sigue symlinks. Tests: VehiculoControllerTest (test_editar_sin_cambiar_la_foto..., test_las_urls_de_imagenes_usan_el_host_real_del_servidor).
