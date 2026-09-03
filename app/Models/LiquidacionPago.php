<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiquidacionPago extends Model
{
    protected $table = 'liquidacion_pagos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'liquidacion_id',
        'monto',
        'fecha_pago',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'fecha_pago' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Liquidacion, $this>
     */
    public function liquidacion(): BelongsTo
    {
        return $this->belongsTo(Liquidacion::class);
    }
}
