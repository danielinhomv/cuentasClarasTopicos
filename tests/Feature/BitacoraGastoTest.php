<?php

namespace Tests\Feature;

use App\Models\GastoBitacora;
use App\Models\Participante;
use App\Models\User;
use App\Models\Viaje;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BitacoraGastoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{
     *     ana: User,
     *     beto: User,
     *     viaje: Viaje,
     *     anaParticipante: Participante,
     *     betoParticipante: Participante,
     *     carlaParticipante: Participante,
     *     diegoParticipante: Participante
     * }
     */
    private function viajeSamaipata(): array
    {
        $ana = User::factory()->create(['name' => 'Ana']);
        $beto = User::factory()->create(['name' => 'Beto']);
        $viaje = Viaje::factory()->for($ana, 'user')->create(['nombre' => 'Viaje a Samaipata']);

        $anaParticipante = Participante::factory()->for($viaje)->create([
            'nombre' => 'Ana',
            'user_id' => $ana->id,
        ]);
        $betoParticipante = Participante::factory()->for($viaje)->create([
            'nombre' => 'Beto',
            'user_id' => $beto->id,
        ]);
        $carlaParticipante = Participante::factory()->for($viaje)->create(['nombre' => 'Carla']);
        $diegoParticipante = Participante::factory()->for($viaje)->create(['nombre' => 'Diego']);

        return compact(
            'ana',
            'beto',
            'viaje',
            'anaParticipante',
            'betoParticipante',
            'carlaParticipante',
            'diegoParticipante'
        );
    }

    public function test_alta_de_gasto_genera_entrada_de_crear(): void
    {
        [
            'beto' => $beto,
            'viaje' => $viaje,
            'betoParticipante' => $betoParticipante,
        ] = $this->viajeSamaipata();

        $this->actingAs($beto)->postJson(route('viajes.gastos.store', $viaje), [
            'concepto' => 'Cena',
            'monto' => 400.00,
            'moneda' => 'BOB',
            'fecha' => '2026-09-02',
            'pagador_id' => $betoParticipante->id,
        ])->assertCreated();

        $entrada = GastoBitacora::query()->sole();

        $this->assertSame('crear', $entrada->accion);
        $this->assertSame($beto->id, $entrada->user_id);
        $this->assertSame('Beto', $entrada->actor_nombre);
        $this->assertSame('Cena', $entrada->gasto_concepto);
        $this->assertSame($viaje->id, $entrada->viaje_id);
        $this->assertNotNull($entrada->gasto_id);
        $this->assertNull($entrada->datos_antes);
        $this->assertSame('Cena', $entrada->datos_despues['concepto']);
        $this->assertSame('400.00', $entrada->datos_despues['monto']);
        $this->assertSame('BOB', $entrada->datos_despues['moneda']);
        $this->assertSame('2026-09-02', $entrada->datos_despues['fecha']);
        $this->assertSame($betoParticipante->id, $entrada->datos_despues['pagador_id']);
        $this->assertSame('Beto', $entrada->datos_despues['pagador_nombre']);
        $this->assertCount(4, $entrada->datos_despues['incluidos']);
        $this->assertSame([], $entrada->datos_despues['excluidos']);
    }

    public function test_edicion_de_monto_registra_valor_anterior_y_nuevo(): void
    {
        [
            'beto' => $beto,
            'viaje' => $viaje,
            'betoParticipante' => $betoParticipante,
        ] = $this->viajeSamaipata();

        $gastoId = $this->actingAs($beto)->postJson(route('viajes.gastos.store', $viaje), [
            'concepto' => 'Cena',
            'monto' => 100.00,
            'fecha' => '2026-09-02',
            'pagador_id' => $betoParticipante->id,
        ])->assertCreated()->json('id');

        $this->actingAs($beto)->putJson(route('gastos.update', $gastoId), [
            'concepto' => 'Cena',
            'monto' => 150.00,
            'fecha' => '2026-09-02',
            'pagador_id' => $betoParticipante->id,
        ])->assertOk();

        $entrada = GastoBitacora::query()->where('accion', 'editar')->sole();

        $this->assertSame('100.00', $entrada->datos_antes['monto']);
        $this->assertSame('150.00', $entrada->datos_despues['monto']);
        $this->assertArrayNotHasKey('concepto', $entrada->datos_antes);
        $this->assertArrayNotHasKey('concepto', $entrada->datos_despues);
        $this->assertSame($gastoId, $entrada->gasto_id);
        $this->assertNotNull($entrada->created_at);
    }

    public function test_edicion_sin_cambio_de_monto_no_inventa_delta_de_monto(): void
    {
        [
            'beto' => $beto,
            'viaje' => $viaje,
            'betoParticipante' => $betoParticipante,
        ] = $this->viajeSamaipata();

        $gastoId = $this->actingAs($beto)->postJson(route('viajes.gastos.store', $viaje), [
            'concepto' => 'Cena',
            'monto' => 100.00,
            'fecha' => '2026-09-02',
            'pagador_id' => $betoParticipante->id,
        ])->assertCreated()->json('id');

        $this->actingAs($beto)->putJson(route('gastos.update', $gastoId), [
            'concepto' => 'Cena especial',
            'monto' => 100.00,
            'fecha' => '2026-09-02',
            'pagador_id' => $betoParticipante->id,
        ])->assertOk();

        $entrada = GastoBitacora::query()->where('accion', 'editar')->sole();

        $this->assertSame('Cena', $entrada->datos_antes['concepto']);
        $this->assertSame('Cena especial', $entrada->datos_despues['concepto']);
        $this->assertArrayNotHasKey('monto', $entrada->datos_antes);
        $this->assertArrayNotHasKey('monto', $entrada->datos_despues);
    }

    public function test_eliminacion_conserva_entrada_con_gasto_id_nulo(): void
    {
        [
            'beto' => $beto,
            'viaje' => $viaje,
            'carlaParticipante' => $carlaParticipante,
        ] = $this->viajeSamaipata();

        $gastoId = $this->actingAs($beto)->postJson(route('viajes.gastos.store', $viaje), [
            'concepto' => 'Gasolina',
            'monto' => 240.00,
            'fecha' => '2026-09-03',
            'pagador_id' => $carlaParticipante->id,
        ])->assertCreated()->json('id');

        $this->actingAs($beto)->deleteJson(route('gastos.destroy', $gastoId))->assertOk();

        $entrada = GastoBitacora::query()->where('accion', 'eliminar')->sole();

        $this->assertNull($entrada->gasto_id);
        $this->assertSame('Gasolina', $entrada->gasto_concepto);
        $this->assertSame('240.00', $entrada->datos_antes['monto']);
        $this->assertSame('Carla', $entrada->datos_antes['pagador_nombre']);
        $this->assertNull($entrada->datos_despues);
        $this->assertDatabaseMissing('gastos', ['id' => $gastoId]);
    }

    public function test_anfitrion_consulta_bitacora_persistida_al_refrescar(): void
    {
        [
            'ana' => $ana,
            'beto' => $beto,
            'viaje' => $viaje,
            'betoParticipante' => $betoParticipante,
        ] = $this->viajeSamaipata();

        $this->actingAs($beto)->postJson(route('viajes.gastos.store', $viaje), [
            'concepto' => 'Cena',
            'monto' => 400.00,
            'fecha' => '2026-09-02',
            'pagador_id' => $betoParticipante->id,
        ])->assertCreated();

        $primera = $this->actingAs($ana)
            ->getJson(route('viajes.bitacora.index', $viaje))
            ->assertOk()
            ->assertJsonCount(1)
            ->json();

        $segunda = $this->actingAs($ana)
            ->getJson(route('viajes.bitacora.index', $viaje))
            ->assertOk()
            ->json();

        $this->assertSame($primera[0]['id'], $segunda[0]['id']);
        $this->assertSame('crear', $primera[0]['accion']);
        $this->assertSame('Cena', $primera[0]['gasto_concepto']);
    }

    public function test_snapshot_de_creacion_incluye_exclusion_y_moneda(): void
    {
        [
            'beto' => $beto,
            'viaje' => $viaje,
            'anaParticipante' => $anaParticipante,
            'diegoParticipante' => $diegoParticipante,
        ] = $this->viajeSamaipata();

        $this->actingAs($beto)->postJson(route('viajes.gastos.store', $viaje), [
            'concepto' => 'Entradas El Fuerte',
            'monto' => 160.00,
            'moneda' => 'USD',
            'fecha' => '2026-09-01',
            'pagador_id' => $anaParticipante->id,
            'excluidos' => [$diegoParticipante->id],
        ])->assertCreated();

        $entrada = GastoBitacora::query()->sole();
        $nombresIncluidos = collect($entrada->datos_despues['incluidos'])->pluck('nombre')->sort()->values()->all();
        $nombresExcluidos = collect($entrada->datos_despues['excluidos'])->pluck('nombre')->all();

        $this->assertSame('160.00', $entrada->datos_despues['monto']);
        $this->assertSame('USD', $entrada->datos_despues['moneda']);
        $this->assertNotNull($entrada->datos_despues['tipo_cambio']);
        $this->assertSame('Ana', $entrada->datos_despues['pagador_nombre']);
        $this->assertSame(['Diego'], $nombresExcluidos);
        $this->assertSame(['Ana', 'Beto', 'Carla'], $nombresIncluidos);
    }

    public function test_edicion_de_exclusiones_solo_guarda_campos_cambiados(): void
    {
        [
            'beto' => $beto,
            'viaje' => $viaje,
            'betoParticipante' => $betoParticipante,
            'diegoParticipante' => $diegoParticipante,
        ] = $this->viajeSamaipata();

        $gastoId = $this->actingAs($beto)->postJson(route('viajes.gastos.store', $viaje), [
            'concepto' => 'Cena',
            'monto' => 400.00,
            'fecha' => '2026-09-02',
            'pagador_id' => $betoParticipante->id,
        ])->assertCreated()->json('id');

        $this->actingAs($beto)->putJson(route('gastos.update', $gastoId), [
            'concepto' => 'Cena',
            'monto' => 400.00,
            'fecha' => '2026-09-02',
            'pagador_id' => $betoParticipante->id,
            'excluidos' => [$diegoParticipante->id],
        ])->assertOk();

        $entrada = GastoBitacora::query()->where('accion', 'editar')->sole();

        $this->assertArrayHasKey('excluidos', $entrada->datos_antes);
        $this->assertArrayHasKey('excluidos', $entrada->datos_despues);
        $this->assertSame([], $entrada->datos_antes['excluidos']);
        $this->assertSame(['Diego'], collect($entrada->datos_despues['excluidos'])->pluck('nombre')->all());
        $this->assertArrayNotHasKey('monto', $entrada->datos_antes);
        $this->assertArrayNotHasKey('concepto', $entrada->datos_antes);
    }

    public function test_monto_invalido_no_escribe_bitacora(): void
    {
        [
            'beto' => $beto,
            'viaje' => $viaje,
            'betoParticipante' => $betoParticipante,
        ] = $this->viajeSamaipata();

        $this->actingAs($beto)->postJson(route('viajes.gastos.store', $viaje), [
            'concepto' => 'Cena',
            'monto' => 0,
            'fecha' => '2026-09-02',
            'pagador_id' => $betoParticipante->id,
        ])->assertUnprocessable();

        $this->actingAs($beto)->postJson(route('viajes.gastos.store', $viaje), [
            'concepto' => 'Cena',
            'monto' => -10,
            'fecha' => '2026-09-02',
            'pagador_id' => $betoParticipante->id,
        ])->assertUnprocessable();

        $this->assertDatabaseCount('gasto_bitacoras', 0);
        $this->assertDatabaseCount('gastos', 0);
    }

    public function test_anfitrion_recibe_lista_vacia_si_no_hay_eventos(): void
    {
        ['ana' => $ana, 'viaje' => $viaje] = $this->viajeSamaipata();

        $this->actingAs($ana)
            ->getJson(route('viajes.bitacora.index', $viaje))
            ->assertOk()
            ->assertExactJson([]);
    }

    public function test_participante_no_anfitrion_recibe_403(): void
    {
        [
            'beto' => $beto,
            'viaje' => $viaje,
            'betoParticipante' => $betoParticipante,
        ] = $this->viajeSamaipata();

        $this->actingAs($beto)->postJson(route('viajes.gastos.store', $viaje), [
            'concepto' => 'Cena',
            'monto' => 400.00,
            'fecha' => '2026-09-02',
            'pagador_id' => $betoParticipante->id,
        ])->assertCreated();

        $this->actingAs($beto)
            ->getJson(route('viajes.bitacora.index', $viaje))
            ->assertForbidden();
    }

    public function test_visitante_no_autenticado_no_consulta_bitacora(): void
    {
        ['viaje' => $viaje] = $this->viajeSamaipata();

        $this->getJson(route('viajes.bitacora.index', $viaje))
            ->assertUnauthorized();

        $this->get(route('viajes.bitacora.index', $viaje))
            ->assertRedirect();
    }

    public function test_no_existen_rutas_de_mutacion_de_bitacora(): void
    {
        ['ana' => $ana, 'viaje' => $viaje] = $this->viajeSamaipata();

        $url = route('viajes.bitacora.index', $viaje);

        $this->actingAs($ana)->postJson($url, ['accion' => 'crear'])->assertStatus(405);
        $this->actingAs($ana)->putJson($url, ['accion' => 'editar'])->assertStatus(405);
        $this->actingAs($ana)->deleteJson($url)->assertStatus(405);

        $this->assertDatabaseCount('gasto_bitacoras', 0);
    }

    public function test_inertia_envia_bitacora_solo_al_anfitrion(): void
    {
        $this->withoutVite();

        [
            'ana' => $ana,
            'beto' => $beto,
            'viaje' => $viaje,
            'betoParticipante' => $betoParticipante,
        ] = $this->viajeSamaipata();

        $this->actingAs($beto)->postJson(route('viajes.gastos.store', $viaje), [
            'concepto' => 'Cena',
            'monto' => 400.00,
            'fecha' => '2026-09-02',
            'pagador_id' => $betoParticipante->id,
        ])->assertCreated();

        $this->actingAs($ana)
            ->get(route('viajes.show', $viaje))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Viajes/Show')
                ->has('bitacora', 1)
                ->where('bitacora.0.accion', 'crear')
                ->where('bitacora.0.gasto_concepto', 'Cena')
            );

        $this->actingAs($beto)
            ->get(route('viajes.show', $viaje))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Viajes/Show')
                ->has('bitacora', 0)
            );
    }

    public function test_bitacora_se_ordena_de_mas_reciente_a_mas_antigua(): void
    {
        [
            'ana' => $ana,
            'beto' => $beto,
            'viaje' => $viaje,
            'betoParticipante' => $betoParticipante,
        ] = $this->viajeSamaipata();

        $this->actingAs($beto)->postJson(route('viajes.gastos.store', $viaje), [
            'concepto' => 'Cena',
            'monto' => 400.00,
            'fecha' => '2026-09-02',
            'pagador_id' => $betoParticipante->id,
        ])->assertCreated();

        $this->actingAs($beto)->postJson(route('viajes.gastos.store', $viaje), [
            'concepto' => 'Gasolina',
            'monto' => 240.00,
            'fecha' => '2026-09-03',
            'pagador_id' => $betoParticipante->id,
        ])->assertCreated();

        $this->actingAs($ana)
            ->getJson(route('viajes.bitacora.index', $viaje))
            ->assertOk()
            ->assertJsonPath('0.gasto_concepto', 'Gasolina')
            ->assertJsonPath('1.gasto_concepto', 'Cena');
    }
}
