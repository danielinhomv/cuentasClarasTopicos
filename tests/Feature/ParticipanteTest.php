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
