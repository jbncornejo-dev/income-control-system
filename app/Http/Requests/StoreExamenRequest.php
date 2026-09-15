<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreExamenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->rol?->nombre_rol === 'administrador';
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'fecha' => trim((string) $this->fecha),
            'hora_inicio' => trim((string) $this->hora_inicio),
            'normas_generales' => $this->normas_generales !== null
                ? trim((string) $this->normas_generales)
                : null,
        ]);

        if ($this->normas_generales === '') {
            $this->merge(['normas_generales' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'id_asignatura' => ['required', 'integer', 'exists:asignatura,id_asignatura'],
            'fecha' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'duracion_minutos' => ['required', 'integer', 'min:1', 'max:720'],
            'normas_generales' => ['nullable', 'string', 'max:5000'],
            'id_ambientes' => ['required', 'array', 'min:1'],
            'id_ambientes.*' => ['required', 'integer', 'distinct', 'exists:ambiente,id_ambiente'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $inicio = Carbon::createFromFormat(
                'Y-m-d H:i',
                $this->input('fecha').' '.$this->input('hora_inicio')
            );

            if ($inicio->lessThanOrEqualTo(now())) {
                $validator->errors()->add(
                    'hora_inicio',
                    'La fecha y hora de inicio deben estar en el futuro.'
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'id_asignatura.required' => 'La asignatura es obligatoria.',
            'id_asignatura.exists' => 'La asignatura seleccionada no existe.',
            'fecha.required' => 'La fecha del examen es obligatoria.',
            'fecha.date_format' => 'La fecha debe tener el formato AAAA-MM-DD.',
            'fecha.after_or_equal' => 'La fecha del examen no puede estar en el pasado.',
            'hora_inicio.required' => 'La hora de inicio es obligatoria.',
            'hora_inicio.date_format' => 'La hora de inicio debe tener el formato HH:MM.',
            'duracion_minutos.required' => 'La duración es obligatoria.',
            'duracion_minutos.min' => 'La duración debe ser de al menos 1 minuto.',
            'duracion_minutos.max' => 'La duración no puede superar 720 minutos.',
            'id_ambientes.required' => 'Debe seleccionar al menos un ambiente.',
            'id_ambientes.min' => 'Debe seleccionar al menos un ambiente.',
            'id_ambientes.*.exists' => 'Uno de los ambientes seleccionados no existe.',
            'id_ambientes.*.distinct' => 'Un ambiente no puede seleccionarse más de una vez.',
        ];
    }
}
