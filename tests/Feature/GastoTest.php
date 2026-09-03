<?php

namespace Tests\Feature;

use App\Models\Gasto;
use App\Models\Participante;
use App\Models\User;
use App\Models\Viaje;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GastoTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_a_gasto(): void
    {
        $user = User::factory()->create();
        $viaje = Viaje::factory()->for($user, 'user')->create(['nombre' => 'Viaje a Samaipata']);
        $ana = Participante::factory()->for($viaje)->create(['nombre' => 'Ana']);

        $response = $this->actingAs($user)->postJson(route('viajes.gastos.store', $viaje), [
            'concepto' => 'Cabaña',
            'monto' => 800.00,
            'fecha' => '2026-09-01',
            'pagador_id' => $ana->id,
        ]);

        $response->assertCreated();
        $response->assertJsonFragment([
            'concepto' => 'Cabaña',
            'monto' => '800.00',
            'pagador_id' => $ana->id,
            'viaje_id' => $viaje->id,
        ]);

        $this->assertDatabaseHas('gastos', [
            'viaje_id' => $viaje->id,
            'pagador_id' => $ana->id,
            'concepto' => 'Cabaña',
            'monto' => 800.00,
        ]);
    }

    public function test_gasto_monto_must_be_greater_than_zero(): void
    {
        $user = User::factory()->create();
        $viaje = Viaje::factory()->for($user, 'user')->create();
        $ana = Participante::factory()->for($viaje)->create(['nombre' => 'Ana']);

        $responseZero = $this->actingAs($user)->postJson(route('viajes.gastos.store', $viaje), [
            'concepto' => 'Cabaña',
            'monto' => 0.00,
            'fecha' => '2026-09-01',
            'pagador_id' => $ana->id,
        ]);
        $responseZero->assertUnprocessable();
        $responseZero->assertJsonValidationErrors('monto');

        $responseNegative = $this->actingAs($user)->postJson(route('viajes.gastos.store', $viaje), [
            'concepto' => 'Cabaña',
            'monto' => -50.00,
            'fecha' => '2026-09-01',
            'pagador_id' => $ana->id,
        ]);
        $responseNegative->assertUnprocessable();
        $responseNegative->assertJsonValidationErrors('monto');

        $this->assertDatabaseCount('gastos', 0);
    }

    public function test_gasto_concepto_is_required(): void
    {
        $user = User::factory()->create();
        $viaje = Viaje::factory()->for($user, 'user')->create();
        $ana = Participante::factory()->for($viaje)->create(['nombre' => 'Ana']);

        $response = $this->actingAs($user)->postJson(route('viajes.gastos.store', $viaje), [
            'concepto' => '   ',
            'monto' => 100.00,
            'fecha' => '2026-09-01',
            'pagador_id' => $ana->id,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('concepto');
        $this->assertDatabaseCount('gastos', 0);
    }

    public function test_pagador_must_belong_to_the_same_viaje(): void
    {
        $user = User::factory()->create();
        $viajeA = Viaje::factory()->for($user, 'user')->create(['nombre' => 'Viaje A']);
        $viajeB = Viaje::factory()->for($user, 'user')->create(['nombre' => 'Viaje B']);

        Participante::factory()->for($viajeA)->create(['nombre' => 'Ana']);
        $zulma = Participante::factory()->for($viajeB)->create(['nombre' => 'Zulma']);

        $response = $this->actingAs($user)->postJson(route('viajes.gastos.store', $viajeA), [
            'concepto' => 'Snacks',
            'monto' => 50.00,
            'fecha' => '2026-09-01',
            'pagador_id' => $zulma->id,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('pagador_id');
        $this->assertDatabaseCount('gastos', 0);
    }

    public function test_user_can_list_gastos_of_own_viaje(): void
    {
        $user = User::factory()->create();
        $viaje = Viaje::factory()->for($user, 'user')->create();
        $ana = Participante::factory()->for($viaje)->create(['nombre' => 'Ana']);

        Gasto::factory()->for($viaje)->create([
            'concepto' => 'Cabaña',
            'monto' => 800.00,
            'fecha' => '2026-09-01',
            'pagador_id' => $ana->id,
        ]);
        Gasto::factory()->for($viaje)->create([
            'concepto' => 'Cena',
            'monto' => 400.00,
            'fecha' => '2026-09-02',
            'pagador_id' => $ana->id,
        ]);

        $response = $this->actingAs($user)->getJson(route('viajes.gastos.index', $viaje));

        $response->assertOk();
        $response->assertJsonCount(2);
        $response->assertJsonFragment(['concepto' => 'Cabaña']);
        $response->assertJsonFragment(['concepto' => 'Cena']);
    }

    public function test_viaje_without_gastos_lists_empty(): void
    {
        $user = User::factory()->create();
        $viaje = Viaje::factory()->for($user, 'user')->create();

        $response = $this->actingAs($user)->getJson(route('viajes.gastos.index', $viaje));

        $response->assertOk();
        $response->assertJsonCount(0);
    }

    public function test_user_can_view_gasto_detail(): void
    {
        $user = User::factory()->create();
        $viaje = Viaje::factory()->for($user, 'user')->create();
        $ana = Participante::factory()->for($viaje)->create(['nombre' => 'Ana']);
        $beto = Participante::factory()->for($viaje)->create(['nombre' => 'Beto']);

        $gasto = Gasto::factory()->for($viaje)->create([
            'concepto' => 'Gasolina',
            'monto' => 240.00,
            'fecha' => '2026-09-03',
            'pagador_id' => $ana->id,
        ]);
        $gasto->excluidos()->attach($beto->id);

        $response = $this->actingAs($user)->getJson(route('gastos.show', $gasto));

        $response->assertOk();
        $response->assertJsonFragment(['concepto' => 'Gasolina', 'monto' => '240.00']);
        $response->assertJsonFragment(['nombre' => 'Ana']);
        $response->assertJsonFragment(['nombre' => 'Beto']);
    }

    public function test_user_can_update_own_gasto(): void
    {
        $user = User::factory()->create();
        $viaje = Viaje::factory()->for($user, 'user')->create();
        $beto = Participante::factory()->for($viaje)->create(['nombre' => 'Beto']);

        $gasto = Gasto::factory()->for($viaje)->create([
            'concepto' => 'Cena',
            'monto' => 400.00,
            'fecha' => '2026-09-02',
            'pagador_id' => $beto->id,
        ]);

        $response = $this->actingAs($user)->putJson(route('gastos.update', $gasto), [
            'concepto' => 'Cena especial',
            'monto' => 450.00,
            'fecha' => '2026-09-02',
            'pagador_id' => $beto->id,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('gastos', [
            'id' => $gasto->id,
            'concepto' => 'Cena especial',
            'monto' => 450.00,
        ]);
    }

    public function test_user_can_delete_own_gasto(): void
    {
        $user = User::factory()->create();
        $viaje = Viaje::factory()->for($user, 'user')->create();
        $ana = Participante::factory()->for($viaje)->create(['nombre' => 'Ana']);

        $gasto = Gasto::factory()->for($viaje)->create([
            'concepto' => 'Almuerzo',
            'monto' => 150.00,
            'fecha' => '2026-09-02',
            'pagador_id' => $ana->id,
        ]);

        $response = $this->actingAs($user)->deleteJson(route('gastos.destroy', $gasto));

        $response->assertOk();
        $this->assertDatabaseMissing('gastos', ['id' => $gasto->id]);
    }

    public function test_user_cannot_manage_gastos_of_another_users_viaje(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $viaje = Viaje::factory()->for($owner, 'user')->create();
        $ana = Participante::factory()->for($viaje)->create(['nombre' => 'Ana']);
        $gasto = Gasto::factory()->for($viaje)->create(['pagador_id' => $ana->id]);

        $this->actingAs($other)
            ->getJson(route('viajes.gastos.index', $viaje))
            ->assertForbidden();

        $this->actingAs($other)
            ->postJson(route('viajes.gastos.store', $viaje), [
                'concepto' => 'Hack',
                'monto' => 100,
                'fecha' => '2026-09-01',
                'pagador_id' => $ana->id,
            ])
            ->assertForbidden();

        $this->actingAs($other)
            ->getJson(route('gastos.show', $gasto))
            ->assertForbidden();

        $this->actingAs($other)
            ->putJson(route('gastos.update', $gasto), [
                'concepto' => 'Hack',
                'monto' => 200,
                'fecha' => '2026-09-01',
                'pagador_id' => $ana->id,
            ])
            ->assertForbidden();

        $this->actingAs($other)
            ->deleteJson(route('gastos.destroy', $gasto))
            ->assertForbidden();
    }

    public function test_guest_cannot_access_gastos(): void
    {
        $viaje = Viaje::factory()->create();

        $this->getJson(route('viajes.gastos.index', $viaje))
            ->assertUnauthorized();
    }

    public function test_user_can_exclude_participantes_from_a_gasto(): void
    {
        $user = User::factory()->create();
        $viaje = Viaje::factory()->for($user, 'user')->create();
        $ana = Participante::factory()->for($viaje)->create(['nombre' => 'Ana']);
        $beto = Participante::factory()->for($viaje)->create(['nombre' => 'Beto']);
        $carla = Participante::factory()->for($viaje)->create(['nombre' => 'Carla']);
        $diego = Participante::factory()->for($viaje)->create(['nombre' => 'Diego']);

        $response = $this->actingAs($user)->postJson(route('viajes.gastos.store', $viaje), [
            'concepto' => 'Gasolina',
            'monto' => 240.00,
            'fecha' => '2026-09-03',
            'pagador_id' => $carla->id,
            'excluidos' => [$diego->id],
        ]);

        $response->assertCreated();
        $gastoId = $response->json('id');

        $this->assertDatabaseHas('gasto_exclusiones', [
            'gasto_id' => $gastoId,
            'participante_id' => $diego->id,
        ]);
    }

    public function test_cannot_exclude_all_participantes_of_a_viaje(): void
    {
        $user = User::factory()->create();
        $viaje = Viaje::factory()->for($user, 'user')->create();
        $ana = Participante::factory()->for($viaje)->create(['nombre' => 'Ana']);
        $beto = Participante::factory()->for($viaje)->create(['nombre' => 'Beto']);

        $response = $this->actingAs($user)->postJson(route('viajes.gastos.store', $viaje), [
            'concepto' => 'Hotel',
            'monto' => 500.00,
            'fecha' => '2026-09-01',
            'pagador_id' => $ana->id,
            'excluidos' => [$ana->id, $beto->id],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('excluidos');
        $this->assertDatabaseCount('gastos', 0);
    }

    public function test_cannot_exclude_participante_from_different_viaje(): void
    {
        $user = User::factory()->create();
        $viajeA = Viaje::factory()->for($user, 'user')->create();
        $viajeB = Viaje::factory()->for($user, 'user')->create();

        $ana = Participante::factory()->for($viajeA)->create(['nombre' => 'Ana']);
        $zulma = Participante::factory()->for($viajeB)->create(['nombre' => 'Zulma']);

        $response = $this->actingAs($user)->postJson(route('viajes.gastos.store', $viajeA), [
            'concepto' => 'Almuerzo',
            'monto' => 120.00,
            'fecha' => '2026-09-01',
            'pagador_id' => $ana->id,
            'excluidos' => [$zulma->id],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('excluidos.0');
        $this->assertDatabaseCount('gastos', 0);
    }

    public function test_user_can_update_exclusiones_of_a_gasto(): void
    {
        $user = User::factory()->create();
        $viaje = Viaje::factory()->for($user, 'user')->create();
        $ana = Participante::factory()->for($viaje)->create(['nombre' => 'Ana']);
        $beto = Participante::factory()->for($viaje)->create(['nombre' => 'Beto']);
        $carla = Participante::factory()->for($viaje)->create(['nombre' => 'Carla']);

        $gasto = Gasto::factory()->for($viaje)->create([
            'concepto' => 'Cena',
            'monto' => 300.00,
            'fecha' => '2026-09-02',
            'pagador_id' => $ana->id,
        ]);

        $response = $this->actingAs($user)->putJson(route('gastos.update', $gasto), [
            'concepto' => 'Cena',
            'monto' => 300.00,
            'fecha' => '2026-09-02',
            'pagador_id' => $ana->id,
            'excluidos' => [$carla->id],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('gasto_exclusiones', [
            'gasto_id' => $gasto->id,
            'participante_id' => $carla->id,
        ]);
    }

    public function test_user_can_remove_all_exclusiones_of_a_gasto(): void
    {
        $user = User::factory()->create();
        $viaje = Viaje::factory()->for($user, 'user')->create();
        $ana = Participante::factory()->for($viaje)->create(['nombre' => 'Ana']);
        $beto = Participante::factory()->for($viaje)->create(['nombre' => 'Beto']);

        $gasto = Gasto::factory()->for($viaje)->create([
            'concepto' => 'Cena',
            'monto' => 300.00,
            'fecha' => '2026-09-02',
            'pagador_id' => $ana->id,
        ]);
        $gasto->excluidos()->attach($beto->id);

        $response = $this->actingAs($user)->putJson(route('gastos.update', $gasto), [
            'concepto' => 'Cena',
            'monto' => 300.00,
            'fecha' => '2026-09-02',
            'pagador_id' => $ana->id,
            'excluidos' => [],
        ]);

        $response->assertOk();
        $this->assertDatabaseCount('gasto_exclusiones', 0);
    }

    public function test_deleting_gasto_cascades_exclusiones(): void
    {
        $user = User::factory()->create();
        $viaje = Viaje::factory()->for($user, 'user')->create();
        $ana = Participante::factory()->for($viaje)->create(['nombre' => 'Ana']);
        $beto = Participante::factory()->for($viaje)->create(['nombre' => 'Beto']);

        $gasto = Gasto::factory()->for($viaje)->create([
            'concepto' => 'Excursión',
            'monto' => 200.00,
            'fecha' => '2026-09-02',
            'pagador_id' => $ana->id,
        ]);
        $gasto->excluidos()->attach($beto->id);

        $this->assertDatabaseHas('gasto_exclusiones', [
            'gasto_id' => $gasto->id,
            'participante_id' => $beto->id,
        ]);

        $this->actingAs($user)->deleteJson(route('gastos.destroy', $gasto))->assertOk();

        $this->assertDatabaseMissing('gastos', ['id' => $gasto->id]);
        $this->assertDatabaseMissing('gasto_exclusiones', ['gasto_id' => $gasto->id]);
    }

    public function test_user_can_create_gasto_in_usd_and_usdt(): void
    {
        $user = User::factory()->create();
        $viaje = Viaje::factory()->for($user, 'user')->create([
            'tipo_cambio_usd' => 6.9600,
            'tipo_cambio_usdt' => 10.5000,
        ]);
        $ana = Participante::factory()->for($viaje)->create(['nombre' => 'Ana']);

        $responseUsd = $this->actingAs($user)->postJson(route('viajes.gastos.store', $viaje), [
            'concepto' => 'Gasto Dólares',
            'monto' => 50.00,
            'moneda' => 'USD',
            'fecha' => '2026-09-01',
            'pagador_id' => $ana->id,
        ]);

        $responseUsd->assertCreated();
        $this->assertDatabaseHas('gastos', [
            'viaje_id' => $viaje->id,
            'concepto' => 'Gasto Dólares',
            'moneda' => 'USD',
            'tipo_cambio' => 6.9600,
        ]);

        $responseUsdt = $this->actingAs($user)->postJson(route('viajes.gastos.store', $viaje), [
            'concepto' => 'Gasto Cripto',
            'monto' => 30.00,
            'moneda' => 'USDT',
            'fecha' => '2026-09-02',
            'pagador_id' => $ana->id,
        ]);

        $responseUsdt->assertCreated();
        $this->assertDatabaseHas('gastos', [
            'viaje_id' => $viaje->id,
            'concepto' => 'Gasto Cripto',
            'moneda' => 'USDT',
            'tipo_cambio' => 10.5000,
        ]);
    }

    public function test_gasto_rejects_invalid_moneda(): void
    {
        $user = User::factory()->create();
        $viaje = Viaje::factory()->for($user, 'user')->create();
        $ana = Participante::factory()->for($viaje)->create(['nombre' => 'Ana']);

        $response = $this->actingAs($user)->postJson(route('viajes.gastos.store', $viaje), [
            'concepto' => 'Gasto Euros',
            'monto' => 100.00,
            'moneda' => 'EUR',
            'fecha' => '2026-09-01',
            'pagador_id' => $ana->id,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('moneda');
    }

    public function test_only_creator_can_update_tipo_cambio(): void
    {
        $owner = User::factory()->create();
        $participant = User::factory()->create();

        $viaje = Viaje::factory()->for($owner, 'user')->create([
            'tipo_cambio_usd' => 6.9600,
            'tipo_cambio_usdt' => 10.5000,
        ]);

        $viaje->participantes()->create([
            'user_id' => $participant->id,
            'nombre' => $participant->name,
        ]);

        // 1. Participante no puede modificar tipo de cambio
        $this->actingAs($participant)
            ->put(route('viajes.tipo-cambio.update', $viaje), [
                'tipo_cambio_usd' => 7.00,
                'tipo_cambio_usdt' => 11.00,
            ])
            ->assertForbidden();

        // 2. Creador sí puede modificar tipo de cambio
        $this->actingAs($owner)
            ->put(route('viajes.tipo-cambio.update', $viaje), [
                'tipo_cambio_usd' => 7.00,
                'tipo_cambio_usdt' => 11.00,
            ])
            ->assertRedirect(route('viajes.show', $viaje))
            ->assertSessionHas('flash.banner');

        $this->assertDatabaseHas('viajes', [
            'id' => $viaje->id,
            'tipo_cambio_usd' => 7.00,
            'tipo_cambio_usdt' => 11.00,
        ]);
    }
}
