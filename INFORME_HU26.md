# INFORME DE AUDITORÍA E IMPLEMENTACIÓN: HISTORIA DE USUARIO HU-26
## Reportes Exportables de Asistencia e Incidencias (PDF y Excel)

**Proyecto:** Sistema de Control de Acceso y Gestión de Evaluaciones (TIS)  
**Autor:** Desarrollador Full-Stack Senior  
**Fecha:** 9 de noviembre de 2026  
**Rama:** `feat-HU17`  
**Estado:** Implementado, Auditado y Verificado (100% de Pruebas Aprobadas)

---

### 1. Resumen Ejecutivo

Se auditó, diseñó e implementó la **Historia de Usuario HU-26 (Reportes exportables de asistencia e incidencias)** en el ecosistema Laravel 11 + Vue 3 / Inertia.js del proyecto.

La solución dota al sistema de capacidades de generación y descarga de documentos oficiales en formatos **PDF** y **Excel (.xlsx)** tanto para el **Panel de Asistencia** de exámenes como para el **Histórico de Incidencias**:
1. **Asistencia a Examen (CA-1):** Generación de reportes PDF y Excel con datos de cabecera del examen, resumen general y totales por ambiente (capacidad, ingresados, ausentes y % de ocupación), así como listado detallado de estudiantes ingresados con su **hora exacta de ingreso** y listado de ausentes.
2. **Histórico de Incidencias (CA-2):** Generación de reportes PDF y Excel que **respetan estrictamente los filtros activos en pantalla** (examen, tipo de incidencia, rango de fechas `desde` y `hasta`), garantizando que el documento exportado refleje con fidelidad lo que el usuario visualiza en la interfaz.
3. **Librerías Open Source Ligeras (CA-3):** Se seleccionaron e integraron librerías de código abierto (MIT / LGPL) compatibles con el entorno de ejecución, resolviendo de forma óptima la restricción de ausencia de la extensión `ext-gd` en el servidor PHP.
4. **Preservación y Compatibilidad Total:** Se mantuvo intacto el funcionamiento existente de `PanelAsistenciaController`, `IncidenciaController`, así como las reglas de negocio recientes de **HU-16** y **HU-17**. La suite global de pruebas pasó de 536 a **547 tests aprobados con 0 fallos**.

---

### 2. Auditoría Inicial y Diagnóstico

| Componente | Estado Previo | Requerimiento HU-26 | Brecha Detectada |
| :--- | :--- | :--- | :--- |
| **Librerías de Exportación** | Solo existía `endroid/qr-code` para generación de SVG. No había librerías para PDF ni Excel en `composer.json`. | Generación nativa y ligera de PDF y Excel con licencias libres (MIT/LGPL/Apache). | Requería instalación de generadores compatibles con PHP 8.2 sin depender de `ext-gd`. |
| **Panel de Asistencia** | Calculaba dinámicamente habilitados, ingresados por ambiente y pendientes en memoria para Inertia. | Botones de exportación en PDF y Excel con cabecera, totales por ambiente, ingresados con hora y ausentes. | Faltaban endpoints, servicio de generación, vista Blade para PDF y botones en `PanelAsistencia.vue`. |
| **Histórico de Incidencias** | Listado paginado con filtros (`id_examen`, `tipo_incidencia`, `desde`, `hasta`) en `IncidenciaController`. | Botones de exportación en PDF y Excel respetando fielmente los filtros aplicados en pantalla. | Faltaban endpoints de exportación, servicio de renderizado/streaming y botones con query strings reactivos en `Incidencias/Index.vue`. |
| **Permisos y Control de Acceso** | Restringido a `administrador`, `docente` y `personal de control de ingreso`. | Mismos roles autorizados para descargar reportes. | Se reutilizó el middleware `role:administrador,docente,personal de control de ingreso`. |

---

### 3. Justificación de Librerías Open Source Seleccionadas (CA-3)

