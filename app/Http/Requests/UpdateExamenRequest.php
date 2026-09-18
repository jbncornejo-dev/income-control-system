<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateExamenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->rol?->nombre_rol === 'administrador';
    }

    protected function prepareForValidation(): void
    {
        foreach (['fecha', 'hora_inicio', 'normas_generales'] as $campo) {
            $valor = $this->input($campo);
            if (is_string($valor)) {
                $this->merge([$campo => trim($valor) === '' ? null : trim($valor)]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'id_asignatura' => ['sometimes', 'integer', 'exists:asignatura,id_asignatura'],
            'fecha' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'hora_inicio' => ['sometimes', 'nullable', 'date_format:H:i'],
            'duracion_minutos' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:720'],
            'normas_generales' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'id_ambientes' => ['sometimes', 'nullable', 'array', 'min:1'],
            'id_ambientes.*' => ['required', 'integer', 'distinct', 'exists:ambiente,id_ambiente'],
        ];
    }

    /**
     * Solo tiene sentido validar "fecha futura" cuando llegan junto fecha y hora de inicio.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $fecha = $this->input('fecha');
            $hora = $this->input('hora_inicio');

            // Si no vienen ambos, no hay horario completo que validar.
            if ($fecha === null || $hora === null) {
                return;
            }

            $inicio = Carbon::createFromFormat('Y-m-d H:i', $fecha.' '.$hora);

            if ($inicio->lessThanOrEqualTo(now())) {
                $validator->errors()->add(
                    'hora_inicio',
                    'La fecha y hora de inicio deben estar en el futuro.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'id_asignatura.integer' => 'La asignatura es inválida.',
            'id_asignatura.exists' => 'La asignatura seleccionada no existe.',
            'fecha.date_format' => 'La fecha debe tener el formato AAAA-MM-DD.',
            'hora_inicio.date_format' => 'La hora de inicio debe tener el formato HH:MM.',
            'duracion_minutos.integer' => 'La duración debe ser un número entero.',
            'duracion_minutos.min' => 'La duración debe ser de al menos 1 minuto.',
            'duracion_minutos.max' => 'La duración no puede superar 720 minutos.',
            'id_ambientes.min' => 'Debe seleccionar al menos un ambiente.',
            'id_ambientes.*.exists' => 'Uno de los ambientes seleccionados no existe.',
            'id_ambientes.*.distinct' => 'Un ambiente no puede seleccionarse más de una vez.',
        ];
    }
}
