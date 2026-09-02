<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $ana = User::factory()->create([
            'name' => 'Ana',
            'email' => 'ana@example.com',
        ]);

        $viaje = $ana->viajes()->create([
            'nombre' => 'Viaje a Samaipata',
            'descripcion' => 'Fin de semana con amigos',
        ]);

        foreach (['Ana', 'Beto', 'Carla', 'Diego'] as $nombre) {
            $viaje->participantes()->create([
                'nombre' => $nombre,
            ]);
        }
    }
}
