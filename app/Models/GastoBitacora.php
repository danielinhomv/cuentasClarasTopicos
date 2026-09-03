<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GastoBitacora extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'gasto_bitacoras';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'viaje_id',
        'gasto_id',
        'user_id',
        'actor_nombre',
        'accion',
        'gasto_concepto',
        'datos_antes',
        'datos_despues',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'datos_antes' => 'array',
            'datos_despues' => 'array',
            'created_at' => 'datetime',
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
     * @return BelongsTo<Gasto, $this>
     */
    public function gasto(): BelongsTo
    {
        return $this->belongsTo(Gasto::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
