<?php

namespace App\Http\Controllers;

use App\Models\Ambiente;
use App\Models\Asignatura;
use App\Models\Estudiante;
use App\Models\Examen;
use App\Models\User;
use Inertia\Inertia;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class DashboardController extends Controller
{
    /**
     * Display the admin dashboard.
     */
    public function admin()
    {
        $mes = request('mes', now()->month);
        $anio = request('anio', now()->year);

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'estudiantes' => Estudiante::count(),
                'examenes' => Examen::count(),
                'asignaturas' => Asignatura::count(),
                'ambientes' => Ambiente::count(),
                'usuarios' => User::count(),
            ],
            'calendarioData' => [
                'mes' => (int) $mes,
                'anio' => (int) $anio,
            ],
            'examenesCalendario' => Examen::with(['asignatura'])
                ->whereMonth('fecha', $mes)
                ->whereYear('fecha', $anio)
                ->get(),
            // Corregida la relación de ambientes para coincidir con tu controlador
            'proximosExamenes' => Examen::with(['asignatura', 'examenesAmbientes.ambiente'])
                ->orderBy('fecha', 'asc')
                ->where('fecha', '>=', now()->toDateString())
                ->take(4)
                ->get(),
            'noticias' => Cache::remember('umss_noticias', 3600, function () {
                try {
                    $response = Http::timeout(5)->get('https://www.umss.edu.bo/categoria/noticias/feed/');
                    if ($response->successful()) {
                        $xml = simplexml_load_string($response->body(), 'SimpleXMLElement', LIBXML_NOCDATA);
                        $items = [];
                        foreach ($xml->channel->item as $item) {
                            $items[] = [
                                'titulo' => (string) $item->title,
                                'enlace' => (string) $item->link,
                                'fecha' => date('M Y', strtotime((string) $item->pubDate)),
                                'resumen' => mb_strimwidth(strip_tags((string) $item->description), 0, 120, '...')
                            ];
                            if (count($items) >= 4) break;
                        }
                        return $items;
                    }
                } catch (\Exception $e) {
                    return null;
                }
                return null;
            }),
        ]);
    }

    /**
     * Display the docente dashboard.
     */
    public function docente()
    {
        $user = auth()->user();

        $proximosExamenes = Examen::with(['asignatura', 'examenesAmbientes.ambiente', 'tipo:id_tipo_examen,nombre'])
            ->withCount([
                'habilitaciones as hab_count' => function ($query) {
                    $query->where('estado_habilitado', true);
                },
                'habilitaciones as inhab_count' => function ($query) {
                    $query->where('estado_habilitado', false);
                },
            ])
            // Solo exámenes que cubren al menos uno de los grupos del docente.
            ->whereHas('grupos', fn ($query) => $query->where('grupo.id_usuario', $user->id))
            ->where(function ($query) {
                $query->where('fecha', '>', now()->toDateString())
                    ->orWhere(function ($q) {
                        $q->where('fecha', '=', now()->toDateString())
                            ->where('hora_inicio', '>', now()->toTimeString());
                    });
            })
            ->orderBy('fecha', 'asc')
            ->orderBy('hora_inicio', 'asc')
            ->take(3)
            ->get();

        $mes = request('mes', now()->month);
        $anio = request('anio', now()->year);

        $examenesCalendario = Examen::with(['asignatura'])
            ->whereHas('grupos', fn ($query) => $query->where('grupo.id_usuario', $user->id))
            ->whereMonth('fecha', $mes)
            ->whereYear('fecha', $anio)
            ->get();

        return Inertia::render('Docente/Dashboard', [
            'stats' => [
                'examenes' => $proximosExamenes->count(),
                'habilitados' => (int) $proximosExamenes->sum('hab_count'),
                'inhabilitados' => (int) $proximosExamenes->sum('inhab_count'),
            ],
            'proximosExamenes' => $proximosExamenes,
            'calendarioData' => [
                'mes' => (int) $mes,
                'anio' => (int) $anio,
            ],
            'examenesCalendario' => $examenesCalendario,
            'noticias' => Cache::remember('umss_noticias', 3600, function () {
                try {
                    $response = Http::timeout(5)->get('https://www.umss.edu.bo/categoria/noticias/feed/');
                    if ($response->successful()) {
                        $xml = simplexml_load_string($response->body(), 'SimpleXMLElement', LIBXML_NOCDATA);
                        $items = [];
                        foreach ($xml->channel->item as $item) {
                            $items[] = [
                                'titulo' => (string) $item->title,
                                'enlace' => (string) $item->link,
                                'fecha' => date('M Y', strtotime((string) $item->pubDate)),
                                'resumen' => mb_strimwidth(strip_tags((string) $item->description), 0, 120, '...')
                            ];
                            if (count($items) >= 4) break;
                        }
                        return $items;
                    }
                } catch (\Exception $e) {
                    return null;
                }
                return null;
            }),
        ]);
    }

    /**
     * Display the control dashboard.
     */
    public function control()
    {
        // Definición del intervalo de "fechas inmediatas"
        // Se define inicialmente como 1 día (hoy y mañana) para que el personal
        // pueda prever y preparar los ambientes de los exámenes próximos inmediatos.
        $diasVisibilidad = 1;

        $fechaInicio = now()->toDateString();
        $fechaFin = now()->addDays($diasVisibilidad)->toDateString();

        $examenesControl = Examen::with(['asignatura', 'examenesAmbientes.ambiente'])
            ->whereBetween('fecha', [$fechaInicio, $fechaFin])
            ->orderBy('fecha', 'asc')
            ->orderBy('hora_inicio', 'asc')
            ->get();

        $idsExamenes = $examenesControl->pluck('id_examen');
        $ingresosTotales = \App\Models\RegistroIngreso::whereHas('examenAmbiente', function ($q) use ($idsExamenes) {
            $q->whereIn('id_examen', $idsExamenes);
        })->count();

        return Inertia::render('Control/Dashboard', [
            'stats' => [
                'hoy' => $examenesControl->where('fecha', $fechaInicio)->count(),
                // Estadísticas calculadas en tiempo real a partir de los datos existentes
                'en_curso' => $examenesControl->filter(fn ($e) => $e->estado_actual === 'en_curso')->count(),
                'ingresos' => $ingresosTotales,
            ],
            'examenes' => $examenesControl,
        ]);
    }
}
