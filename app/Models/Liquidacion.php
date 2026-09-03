<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Liquidacion extends Model
{
    protected $table = 'liquidaciones';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'viaje_id',
        'deudor_id',
        'acreedor_id',
        'monto_original',
        'monto_pagado',
        'monto_pendiente',
        'estado',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monto_original' => 'decimal:2',
            'monto_pagado' => 'decimal:2',
            'monto_pendiente' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Viaje, $this>
     */
    public function viaje(): BelongsTo
    {
        return $this->belongsTo(Viaje::class);
    }

    /**
     * @return BelongsTo<Participante, $this>
     */
    public function deudor(): BelongsTo
    {
        return $this->belongsTo(Participante::class, 'deudor_id');
    }

    /**
     * @return BelongsTo<Participante, $this>
     */
    public function acreedor(): BelongsTo
    {
        return $this->belongsTo(Participante::class, 'acreedor_id');
    }

    /**
     * @return HasMany<LiquidacionPago, $this>
     */
    public function pagos(): HasMany
    {
        return $this->hasMany(LiquidacionPago::class);
    }

    public function estaLiquidada(): bool
    {
        return $this->estado === 'liquidada' || (int) round(((float) $this->monto_pendiente) * 100) === 0;
    }
}