Durante la auditoría del entorno (`php -m`), se constató que la extensión `ext-gd` **no se encuentra instalada** en el runtime de PHP de la máquina host.
* `phpoffice/phpspreadsheet` en sus versiones recientes requiere de forma estricta `ext-gd *`, lo que impedía su instalación directa vía Composer.
* Por este motivo, se evaluó e implementó:
  1. **`openspout/openspout` (^4.28, Licencia MIT):**
     * Generador Open Source ultra-rápido de hojas de cálculo OpenXML (`.xlsx`).
     * No requiere `ext-gd`, operando directamente con las extensiones estándar `ext-zip` y `ext-xmlwriter` (ambas habilitadas en el servidor).
     * Mantiene un consumo de memoria plano (O(1)) incluso para miles de registros, generando archivos `.xlsx` válidos y compatibles con Microsoft Excel, Google Sheets y LibreOffice.
  2. **`barryvdh/laravel-dompdf` (^3.1, Licencia MIT / LGPL-2.1):**
     * Wrapper canónico para Laravel que encapsula `dompdf/dompdf`.
     * Permite diseñar las plantillas de los reportes en Blade con HTML5 y CSS estándar, garantizando tipografía institucional, colores y paginación sin requerir servicios externos ni binarios pesados de terceros.

Ambas dependencias cumplen al 100% el **Criterio de Aceptación CA-3**.

---

### 4. Cambios Realizados por Archivo

#### 4.1. Backend

* **`composer.json` y `composer.lock`:**
  * Se añadieron `barryvdh/laravel-dompdf: ^3.1` y `openspout/openspout: ^4.28`.
* **`app/Services/ReporteExportService.php` (Nuevo):**
  * Servicio desacoplado y reutilizable que implementa:
    * `exportarAsistenciaPdf(Examen $examen, array $datos)`: Carga la vista `reportes.asistencia_pdf`, configura tamaño A4 vertical y retorna la descarga del PDF.
    * `exportarAsistenciaExcel(Examen $examen, array $datos)`: Emplea `OpenSpout\Writer\XLSX\Writer` para volcar en un archivo `.xlsx` estilizado: cabecera del examen, resumen general, tabla de totales por ambiente, listado detallado de ingresados (con su hora exacta) y listado de ausentes. Retorna `response()->download()` eliminando el archivo temporal tras el envío.
    * `exportarIncidenciasPdf(Collection $incidencias, array $filtros, ?Examen $examenFiltro)`: Carga `reportes.incidencias_pdf` en orientación horizontal (A4 landscape) con el resumen de filtros aplicados y la tabla detallada de incidencias.
    * `exportarIncidenciasExcel(Collection $incidencias, array $filtros, ?Examen $examenFiltro)`: Genera el archivo Excel de incidencias filtradas con metadatos de emisión y columnas completas.
* **`app/Http/Controllers/PanelAsistenciaController.php`:**
  * Se extrajo el método privado `obtenerDatosCompletos(Examen $seleccionado)` para unificar el cálculo de ambientes, ingresados (con hora formateada `H:i:s` en zona horaria `America/La_Paz`), ausentes y resumen estadístico.
  * Se añadieron los métodos `exportarPdf()` y `exportarExcel()` protegidos por validación de roles (`administrador`, `docente`, `personal de control de ingreso`).
* **`app/Http/Controllers/IncidenciaController.php`:**
  * Se extrajo el método privado `queryIncidencias(array $filtros)` para compartir la lógica de filtrado exacto entre el listado paginado y las exportaciones.
  * Se implementaron `exportarPdf()` y `exportarExcel()`, inyectando `IndexIncidenciaRequest` para validar estrictamente los filtros antes de la descarga.
* **`routes/web.php`:**
  * Se registraron las rutas dentro del grupo autenticado con middleware de roles:
    * `GET /panel-asistencia/exportar/pdf` (`panel-asistencia.exportar.pdf`)
    * `GET /panel-asistencia/exportar/excel` (`panel-asistencia.exportar.excel`)
    * `GET /incidencias/exportar/pdf` (`incidencias.exportar.pdf`)
    * `GET /incidencias/exportar/excel` (`incidencias.exportar.excel`)

#### 4.2. Vistas Blade para PDF

