<?php

namespace Tests\Feature;

use App\Models\Gasto;
use App\Models\Participante;
use App\Models\User;
use App\Models\Viaje;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiquidacionTest extends TestCase
{
    use RefreshDatabase;

    private function crearEscenarioSamaipata(User $user): Viaje
    {
        $viaje = Viaje::factory()->for($user, 'user')->create([
            'nombre' => 'Viaje a Samaipata',
        ]);

        $ana = Participante::factory()->for($viaje)->create(['nombre' => 'Ana']);
        $beto = Participante::factory()->for($viaje)->create(['nombre' => 'Beto']);
        $carla = Participante::factory()->for($viaje)->create(['nombre' => 'Carla']);
        $diego = Participante::factory()->for($viaje)->create(['nombre' => 'Diego']);

        // 1. Cabaña: 800 (Ana)
        Gasto::factory()->for($viaje)->create([
            'concepto' => 'Cabaña',
            'monto' => 800.00,
            'fecha' => '2026-09-01',
            'pagador_id' => $ana->id,
        ]);
        // 2. Entradas El Fuerte: 160 (Ana)
        Gasto::factory()->for($viaje)->create([
            'concepto' => 'Entradas a El Fuerte',
            'monto' => 160.00,
            'fecha' => '2026-09-02',
            'pagador_id' => $ana->id,
        ]);
        // 3. Cena: 400 (Beto)
        Gasto::factory()->for($viaje)->create([
            'concepto' => 'Cena',
            'monto' => 400.00,
            'fecha' => '2026-09-02',
            'pagador_id' => $beto->id,
        ]);
        // 4. Gasolina: 240 (Carla)
        Gasto::factory()->for($viaje)->create([
            'concepto' => 'Gasolina',
            'monto' => 240.00,
            'fecha' => '2026-09-03',
            'pagador_id' => $carla->id,
        ]);

        return $viaje;
    }

    public function test_usuario_puede_consultar_saldos_de_su_viaje_samaipata(): void
    {
        $user = User::factory()->create();
        $viaje = $this->crearEscenarioSamaipata($user);

        $response = $this->actingAs($user)->getJson(route('viajes.saldos', $viaje));

        $response->assertOk();
        $response->assertJsonCount(4);

        $response->assertJsonFragment([
            'nombre' => 'Ana',
            'total_pagado' => 960.00,
            'total_consumido' => 400.00,
            'balance' => 560.00,
        ]);
        $response->assertJsonFragment([
            'nombre' => 'Beto',
            'total_pagado' => 400.00,
            'total_consumido' => 400.00,
            'balance' => 0.00,
        ]);
        $response->assertJsonFragment([
            'nombre' => 'Carla',
            'total_pagado' => 240.00,
            'total_consumido' => 400.00,
            'balance' => -160.00,
        ]);
        $response->assertJsonFragment([
            'nombre' => 'Diego',
            'total_pagado' => 0.00,
            'total_consumido' => 400.00,
            'balance' => -400.00,
        ]);
    }

    public function test_usuario_puede_consultar_liquidacion_de_su_viaje_samaipata(): void
    {
        $user = User::factory()->create();
        $viaje = $this->crearEscenarioSamaipata($user);

        $response = $this->actingAs($user)->getJson(route('viajes.liquidacion', $viaje));

        $response->assertOk();
        $response->assertJsonCount(2);

        $transferencias = $response->json();

        $this->assertEquals('Diego', $transferencias[0]['deudor']);
        $this->assertEquals('Ana', $transferencias[0]['acreedor']);
        $this->assertEquals(400.00, $transferencias[0]['monto']);

        $this->assertEquals('Carla', $transferencias[1]['deudor']);
        $this->assertEquals('Ana', $transferencias[1]['acreedor']);
        $this->assertEquals(160.00, $transferencias[1]['monto']);
    }

    public function test_viaje_sin_gastos_retorna_saldos_en_cero_y_cero_transferencias(): void
    {
        $user = User::factory()->create();
        $viaje = Viaje::factory()->for($user, 'user')->create();
        Participante::factory()->for($viaje)->create(['nombre' => 'Ana']);

        $saldosResponse = $this->actingAs($user)->getJson(route('viajes.saldos', $viaje));
        $saldosResponse->assertOk();
        $saldosResponse->assertJsonFragment([
            'nombre' => 'Ana',
            'total_pagado' => 0.00,
            'total_consumido' => 0.00,
            'balance' => 0.00,
        ]);

        $liquidacionResponse = $this->actingAs($user)->getJson(route('viajes.liquidacion', $viaje));
        $liquidacionResponse->assertOk();
        $liquidacionResponse->assertJsonCount(0);
    }

    public function test_usuario_no_dueno_no_puede_consultar_saldos_o_liquidacion(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $viaje = $this->crearEscenarioSamaipata($owner);

        $this->actingAs($other)
            ->getJson(route('viajes.saldos', $viaje))
            ->assertForbidden();

        $this->actingAs($other)
            ->getJson(route('viajes.liquidacion', $viaje))
            ->assertForbidden();
    }

    public function test_invitado_no_autenticado_es_redirigido_o_denegado(): void
    {
        $user = User::factory()->create();
        $viaje = Viaje::factory()->for($user, 'user')->create();

        $this->getJson(route('viajes.saldos', $viaje))
            ->assertUnauthorized();

        $this->getJson(route('viajes.liquidacion', $viaje))
            ->assertUnauthorized();
    }
}
