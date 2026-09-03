<?php

namespace Tests\Unit\Services;

use App\Models\Gasto;
use App\Models\Participante;
use App\Models\User;
use App\Models\Viaje;
use App\Services\CalculoBalanceService;
use App\Services\ExportarViajePdfService;
use App\Services\AlgoritmoLiquidacionService;
use App\Services\RegistroLiquidacionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportarViajePdfServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Mismo escenario de Samaipata que LiquidacionTest (no el seeder de DatabaseSeeder).
     */
    private function crearEscenarioSamaipata(User $user): Viaje
    {
        $viaje = Viaje::factory()->for($user, 'user')->create([
            'nombre' => 'Viaje a Samaipata',
            'descripcion' => 'Fin de semana',
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

        return $viaje->fresh();
    }

    public function test_dto_samaipata_coincide_con_servicios_existentes(): void
    {
        Carbon::setTestNow('2026-09-03 18:30:00');

        $user = User::factory()->create();
        $viaje = $this->crearEscenarioSamaipata($user);

        $reporte = app(ExportarViajePdfService::class)->armar($viaje);

        $this->assertSame('Viaje a Samaipata', $reporte['viaje']['nombre']);
        $this->assertSame('Fin de semana', $reporte['viaje']['descripcion']);
        $this->assertNotEmpty($reporte['viaje']['generado_en']);

        $this->assertEquals(1600.00, $reporte['resumen']['total_gastado_bs']);
        $this->assertSame(4, $reporte['resumen']['cantidad_participantes']);
        $this->assertSame(4, $reporte['resumen']['cantidad_gastos']);
        $this->assertFalse($reporte['resumen']['sin_gastos']);

        $nombres = collect($reporte['participantes'])->pluck('nombre')->all();
        $this->assertEqualsCanonicalizing(['Ana', 'Beto', 'Carla', 'Diego'], $nombres);

        $balances = app(CalculoBalanceService::class)->calcularBalances($viaje->fresh(['participantes', 'gastos.participantes']));
        $transferencias = app(AlgoritmoLiquidacionService::class)->calcularLiquidacion($balances);
        $deudas = app(RegistroLiquidacionService::class)->listarDeudas($viaje->fresh());
        $saldosExpuestos = app(RegistroLiquidacionService::class)->aplicarPagosABalances($viaje->fresh(), $balances);

        $this->assertEquals(0.00, round(collect($reporte['saldos'])->sum('balance'), 2));
        $this->assertEquals(0.00, round(collect($reporte['saldos'])->sum('balance_expuesto'), 2));

        $saldosPorNombre = collect($reporte['saldos'])->keyBy('nombre');
        $balancesPorNombre = collect($balances)->keyBy('nombre');
        $expuestosPorNombre = collect($saldosExpuestos)->keyBy('nombre');

        foreach (['Ana', 'Beto', 'Carla', 'Diego'] as $nombre) {
            $this->assertEquals($balancesPorNombre[$nombre]['total_pagado'], $saldosPorNombre[$nombre]['total_pagado']);
            $this->assertEquals($balancesPorNombre[$nombre]['total_consumido'], $saldosPorNombre[$nombre]['total_consumido']);
            $this->assertEquals($balancesPorNombre[$nombre]['balance'], $saldosPorNombre[$nombre]['balance']);
            $this->assertEquals($expuestosPorNombre[$nombre]['balance'], $saldosPorNombre[$nombre]['balance_expuesto']);
        }

        $diegoAna = collect($reporte['liquidaciones'])->first(
            fn (array $l) => $l['deudor'] === 'Diego' && $l['acreedor'] === 'Ana'
        );
        $carlaAna = collect($reporte['liquidaciones'])->first(
            fn (array $l) => $l['deudor'] === 'Carla' && $l['acreedor'] === 'Ana'
        );

        $this->assertNotNull($diegoAna);
        $this->assertEquals(400.00, $diegoAna['monto_original']);
        $this->assertEquals(400.00, $diegoAna['monto_pendiente']);
        $this->assertSame('pendiente', $diegoAna['estado']);

        $this->assertNotNull($carlaAna);
        $this->assertEquals(160.00, $carlaAna['monto_original']);
        $this->assertEquals(160.00, $carlaAna['monto_pendiente']);

        $deudasServicio = collect($deudas);
        $this->assertEquals(
            $deudasServicio->firstWhere('deudor', 'Diego')['monto_pendiente'],
            $diegoAna['monto_pendiente']
        );
        $this->assertEquals(
            collect($transferencias)->firstWhere('deudor', 'Diego')['monto'],
            $diegoAna['monto_original']
        );

        $this->assertDtoSinClavesInternas($reporte);
    }

    public function test_dto_gasto_55_40_reparte_cuotas_27_50_y_28_00(): void
    {
        $user = User::factory()->create();
        $viaje = Viaje::factory()->for($user, 'user')->create(['nombre' => 'Cena']);
        $ana = Participante::factory()->for($viaje)->create(['nombre' => 'Ana', 'user_id' => $user->id]);
        $beto = Participante::factory()->for($viaje)->create(['nombre' => 'Beto']);

        $gasto = Gasto::factory()->for($viaje)->create([
            'concepto' => 'Cena',
            'monto' => 55.40,
            'fecha' => '2026-09-01',
            'pagador_id' => $ana->id,
        ]);
        $gasto->participantes()->sync([$ana->id, $beto->id]);

        $reporte = app(ExportarViajePdfService::class)->armar($viaje->fresh());

        $this->assertEquals(55.40, $reporte['resumen']['total_gastado_bs']);
        $this->assertEquals(0.00, round(collect($reporte['saldos'])->sum('balance'), 2));

        $detalle = collect($reporte['gastos'])->firstWhere('concepto', 'Cena');
        $this->assertNotNull($detalle);
        $this->assertEquals(55.40, $detalle['monto_original']);
        $this->assertTrue($detalle['tiene_ajuste_efectivo']);

        $cuotas = collect($detalle['cuotas_efectivo'])->keyBy('nombre');
        $this->assertEquals(27.50, $cuotas['Ana']['cuota_final']);
        $this->assertEquals(28.00, $cuotas['Beto']['cuota_final']);
        $this->assertArrayNotHasKey('id', $cuotas['Ana']);
    }

    /**
     * @param  array<string, mixed>  $nodo
     */
    private function assertDtoSinClavesInternas(array $nodo): void
    {
        $prohibidas = [
            'id',
            'user_id',
            'email',
            'password',
            'codigo_invitacion',
            'participante_id',
            'deudor_id',
            'acreedor_id',
            'gasto_id',
        ];

        foreach ($nodo as $clave => $valor) {
            $this->assertFalse(
                in_array($clave, $prohibidas, true),
                "El DTO no debe exponer la clave interna [{$clave}]."
            );

            if (is_array($valor)) {
                $this->assertDtoSinClavesInternas($valor);
            }
        }
    }
}
