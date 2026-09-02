<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Viaje;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Viaje>
 */
class ViajeFactory extends Factory
{
    protected $model = Viaje::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'nombre' => fake()->words(3, true),
            'descripcion' => fake()->optional()->sentence(),
        ];
    }
}
