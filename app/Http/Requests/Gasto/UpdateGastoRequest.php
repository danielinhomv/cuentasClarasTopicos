<?php

namespace App\Http\Requests\Gasto;

use App\Models\Gasto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateGastoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $gasto = $this->route('gasto');

        return $gasto instanceof Gasto
            && $this->user()?->can('update', $gasto);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'concepto' => is_string($this->concepto) ? trim($this->concepto) : $this->concepto,
            'moneda' => $this->moneda ? strtoupper(trim($this->moneda)) : 'BOB',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $gasto = $this->route('gasto');
        $viajeId = $gasto instanceof Gasto ? $gasto->viaje_id : null;

        return [
            'concepto' => ['required', 'string', 'min:2', 'max:200'],
            'monto' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'moneda' => ['nullable', 'string', 'in:BOB,USD,USDT'],
            'fecha' => ['required', 'date'],
            'pagador_id' => [
                'required',
                'integer',
                Rule::exists('participantes', 'id')->where('viaje_id', $viajeId),
            ],
            'excluidos' => ['nullable', 'array'],
            'excluidos.*' => [
                'integer',
                'distinct',
                Rule::exists('participantes', 'id')->where('viaje_id', $viajeId),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'concepto.required' => 'El concepto del gasto es obligatorio.',
            'concepto.min' => 'El concepto debe tener al menos 2 caracteres.',
            'concepto.max' => 'El concepto no puede superar los 200 caracteres.',
            'monto.required' => 'El monto es obligatorio.',
            'monto.numeric' => 'El monto debe ser un valor numérico.',
            'monto.min' => 'El monto debe ser mayor a cero.',
            'moneda.in' => 'La divisa seleccionada debe ser BOB, USD o USDT.',
            'fecha.required' => 'La fecha del gasto es obligatoria.',
            'fecha.date' => 'La fecha ingresada no es válida.',
            'pagador_id.required' => 'El pagador es obligatorio.',
            'pagador_id.exists' => 'El pagador seleccionado no es válido para este viaje.',
            'excluidos.array' => 'La lista de excluidos debe ser un arreglo.',
            'excluidos.*.exists' => 'Uno o más participantes excluidos no pertenecen a este viaje.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $gasto = $this->route('gasto');
            if (! ($gasto instanceof Gasto)) {
                return;
            }

            $excluidos = $this->input('excluidos');
            if (is_array($excluidos) && count($excluidos) > 0) {
                $totalParticipantes = $gasto->viaje->participantes()->count();
                $uniqueExcluidos = count(array_unique($excluidos));

                if ($totalParticipantes > 0 && $uniqueExcluidos >= $totalParticipantes) {
                    $validator->errors()->add(
                        'excluidos',
                        'Al menos un participante debe quedar incluido en la división del gasto.'
                    );
                }
            }
        });
    }
}