* **`resources/views/reportes/asistencia_pdf.blade.php` (Nuevo):**
  * Plantilla estructurada para el reporte de asistencia con paleta institucional (#002147), cabecera del examen, tarjetas métricas de presentes/ausentes/% de asistencia, tabla de totales por ambiente y listados nominales.
* **`resources/views/reportes/incidencias_pdf.blade.php` (Nuevo):**
  * Plantilla para el histórico de incidencias con badges por tipo de incidencia (Expulsión, Intento indebido, Problema de identificación, Cambio de ambiente), bloque de filtros aplicados y detalle por registro.

#### 4.3. Frontend (Vue 3 / Inertia)

* **`resources/js/Pages/Control/PanelAsistencia.vue`:**
  * Se importaron los iconos `FileText` y `FileSpreadsheet` de `lucide-vue-next`.
  * Se agregaron los botones `"Exportar PDF"` y `"Exportar Excel"` en la barra de estado cuando un examen se encuentra seleccionado, vinculados con `target="_blank"` y descarga directa.
  * Estilos responsive `.btn-export`, `.btn-pdf` y `.btn-excel`.
* **`resources/js/Pages/Incidencias/Index.vue`:**
  * Se agregaron propiedades computadas reactivas `urlExportarPdf` y `urlExportarExcel` que serializan en tiempo real los filtros activos (`id_examen`, `tipo_incidencia`, `desde`, `hasta`) en la URL de descarga.
  * Se incorporaron los botones en la cabecera junto a `+ REPORTAR INCIDENCIA`.

---

### 5. Matriz de Cumplimiento de Criterios de Aceptación

| Criterio | Descripción | Estado | Detalle de Implementación y Prueba |
| :--- | :--- | :---: | :--- |
| **CA-1** | **Exportación de Asistencia (PDF y Excel):** Botones en el Panel de Asistencia, cabecera de examen, totales por ambiente, ingresados con hora exacta y ausentes. | **CUMPLIDO** | `PanelAsistenciaController` genera PDF y Excel (.xlsx) conteniendo exactamente: cabecera, tabla de ambientes con capacidad/ingresados/% ocupación, ingresados con `hora_ingreso_formateada`, y ausentes. |
| **CA-2** | **Exportación de Incidencias con Filtros (PDF y Excel):** Botones en histórico de incidencias respetando estrictamente los filtros activos en pantalla. | **CUMPLIDO** | `IncidenciaController@queryIncidencias` aplica los mismos filtros de fecha, examen y tipo tanto a la vista como a `exportarPdf` y `exportarExcel`. |
| **CA-3** | **Librerías Open Source:** Exclusivamente dependencias MIT/LGPL sin librerías privativas ni dependencias rotas en el entorno. | **CUMPLIDO** | `barryvdh/laravel-dompdf` (MIT/LGPL) y `openspout/openspout` (MIT). Ambos funcionan al 100% en PHP 8.2 sin requerir `ext-gd`. |
| **Guardrails** | **Zero Creep y Permisos:** Acceso para `admin`, `docente` y `control`; rechazo a roles no autorizados (`estudiante`) e invitados. | **CUMPLIDO** | Validado mediante pruebas automatizadas con códigos HTTP 403 y redirección a `/login`. Cero regresiones en la suite existente. |

---

### 6. Resultados de Verificación y Testing

#### 6.1. Pruebas Automatizadas de la HU-26 (`tests/Feature/HU26ReportesExportablesTest.php`)
Batería de 11 tests exhaustivos verificando generación binaria de PDF, integridad estructural ZIP/OpenXML de los archivos Excel, control de acceso y existencia de botones:

```
PASS  Tests\Feature\HU26ReportesExportablesTest
✓ asistencia exportar pdf genera documento valido con estructura completa
✓ asistencia exportar excel genera archivo xlsx valido y estructurado
✓ asistencia exportar sin id examen retorna 400
✓ asistencia exportar estudiante recibe 403
✓ asistencia exportar invitado es redirigido al login
✓ incidencias exportar pdf respeta filtros activos
✓ incidencias exportar excel respeta filtros de rango de fechas
✓ incidencias exportar estudiante es rechazado
✓ incidencias exportar invitado es redirigido al login
✓ panel asistencia vue contiene botones de exportar
✓ incidencias index vue contiene botones y urls de exportacion filtrada

Tests:    11 passed (44 assertions)
Duration: 5.44s
```

#### 6.2. Pruebas de Módulos Relacionados
* **`PanelAsistenciaTest` & `IncidenciaTest`:** `24 passed (72 assertions)`.
* **`HU16ActualizacionFotoQRTest` & `HU17VentanasTiempoTest`:** `18 passed (137 assertions)`.

#### 6.3. Suite Completa del Proyecto (`php artisan test`)
* **Resultado:** **`547 passed (2987 assertions)`** en 104.07s. **Cero fallos, cero regresiones**.

#### 6.4. Compilación de Assets (`npm run build`)
* **Resultado:** Vite v5.4.21 transpiló 2,420 módulos satisfactoriamente sin errores de sintaxis (`built in 13.87s`).

---

### 7. Conclusión

La **Historia de Usuario HU-26** se encuentra completamente implementada, auditada y verificada. Cumple con los más altos estándares de calidad arquitectónica, rendimiento y fidelidad a los requerimientos funcionales solicitados.

