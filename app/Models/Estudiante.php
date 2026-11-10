<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Estudiante extends Model
{
    use HasFactory;

    protected $table = 'estudiante';

    protected $primaryKey = 'id_estudiante';

    public $timestamps = false;

    protected $fillable = [
        'codigo_universitario',
        'documento_identidad',
        'nombres',
        'apellidos',
        'codigo_qr',
        'email',
        'foto_url',
        'foto_referencia',
        'puede_regenerar_qr',
    ];

    protected $casts = [
        'puede_regenerar_qr' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Estudiante $estudiante) {
            if (! empty($estudiante->foto_url) && empty($estudiante->foto_referencia)) {
                $estudiante->foto_referencia = $estudiante->foto_url;
            }
        });

        static::updating(function (Estudiante $estudiante) {
            // Protección de inmutabilidad: la foto de referencia nunca puede ser sobrescrita una vez establecida
            $originalReferencia = $estudiante->getOriginal('foto_referencia');
            if (! empty($originalReferencia) && $estudiante->isDirty('foto_referencia') && $estudiante->foto_referencia !== $originalReferencia) {
                $estudiante->foto_referencia = $originalReferencia;
            }
        });
    }

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
     * - Correo: cualquier dirección válida (el estudiante recibe notificaciones
     *   y la usa para login; la identidad se valida con documento y código).
     */
    public const REGEX_NOMBRES = "/^[\p{L}][\p{L}\s'-]*$/u";

    public const REGEX_CODIGO_SIS = '/^(19|20)\d{2}\d{5}$/';

    public const REGEX_DOCUMENTO_CI = '/^\d{6,8}$/';

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

    /**
     * Query base de exámenes asociados al estudiante (vía inscripción a grupo o habilitación directa).
     */
    public function examenesQuery()
    {
        $grupoIds = $this->inscripciones()->select('id_grupo');

        return Examen::where(function ($q) {
                $q->whereNull('estado')
                  ->orWhereNotIn('estado', ['cancelado', 'anulado']);
            })
            ->where(function ($query) use ($grupoIds) {
                $query->whereHas('grupos', fn ($q) => $q->whereIn('grupo.id_grupo', $grupoIds))
                    ->orWhereHas('habilitaciones', fn ($q) => $q->where('habilitacion.id_estudiante', $this->id_estudiante));
            });
    }

    /**
     * HU-17: Verifica si el estudiante tiene al menos un examen dentro de la ventana de 24 horas del QR.
     */
    public function tieneExamenEnVentanaQr(?Carbon $ahora = null): bool
    {
        $ahora = $ahora ?? Carbon::now(config('app.timezone', 'America/La_Paz'));

        return $this->examenesQuery()
            ->lazy(500)
            ->contains(fn (Examen $examen) => $examen->estaEnVentanaQr($ahora));
    }

    /**
     * Obtiene el examen vigente más próximo del estudiante (que no haya finalizado).
     */
    public function proximoExamen(?Carbon $ahora = null): ?Examen
    {
        $ahora = $ahora ?? Carbon::now(config('app.timezone', 'America/La_Paz'));

        return $this->examenesQuery()
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->lazy(500)
            ->first(fn (Examen $e) => $e->finExamen()->gte($ahora));
    }
}
