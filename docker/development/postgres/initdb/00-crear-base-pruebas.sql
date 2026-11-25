-- Base de datos dedicada para las pruebas (phpunit la usa vía app_testing).
-- Los scripts de /docker-entrypoint-initdb.d solo corren la primera vez que se
-- inicializa el volumen de Postgres; si el volumen ya existe (caso del dev
-- actual), crear la base manualmente con:
--   podman compose exec postgres createdb -U nath app_testing
CREATE DATABASE app_testing OWNER nath;