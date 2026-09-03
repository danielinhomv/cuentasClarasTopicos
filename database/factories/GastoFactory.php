<?php

namespace Database\Factories;

use App\Models\Gasto;
use App\Models\Participante;
use App\Models\Viaje;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Gasto>
 */
class GastoFactory extends Factory
{
    protected $model = Gasto::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'viaje_id' => Viaje::factory(),
            'pagador_id' => function (array $attributes) {
                return Participante::factory()->create(['viaje_id' => $attributes['viaje_id']])->id;
            },
            'concepto' => fake()->sentence(3),
            'monto' => fake()->randomFloat(2, 10, 1000),
            'fecha' => fake()->date(),
        ];
    }
}
