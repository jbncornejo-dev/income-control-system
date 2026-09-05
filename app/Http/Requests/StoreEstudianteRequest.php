<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEstudianteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'codigo_universitario' => trim((string) $this->codigo_universitario),
            'documento_identidad' => trim((string) $this->documento_identidad),
            'nombres' => trim((string) $this->nombres),
            'apellidos' => trim((string) $this->apellidos),
            'codigo_qr' => $this->codigo_qr !== null ? trim((string) $this->codigo_qr) : null,
        ]);

        if ($this->codigo_qr === '') {
            $this->merge(['codigo_qr' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'codigo_universitario' => ['required', 'string', 'max:20', 'unique:estudiante,codigo_universitario'],
            'documento_identidad' => ['required', 'string', 'max:20', 'unique:estudiante,documento_identidad'],
            'nombres' => ['required', 'string', 'max:100'],
            'apellidos' => ['required', 'string', 'max:100'],
            'codigo_qr' => ['nullable', 'string', 'max:255', 'unique:estudiante,codigo_qr'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'codigo_universitario.required' => 'El código universitario es obligatorio.',
            'codigo_universitario.unique' => 'El código universitario ya está registrado.',
            'documento_identidad.required' => 'El documento de identidad es obligatorio.',
            'documento_identidad.unique' => 'El documento de identidad ya está registrado.',
            'nombres.required' => 'Los nombres son obligatorios.',
            'apellidos.required' => 'Los apellidos son obligatorios.',
            'codigo_qr.unique' => 'El código QR ya está registrado.',
        ];
    }
}
