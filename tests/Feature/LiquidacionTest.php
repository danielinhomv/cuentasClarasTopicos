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
        $allIds = [$ana->id, $beto->id, $carla->id, $diego->id];

        $gastos = [
            ['concepto' => 'Cabaña', 'monto' => 800.00, 'fecha' => '2026-09-01', 'pagador_id' => $ana->id],
            ['concepto' => 'Entradas a El Fuerte', 'monto' => 160.00, 'fecha' => '2026-09-02', 'pagador_id' => $ana->id],
            ['concepto' => 'Cena', 'monto' => 400.00, 'fecha' => '2026-09-02', 'pagador_id' => $beto->id],
            ['concepto' => 'Gasolina', 'monto' => 240.00, 'fecha' => '2026-09-03', 'pagador_id' => $carla->id],
        ];

        foreach ($gastos as $datos) {
            $gasto = Gasto::factory()->for($viaje)->create($datos);
            $gasto->participantes()->sync($allIds);
        }

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

    public function test_puede_registrar_pagos_parciales_y_cerrar_una_deuda(): void
    {
        $user = User::factory()->create();
        $viaje = Viaje::factory()->for($user, 'user')->create();
        $ana = Participante::factory()->for($viaje)->create(['nombre' => 'Ana']);
        $diego = Participante::factory()->for($viaje)->create(['nombre' => 'Diego']);

        $gasto = Gasto::factory()->for($viaje)->create([
            'concepto' => 'Cena',
            'monto' => 80.00,
            'fecha' => '2026-09-01',
            'pagador_id' => $ana->id,
        ]);
        $gasto->participantes()->sync([$ana->id, $diego->id]);

        $liquidacionResponse = $this->actingAs($user)->getJson(route('viajes.liquidacion', $viaje));
        $liquidacionResponse->assertOk();
        $deuda = collect($liquidacionResponse->json())->firstWhere('deudor', 'Diego');

        $this->assertEquals(40.00, $deuda['monto_original']);
        $this->assertEquals(0.00, $deuda['monto_pagado']);
        $this->assertEquals(40.00, $deuda['monto_pendiente']);
        $this->assertFalse($deuda['liquidada']);

        $this->actingAs($user)
            ->postJson(route('liquidaciones.pagos.store', $deuda['id']), ['monto' => 20.00])
            ->assertCreated()
            ->assertJsonFragment([
                'monto_original' => 40.00,
                'monto_pagado' => 20.00,
                'monto_pendiente' => 20.00,
                'liquidada' => false,
            ]);

        $saldos = collect($this->actingAs($user)->getJson(route('viajes.saldos', $viaje))->json())->keyBy('nombre');
        $this->assertEquals(20.00, $saldos['Ana']['balance']);
        $this->assertEquals(-20.00, $saldos['Diego']['balance']);

        $this->actingAs($user)
            ->postJson(route('liquidaciones.pagos.store', $deuda['id']), ['monto' => 20.00])
            ->assertCreated()
            ->assertJsonFragment([
                'monto_pagado' => 40.00,
                'monto_pendiente' => 0.00,
                'liquidada' => true,
            ]);
    }

    public function test_rechaza_pago_mayor_al_pendiente(): void
    {
        $user = User::factory()->create();
        $viaje = Viaje::factory()->for($user, 'user')->create();
        $ana = Participante::factory()->for($viaje)->create(['nombre' => 'Ana']);
        $diego = Participante::factory()->for($viaje)->create(['nombre' => 'Diego']);

        $gasto = Gasto::factory()->for($viaje)->create([
            'concepto' => 'Cena',
            'monto' => 80.00,
            'fecha' => '2026-09-01',
            'pagador_id' => $ana->id,
        ]);
        $gasto->participantes()->sync([$ana->id, $diego->id]);

        $deuda = collect($this->actingAs($user)->getJson(route('viajes.liquidacion', $viaje))->json())->first();

        $this->actingAs($user)
            ->postJson(route('liquidaciones.pagos.store', $deuda['id']), ['monto' => 50.00])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('monto');
    }

    public function test_liquidacion_completa_sigue_funcionando_en_samaipata(): void
    {
        $user = User::factory()->create();
        $viaje = $this->crearEscenarioSamaipata($user);

        $response = $this->actingAs($user)->getJson(route('viajes.liquidacion', $viaje));
        $response->assertOk();

        $diegoAna = collect($response->json())->firstWhere('deudor', 'Diego');
        $this->actingAs($user)
            ->postJson(route('liquidaciones.pagos.store', $diegoAna['id']), ['monto' => 400.00])
            ->assertCreated()
            ->assertJsonPath('liquidada', true)
            ->assertJsonPath('monto_pendiente', 0);
    }

    public function test_liquidacion_parcial_sigue_funcionando_con_ajuste_de_efectivo(): void
    {
        $user = User::factory()->create();
        $viaje = Viaje::factory()->for($user, 'user')->create();
        $ana = Participante::factory()->for($viaje)->create(['nombre' => 'Ana', 'user_id' => $user->id]);
        $diego = Participante::factory()->for($viaje)->create(['nombre' => 'Diego']);

        $gasto = Gasto::factory()->for($viaje)->create([
            'concepto' => 'Almuerzo',
            'monto' => 45.35,
            'fecha' => '2026-09-01',
            'pagador_id' => $ana->id,
        ]);
        $gasto->participantes()->sync([$ana->id, $diego->id]);

        $deuda = collect($this->actingAs($user)->getJson(route('viajes.liquidacion', $viaje))->json())->first();

        $this->assertEquals(23.00, $deuda['monto_original']);

        $this->actingAs($user)
            ->postJson(route('liquidaciones.pagos.store', $deuda['id']), ['monto' => 10.00])
            ->assertCreated()
            ->assertJsonFragment([
                'monto_original' => 23.00,
                'monto_pagado' => 10.00,
                'monto_pendiente' => 13.00,
                'liquidada' => false,
            ]);

        $this->assertDatabaseHas('gastos', [
            'id' => $gasto->id,
            'monto' => 45.35,
        ]);
    }

    public function test_crear_gasto_genera_deuda_y_eliminarlo_limpia_liquidacion_y_saldos(): void
    {
        $user = User::factory()->create();
        $viaje = Viaje::factory()->for($user, 'user')->create();
        $ana = Participante::factory()->for($viaje)->create(['nombre' => 'Ana']);
        $diego = Participante::factory()->for($viaje)->create(['nombre' => 'Diego']);

        $gasto = Gasto::factory()->for($viaje)->create([
            'concepto' => 'Cena',
            'monto' => 80.00,
            'fecha' => '2026-09-01',
            'pagador_id' => $ana->id,
        ]);
        $gasto->participantes()->sync([$ana->id, $diego->id]);

        $deuda = collect($this->actingAs($user)->getJson(route('viajes.liquidacion', $viaje))->json())
            ->firstWhere('deudor', 'Diego');

        $this->assertNotNull($deuda);
        $this->assertEquals(40.00, $deuda['monto_original']);
        $this->assertEquals(40.00, $deuda['monto_pendiente']);
        $this->assertDatabaseCount('liquidaciones', 1);

        $this->actingAs($user)->deleteJson(route('gastos.destroy', $gasto))->assertOk();
        $this->assertDatabaseMissing('gastos', ['id' => $gasto->id]);

        $this->actingAs($user)
            ->getJson(route('viajes.liquidacion', $viaje))
            ->assertOk()
            ->assertJsonCount(0);

        $saldos = collect($this->actingAs($user)->getJson(route('viajes.saldos', $viaje))->json())->keyBy('nombre');
        $this->assertEquals(0.00, $saldos['Ana']['balance']);
        $this->assertEquals(0.00, $saldos['Diego']['balance']);
        $this->assertEquals(0.00, $saldos->sum('balance'));
        $this->assertDatabaseCount('liquidaciones', 0);
    }

    public function test_eliminar_gasto_que_genera_una_deuda_completa(): void
    {
        $user = User::factory()->create();
        $viaje = Viaje::factory()->for($user, 'user')->create();
        $ana = Participante::factory()->for($viaje)->create(['nombre' => 'Ana']);
        $diego = Participante::factory()->for($viaje)->create(['nombre' => 'Diego']);

        $gasto = Gasto::factory()->for($viaje)->create([
            'concepto' => 'Hotel',
            'monto' => 80.00,
            'fecha' => '2026-09-01',
            'pagador_id' => $ana->id,
        ]);
        $gasto->participantes()->sync([$ana->id, $diego->id]);

        $this->actingAs($user)->getJson(route('viajes.liquidacion', $viaje))->assertJsonCount(1);

        $this->actingAs($user)->deleteJson(route('gastos.destroy', $gasto))->assertOk();

        $this->actingAs($user)
            ->getJson(route('viajes.liquidacion', $viaje))
            ->assertOk()
            ->assertJsonCount(0);

        $this->assertDatabaseMissing('liquidaciones', [
            'viaje_id' => $viaje->id,
            'deudor_id' => $diego->id,
            'acreedor_id' => $ana->id,
        ]);
    }

    public function test_eliminar_gasto_que_afecta_parcialmente_una_deuda_existente(): void
    {
        $user = User::factory()->create();
        $viaje = Viaje::factory()->for($user, 'user')->create();
        $ana = Participante::factory()->for($viaje)->create(['nombre' => 'Ana']);
        $diego = Participante::factory()->for($viaje)->create(['nombre' => 'Diego']);

        $primero = Gasto::factory()->for($viaje)->create([
            'concepto' => 'Cena',
            'monto' => 80.00,
            'fecha' => '2026-09-01',
            'pagador_id' => $ana->id,
        ]);
        $primero->participantes()->sync([$ana->id, $diego->id]);

        $segundo = Gasto::factory()->for($viaje)->create([
            'concepto' => 'Almuerzo',
            'monto' => 80.00,
            'fecha' => '2026-09-02',
            'pagador_id' => $ana->id,
        ]);
        $segundo->participantes()->sync([$ana->id, $diego->id]);

        $deuda = collect($this->actingAs($user)->getJson(route('viajes.liquidacion', $viaje))->json())->first();
        $this->assertEquals(80.00, $deuda['monto_original']);
        $this->assertEquals(80.00, $deuda['monto_pendiente']);

        $this->actingAs($user)->deleteJson(route('gastos.destroy', $segundo))->assertOk();

        $deuda = collect($this->actingAs($user)->getJson(route('viajes.liquidacion', $viaje))->json())->first();
        $this->assertEquals('Diego', $deuda['deudor']);
        $this->assertEquals('Ana', $deuda['acreedor']);
        $this->assertEquals(40.00, $deuda['monto_original']);
        $this->assertEquals(0.00, $deuda['monto_pagado']);
        $this->assertEquals(40.00, $deuda['monto_pendiente']);
        $this->assertFalse($deuda['liquidada']);

        $saldos = collect($this->actingAs($user)->getJson(route('viajes.saldos', $viaje))->json())->keyBy('nombre');
        $this->assertEquals(40.00, $saldos['Ana']['balance']);
        $this->assertEquals(-40.00, $saldos['Diego']['balance']);
        $this->assertEquals(0.00, $saldos->sum('balance'));
        $this->assertDatabaseCount('liquidaciones', 1);
    }

    public function test_eliminar_gasto_con_liquidacion_parcial_recalcula_pendiente_y_conserva_pagos(): void
    {
        $user = User::factory()->create();
        $viaje = Viaje::factory()->for($user, 'user')->create();
        $ana = Participante::factory()->for($viaje)->create(['nombre' => 'Ana']);
        $diego = Participante::factory()->for($viaje)->create(['nombre' => 'Diego']);

        $primero = Gasto::factory()->for($viaje)->create([
            'concepto' => 'Cena',
            'monto' => 80.00,
            'fecha' => '2026-09-01',
            'pagador_id' => $ana->id,
        ]);
        $primero->participantes()->sync([$ana->id, $diego->id]);

        $segundo = Gasto::factory()->for($viaje)->create([
            'concepto' => 'Almuerzo',
            'monto' => 80.00,
            'fecha' => '2026-09-02',
            'pagador_id' => $ana->id,
        ]);
        $segundo->participantes()->sync([$ana->id, $diego->id]);

        $deuda = collect($this->actingAs($user)->getJson(route('viajes.liquidacion', $viaje))->json())->first();
        $this->actingAs($user)
            ->postJson(route('liquidaciones.pagos.store', $deuda['id']), ['monto' => 30.00])
            ->assertCreated();

        $this->actingAs($user)->deleteJson(route('gastos.destroy', $segundo))->assertOk();

        $deuda = collect($this->actingAs($user)->getJson(route('viajes.liquidacion', $viaje))->json())->first();
        $this->assertEquals(40.00, $deuda['monto_original']);
        $this->assertEquals(30.00, $deuda['monto_pagado']);
        $this->assertEquals(10.00, $deuda['monto_pendiente']);
        $this->assertFalse($deuda['liquidada']);

        $this->assertDatabaseHas('liquidacion_pagos', [
            'liquidacion_id' => $deuda['id'],
            'monto' => 30.00,
        ]);

        $saldos = collect($this->actingAs($user)->getJson(route('viajes.saldos', $viaje))->json())->keyBy('nombre');
        $this->assertEquals(10.00, $saldos['Ana']['balance']);
        $this->assertEquals(-10.00, $saldos['Diego']['balance']);
        $this->assertEquals(0.00, $saldos->sum('balance'));
    }

    public function test_eliminar_gasto_con_pago_parcial_no_deja_deudas_huerfanas_ni_saldos_invertidos(): void
    {
        $user = User::factory()->create();
        $viaje = Viaje::factory()->for($user, 'user')->create();
        $ana = Participante::factory()->for($viaje)->create(['nombre' => 'Ana']);
        $diego = Participante::factory()->for($viaje)->create(['nombre' => 'Diego']);

        $gasto = Gasto::factory()->for($viaje)->create([
            'concepto' => 'Cena',
            'monto' => 80.00,
            'fecha' => '2026-09-01',
            'pagador_id' => $ana->id,
        ]);
        $gasto->participantes()->sync([$ana->id, $diego->id]);

        $deuda = collect($this->actingAs($user)->getJson(route('viajes.liquidacion', $viaje))->json())->first();
        $this->actingAs($user)
            ->postJson(route('liquidaciones.pagos.store', $deuda['id']), ['monto' => 20.00])
            ->assertCreated();

        $this->actingAs($user)->deleteJson(route('gastos.destroy', $gasto))->assertOk();

        $this->actingAs($user)
            ->getJson(route('viajes.liquidacion', $viaje))
            ->assertOk()
            ->assertJsonCount(0);

        $this->assertDatabaseMissing('liquidaciones', [
            'viaje_id' => $viaje->id,
            'estado' => 'pendiente',
        ]);
        $this->assertDatabaseCount('liquidacion_pagos', 1);

        $saldos = collect($this->actingAs($user)->getJson(route('viajes.saldos', $viaje))->json())->keyBy('nombre');
        $this->assertEquals(0.00, $saldos['Ana']['balance']);
        $this->assertEquals(0.00, $saldos['Diego']['balance']);
        $this->assertEquals(0.00, $saldos->sum('balance'));
    }
}
