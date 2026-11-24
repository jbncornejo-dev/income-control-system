<?php

namespace App\Http\Controllers;

use App\Models\Asignatura;
use App\Models\Rol;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class RolController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Roles/Index', [
            'roles' => Rol::orderBy('nombre_rol')->withCount('users')->get(),
            'rolesBase' => Rol::ROLES_BASE,
            'permisos' => Rol::PERMISOS_REPORTES,
            'asignaturas' => Asignatura::orderBy('nombre_asignatura')->get(['id_asignatura', 'nombre_asignatura']),
        ]);
    }

    public function store(Request $request)
    {
        $this->guardar($request, new Rol);

        return back()->with('success', 'Rol creado.');
    }

    public function update(Request $request, Rol $rol)
    {
        abort_if(array_key_exists($rol->nombre_rol, Rol::ROLES_BASE), 403);
        $this->guardar($request, $rol);

        return back()->with('success', 'Rol actualizado.');
    }

    private function guardar(Request $request, Rol $rol): void
    {
        $nombre = $request->input('nombre_rol');
        if (is_string($nombre)) {
            $request->merge(['nombre_rol' => Str::lower(Str::squish($nombre))]);
        }

        $datos = $request->validate([
            'nombre_rol' => [
                'required', 'string', 'max:'.Rol::LONGITUD_NOMBRE,
                Rule::notIn(array_keys(Rol::ROLES_BASE)),
                Rule::unique('rol', 'nombre_rol')->ignore($rol),
            ],
            'asignaturas_reportes' => ['sometimes', 'nullable', 'array', 'list', 'min:1'],
            'asignaturas_reportes.*' => ['required', 'integer', 'distinct', Rule::exists('asignatura', 'id_asignatura')],
            'permisos' => ['present', 'array', 'list'],
            'permisos.*' => ['string', 'distinct', Rule::in(array_keys(Rol::PERMISOS_REPORTES))],
        ], [
            'nombre_rol.required' => 'Escribe un nombre para el rol.',
            'nombre_rol.unique' => 'Ya existe un rol con ese nombre.',
            'nombre_rol.not_in' => 'Ese nombre pertenece a un rol del sistema.',
            'permisos.*.in' => 'Selecciona permisos del catálogo.',
            'asignaturas_reportes.min' => 'Selecciona al menos una asignatura.',
            'asignaturas_reportes.*.exists' => 'Una de las asignaturas seleccionadas ya no está disponible.',
        ]);

        if (isset($datos['asignaturas_reportes'])) {
            $datos['asignaturas_reportes'] = array_map('intval', $datos['asignaturas_reportes']);
        }

        try {
            $rol->fill($datos)->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['nombre_rol' => 'Ya existe un rol con ese nombre.']);
        }
    }
}
