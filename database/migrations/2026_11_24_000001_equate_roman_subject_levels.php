<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('LOCK TABLE asignatura IN ACCESS EXCLUSIVE MODE');
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION normalizar_nombre_asignatura(nombre text) RETURNS text
                LANGUAGE plpgsql IMMUTABLE STRICT PARALLEL SAFE AS $$
            DECLARE
                nombre_base text := btrim(regexp_replace(
                    translate(lower(normalize(nombre, NFC)),
                        'áéíóúüÁÉÍÓÚÜ', 'aeiouuaeiouu'), '[[:space:]]+', ' ', 'g'));
                sufijo text := substring(nombre_base from ' ([ivxlcdm]+)$');
                valores jsonb := '{"i":1,"v":5,"x":10,"l":50,"c":100,"d":500,"m":1000}';
                total integer := 0;
                anterior integer := 0;
                actual integer;
            BEGIN
                IF sufijo IS NULL OR sufijo !~ '^m{0,3}(cm|cd|d?c{0,3})(xc|xl|l?x{0,3})(ix|iv|v?i{0,3})$' THEN
                    RETURN nombre_base;
                END IF;

                FOR posicion IN REVERSE length(sufijo)..1 LOOP
                    actual := (valores ->> substr(sufijo, posicion, 1))::integer;
                    total := total + CASE WHEN actual < anterior THEN -actual ELSE actual END;
                    anterior := actual;
                END LOOP;

                RETURN left(nombre_base, -length(sufijo)) || total::text;
            END;
            $$;
            SQL);
        $this->rebuild();
    }

    public function down(): void
    {
        DB::statement('LOCK TABLE asignatura IN ACCESS EXCLUSIVE MODE');
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION normalizar_nombre_asignatura(nombre text) RETURNS text
                LANGUAGE sql IMMUTABLE STRICT PARALLEL SAFE
                RETURN btrim(regexp_replace(
                    translate(lower(normalize(nombre, NFC)),
                        'áéíóúüÁÉÍÓÚÜ', 'aeiouuaeiouu'), '[[:space:]]+', ' ', 'g'));
            SQL);
        $this->rebuild();
    }

    private function rebuild(): void
    {
        DB::statement('REINDEX INDEX asignatura_nombre_normalizado');
        DB::statement('DELETE FROM asignatura_nombres_reservados');
        DB::statement('INSERT INTO asignatura_nombres_reservados(nombre, cantidad)
            SELECT normalizar_nombre_asignatura(nombre_asignatura), count(*) FROM asignatura GROUP BY 1');
    }
};
