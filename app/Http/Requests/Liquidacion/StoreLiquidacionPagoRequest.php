<?php

namespace App\Http\Requests\Liquidacion;

use App\Models\Liquidacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreLiquidacionPagoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $liquidacion = $this->route('liquidacion');

        return $liquidacion instanceof Liquidacion
            && $this->user()?->can('view', $liquidacion->viaje);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'monto' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'fecha_pago' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'monto.required' => 'El monto del pago es obligatorio.',
            'monto.numeric' => 'El monto del pago debe ser un valor numérico.',
            'monto.min' => 'El monto del pago debe ser mayor a cero.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $liquidacion = $this->route('liquidacion');
            if (! ($liquidacion instanceof Liquidacion)) {
                return;
            }

            $monto = $this->input('monto');
            if (! is_numeric($monto)) {
                return;
            }

            $montoCentavos = (int) round(((float) $monto) * 100);
            $pendienteCentavos = (int) round(((float) $liquidacion->monto_pendiente) * 100);

            if ($montoCentavos > $pendienteCentavos) {
                $validator->errors()->add(
                    'monto',
                    'El pago no puede superar el monto pendiente de la deuda.'
                );
            }
        });
    }
}
