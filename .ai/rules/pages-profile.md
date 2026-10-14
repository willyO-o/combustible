---
paths:
  - 'app/Http/Controllers/ProfileController.php,resources/js/Pages/Profile/Edit.vue'
---

# Pages Profile

## Mi Perfil: ficha de sólo lectura (prop `ficha`) separada del formulario (prop `usuario`)
Desde 2026-10-14 Profile/Edit.vue muestra arriba una ficha de identidad (avatar con la cámara, nombre, correo, roles con etiquetaRol/claseRol de Utils/rolUtil.js, estado de cuenta) y "Datos de la Persona" (CI, nombres, nacimiento, estado) + secciones "Como conductor" (estado, licencia vía Conductor::licenciaConducir() con flag vencida, vehículos de asignacionesActivas) y "Como jefe de área" (encargadoAreas). Los datos vienen del prop `ficha` = ProfileController::ficha(), que hace su PROPIA consulta User::with(...)->findOrFail() — no ->load() sobre $request->user(), si no esas relaciones viajarían dentro del prop `usuario`. El prop `usuario` y ambos formularios (perfil + contraseña) no cambiaron. Ojo: `usuario.roles` y `usuario.persona.conductor` YA venían en el prop antes (hasRole() del middleware carga roles; Persona::tipo_actual en $appends carga conductor). La foto sigue guardándose con submitPerfil; junto al avatar aparece "Foto nueva sin guardar" con Guardar/Descartar. Estilos con prefijo .perfil-* en my-styles.css (sólo tokens del tema). Tests: ProfileTest.
