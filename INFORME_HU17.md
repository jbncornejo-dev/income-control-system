# INFORME DE AUDITORÍA E IMPLEMENTACIÓN: HISTORIA DE USUARIO HU-17
## Ventanas de Tiempo para Generación de QR e Ingreso a Examen

**Proyecto:** Sistema de Control de Acceso y Gestión de Evaluaciones (TIS)  
**Autor:** Desarrollador Full-Stack Senior  
**Fecha:** 9 de noviembre de 2026  
**Rama:** `develop`  
**Estado:** Implementado, Auditado y Verificado (100% de Pruebas Aprobadas)

---

### 1. Resumen Ejecutivo

Se auditó, diseñó e implementó la **Historia de Usuario HU-17 (Ventanas de tiempo para generación de QR e ingreso a examen)** sobre la arquitectura Laravel 11 + Vue 3 / Inertia.js del proyecto.

La solución garantiza que:
1. **Estudiantes:** Solo pueden visualizar y generar/regenerar su código QR dentro de las **24 horas previas al inicio de su examen** y hasta que este finalice (`inicio - 24h` a `fin`). Fuera de esta ventana, el frontend bloquea la acción mostrando mensajes contextuales claros con la fecha y hora de habilitación, y el backend rechaza cualquier petición a `/estudiante/qr/regenerar` con código HTTP 403.
2. **Personal de Control (Registro de Ingreso):** Solo puede validar estudiantes y confirmar su acceso dentro de la ventana operativa estricta: entre **10 minutos antes del inicio** (`inicio - 10 min`) y **10 minutos después de la finalización** (`fin + 10 min`). Fuera de dicho rango, tanto la interfaz (Paso 1 y Paso 2 de Registro de Ingreso) como los endpoints `/registro-ingreso/validar` y `/registro-ingreso` (store) bloquean la operación indicando el horario permitido.
3. **Personal de Control (Reporte de Incidencias):** El registro y modal de reporte de incidencias se limita a la misma ventana operativa (`inicio - 10 min` a `fin + 10 min`). El controlador `IncidenciaController` y la vista filtran y validan que no se puedan reportar situaciones fuera del horario real del examen.
4. **Coexistencia Integral:** Se mantiene intacta la lógica de la **HU-15 (Panel de estudiante)** y **HU-16 (Foto de referencia inmutable y regeneración condicionada por actualización de foto)**. Ambas condiciones conviven armónicamente.

---

### 2. Criterios de Aceptación (CA) y Matriz de Cumplimiento

| Criterio | Descripción | Estado | Validación |
| :--- | :--- | :--- | :--- |
| **CA-1** | **Ventana QR de Estudiante (24h previas a fin):** El estudiante solo puede generar/regenerar QR dentro de las 24 h previas al inicio del examen y hasta su finalización. Fuera de ese rango, el botón en UI se desactiva con mensaje claro y el backend rechaza con HTTP 403. | **Cumplido** | Verificado con tests unitarios y de integración con saltos temporales (`Carbon::setTestNow`). |
| **CA-2** | **Ventana de Ingresos (Personal de Control):** El personal de control solo puede validar/confirmar ingresos de estudiantes entre `inicio - 10 min` y `fin + 10 min`. Fuera de ese rango, la interfaz bloquea la acción con aviso claro del horario permitido y el backend (`RegistroIngresoController`) rechaza con HTTP 403. | **Cumplido** | Implementado en `validarEstudiante()`, `store()`, y banners reactivos en Paso 1 y Paso 2 de Vue. |
| **CA-3** | **Ventana de Incidencias (Personal de Control):** El registro de incidencias asociadas al examen solo está permitido dentro de la ventana de `inicio - 10 min` a `fin + 10 min`. Fuera de ese rango, se bloquea en UI y en `IncidenciaController`. | **Cumplido** | Implementado en `IncidenciaController@index` (filtro de exámenes asignados) y `@store` (validación HTTP 422 / error de sesión). |
| **CA-4** | **Convivencia HU-16:** Ambas condiciones (foto reciente y ventana horaria) deben cumplirse simultáneamente para regenerar el QR. | **Cumplido** | Endpoint `/estudiante/qr/regenerar` evalúa ventana de 24h y bandera `puede_regenerar_qr`. Suite HU-16 pasa al 100% (8/8). |
| **CA-5** | **Zero Creep y Robustez en Timezone:** Respeto a zona horaria `America/La_Paz` y preservación de todas las pruebas existentes de la suite. | **Cumplido** | 536/536 pruebas aprobadas en el proyecto sin ninguna regresión. |

---

### 3. Detalle de Modificaciones Realizadas

#### 3.1. Backend y Modelos

