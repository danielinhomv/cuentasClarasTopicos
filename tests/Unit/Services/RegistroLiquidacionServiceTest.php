<?php

namespace Tests\Unit\Services;

use App\Models\Participante;
use App\Models\User;
use App\Models\Viaje;
use App\Services\RegistroLiquidacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class RegistroLiquidacionServiceTest extends TestCase
{
    use RefreshDatabase;

    private RegistroLiquidacionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RegistroLiquidacionService();
    }

    public function test_reconciliar_persiste_deudas_del_plan(): void
    {
        [$viaje, $diego, $ana] = $this->crearViajeConDosParticipantes();

        $deudas = $this->service->reconciliar($viaje, [
            [
                'deudor_id' => $diego->id,
                'deudor_nombre' => 'Diego',
                'deudor' => 'Diego',
                'acreedor_id' => $ana->id,
                'acreedor_nombre' => 'Ana',
                'acreedor' => 'Ana',
                'monto' => 40.00,
            ],
        ]);

        $this->assertCount(1, $deudas);
        $this->assertEquals(40.00, $deudas[0]['monto_original']);
        $this->assertEquals(0.00, $deudas[0]['monto_pagado']);
        $this->assertEquals(40.00, $deudas[0]['monto_pendiente']);
        $this->assertFalse($deudas[0]['liquidada']);
    }

    public function test_pago_parcial_y_cierre_total(): void
    {
        [$viaje, $diego, $ana] = $this->crearViajeConDosParticipantes();
        $this->service->reconciliar($viaje, [[
            'deudor_id' => $diego->id,
            'deudor_nombre' => 'Diego',
            'deudor' => 'Diego',
            'acreedor_id' => $ana->id,
            'acreedor_nombre' => 'Ana',
            'acreedor' => 'Ana',
            'monto' => 40.00,
        ]]);

        $liquidacion = $viaje->liquidaciones()->first();
        $this->service->registrarPago($liquidacion, 20.00);
        $liquidacion->refresh();

        $this->assertEquals(20.00, (float) $liquidacion->monto_pagado);
        $this->assertEquals(20.00, (float) $liquidacion->monto_pendiente);
        $this->assertEquals('pendiente', $liquidacion->estado);

        $this->service->registrarPago($liquidacion->fresh(), 20.00);
        $liquidacion->refresh();

        $this->assertEquals(40.00, (float) $liquidacion->monto_pagado);
        $this->assertEquals(0.00, (float) $liquidacion->monto_pendiente);
        $this->assertEquals('liquidada', $liquidacion->estado);
    }

    public function test_rechaza_sobrepago(): void
    {
        [$viaje, $diego, $ana] = $this->crearViajeConDosParticipantes();
        $this->service->reconciliar($viaje, [[
            'deudor_id' => $diego->id,
            'deudor_nombre' => 'Diego',
            'deudor' => 'Diego',
            'acreedor_id' => $ana->id,
            'acreedor_nombre' => 'Ana',
            'acreedor' => 'Ana',
            'monto' => 40.00,
        ]]);

        $this->expectException(InvalidArgumentException::class);
        $this->service->registrarPago($viaje->liquidaciones()->first(), 50.00);
    }

    public function test_aplicar_pagos_actualiza_saldos_expuestos(): void
    {
        [$viaje, $diego, $ana] = $this->crearViajeConDosParticipantes();
        $this->service->reconciliar($viaje, [[
            'deudor_id' => $diego->id,
            'deudor_nombre' => 'Diego',
            'deudor' => 'Diego',
            'acreedor_id' => $ana->id,
            'acreedor_nombre' => 'Ana',
            'acreedor' => 'Ana',
            'monto' => 40.00,
        ]]);
        $this->service->registrarPago($viaje->liquidaciones()->first(), 20.00);

        $saldos = $this->service->aplicarPagosABalances($viaje, [
            ['participante_id' => $ana->id, 'nombre' => 'Ana', 'balance' => 40.00],
            ['participante_id' => $diego->id, 'nombre' => 'Diego', 'balance' => -40.00],
        ]);

        $porNombre = collect($saldos)->keyBy('nombre');
        $this->assertEquals(20.00, $porNombre['Ana']['balance']);
        $this->assertEquals(-20.00, $porNombre['Diego']['balance']);
        $this->assertEquals(0.00, collect($saldos)->sum('balance'));
    }

    public function test_reconciliar_plan_vacio_elimina_deuda_sin_pagos(): void
    {
        [$viaje, $diego, $ana] = $this->crearViajeConDosParticipantes();

        $this->service->reconciliar($viaje, [[
            'deudor_id' => $diego->id,
            'deudor_nombre' => 'Diego',
            'deudor' => 'Diego',
            'acreedor_id' => $ana->id,
            'acreedor_nombre' => 'Ana',
            'acreedor' => 'Ana',
            'monto' => 40.00,
        ]]);

        $this->assertDatabaseCount('liquidaciones', 1);

        $deudas = $this->service->reconciliar($viaje, []);

        $this->assertCount(0, $deudas);
        $this->assertDatabaseCount('liquidaciones', 0);
    }

    public function test_reconciliar_reduce_original_cuando_el_plan_disminuye(): void
    {
        [$viaje, $diego, $ana] = $this->crearViajeConDosParticipantes();
        $this->service->reconciliar($viaje, [[
            'deudor_id' => $diego->id,
            'deudor_nombre' => 'Diego',
            'deudor' => 'Diego',
            'acreedor_id' => $ana->id,
            'acreedor_nombre' => 'Ana',
            'acreedor' => 'Ana',
            'monto' => 80.00,
        ]]);
        $this->service->registrarPago($viaje->liquidaciones()->first(), 30.00);

        $deudas = $this->service->reconciliar($viaje, [[
            'deudor_id' => $diego->id,
            'deudor_nombre' => 'Diego',
            'deudor' => 'Diego',
            'acreedor_id' => $ana->id,
            'acreedor_nombre' => 'Ana',
            'acreedor' => 'Ana',
            'monto' => 40.00,
        ]]);

        $this->assertCount(1, $deudas);
        $this->assertEquals(40.00, $deudas[0]['monto_original']);
        $this->assertEquals(30.00, $deudas[0]['monto_pagado']);
        $this->assertEquals(10.00, $deudas[0]['monto_pendiente']);
        $this->assertFalse($deudas[0]['liquidada']);
    }

    public function test_reconciliar_cierra_par_obsoleto_con_pagos_sin_invertir_saldos(): void
    {
        [$viaje, $diego, $ana] = $this->crearViajeConDosParticipantes();
        $this->service->reconciliar($viaje, [[
            'deudor_id' => $diego->id,
            'deudor_nombre' => 'Diego',
            'deudor' => 'Diego',
            'acreedor_id' => $ana->id,
            'acreedor_nombre' => 'Ana',
            'acreedor' => 'Ana',
            'monto' => 40.00,
        ]]);
        $this->service->registrarPago($viaje->liquidaciones()->first(), 20.00);

        $deudas = $this->service->reconciliar($viaje, []);

        $this->assertCount(0, $deudas);
        $this->assertDatabaseHas('liquidaciones', [
            'viaje_id' => $viaje->id,
            'monto_original' => 0.00,
            'monto_pagado' => 20.00,
            'monto_pendiente' => 0.00,
            'estado' => 'liquidada',
        ]);

        $saldos = $this->service->aplicarPagosABalances($viaje, [
            ['participante_id' => $ana->id, 'nombre' => 'Ana', 'balance' => 0.00],
            ['participante_id' => $diego->id, 'nombre' => 'Diego', 'balance' => 0.00],
        ]);

        $porNombre = collect($saldos)->keyBy('nombre');
        $this->assertEquals(0.00, $porNombre['Ana']['balance']);
        $this->assertEquals(0.00, $porNombre['Diego']['balance']);
    }

    /**
     * @return array{0: Viaje, 1: Participante, 2: Participante}
     */
    private function crearViajeConDosParticipantes(): array
    {
        $user = User::factory()->create();
        $viaje = Viaje::factory()->for($user, 'user')->create();
        $ana = Participante::factory()->for($viaje)->create(['nombre' => 'Ana']);
        $diego = Participante::factory()->for($viaje)->create(['nombre' => 'Diego']);

        return [$viaje, $diego, $ana];
    }
}
