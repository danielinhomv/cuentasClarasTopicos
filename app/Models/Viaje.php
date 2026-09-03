<?php

namespace App\Models;

use Database\Factories\ViajeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Viaje extends Model
{
    /** @use HasFactory<ViajeFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nombre',
        'descripcion',
        'user_id',
        'codigo_invitacion',
        'tipo_cambio_usd',
        'tipo_cambio_usdt',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo_cambio_usd' => 'float',
            'tipo_cambio_usdt' => 'float',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Viaje $viaje) {
            if (empty($viaje->codigo_invitacion)) {
                do {
                    $codigo = strtoupper(Str::random(8));
                } while (static::where('codigo_invitacion', $codigo)->exists());
                $viaje->codigo_invitacion = $codigo;
            }

            if (is_null($viaje->tipo_cambio_usd)) {
                $viaje->tipo_cambio_usd = 6.9600;
            }

            if (is_null($viaje->tipo_cambio_usdt)) {
                $viaje->tipo_cambio_usdt = 10.5000;
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Participante, $this>
     */
    public function participantes(): HasMany
    {
        return $this->hasMany(Participante::class);
    }

    /**
     * @return HasMany<Gasto, $this>
     */
    public function gastos(): HasMany
    {
        return $this->hasMany(Gasto::class);
    }

    /**
     * @return HasMany<Liquidacion, $this>
     */
    public function liquidaciones(): HasMany
    {
        return $this->hasMany(Liquidacion::class);
    }

    /**
     * @return HasMany<GastoBitacora, $this>
     */
    public function bitacoras(): HasMany
    {
        return $this->hasMany(GastoBitacora::class);
    }
}
