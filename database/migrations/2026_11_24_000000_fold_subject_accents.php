<?php

use App\Models\Asignatura;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // NFC also handles accents typed as a separate combining character.
        $this->rebuild("translate(lower(normalize(nombre, NFC)), U&'\\00e1\\00e9\\00ed\\00f3\\00fa\\00fc\\00c1\\00c9\\00cd\\00d3\\00da\\00dc', 'aeiouuaeiouu')");
        DB::table('asignatura')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                $nombre = Asignatura::formatearNombre($row->nombre_asignatura);
                if ($nombre !== $row->nombre_asignatura) {
                    DB::table('asignatura')->where('id_asignatura', $row->id_asignatura)
                        ->update(['nombre_asignatura' => $nombre]);
                }
            }
        }, 'id_asignatura');
    }

    public function down(): void
    {
        $this->rebuild("lower(translate(nombre, U&'\\00c1\\00c9\\00cd\\00d3\\00da\\00dc\\00d1', U&'\\00e1\\00e9\\00ed\\00f3\\00fa\\00fc\\00f1'))");
    }

    private function rebuild(string $expression): void
    {
        DB::statement('LOCK TABLE asignatura IN ACCESS EXCLUSIVE MODE');
        DB::statement("CREATE OR REPLACE FUNCTION normalizar_nombre_asignatura(nombre text) RETURNS text
            LANGUAGE sql IMMUTABLE STRICT PARALLEL SAFE
            RETURN btrim(regexp_replace($expression, '[[:space:]]+', ' ', 'g'))");
        DB::statement('REINDEX INDEX asignatura_nombre_normalizado');
        DB::statement('DELETE FROM asignatura_nombres_reservados');
        DB::statement('INSERT INTO asignatura_nombres_reservados(nombre, cantidad)
            SELECT normalizar_nombre_asignatura(nombre_asignatura), count(*) FROM asignatura GROUP BY 1');
    }
};
