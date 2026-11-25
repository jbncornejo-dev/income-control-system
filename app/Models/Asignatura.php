<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Asignatura extends Model
{
    use HasFactory;

    protected $table = 'asignatura';
    protected $primaryKey = 'id_asignatura';
    public $timestamps = false;
    protected $fillable = ['nombre_asignatura'];

    public static function formatearNombre(string $nombre): string
    {
        $nombre = mb_convert_case(trim(preg_replace('/\s+/u', ' ', $nombre)), MB_CASE_TITLE, 'UTF-8');
        return preg_replace_callback('/\b[ivxlcdm]+\b/iu', static function ($match) {
            $romano = strtoupper($match[0]);
            return preg_match('/^M{0,3}(CM|CD|D?C{0,3})(XC|XL|L?X{0,3})(IX|IV|V?I{0,3})$/', $romano)
                ? $romano : $match[0];
        }, $nombre);
    }

    public function setNombreAsignaturaAttribute(string $nombre): void
    {
        $this->attributes['nombre_asignatura'] = self::formatearNombre($nombre);
    }

    // Relación de uno a muchos (asignatura-examen)
    public function examenes()
    {
        return $this->hasMany(Examen::class, 'id_asignatura', 'id_asignatura');
    }

    // Relación de uno a muchos (asignatura-grupo)
    public function grupos()
    {
        return $this->hasMany(Grupo::class, 'id_asignatura', 'id_asignatura');
    }
}
