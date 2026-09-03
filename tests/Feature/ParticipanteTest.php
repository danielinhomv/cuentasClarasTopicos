<?php

namespace Tests\Feature;

use App\Models\Participante;
use App\Models\User;
use App\Models\Viaje;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParticipanteTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_add_a_participante(): void
    {
        $user = User::factory()->create();
        $viaje = Viaje::factory()->for($user, 'user')->create(['nombre' => 'Viaje a Samaipata']);

        $this->actingAs($user)
            ->post(route('viajes.participantes.store', $viaje), [
                'nombre' => 'Ana',
            ])
            ->assertRedirect(route('viajes.show', $viaje));

        $this->assertDatabaseHas('participantes', [
            'viaje_id' => $viaje->id,
            'nombre' => 'Ana',
        ]);
    }

    public function test_owner_can_add_participante_without_account(): void
    {
        $user = User::factory()->create();
        $viaje = Viaje::factory()->for($user, 'user')->create(['nombre' => 'Viaje a Samaipata']);

        $this->actingAs($user)
            ->post(route('viajes.participantes.store', $viaje), [
                'nombre' => 'Diego',
            ])
            ->assertRedirect(route('viajes.show', $viaje));

        $this->assertDatabaseHas('participantes', [
            'viaje_id' => $viaje->id,
            'nombre' => 'Diego',
            'user_id' => null,
        ]);
    }

    public function test_guest_participante_appears_in_balance_calculation(): void
    {
        $user = User::factory()->create();
        $viaje = Viaje::factory()->for($user, 'user')->create();
        $ana = Participante::factory()->for($viaje)->create(['nombre' => 'Ana', 'user_id' => $user->id]);
        $diego = Participante::factory()->for($viaje)->create(['nombre' => 'Diego', 'user_id' => null]);

        $gasto = \App\Models\Gasto::factory()->for($viaje)->create([
            'concepto' => 'Cena',
            'monto' => 100.00,
            'fecha' => '2026-09-01',
            'pagador_id' => $ana->id,
        ]);
        $gasto->participantes()->sync([$ana->id, $diego->id]);

        $service = new \App\Services\CalculoBalanceService();
        $balances = collect($service->calcularBalances($viaje))->keyBy('nombre');

        $this->assertEquals(100.00, $balances['Ana']['total_pagado']);
        $this->assertEquals(50.00, $balances['Ana']['total_consumido']);
        $this->assertEquals(50.00, $balances['Ana']['balance']);

        $this->assertEquals(0.00, $balances['Diego']['total_pagado']);
        $this->assertEquals(50.00, $balances['Diego']['total_consumido']);
        $this->assertEquals(-50.00, $balances['Diego']['balance']);

        $this->assertEquals(0.00, collect($balances)->sum('balance'));
    }

    public function test_guest_participante_can_be_payer_in_gasto(): void
    {
        $user = User::factory()->create();
        $viaje = Viaje::factory()->for($user, 'user')->create();
        $diego = Participante::factory()->for($viaje)->create(['nombre' => 'Diego', 'user_id' => null]);
        $ana = Participante::factory()->for($viaje)->create(['nombre' => 'Ana']);

        $response = $this->actingAs($user)->postJson(route('viajes.gastos.store', $viaje), [
            'concepto' => 'Gasolina',
            'monto' => 240.00,
            'fecha' => '2026-09-01',
            'pagador_id' => $diego->id,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('gastos', [
            'viaje_id' => $viaje->id,
            'pagador_id' => $diego->id,
            'concepto' => 'Gasolina',
        ]);
    }

    public function test_participante_nombre_is_required(): void
    {
        $user = User::factory()->create();
        $viaje = Viaje::factory()->for($user, 'user')->create();

        $this->actingAs($user)
            ->from(route('viajes.show', $viaje))
            ->post(route('viajes.participantes.store', $viaje), [
                'nombre' => '  ',
            ])
            ->assertRedirect(route('viajes.show', $viaje))
            ->assertSessionHasErrors('nombre');

        $this->assertDatabaseCount('participantes', 0);
    }

    public function test_duplicate_nombre_in_same_viaje_is_rejected(): void
    {
        $user = User::factory()->create();
        $viaje = Viaje::factory()->for($user, 'user')->create();
        Participante::factory()->for($viaje)->create(['nombre' => 'Ana']);

        $this->actingAs($user)
            ->from(route('viajes.show', $viaje))
            ->post(route('viajes.participantes.store', $viaje), [
                'nombre' => 'Ana',
            ])
            ->assertRedirect(route('viajes.show', $viaje))
            ->assertSessionHasErrors('nombre');

        $this->assertDatabaseCount('participantes', 1);
    }

    public function test_same_nombre_is_allowed_in_different_viajes(): void
    {
        $user = User::factory()->create();
        $samaipata = Viaje::factory()->for($user, 'user')->create(['nombre' => 'Viaje Samaipata']);
        $tarija = Viaje::factory()->for($user, 'user')->create(['nombre' => 'Viaje Tarija']);

        Participante::factory()->for($samaipata)->create(['nombre' => 'Ana']);

        $this->actingAs($user)
            ->post(route('viajes.participantes.store', $tarija), [
                'nombre' => 'Ana',
            ])
            ->assertRedirect(route('viajes.show', $tarija));

        $this->assertDatabaseCount('participantes', 2);
    }

    public function test_viaje_without_participantes_lists_empty_without_error(): void
    {
        $user = User::factory()->create();
        $viaje = Viaje::factory()->for($user, 'user')->create();

        $this->actingAs($user)
            ->get(route('viajes.participantes.index', $viaje))
            ->assertRedirect(route('viajes.show', $viaje));

        $this->withoutVite();
        $this->actingAs($user)
            ->get(route('viajes.show', $viaje))
            ->assertOk();

        $this->assertCount(0, $viaje->fresh()->participantes);
    }

    public function test_owner_can_rename_a_participante(): void
    {
        $user = User::factory()->create();
        $viaje = Viaje::factory()->for($user, 'user')->create();
        $participante = Participante::factory()->for($viaje)->create(['nombre' => 'Beto']);

        $this->actingAs($user)
            ->put(route('participantes.update', $participante), [
                'nombre' => 'Alberto',
            ])
            ->assertRedirect(route('viajes.show', $viaje));

        $this->assertDatabaseHas('participantes', [
            'id' => $participante->id,
            'nombre' => 'Alberto',
        ]);
    }

    public function test_rename_to_existing_nombre_in_same_viaje_is_rejected(): void
    {
        $user = User::factory()->create();
        $viaje = Viaje::factory()->for($user, 'user')->create();
        Participante::factory()->for($viaje)->create(['nombre' => 'Ana']);
        $beto = Participante::factory()->for($viaje)->create(['nombre' => 'Beto']);

        $this->actingAs($user)
            ->from(route('viajes.show', $viaje))
            ->put(route('participantes.update', $beto), [
                'nombre' => 'Ana',
            ])
            ->assertRedirect(route('viajes.show', $viaje))
            ->assertSessionHasErrors('nombre');
    }

    public function test_owner_can_delete_a_participante(): void
    {
        $user = User::factory()->create();
        $viaje = Viaje::factory()->for($user, 'user')->create();
        $participante = Participante::factory()->for($viaje)->create(['nombre' => 'Diego']);

        $this->actingAs($user)
            ->delete(route('participantes.destroy', $participante))
            ->assertRedirect(route('viajes.show', $viaje));

        $this->assertDatabaseMissing('participantes', ['id' => $participante->id]);
    }

    public function test_cannot_delete_participante_who_owes_pending_debt(): void
    {
        $user = User::factory()->create();
        $viaje = Viaje::factory()->for($user, 'user')->create(['nombre' => 'Viaje a Samaipata']);
        $ana = Participante::factory()->for($viaje)->create(['nombre' => 'Ana']);
        $diego = Participante::factory()->for($viaje)->create(['nombre' => 'Diego']);

        \App\Models\Liquidacion::query()->create([
            'viaje_id' => $viaje->id,
            'deudor_id' => $diego->id,
            'acreedor_id' => $ana->id,
            'monto_original' => 400.00,
            'monto_pagado' => 0,
            'monto_pendiente' => 400.00,
            'estado' => 'pendiente',
        ]);

        $this->actingAs($user)
            ->from(route('viajes.show', $viaje))
            ->delete(route('participantes.destroy', $diego))
            ->assertRedirect(route('viajes.show', $viaje))
            ->assertSessionHas('flash.banner')
            ->assertSessionHas('flash.bannerStyle', 'danger');

        $this->assertStringContainsStringIgnoringCase(
            'deuda pendiente',
            session('flash.banner')
        );
        $this->assertDatabaseHas('participantes', ['id' => $diego->id]);
    }

    public function test_cannot_delete_participante_who_is_owed_pending_debt(): void
    {
        $user = User::factory()->create();
        $viaje = Viaje::factory()->for($user, 'user')->create(['nombre' => 'Viaje a Samaipata']);
        $ana = Participante::factory()->for($viaje)->create(['nombre' => 'Ana']);
        $diego = Participante::factory()->for($viaje)->create(['nombre' => 'Diego']);

        \App\Models\Liquidacion::query()->create([
            'viaje_id' => $viaje->id,
            'deudor_id' => $diego->id,
            'acreedor_id' => $ana->id,
            'monto_original' => 160.00,
            'monto_pagado' => 0,
            'monto_pendiente' => 160.00,
            'estado' => 'pendiente',
        ]);

        $this->actingAs($user)
            ->from(route('viajes.show', $viaje))
            ->delete(route('participantes.destroy', $ana))
            ->assertRedirect(route('viajes.show', $viaje))
            ->assertSessionHas('flash.bannerStyle', 'danger');

        $this->assertStringContainsStringIgnoringCase(
            'deuda pendiente',
            session('flash.banner')
        );
        $this->assertDatabaseHas('participantes', ['id' => $ana->id]);
    }

    public function test_cannot_delete_participante_who_participated_in_a_gasto(): void
    {
        $user = User::factory()->create();
        $viaje = Viaje::factory()->for($user, 'user')->create();
        $ana = Participante::factory()->for($viaje)->create(['nombre' => 'Ana']);

        $gasto = \App\Models\Gasto::factory()->for($viaje)->create([
            'concepto' => 'Cabaña',
            'monto' => 800.00,
            'fecha' => '2026-09-01',
            'pagador_id' => $ana->id,
        ]);
        $gasto->participantes()->sync([$ana->id]);

        $this->actingAs($user)
            ->from(route('viajes.show', $viaje))
            ->delete(route('participantes.destroy', $ana))
            ->assertRedirect(route('viajes.show', $viaje))
            ->assertSessionHas('flash.bannerStyle', 'danger');

        $this->assertStringContainsStringIgnoringCase(
            'participó en un gasto',
            session('flash.banner')
        );
        $this->assertDatabaseHas('participantes', ['id' => $ana->id]);
    }

    public function test_owner_can_delete_participante_without_debts_or_gastos(): void
    {
        $user = User::factory()->create();
        $viaje = Viaje::factory()->for($user, 'user')->create(['nombre' => 'Viaje a Samaipata']);
        Participante::factory()->for($viaje)->create(['nombre' => 'Ana']);
        $zulma = Participante::factory()->for($viaje)->create(['nombre' => 'Zulma']);

        $this->actingAs($user)
            ->delete(route('participantes.destroy', $zulma))
            ->assertRedirect(route('viajes.show', $viaje))
            ->assertSessionHas('flash.bannerStyle', 'success');

        $this->assertDatabaseMissing('participantes', ['id' => $zulma->id]);
    }

    public function test_user_cannot_manage_participantes_of_another_users_viaje(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $viaje = Viaje::factory()->for($owner, 'user')->create();
        $participante = Participante::factory()->for($viaje)->create(['nombre' => 'Ana']);

        $this->actingAs($other)
            ->post(route('viajes.participantes.store', $viaje), ['nombre' => 'Beto'])
            ->assertForbidden();

        $this->actingAs($other)
            ->put(route('participantes.update', $participante), ['nombre' => 'Anita'])
            ->assertForbidden();

        $this->actingAs($other)
            ->delete(route('participantes.destroy', $participante))
            ->assertForbidden();
    }

    public function test_guest_cannot_access_participantes(): void
    {
        $viaje = Viaje::factory()->create();

        $this->post(route('viajes.participantes.store', $viaje), ['nombre' => 'Ana'])
            ->assertRedirect(route('login'));
    }
}
