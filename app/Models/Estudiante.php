<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Estudiante extends Model
{
    use HasFactory;

    protected $table = 'estudiante';

    protected $primaryKey = 'id_estudiante';

    public $timestamps = false;

    protected $fillable = ['codigo_universitario', 'documento_identidad', 'nombres', 'apellidos', 'codigo_qr', 'email'];

    /**
     * Prefijo del payload del QR generado por el sistema. El resto del payload
     * es el código universitario, que identifica de forma única al estudiante.
     */
    public const QR_PREFIX = 'ident:v1:';

    /**
     * Formatos de la información del estudiante:
     * - Código: 9 dígitos; los 4 primeros indican el año de ingreso, ej. 201809372.
     * - Documento de identidad: 6 a 8 dígitos, ej. 12590804.
     * - Nombre/Apellidos: letras (con acentos), espacios, apóstrofes y guiones.
     * - Correo institucional: código@dominio, ej. 201809372@est.umss.edu.
     */
    public const REGEX_NOMBRES = "/^[\p{L}][\p{L}\s'-]*$/u";

    public const REGEX_CODIGO_SIS = '/^(19|20)\d{2}\d{5}$/';

    public const REGEX_DOCUMENTO_CI = '/^\d{6,8}$/';

    public const REGEX_EMAIL_UMSS = '/^\d{9}@est\.umss\.edu$/';

    /**
     * Payload del QR autogenerado para un código universitario.
     */
    public static function qrPayload(string $codigoUniversitario): string
    {
        return self::QR_PREFIX.$codigoUniversitario;
    }

    // Relación de uno a muchos (estudiante-habilitacion)
    public function habilitaciones()
    {
        return $this->hasMany(Habilitacion::class, 'id_estudiante', 'id_estudiante');
    }

    // Relación de uno a muchos (estudiante-registro_ingreso)
    public function registrosIngreso()
    {
        return $this->hasMany(RegistroIngreso::class, 'id_estudiante', 'id_estudiante');
    }

    // Relación de uno a muchos (estudiante-incidencia)
    public function incidencias()
    {
        return $this->hasMany(Incidencia::class, 'id_estudiante', 'id_estudiante');
    }

    // Relación de uno a muchos (estudiante-inscripcion)
    public function inscripciones()
    {
        return $this->hasMany(Inscripcion::class, 'id_estudiante', 'id_estudiante');
    }

    // Relación de uno a uno (estudiante-cuenta de usuario)
    public function user()
    {
        return $this->hasOne(User::class, 'id_estudiante', 'id_estudiante');
    }
}
