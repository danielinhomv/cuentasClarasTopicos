<?php

namespace Database\Factories;

use App\Models\Participante;
use App\Models\Viaje;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Participante>
 */
class ParticipanteFactory extends Factory
{
    protected $model = Participante::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'viaje_id' => Viaje::factory(),
            'nombre' => fake()->unique()->firstName(),
        ];
    }
}
