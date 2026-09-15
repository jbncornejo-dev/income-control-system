# HU7: verificar la integración de asignaturas

La página `/asignaturas` utiliza Inertia y datos de PostgreSQL. El administrador accede desde el enlace **Asignaturas** del menú.

## Preparación

Con los contenedores levantados, compilar la vista:

```bash
podman compose exec -T workspace bash -ic 'npm run build'
```

Abrir `http://localhost:8080/login`, iniciar sesión como administrador y seleccionar **Asignaturas**. Debe aparecer la tabla, no el JSON ni los cinco registros ficticios originales. Si se había abierto la aplicación antes de compilar, recargar el navegador.

## Verificación manual

Usar registros de prueba identificables; eliminar solo los creados para esta comprobación.

1. **Registrar:** pulsar **Nueva Asignatura**, escribir `Prueba integración HU7 A` y guardar. Debe cerrarse el modal y aparecer el mensaje de registro. Recargar y comprobar que el registro persiste.
2. **Validar:** intentar registrar el nombre vacío y luego el mismo nombre del paso anterior. El modal debe permanecer abierto y mostrar el error junto al nombre; para duplicados: `Ya existe una asignatura con ese nombre`.
3. **Buscar:** escribir una parte del nombre, también con otras mayúsculas, y pulsar **Buscar**. Probar el ID exacto, ambos filtros juntos y una combinación que no coincida. **Limpiar** debe restaurar el listado completo. La búsqueda distingue tildes.
4. **Paginar:** disponer de al menos 16 asignaturas que coincidan con el filtro. La primera página debe mostrar 15 y **Siguiente** debe conservar la búsqueda. El contador indica el total filtrado. Una nueva búsqueda comienza en la página 1.
5. **Editar:** abrir **Editar**, comprobar que el ID es de solo lectura y cambiar el nombre. Recargar y verificar que conserva el ID. Guardar sin cambiar el nombre debe funcionar; usar el nombre de otra asignatura debe mostrar el error de duplicado.
6. **Eliminar sin exámenes:** cancelar una eliminación y comprobar que la fila permanece. Volver a eliminar y confirmar; debe desaparecer y mostrarse `Asignatura eliminada correctamente.`.
7. **Eliminar con exámenes:** usar una asignatura que tenga un examen relacionado en los datos de desarrollo. Al confirmar debe permanecer la fila y mostrarse `No se puede eliminar la asignatura porque tiene exámenes registrados`, sin mensaje de éxito. Si no hay datos para este caso, puede comprobarse con la prueba automatizada indicada abajo.
8. **Roles:** iniciar sesión como docente, personal de control y estudiante. El menú no debe incluir Asignaturas; el acceso directo a `/asignaturas` debe devolver 403. Sin sesión debe redirigir al login.

En las herramientas del navegador, la pestaña **Red/Network** permite comprobar:

| Acción | Solicitud |
|---|---|
| Listar/buscar | `GET /asignaturas`, con `id_asignatura` y/o `nombre_asignatura` |
| Paginar | `GET /asignaturas?page=2`, conservando filtros |
| Registrar | `POST /asignaturas`, con `nombre_asignatura` |
| Editar | `PATCH /asignaturas/{id}`, con `nombre_asignatura` |
| Eliminar | `DELETE /asignaturas/{id}` |

Las visitas Inertia reciben `component: Asignaturas/Index`, `props.asignaturas` y `props.filtros`. Registro, edición y eliminación redirigen al listado; los mensajes llegan mediante `props.flash` y los errores de validación mediante `props.errors`.

## Pruebas automatizadas

La base `hu7_testing` es independiente de `app`. **No sustituirla por la base de desarrollo:** las pruebas usan `RefreshDatabase`.

```bash
podman compose exec -T workspace env \
  APP_ENV=testing DB_CONNECTION=pgsql \
  DB_HOST=postgres DB_PORT=5432 DB_DATABASE=hu7_testing \
  XDEBUG_MODE=off php artisan test --filter=Asignatura
```

Para ejecutar únicamente los casos de integración, usar `--filter=AsignaturaInertiaTest`. Para la suite completa, quitar `--filter=Asignatura`.

Se verifican las propiedades de Inertia, filtros, datos persistidos, mensajes tras las redirecciones, validaciones y permisos. Las comprobaciones visuales del modal y el menú se realizan con los pasos manuales anteriores.
