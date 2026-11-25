# Informe de Auditoría e Implementación: HU-16
## Actualización de Foto y Regeneración de QR

**Proyecto:** Sistema de Control de Ingreso a Exámenes - UMSS  
**Fecha:** 3 de noviembre de 2026  
**Autor:** Desarrollador Full-Stack Senior  
**Estado:** Implementado, Auditado y Verificado (100% Tests Pasados)

---

## 1. Estado Inicial (Auditoría y Diagnóstico)

Tras realizar la inspección técnica exhaustiva de la base de datos, modelos Eloquent, rutas web, controladores y vistas Vue/Inertia (específicamente el Panel de Estudiante HU-15 y el Registro de Ingreso), se diagnosticó el siguiente estado previo:

| Componente | Estado Inicial | Brecha Encontrada para HU-16 |
| :--- | :--- | :--- |
| **Esquema de BD (`estudiante`)** | Contaba con `foto_url` y `codigo_qr`. | Faltaba la columna para la foto permanente (`foto_referencia`) y el mecanismo de estado para control de regeneración de QR (`puede_regenerar_qr`). |
| **Modelo `Estudiante`** | Solo contemplaba asignación masiva de `foto_url`. | No existían reglas de inmutabilidad para proteger la foto de referencia frente a actualizaciones accidentales o indebidas. |
| **Endpoint `/estudiante/foto`** | Sobrescribía `foto_url` sin separar foto de referencia de la foto actual. | No guardaba la primera foto como referencia inmutable ni habilitaba el flag de regeneración de QR. |
| **Endpoint `/estudiante/qr/regenerar`** | Generaba un nuevo token y lo guardaba sin validar si el estudiante había actualizado su foto. | Permitía regenerar QR indefinidamente sin foto reciente (ausencia de control de negocio y retorno 403). |
| **Vista Estudiante (`Panel.vue`)** | Botón `"REGENERAR CÓDIGO"` siempre habilitado (mientras hubiera examen activo). | No reflejaba el bloqueo por falta de foto reciente ni mostraba el mensaje obligatorio `"Debes actualizar tu foto antes de generar un nuevo QR"`. No actualizaba el estado reactivo tras subir foto. |
| **Registro de Ingreso (`RegistroIngresoController` e `Index.vue`)** | Se mostraba un único avatar circular con la foto actual (`foto_url`). | El personal de control no contaba con la vista lado a lado de ambas fotos (Referencia permanente vs. Actual reciente) para validar visualmente la identidad del postulante antes de confirmar el ingreso. |

---

## 2. Cambios Realizados

Se aplicaron cambios quirúrgicos respetando estrictamente el principio de *Zero Creep*, sin alterar dependencias globales ni componentes ajenos a la HU-16:

### A. Base de Datos
* **Archivo Creado:** `database/migrations/2026_11_03_000000_add_foto_referencia_and_qr_flag_to_estudiante_table.php`
  * Agrega la columna `foto_referencia` (`VARCHAR(255) NULL`) para almacenar la fotografía original de referencia permanente.
  * Agrega la columna `puede_regenerar_qr` (`BOOLEAN DEFAULT FALSE`) para controlar el permiso de regeneración de QR.

### B. Backend (Modelos, Rutas y Controladores)
* **Archivo Modificado:** `app/Models/Estudiante.php`
  * Se añadieron `foto_referencia` y `puede_regenerar_qr` al arreglo `$fillable`.
  * Se configuró el cast `'puede_regenerar_qr' => 'boolean'`.
  * Se implementó el método `booted()` con listeners de ciclo de vida Eloquent:
    * `creating`: Si el estudiante se crea con fotografía (`foto_url`), se inicializa automáticamente `foto_referencia` con la misma ruta.
    * `updating`: Regla estricta de inmutabilidad. Si `foto_referencia` ya contiene un valor previo, cualquier intento de mutación externa es bloqueado y preserva la referencia original intacta.
* **Archivo Modificado:** `routes/web.php`
  * **Ruta `/estudiante/panel`:** Pasa la propiedad `puede_regenerar_qr` (booleano) y `foto_referencia` al renderizado Inertia de `Estudiantes/Panel`.
  * **Ruta `/estudiante/foto`:** Al recibir una nueva fotografía, actualiza `foto_url`, establece `puede_regenerar_qr = true` y, si el estudiante no tenía `foto_referencia` (primera foto), fija también la de referencia.
  * **Ruta `/estudiante/qr/regenerar`:** Valida que `$estudiante->puede_regenerar_qr` sea verdadero. Si no lo es, responde con HTTP 403 y el mensaje exacto `{"message": "Debes actualizar tu foto antes de generar un nuevo QR", "error": "Debes actualizar tu foto antes de generar un nuevo QR"}`. Si es válido, regenera el payload del QR y consume el permiso asignando `puede_regenerar_qr = false`.
