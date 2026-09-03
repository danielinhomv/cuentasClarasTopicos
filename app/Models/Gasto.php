<?php

namespace App\Models;

use Database\Factories\GastoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Gasto extends Model
{
    /** @use HasFactory<GastoFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'concepto',
        'monto',
        'moneda',
        'tipo_cambio',
        'fecha',
        'pagador_id',
        'viaje_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'tipo_cambio' => 'float',
            'fecha' => 'date',
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
    public function pagador(): BelongsTo
    {
        return $this->belongsTo(Participante::class, 'pagador_id');
    }

    /**
     * @return BelongsToMany<Participante, $this>
     */
    public function excluidos(): BelongsToMany
    {
        return $this->belongsToMany(Participante::class, 'gasto_exclusiones')
            ->withTimestamps();
    }
}
