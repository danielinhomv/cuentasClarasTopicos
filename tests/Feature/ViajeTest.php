<?php

namespace Tests\Feature;

use App\Models\Participante;
use App\Models\User;
use App\Models\Viaje;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ViajeTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_a_viaje(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('viajes.store'), [
            'nombre' => 'Viaje a Samaipata',
            'descripcion' => 'Fin de semana con amigos',
        ]);

        $viaje = Viaje::query()->first();

        $this->assertNotNull($viaje);
        $this->assertSame($user->id, $viaje->user_id);
        $this->assertSame('Viaje a Samaipata', $viaje->nombre);
        $response->assertRedirect(route('viajes.show', $viaje));
    }

    public function test_viaje_nombre_is_required(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('viajes.create'))
            ->post(route('viajes.store'), [
                'nombre' => '   ',
                'descripcion' => 'Opcional',
            ])
            ->assertRedirect(route('viajes.create'))
            ->assertSessionHasErrors('nombre');

        $this->assertDatabaseCount('viajes', 0);
    }

    public function test_index_lists_only_own_viajes(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $own = Viaje::factory()->for($userA, 'user')->create(['nombre' => 'Viaje a Samaipata']);
        Viaje::factory()->for($userB, 'user')->create(['nombre' => 'Viaje ajeno']);

        $this->actingAs($userA)
            ->get(route('viajes.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Viajes/Index')
                ->has('viajes.data', 1)
                ->where('viajes.data.0.id', $own->id)
                ->where('viajes.data.0.nombre', 'Viaje a Samaipata')
            );
    }

    public function test_user_can_view_own_viaje_detail_with_participantes(): void
    {
        $user = User::factory()->create();
        $viaje = Viaje::factory()->for($user, 'user')->create(['nombre' => 'Viaje a Samaipata']);

        foreach (['Ana', 'Beto', 'Carla', 'Diego'] as $nombre) {
            Participante::factory()->for($viaje)->create(['nombre' => $nombre]);
        }

        $this->actingAs($user)
            ->get(route('viajes.show', $viaje))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Viajes/Show')
                ->where('viaje.id', $viaje->id)
                ->has('viaje.participantes', 4)
            );
    }

    public function test_user_cannot_view_another_users_viaje(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $viaje = Viaje::factory()->for($owner, 'user')->create();

        $this->actingAs($other)
            ->get(route('viajes.show', $viaje))
            ->assertForbidden();
    }

    public function test_user_can_update_own_viaje(): void
    {
        $user = User::factory()->create();
        $viaje = Viaje::factory()->for($user, 'user')->create(['nombre' => 'Viaje a Samaipata']);

        $this->actingAs($user)
            ->put(route('viajes.update', $viaje), [
                'nombre' => 'Samaipata 2026',
                'descripcion' => $viaje->descripcion,
            ])
            ->assertRedirect(route('viajes.show', $viaje));

        $this->assertDatabaseHas('viajes', [
            'id' => $viaje->id,
            'nombre' => 'Samaipata 2026',
        ]);
    }

    public function test_deleting_viaje_cascades_participantes(): void
    {
        $user = User::factory()->create();
        $viaje = Viaje::factory()->for($user, 'user')->create();
        $participante = Participante::factory()->for($viaje)->create(['nombre' => 'Ana']);

        $this->actingAs($user)
            ->delete(route('viajes.destroy', $viaje))
            ->assertRedirect(route('viajes.index'));

        $this->assertDatabaseMissing('viajes', ['id' => $viaje->id]);
        $this->assertDatabaseMissing('participantes', ['id' => $participante->id]);
    }

    public function test_user_cannot_update_or_delete_another_users_viaje(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $viaje = Viaje::factory()->for($owner, 'user')->create();

        $this->actingAs($other)
            ->put(route('viajes.update', $viaje), ['nombre' => 'Hack'])
            ->assertForbidden();

        $this->actingAs($other)
            ->delete(route('viajes.destroy', $viaje))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_from_viajes_to_login(): void
    {
        $this->get(route('viajes.index'))
            ->assertRedirect(route('login'));
    }

    public function test_user_can_join_viaje_using_valid_codigo_invitacion(): void
    {
        $owner = User::factory()->create(['name' => 'Ana']);
        $friend = User::factory()->create(['name' => 'Beto']);

        $viaje = Viaje::factory()->for($owner, 'user')->create([
            'codigo_invitacion' => 'SAMA8X12',
        ]);

        $this->actingAs($friend)
            ->post(route('viajes.unirse'), [
                'codigo_invitacion' => 'sama8x12',
            ])
            ->assertRedirect(route('viajes.show', $viaje))
            ->assertSessionHas('flash.banner');

        $this->assertDatabaseHas('participantes', [
            'viaje_id' => $viaje->id,
            'user_id' => $friend->id,
            'nombre' => 'Beto',
        ]);
    }

    public function test_user_cannot_join_viaje_with_invalid_codigo(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('viajes.unirse'), [
                'codigo_invitacion' => 'NOEXISTE',
            ])
            ->assertSessionHasErrors('codigo_invitacion');
    }

    public function test_user_cannot_join_viaje_twice(): void
    {
        $owner = User::factory()->create();
        $friend = User::factory()->create();

        $viaje = Viaje::factory()->for($owner, 'user')->create([
            'codigo_invitacion' => 'SAMA8X12',
        ]);

        $viaje->participantes()->create([
            'user_id' => $friend->id,
            'nombre' => $friend->name,
        ]);

        $this->actingAs($friend)
            ->post(route('viajes.unirse'), [
                'codigo_invitacion' => 'SAMA8X12',
            ])
            ->assertSessionHasErrors('codigo_invitacion');
    }

    public function test_participating_user_can_view_viaje_and_lists_in_index(): void
    {
        $owner = User::factory()->create();
        $friend = User::factory()->create();

        $viaje = Viaje::factory()->for($owner, 'user')->create([
            'nombre' => 'Samaipata Amigos',
            'codigo_invitacion' => 'SAMA8X12',
        ]);

        $viaje->participantes()->create([
            'user_id' => $friend->id,
            'nombre' => $friend->name,
        ]);

        $this->actingAs($friend)
            ->get(route('viajes.show', $viaje))
            ->assertOk();

        $this->actingAs($friend)
            ->get(route('viajes.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Viajes/Index')
                ->has('viajes.data', 1)
                ->where('viajes.data.0.nombre', 'Samaipata Amigos')
            );
    }
}