* **Archivo Modificado:** `app/Http/Controllers/RegistroIngresoController.php`
  * En `index()`: Se incluye `foto_actual` y `foto_referencia` dentro del arreglo `mis_asignados` para personal de control (disponible tanto en modo online como en la sublista offline en caché).
  * En `validarEstudiante()`: Se devuelven explícitamente `foto_actual` y `foto_referencia` en la respuesta JSON tras el escaneo o búsqueda del estudiante (tanto en caso de éxito como en casos de error/inhabilitado).

### C. Frontend (Vue 3 / Inertia)
* **Archivo Modificado:** `resources/js/Pages/Estudiantes/Panel.vue` (HU-15 / Panel de Estudiante)
  * Se conectó el estado reactivo `puedeRegenerarQr = ref(Boolean(props.estudiante?.puede_regenerar_qr))` con un `watch` sobre las props.
  * Botón `"REGENERAR CÓDIGO QR"`: se condicionó `:disabled="isRegeneratingQr || !puedeRegenerarQr"`.
  * Se muestra claramente bajo el botón el mensaje exacto: `"Debes actualizar tu foto antes de generar un nuevo QR"` cuando `!puedeRegenerarQr`.
  * En `confirmarSubidaFoto`: al completar la subida de foto exitosamente, se activa de forma reactiva e inmediata `puedeRegenerarQr.value = true` sin recargar la página.
  * En `handleRegenerateQr`: tras regenerar el QR exitosamente, se deshabilita inmediatamente `puedeRegenerarQr.value = false`.
* **Archivo Modificado:** `resources/js/Pages/Control/RegistroIngreso/Index.vue` (Registro de Ingreso)
  * Se crearon las propiedades computadas `fotoReferenciaUrl` y `fotoActualUrl`, resolviendo rutas de almacenamiento público (`/storage/`).
  * Se reemplazó el avatar único de la tarjeta de validación por el bloque de verificación comparativa **"Lado a Lado"** (`photo-comparison-block`), ubicando lado a lado:
    1. **Foto de Referencia:** Identificada con la etiqueta `Foto de Referencia`.
    2. **Foto Actual:** Identificada con la etiqueta `Foto Actual`.
  * Se implementó diseño responsive con grid de 2 columnas (`grid-template-columns: repeat(2, minmax(0, 1fr))`), `aspect-ratio: 1 / 1` y `object-fit: cover` para que el personal de control pueda contrastar ambos rostros a primera vista sin hacer scroll en móviles y tablets.
  * Se añadieron fallbacks visuales elegantes (`photo-placeholder` con iniciales y mensajes `"Sin foto de referencia"` / `"Sin foto actual"`) en caso de registros antiguos o fallas de carga de imagen.

---

## 3. Cumplimiento de Criterios de Aceptación

| Criterio de Aceptación | Estado | Solución Técnica Implementada |
| :--- | :---: | :--- |
| **CA-1:** Al crear el estudiante (o subir su primera foto), se guarda una foto de referencia permanente, separada de la foto de perfil actual que el estudiante sí puede actualizar. | **CUMPLIDO** | Campo `foto_referencia` en BD. Evento Eloquent `creating` auto-asigna la foto inicial. Listener `updating` garantiza inmutabilidad impidiendo la sobrescritura de `foto_referencia`. Endpoint `/estudiante/foto` solo actualiza `foto_url` una vez establecida la referencia inicial. |
| **CA-2:** El botón "Regenerar código QR" queda deshabilitado (mostrando el mensaje exacto: *"Debes actualizar tu foto antes de generar un nuevo QR"*) si la foto actual no se actualizó desde la última regeneración de QR. | **CUMPLIDO** | Prop `puede_regenerar_qr` enviada desde backend. En frontend el botón tiene `:disabled="isRegeneratingQr \|\| !puedeRegenerarQr"` y se renderiza el aviso con el texto exacto `"Debes actualizar tu foto antes de generar un nuevo QR"`. El endpoint `/estudiante/qr/regenerar` valida el flag en backend retornando HTTP 403 con dicho mensaje ante peticiones no autorizadas. |
| **CA-3:** Al subir una foto nueva, se habilita nuevamente el botón de regenerar QR (y una vez regenerado el QR, vuelve a bloquearse hasta una futura actualización de foto). | **CUMPLIDO** | `/estudiante/foto` fija `puede_regenerar_qr = true` y el callback `onSuccess` del frontend actualiza el estado de forma reactiva instantánea. Al ejecutar `/estudiante/qr/regenerar`, se genera el nuevo token QR y se establece `puede_regenerar_qr = false`, bloqueando nuevamente el botón en backend y frontend. |
| **CA-4:** En la vista de Registro de Ingreso, el personal de control visualiza lado a lado la foto de referencia y la foto actual del estudiante antes de confirmar el ingreso. | **CUMPLIDO** | `RegistroIngresoController` provee `foto_referencia` y `foto_actual`. En `Control/RegistroIngreso/Index.vue` se renderiza el bloque lado a lado con las etiquetas `"Foto de Referencia"` y `"Foto Actual"` directamente encima de los botones Aceptar/Rechazar ingreso. |
| **CA-5:** La vista/componente de comparación de fotos es completamente responsive para dispositivos móviles y tablets. | **CUMPLIDO** | Estructura en CSS/grid de 2 columnas (`photo-comparison-grid`), encuadre cuadrado (`aspect-ratio: 1 / 1`), recorte facial óptimo (`object-fit: cover`) y contención máxima adaptable para pantallas pequeñas, tablets y monitores de escritorio. |

