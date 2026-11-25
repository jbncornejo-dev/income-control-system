<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Conservar las asignaturas existentes y sus relaciones, incluso si ya
        // hay duplicados. La reserva por nombre normalizado impide que se
        // agreguen coincidencias, incluso mediante escrituras simultáneas.
        DB::statement('LOCK TABLE asignatura IN SHARE ROW EXCLUSIVE MODE');
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION normalizar_nombre_asignatura(nombre text) RETURNS text
                LANGUAGE sql IMMUTABLE STRICT PARALLEL SAFE
                RETURN lower(btrim(regexp_replace(
                    translate(nombre, 'ÁÉÍÓÚÜÑ', 'áéíóúüñ'), '[[:space:]]+', ' ', 'g'
                )));

            CREATE INDEX asignatura_nombre_normalizado
                ON asignatura (normalizar_nombre_asignatura(nombre_asignatura));

            CREATE OR REPLACE FUNCTION validar_nombre_asignatura() RETURNS trigger AS $$
            DECLARE nombre_normalizado text;
            BEGIN
                nombre_normalizado := normalizar_nombre_asignatura(NEW.nombre_asignatura);
                IF TG_OP = 'UPDATE' AND nombre_normalizado = normalizar_nombre_asignatura(OLD.nombre_asignatura) THEN
                    RETURN NEW;
                END IF;

                INSERT INTO asignatura_nombres_reservados (nombre)
                    VALUES (nombre_normalizado)
                    ON CONFLICT DO NOTHING;
                IF NOT FOUND THEN
                    RAISE unique_violation USING MESSAGE = 'Ya existe una asignatura con ese nombre';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE OR REPLACE FUNCTION liberar_nombre_asignatura() RETURNS trigger AS $$
            DECLARE nombre_anterior text;
            BEGIN
                nombre_anterior := normalizar_nombre_asignatura(OLD.nombre_asignatura);
                IF TG_OP = 'UPDATE' AND nombre_anterior = normalizar_nombre_asignatura(NEW.nombre_asignatura) THEN
                    RETURN NULL;
                END IF;

                UPDATE asignatura_nombres_reservados SET cantidad = cantidad - 1 WHERE nombre = nombre_anterior;
                DELETE FROM asignatura_nombres_reservados WHERE nombre = nombre_anterior AND cantidad = 0;
                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TABLE asignatura_nombres_reservados (
                nombre text PRIMARY KEY,
                cantidad bigint NOT NULL DEFAULT 1
            );
            INSERT INTO asignatura_nombres_reservados (nombre, cantidad) SELECT
                normalizar_nombre_asignatura(nombre_asignatura), count(*)
                FROM asignatura GROUP BY 1;

            CREATE TRIGGER asignatura_reservar_nombre
                BEFORE INSERT OR UPDATE OF nombre_asignatura ON asignatura
                FOR EACH ROW EXECUTE FUNCTION validar_nombre_asignatura();
            CREATE TRIGGER asignatura_liberar_nombre
                AFTER DELETE OR UPDATE OF nombre_asignatura ON asignatura
                FOR EACH ROW EXECUTE FUNCTION liberar_nombre_asignatura();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TRIGGER asignatura_liberar_nombre ON asignatura;
            DROP TRIGGER asignatura_reservar_nombre ON asignatura;
            DROP FUNCTION liberar_nombre_asignatura();
            DROP FUNCTION validar_nombre_asignatura();
            DROP TABLE asignatura_nombres_reservados;
            DROP INDEX asignatura_nombre_normalizado;
            DROP FUNCTION normalizar_nombre_asignatura(text);
            SQL);
    }
};
