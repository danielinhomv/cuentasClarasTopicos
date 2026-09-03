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
        $ana = User::factory()->create([
            'name' => 'Ana',
            'email' => 'ana@example.com',
            'password' => bcrypt('password'),
        ]);

        $beto = User::factory()->create([
            'name' => 'Beto',
            'email' => 'beto@example.com',
            'password' => bcrypt('password'),
        ]);

        $carla = User::factory()->create([
            'name' => 'Carla',
            'email' => 'carla@example.com',
            'password' => bcrypt('password'),
        ]);

        $diego = User::factory()->create([
            'name' => 'Diego',
            'email' => 'diego@example.com',
            'password' => bcrypt('password'),
        ]);

        $viaje = $ana->viajes()->create([
            'nombre' => 'Viaje a Samaipata',
            'descripcion' => 'Fin de semana con amigos',
            'codigo_invitacion' => 'SAMAI123',
        ]);

        $users = [
            'Ana' => $ana,
            'Beto' => $beto,
            'Carla' => $carla,
            'Diego' => $diego,
        ];

        $participantes = [];
        foreach ($users as $nombre => $user) {
            $participantes[$nombre] = $viaje->participantes()->create([
                'user_id' => $user->id,
                'nombre' => $nombre,
            ]);
        }

        $viaje->gastos()->create([
            'concepto' => 'Cabaña',
            'monto' => 800.00,
            'fecha' => '2026-09-01',
            'pagador_id' => $participantes['Ana']->id,
        ]);

        $viaje->gastos()->create([
            'concepto' => 'Entradas a El Fuerte',
            'monto' => 160.00,
            'fecha' => '2026-09-02',
            'pagador_id' => $participantes['Ana']->id,
        ]);

        $viaje->gastos()->create([
            'concepto' => 'Cena',
            'monto' => 400.00,
            'fecha' => '2026-09-02',
            'pagador_id' => $participantes['Beto']->id,
        ]);

        $gasolina = $viaje->gastos()->create([
            'concepto' => 'Gasolina',
            'monto' => 240.00,
            'fecha' => '2026-09-03',
            'pagador_id' => $participantes['Carla']->id,
        ]);

        $gasolina->excluidos()->attach($participantes['Diego']->id);
    }
}