* **`app/Models/Examen.php`:**
  * Se añadieron métodos auxiliares centrales para unificar los cálculos de tiempo con la zona horaria del sistema (`America/La_Paz`):
    * `inicioExamen(): Carbon`: Devuelve la instancia Carbon precisa combinando `fecha` y `hora_inicio`.
    * `finExamen(): Carbon`: Calcula la hora de término sumando `duracion_minutos`.
    * `estaEnVentanaQr(?Carbon $ahora = null): bool`: Evalúa si `$ahora` está entre `inicio - 24 horas` y `fin`, descartando exámenes cancelados o anulados.
    * `estaEnVentanaOperacionControl(?Carbon $ahora = null): bool`: Evalúa si `$ahora` está entre `inicio - 10 minutos` y `fin + 10 minutos`, descartando exámenes cancelados o anulados.
    * `horarioPermitidoControl(): string`: Retorna el formato legible del rango (ej. `"08:50 a 10:40"`).

* **`app/Models/Estudiante.php`:**
  * Se implementaron métodos para evaluar los exámenes del estudiante:
    * `examenesQuery()`: Consulta base de exámenes vigentes (no cancelados/anulados) asociados al estudiante ya sea mediante grupos inscritos o habilitaciones individuales.
    * `tieneExamenEnVentanaQr(?Carbon $ahora = null): bool`: Verifica si alguno de sus exámenes está dentro de la ventana de 24h.
    * `proximoExamen(?Carbon $ahora = null): ?Examen`: Obtiene el examen futuro más próximo para construir mensajes informativos precisos.

* **`routes/web.php`:**
  * **`GET /estudiante/panel`:**
    * Evalúa `en_ventana_qr`, `mensaje_ventana_qr` y `tiene_examen_activo`.
    * Solo genera el payload y SVG del código QR si el estudiante se encuentra dentro de la ventana de 24 horas previas al examen.
  * **`POST /estudiante/qr/regenerar`:**
    * Valida primero la ventana de 24 horas (`tieneExamenEnVentanaQr`). Si está fuera de horario, retorna HTTP 403 con mensaje explicativo.
    * Valida a continuación la regla de la HU-16 (`puede_regenerar_qr`). Si no ha subido foto reciente, retorna HTTP 403 con `"Debes actualizar tu foto antes de generar un nuevo QR"`.

* **`app/Http/Controllers/RegistroIngresoController.php`:**
  * **`index()`:**
    * Se amplió el filtro de exámenes vigentes a `duracion_minutos + 10 minutes` para incluir aquellos que están en los 10 minutos posteriores a su finalización, vinculando `now('America/La_Paz')`.
    * Cada examen expone `en_ventana_ingreso` (booleano) y `horario_permitido` (string).
  * **`validarEstudiante(Request $request)`:**
    * Si `$examen->estaEnVentanaOperacionControl()` es falso, rechaza inmediatamente con HTTP 403:  
      `"El registro de ingreso solo está permitido entre 10 minutos antes del inicio y 10 minutos después de la finalización del examen (Horario permitido: HH:MM a HH:MM)."`
  * **`store(Request $request)`:**
    * Rechaza intentos directos de registro si el examen no se encuentra en la ventana de control con HTTP 403.
  * **`sincronizarLote(Request $request)`:**
    * Se sincronizó la regla offline con `estaEnVentanaOperacionControl($fechaHoraIntento)`.

* **`app/Http/Controllers/IncidenciaController.php`:**
  * **`index()`:**
    * Filtra los exámenes asignados al personal de control utilizando `$examen->estaEnVentanaOperacionControl($ahora)`, permitiendo que aparezcan desde 10 minutos antes del inicio hasta 10 minutos tras su fin.
    * Expone el campo `horario_permitido` para visualización en el modal.
  * **`store()`:**
    * Valida del lado del servidor que el examen esté dentro de la ventana de control (`$examen->estaEnVentanaOperacionControl($ahora)`). Si está fuera, rechaza con HTTP 422 en peticiones JSON o con error de sesión en formularios web tradicionales.

---

#### 3.2. Frontend (Vue 3 / Inertia)

* **`resources/js/Pages/Estudiantes/Panel.vue`:**
  * Renderiza condicionalmente el código QR: solo se dibuja cuando `estudiante.en_ventana_qr` es verdadero.
  * Muestra una tarjeta/aviso informativo con diseño consistente cuando está fuera de la ventana de 24 horas (`estudiante.mensaje_ventana_qr`).
  * Deshabilita el botón de regeneración si `!estudiante.en_ventana_qr || !puedeRegenerarQr || isRegeneratingQr`.
  * Muestra mensajes descriptivos sobre por qué el botón está inactivo (restricción horaria de 24h o requisito de actualización de foto).

* **`resources/js/Pages/Control/RegistroIngreso/Index.vue`:**
  * **Paso 1 (Configuración):** Al seleccionar un examen fuera de horario, muestra una alerta amarilla indicando la restricción con el horario exacto permitido y deshabilita el botón `"INICIAR CONTROL DE INGRESO"`.
  * **Paso 2 (Escaneo y Validación):** Si el examen no está en ventana activa, muestra un banner de advertencia superior en color de error/alerta, deshabilita la búsqueda manual, el botón `"ACEPTAR INGRESO"`, y los botones de validación de la sublista `"Mis Asignados"`.

