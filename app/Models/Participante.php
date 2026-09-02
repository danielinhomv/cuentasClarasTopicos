<?php

namespace App\Models;

use Database\Factories\ParticipanteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
    ];

    /**
     * @return BelongsTo<Viaje, $this>
     */
    public function viaje(): BelongsTo
    {
        return $this->belongsTo(Viaje::class);
    }
}
