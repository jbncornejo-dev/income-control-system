<?php

namespace App\Http\Requests;

use App\Models\Examen;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CambiarEstadoExamenRequest extends FormRequest
{
    public function authorize(): bool
    {
        $rol = $this->user()?->rol?->nombre_rol;

        if ($rol === 'administrador') {
            return true;
        }

        // El docente solo puede cambiar el estado de exámenes cuyos grupos le
        // pertenecen por completo (un examen compartido lo gestiona el admin).
        if ($rol === 'docente') {
            $examen = $this->route('examen');

            return $examen instanceof Examen
                && $examen->perteneceIntegramenteA($this->user()->id);
        }

        return false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'accion' => ['required', Rule::in(['cancelar', 'anular', 'suspender', 'reanudar'])],
        ];
    }

    public function messages(): array
    {
        return [
            'accion.required' => 'Debe indicarse una acción sobre el estado del examen.',
            'accion.in' => 'La acción sobre el estado no es válida.',
        ];
    }
}