* **`resources/js/Pages/Incidencias/Index.vue`:**
  * Si no hay exámenes en ventana activa, el modal de reporte muestra un aviso explicativo y bloquea el botón `"GUARDAR INCIDENCIA"`.
  * Al seleccionar un examen en el modal, muestra el horario permitido (`examenSeleccionado.horario_permitido`).

---

### 4. Coexistencia y Compatibilidad HU-15 / HU-16 / HU-17

1. **Inmutabilidad de `foto_referencia`:**
   * La foto de referencia permanente continúa protegida y no puede ser alterada por el endpoint de perfil del estudiante una vez registrada.
2. **Ciclo de Regeneración de QR:**
   * **Foto actualizada (HU-16):** Exige subir una foto nueva para colocar `puede_regenerar_qr = true`.
   * **Ventana de 24 horas (HU-17):** Exige que el examen esté a menos de 24 horas de iniciar y no haya concluido (`estaEnVentanaQr = true`).
   * Ambos requisitos son obligatorios. Al ejecutarse la regeneración, se produce un nuevo token con timestamp, se actualiza el código QR y `puede_regenerar_qr` vuelve a `false`.
3. **Control de Acceso y Comparación de Fotos:**
   * El personal de control sigue visualizando lado a lado la foto de referencia y la foto actual durante la validación, únicamente dentro de los 10 minutos previos al inicio y 10 minutos posteriores a la conclusión del examen.

---

### 5. Verificación y Resultados de Pruebas

#### 5.1. Pruebas Específicas de HU-17 (`tests/Feature/HU17VentanasTiempoTest.php`)
Se construyó una batería de 10 pruebas automatizadas con manipulación de tiempo (`Carbon::setTestNow`):

| Test Case | Escenario Evaluado | Resultado |
| :--- | :--- | :---: |
| `test_estudiante_no_puede_generar_o_regenerar_qr_con_mas_de_24_horas_de_anticipacion` | Estudiante intenta ver o regenerar QR 24h 1min antes del examen. | **PASS** |
| `test_estudiante_puede_regenerar_qr_dentro_de_la_ventana_de_24_horas_con_foto_actualizada` | Estudiante con foto actualizada regenera QR dentro de la ventana de 24h. | **PASS** |
| `test_estudiante_no_puede_regenerar_qr_dentro_de_las_24_horas_si_no_ha_actualizado_foto` | Estudiante en ventana pero sin subir foto nueva es bloqueado por HU-16. | **PASS** |
| `test_estudiante_no_puede_generar_o_regenerar_qr_una_vez_finalizado_el_examen` | Estudiante intenta ver o regenerar QR después de que el examen terminó. | **PASS** |
| `test_control_no_puede_validar_ingreso_antes_de_los_10_minutos_previos_al_inicio` | Personal de control intenta validar 11 min antes del inicio (bloqueado con 403). | **PASS** |
| `test_control_puede_validar_y_confirmar_ingreso_en_los_limites_permitidos` | Validación y guardado en límites exactos (-10 min inicio y +10 min fin). | **PASS** |
| `test_control_no_puede_validar_ni_confirmar_despues_de_los_10_minutos_posteriores_al_fin` | Personal de control intenta validar 11 min tras finalizar el examen (bloqueado con 403). | **PASS** |
| `test_control_no_puede_registrar_incidencia_fuera_de_la_ventana_permitida` | Reporte de incidencia rechazado fuera de ventana (antes de -10 min o tras +10 min). | **PASS** |
| `test_control_puede_registrar_incidencia_dentro_de_la_ventana_permitida` | Reporte de incidencia aceptado dentro de la ventana de control. | **PASS** |
| `test_examen_cancelado_o_anulado_no_habilita_ventanas` | Exámenes cancelados o anulados no abren ninguna ventana horaria. | **PASS** |

**Resultado:** `10 passed (65 assertions)` en 5.19s.

#### 5.2. Pruebas de Regresión HU-16 (`tests/Feature/HU16ActualizacionFotoQRTest.php`)
**Resultado:** `8 passed (72 assertions)` en 3.54s.

#### 5.3. Suite Completa del Proyecto (`php artisan test`)
**Resultado Global:** `536 passed (2943 assertions)` en 95.36s. Cero fallos, cero regresiones.

#### 5.4. Compilación de Assets (`npm run build`)
**Resultado:** Vite v5.4.21 transpiló 2,420 módulos satisfactoriamente sin advertencias de sintaxis.

---

### 6. Conclusión

La **Historia de Usuario HU-17** se encuentra implementada en su totalidad, cumpliendo rigurosamente los lineamientos de negocio, los criterios de aceptación y las políticas de calidad y consistencia del proyecto.
El código está preparado para ser revisado y fusionado a través del Pull Request correspondiente.

