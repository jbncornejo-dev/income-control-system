# Rendimiento del backend

La refactorización reduce consultas repetidas y modelos cargados en memoria. Las rutas, permisos, validaciones, auditoría, ventanas horarias y formatos de respuesta conservan sus contratos.

## Mediciones

Comparación con el backend anterior, usando PHP 8.2 y PostgreSQL 14 en una base temporal separada de desarrollo:

| Operación y datos sintéticos | Consultas antes → después | Pico adicional del heap PHP antes → después |
| --- | --- | --- |
| Comprobar un CSV de una fila contra 30.000 estudiantes existentes | 32 → 2 | 9,814 → 0,842 MiB |
| Preparar el registro de ingreso de 20 exámenes | 64 → 6 | 0,890 → 0,796 MiB |
| Consolidar asistencia: 8.000 habilitados, 4.000 ingresos y 40 ambientes | 7 → 3 | 54,084 → 11,571 MiB |

Las respuestas completas de registro y los datos de asistencia se compararon byte a byte con la implementación anterior. El CSV conserva la clasificación de duplicados de la base y del archivo, incluso entre lotes.

La memoria se midió con `memory_get_peak_usage()` respecto al inicio de cada operación; excluye el arranque de Laravel y no representa el RSS completo del proceso ni la memoria del servidor PostgreSQL. Son mediciones sintéticas, no una garantía de consumo en producción.

## Cambios principales

- El CSV consulta candidatos en lotes de 500 y conserva solo coincidencias, manteniendo la comprobación previa a las inserciones.
- Asistencia utiliza proyecciones de lectura, agrupa ingresos una sola vez y evita construir la lista de exportación durante el refresco del panel.
- El registro carga las asignaciones en conjunto y comprueba ingresos por examen con `EXISTS`.
- Las habilitaciones derivadas de grupos se insertan directamente desde SQL; el reparto mantiene su posición entre lotes.
- Las peticiones parciales de Inertia calculan únicamente las propiedades solicitadas. Los próximos exámenes del docente se comparten dentro de la petición entre la lista y sus métricas.
- Las exportaciones de incidencias recorren lotes con orden estable. `ReadSnapshot` mantiene una lectura PostgreSQL consistente durante el conteo y la generación; si existe una transacción del llamador, conserva su aislamiento.
- La caché de noticias sigue limitada a cuatro elementos durante una hora. Las mejoras no requieren cachés persistentes nuevas ni migraciones.

## Verificación

`tests/Feature/BackendPerformanceTest.php` comprueba presupuestos de consultas, límites entre lotes, filtros, aislamiento por examen y respuestas parciales. `tests/Feature/ReadSnapshotTest.php` prueba una inserción concurrente real; `tests/Unit/ReportExporterTest.php` compara los bytes de los reportes con filas normales y diferidas.

Ejecutar `php artisan test` con PostgreSQL y la base dedicada `app_testing` configurados. Las pruebas de PDF/XLSX requieren las dependencias DomPDF/OpenSpout declaradas en `composer.json` y sus extensiones PHP. Estas dependencias faltaban en el entorno de medición, por lo que la generación final de esos archivos sigue pendiente de validación.

Las respuestas que deben incluir listas completas y el renderizado de documentos siguen consumiendo memoria proporcional al volumen devuelto. La auditoría y las validaciones de concurrencia de las escrituras permanecen activas.