---

## 4. Resultados de las Pruebas

Se diseñó e implementó la suite completa de pruebas automatizadas en `tests/Feature/HU16ActualizacionFotoQRTest.php`, verificando metódicamente cada uno de los criterios.

### A. Ejecución de la Suite HU-16 (`HU16ActualizacionFotoQRTest`)

```text
PASS Tests\Feature\HU16ActualizacionFotoQRTest
✓ ca1 creacion con foto establece foto referencia permanente
✓ ca1 primera subida establece referencia y subidas posteriores no la alteran
✓ ca2 intento regenerar qr sin actualizar foto es bloqueado en endpoint
✓ ca2 panel estudiante prop puede regenerar qr es false
✓ ca3 ciclo completo subir foto habilita qr y regenerar lo consume
✓ ca4 endpoint validar estudiante retorna foto referencia y foto actual
✓ ca4 registro ingreso index entrega ambas fotos en mis asignados
✓ ca2 ca4 ca5 consistencia visual y responsive en vistas vue

Tests: 8 passed (72 assertions)
Duration: 3.34s
```

### B. Verificación de No Regresión (Suite Completa del Proyecto)

Se ejecutó la suite de pruebas general de Laravel para verificar que las modificaciones no alteraron ninguna funcionalidad preexistente:

```text
   PASS  Tests\Unit\ExampleTest
   PASS  Tests\Feature\AmbienteAccessTest
   PASS  Tests\Feature\AmbienteDestroyTest
   PASS  Tests\Feature\AmbienteIndexTest
   PASS  Tests\Feature\AmbienteSearchTest
   PASS  Tests\Feature\AmbienteStoreTest
   PASS  Tests\Feature\AmbienteUpdateTest
   PASS  Tests\Feature\AsignaturaAccessTest
   PASS  Tests\Feature\AsignaturaDestroyTest
   PASS  Tests\Feature\AsignaturaIndexTest
   PASS  Tests\Feature\AsignaturaInertiaTest
   PASS  Tests\Feature\AsignaturaSearchTest
   PASS  Tests\Feature\AsignaturaStoreTest
   PASS  Tests\Feature\AsignaturaUpdateTest
   PASS  Tests\Feature\AuthTest
   PASS  Tests\Feature\CambiarPasswordTest
   PASS  Tests\Feature\DashboardAdminTest
   PASS  Tests\Feature\DashboardControlTest
   PASS  Tests\Feature\DashboardDocenteTest
   PASS  Tests\Feature\EstudianteCuentaTest
   PASS  Tests\Feature\EstudianteDestroyTest
   PASS  Tests\Feature\EstudianteImportarTest
   PASS  Tests\Feature\EstudianteIndexTest
   PASS  Tests\Feature\EstudianteInertiaTest
   PASS  Tests\Feature\EstudianteStoreTest
   PASS  Tests\Feature\EstudianteUpdateTest
   PASS  Tests\Feature\ExamenCreateTest
   PASS  Tests\Feature\ExamenDestroyTest
   PASS  Tests\Feature\ExamenDisponibilidadTest
   PASS  Tests\Feature\ExamenEditTest
   PASS  Tests\Feature\ExamenEstadoTest
   PASS  Tests\Feature\ExamenIndexTest
   PASS  Tests\Feature\ExamenStoreTest
   PASS  Tests\Feature\ExamenUpdateTest
   PASS  Tests\Feature\GrupoTest
   PASS  Tests\Feature\HabilitacionIndexTest
   PASS  Tests\Feature\HU16ActualizacionFotoQRTest
   PASS  Tests\Feature\IncidenciaTest
   PASS  Tests\Feature\InertiaGlobalPropsTest
   PASS  Tests\Feature\InscripcionTest
   PASS  Tests\Feature\MisExamenesTest
   PASS  Tests\Feature\PanelAsistenciaTest
   PASS  Tests\Feature\PersonalControlTest
   PASS  Tests\Feature\RoleAccessTest
   PASS  Tests\Feature\TipoExamenPlanTest
   PASS  Tests\Feature\TipoExamenTest
   PASS  Tests\Feature\UserAdminTest
   PASS  Tests\Feature\UserStoreTest

Tests:    504 passed (2811 assertions)
Duration: 86.15s
```

### C. Compilación de Assets Frontend (Vite)
Se ejecutó la compilación de producción de Vite:
```text
> npm run build
✓ built in 14.06s
Exit code: 0
```
Sin advertencias ni errores de sintaxis en los SFC de Vue.

---

## 5. Conclusión
La Historia de Usuario **HU-16** ha sido implementada satisfactoriamente con alcance quirúrgico, seguridad e inmutabilidad garantizadas en el modelo de datos, diseño de interfaz responsivo y coherente con el sistema de diseño del proyecto, y un 100% de pruebas unitarias y de integración pasando en verde.

