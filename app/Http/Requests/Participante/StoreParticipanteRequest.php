<?php

namespace App\Http\Requests\Participante;

use App\Models\Participante;
use App\Models\Viaje;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreParticipanteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $viaje = $this->route('viaje');

        return $viaje instanceof Viaje
            && $this->user()?->can('create', [Participante::class, $viaje]);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nombre' => is_string($this->nombre) ? trim($this->nombre) : $this->nombre,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $viaje = $this->route('viaje');
        $viajeId = $viaje instanceof Viaje ? $viaje->id : null;

        return [
            'nombre' => [
                'required',
                'string',
                'min:2',
                'max:100',
                Rule::unique('participantes', 'nombre')->where('viaje_id', $viajeId),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del participante es obligatorio.',
            'nombre.min' => 'El nombre del participante debe tener al menos 2 caracteres.',
            'nombre.max' => 'El nombre del participante no puede superar los 100 caracteres.',
            'nombre.unique' => 'Ya existe un participante con ese nombre en este viaje.',
        ];
    }
}
