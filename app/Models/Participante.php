<?php

namespace App\Models;

use Database\Factories\ParticipanteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Participante extends Model
{
    /** @use HasFactory<ParticipanteFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nombre',
        'viaje_id',
        'user_id',
    ];

    /**
     * @return BelongsTo<Viaje, $this>
     */
    public function viaje(): BelongsTo
    {
        return $this->belongsTo(Viaje::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Gasto, $this>
     */
    public function gastosPagados(): HasMany
    {
        return $this->hasMany(Gasto::class, 'pagador_id');
    }

    /**
     * @return BelongsToMany<Gasto, $this>
     */
    public function gastosExcluidos(): BelongsToMany
    {
        return $this->belongsToMany(Gasto::class, 'gasto_exclusiones')
            ->withTimestamps();
    }

    /**
     * Gastos en los que este participante está incluido en la división.
     *
     * @return BelongsToMany<Gasto, $this>
     */
    public function gastosParticipados(): BelongsToMany
    {
        return $this->belongsToMany(Gasto::class, 'gasto_participantes')
            ->withTimestamps();
    }

    /**
     * @return HasMany<Liquidacion, $this>
     */
    public function deudasComoDeudor(): HasMany
    {
        return $this->hasMany(Liquidacion::class, 'deudor_id');
    }

    /**
     * @return HasMany<Liquidacion, $this>
     */
    public function deudasComoAcreedor(): HasMany
    {
        return $this->hasMany(Liquidacion::class, 'acreedor_id');
    }
}
