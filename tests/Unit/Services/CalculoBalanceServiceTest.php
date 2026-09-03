<?php

namespace Tests\Unit\Services;

use App\Models\Gasto;
use App\Models\Participante;
use App\Models\User;
use App\Models\Viaje;
use App\Services\CalculoBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalculoBalanceServiceTest extends TestCase
{
    use RefreshDatabase;

    private CalculoBalanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CalculoBalanceService();
    }

    public function test_calcular_balances_con_escenario_samaipata(): void
    {
        $user = User::factory()->create();
        $viaje = Viaje::factory()->for($user, 'user')->create();

        $ana = Participante::factory()->for($viaje)->create(['nombre' => 'Ana']);
        $beto = Participante::factory()->for($viaje)->create(['nombre' => 'Beto']);
        $carla = Participante::factory()->for($viaje)->create(['nombre' => 'Carla']);
        $diego = Participante::factory()->for($viaje)->create(['nombre' => 'Diego']);

        $allIds = [$ana->id, $beto->id, $carla->id, $diego->id];

        // 1. Cabaña 800 (Ana)
        $g1 = Gasto::factory()->for($viaje)->create([
            'concepto' => 'Cabaña',
            'monto' => 800.00,
            'fecha' => '2026-09-01',
            'pagador_id' => $ana->id,
        ]);
        $g1->participantes()->sync($allIds);
        // 2. Entradas El Fuerte 160 (Ana)
        $g2 = Gasto::factory()->for($viaje)->create([
            'concepto' => 'Entradas a El Fuerte',
            'monto' => 160.00,
            'fecha' => '2026-09-02',
            'pagador_id' => $ana->id,
        ]);
        $g2->participantes()->sync($allIds);
        // 3. Cena 400 (Beto)
        $g3 = Gasto::factory()->for($viaje)->create([
            'concepto' => 'Cena',
            'monto' => 400.00,
            'fecha' => '2026-09-02',
            'pagador_id' => $beto->id,
        ]);
        $g3->participantes()->sync($allIds);
        // 4. Gasolina 240 (Carla)
        $g4 = Gasto::factory()->for($viaje)->create([
            'concepto' => 'Gasolina',
            'monto' => 240.00,
            'fecha' => '2026-09-03',
            'pagador_id' => $carla->id,
        ]);
        $g4->participantes()->sync($allIds);

        $balances = $this->service->calcularBalances($viaje);

        $balancesPorNombre = collect($balances)->keyBy('nombre');

        $this->assertEquals(960.00, $balancesPorNombre['Ana']['total_pagado']);
        $this->assertEquals(400.00, $balancesPorNombre['Ana']['total_consumido']);
        $this->assertEquals(560.00, $balancesPorNombre['Ana']['balance']);

        $this->assertEquals(400.00, $balancesPorNombre['Beto']['total_pagado']);
        $this->assertEquals(400.00, $balancesPorNombre['Beto']['total_consumido']);
        $this->assertEquals(0.00, $balancesPorNombre['Beto']['balance']);

        $this->assertEquals(240.00, $balancesPorNombre['Carla']['total_pagado']);
        $this->assertEquals(400.00, $balancesPorNombre['Carla']['total_consumido']);
        $this->assertEquals(-160.00, $balancesPorNombre['Carla']['balance']);

        $this->assertEquals(0.00, $balancesPorNombre['Diego']['total_pagado']);
        $this->assertEquals(400.00, $balancesPorNombre['Diego']['total_consumido']);
        $this->assertEquals(-400.00, $balancesPorNombre['Diego']['balance']);

        $suma = collect($balances)->sum('balance');
        $this->assertEquals(0.00, $suma);
    }

    public function test_calcular_balances_con_viaje_sin_gastos(): void
    {
        $user = User::factory()->create();
        $viaje = Viaje::factory()->for($user, 'user')->create();
        $ana = Participante::factory()->for($viaje)->create(['nombre' => 'Ana']);

        $balances = $this->service->calcularBalances($viaje);

        $this->assertCount(1, $balances);
        $this->assertEquals(0.00, $balances[0]['total_pagado']);
        $this->assertEquals(0.00, $balances[0]['total_consumido']);
        $this->assertEquals(0.00, $balances[0]['balance']);
    }

    public function test_calcular_balances_con_exclusiones(): void
    {
        $user = User::factory()->create();
        $viaje = Viaje::factory()->for($user, 'user')->create();

        $ana = Participante::factory()->for($viaje)->create(['nombre' => 'Ana']);
        $beto = Participante::factory()->for($viaje)->create(['nombre' => 'Beto']);
        $carla = Participante::factory()->for($viaje)->create(['nombre' => 'Carla']);

        $gasto = Gasto::factory()->for($viaje)->create([
            'concepto' => 'Almuerzo sin Carla',
            'monto' => 100.00,
            'fecha' => '2026-09-01',
            'pagador_id' => $ana->id,
        ]);
        $gasto->excluidos()->attach($carla->id);
        $gasto->participantes()->sync([$ana->id, $beto->id]); // Solo Ana y Beto

        $balances = $this->service->calcularBalances($viaje);
        $balancesPorNombre = collect($balances)->keyBy('nombre');

        $this->assertEquals(100.00, $balancesPorNombre['Ana']['total_pagado']);
        $this->assertEquals(50.00, $balancesPorNombre['Ana']['total_consumido']);
        $this->assertEquals(50.00, $balancesPorNombre['Ana']['balance']);

        $this->assertEquals(0.00, $balancesPorNombre['Beto']['total_pagado']);
        $this->assertEquals(50.00, $balancesPorNombre['Beto']['total_consumido']);
        $this->assertEquals(-50.00, $balancesPorNombre['Beto']['balance']);

        $this->assertEquals(0.00, $balancesPorNombre['Carla']['total_pagado']);
        $this->assertEquals(0.00, $balancesPorNombre['Carla']['total_consumido']);
        $this->assertEquals(0.00, $balancesPorNombre['Carla']['balance']);
    }

    public function test_calcular_balances_con_pagador_excluido_del_beneficio(): void
    {
        $user = User::factory()->create();
        $viaje = Viaje::factory()->for($user, 'user')->create();

        $ana = Participante::factory()->for($viaje)->create(['nombre' => 'Ana']);
        $beto = Participante::factory()->for($viaje)->create(['nombre' => 'Beto']);
        $carla = Participante::factory()->for($viaje)->create(['nombre' => 'Carla']);

        $gasto = Gasto::factory()->for($viaje)->create([
            'concepto' => 'Regalo pagado por Ana para Beto y Carla',
            'monto' => 60.00,
            'fecha' => '2026-09-01',
            'pagador_id' => $ana->id,
        ]);
        $gasto->excluidos()->attach($ana->id);
        $gasto->participantes()->sync([$beto->id, $carla->id]); // Solo Beto y Carla

        $balances = $this->service->calcularBalances($viaje);
        $balancesPorNombre = collect($balances)->keyBy('nombre');

        $this->assertEquals(60.00, $balancesPorNombre['Ana']['total_pagado']);
        $this->assertEquals(0.00, $balancesPorNombre['Ana']['total_consumido']);
        $this->assertEquals(60.00, $balancesPorNombre['Ana']['balance']);

        $this->assertEquals(0.00, $balancesPorNombre['Beto']['total_pagado']);
        $this->assertEquals(30.00, $balancesPorNombre['Beto']['total_consumido']);
        $this->assertEquals(-30.00, $balancesPorNombre['Beto']['balance']);

        $this->assertEquals(0.00, $balancesPorNombre['Carla']['total_pagado']);
        $this->assertEquals(30.00, $balancesPorNombre['Carla']['total_consumido']);
        $this->assertEquals(-30.00, $balancesPorNombre['Carla']['balance']);
    }

    public function test_division_no_exacta_reparte_ajuste_entre_deudores(): void
    {
        $user = User::factory()->create();
        $viaje = Viaje::factory()->for($user, 'user')->create();

        $ana = Participante::factory()->for($viaje)->create(['nombre' => 'Ana', 'user_id' => $user->id]);
        $beto = Participante::factory()->for($viaje)->create(['nombre' => 'Beto']);
        $carla = Participante::factory()->for($viaje)->create(['nombre' => 'Carla']);

        $g = Gasto::factory()->for($viaje)->create([
            'concepto' => 'Gasto indivisible 100 entre 3',
            'monto' => 100.00,
            'fecha' => '2026-09-01',
            'pagador_id' => $ana->id,
        ]);
        $g->participantes()->sync([$ana->id, $beto->id, $carla->id]);

        $balances = $this->service->calcularBalances($viaje);
        $balancesPorNombre = collect($balances)->keyBy('nombre');

        $this->assertEquals(33.00, $balancesPorNombre['Ana']['total_consumido']);
        $this->assertEquals(67.00, $balancesPorNombre['Ana']['balance']);

        $this->assertEquals(33.50, $balancesPorNombre['Beto']['total_consumido']);
        $this->assertEquals(-33.50, $balancesPorNombre['Beto']['balance']);

        $this->assertEquals(33.50, $balancesPorNombre['Carla']['total_consumido']);
        $this->assertEquals(-33.50, $balancesPorNombre['Carla']['balance']);

        $suma = collect($balances)->sum('balance');
        $this->assertEquals(0.00, $suma);
    }

    public function test_gasto_45_35_muestra_original_y_ajuste_entre_empatados(): void
    {
        $user = User::factory()->create();
        $viaje = Viaje::factory()->for($user, 'user')->create();

        $ana = Participante::factory()->for($viaje)->create(['nombre' => 'Ana', 'user_id' => $user->id]);
        $beto = Participante::factory()->for($viaje)->create(['nombre' => 'Beto']);
        $carla = Participante::factory()->for($viaje)->create(['nombre' => 'Carla']);

        $g = Gasto::factory()->for($viaje)->create([
            'concepto' => 'Almuerzo',
            'monto' => 45.35,
            'fecha' => '2026-09-01',
            'pagador_id' => $ana->id,
        ]);
        $g->participantes()->sync([$ana->id, $beto->id, $carla->id]);

        $balances = $this->service->calcularBalances($viaje);
        $balancesPorNombre = collect($balances)->keyBy('nombre');

        $this->assertEquals(45.35, $g->fresh()->monto);
        $this->assertEquals(15.00, $balancesPorNombre['Ana']['total_consumido']);
        $consumosOtros = [$balancesPorNombre['Beto']['total_consumido'], $balancesPorNombre['Carla']['total_consumido']];
        sort($consumosOtros);
        $this->assertEquals([15.00, 15.50], $consumosOtros);
        $this->assertEquals(0.00, collect($balances)->sum('balance'));

        $desglose = $this->service->desgloseEfectivo($g->fresh(['participantes']), $viaje);
        $this->assertTrue($desglose['tiene_ajuste_efectivo']);
        $this->assertEquals(45.35, $desglose['monto_original']);
    }

    public function test_gasto_indivisible_con_centavos_impares(): void
    {
        $user = User::factory()->create();
        $viaje = Viaje::factory()->for($user, 'user')->create();

        $ana = Participante::factory()->for($viaje)->create(['nombre' => 'Ana', 'user_id' => $user->id]);
        $beto = Participante::factory()->for($viaje)->create(['nombre' => 'Beto']);
        $carla = Participante::factory()->for($viaje)->create(['nombre' => 'Carla']);

        $g = Gasto::factory()->for($viaje)->create([
            'concepto' => 'Gasto 10 entre 3 pagado por Beto',
            'monto' => 10.00,
            'fecha' => '2026-09-01',
            'pagador_id' => $beto->id,
        ]);
        $g->participantes()->sync([$ana->id, $beto->id, $carla->id]);

        $balances = $this->service->calcularBalances($viaje);
        $balancesPorNombre = collect($balances)->keyBy('nombre');

        $this->assertEquals(3.00, $balancesPorNombre['Ana']['total_consumido']);
        $this->assertEquals(3.50, $balancesPorNombre['Beto']['total_consumido']);
        $this->assertEquals(3.50, $balancesPorNombre['Carla']['total_consumido']);

        $suma = collect($balances)->sum('balance');
        $this->assertEquals(0.00, $suma);
    }

    public function test_gasto_minimo_un_centavo(): void
    {
        $user = User::factory()->create();
        $viaje = Viaje::factory()->for($user, 'user')->create();

        $ana = Participante::factory()->for($viaje)->create(['nombre' => 'Ana', 'user_id' => $user->id]);
        $beto = Participante::factory()->for($viaje)->create(['nombre' => 'Beto']);

        $g = Gasto::factory()->for($viaje)->create([
            'concepto' => 'Centavo',
            'monto' => 0.01,
            'fecha' => '2026-09-01',
            'pagador_id' => $ana->id,
        ]);
        $g->participantes()->sync([$ana->id, $beto->id]);

        $balances = $this->service->calcularBalances($viaje);
        $balancesPorNombre = collect($balances)->keyBy('nombre');

        $this->assertEquals(0.00, $balancesPorNombre['Ana']['total_consumido']);
        $this->assertEquals(0.50, $balancesPorNombre['Beto']['total_consumido']);
        $this->assertEquals(0.00, collect($balances)->sum('balance'));
        $this->assertEquals(0.01, $g->fresh()->monto);

        $suma = collect($balances)->sum('balance');
        $this->assertEquals(0.00, $suma);
    }

    public function test_viaje_con_unico_participante(): void
    {
        $user = User::factory()->create();
        $viaje = Viaje::factory()->for($user, 'user')->create();

        $ana = Participante::factory()->for($viaje)->create(['nombre' => 'Ana']);

        $g = Gasto::factory()->for($viaje)->create([
            'concepto' => 'Gasto solo',
            'monto' => 50.00,
            'fecha' => '2026-09-01',
            'pagador_id' => $ana->id,
        ]);
        $g->participantes()->sync([$ana->id]);

        $balances = $this->service->calcularBalances($viaje);

        $this->assertCount(1, $balances);
        $this->assertEquals(50.00, $balances[0]['total_pagado']);
        $this->assertEquals(50.00, $balances[0]['total_consumido']);
        $this->assertEquals(0.00, $balances[0]['balance']);
    }

    public function test_calcular_balances_con_gastos_en_usd_y_usdt_consolidados(): void
    {
        $user = User::factory()->create();
        $viaje = Viaje::factory()->for($user, 'user')->create([
            'tipo_cambio_usd' => 6.9600,
            'tipo_cambio_usdt' => 10.5000,
        ]);

        $ana = Participante::factory()->for($viaje)->create(['nombre' => 'Ana']);
        $beto = Participante::factory()->for($viaje)->create(['nombre' => 'Beto']);

        $allIds = [$ana->id, $beto->id];

        // Ana paga 100 USD (696.00 Bs)
        $g1 = Gasto::factory()->for($viaje)->create([
            'concepto' => 'Alojamiento USD',
            'monto' => 100.00,
            'moneda' => 'USD',
            'tipo_cambio' => 6.9600,
            'fecha' => '2026-09-01',
            'pagador_id' => $ana->id,
        ]);
        $g1->participantes()->sync($allIds);

        // Beto paga 50 USDT (525.00 Bs)
        $g2 = Gasto::factory()->for($viaje)->create([
            'concepto' => 'Actividad USDT',
            'monto' => 50.00,
            'moneda' => 'USDT',
            'tipo_cambio' => 10.5000,
            'fecha' => '2026-09-02',
            'pagador_id' => $beto->id,
        ]);
        $g2->participantes()->sync($allIds);

        // Total consolidado = 696.00 + 525.00 = 1221.00 Bs
        // Consumo por persona = 610.50 Bs
        // Ana: pagó 696.00, consumió 610.50 -> balance +85.50
        // Beto: pagó 525.00, consumió 610.50 -> balance -85.50

        $balances = $this->service->calcularBalances($viaje);
        $balancesPorNombre = collect($balances)->keyBy('nombre');

        $this->assertEquals(696.00, $balancesPorNombre['Ana']['total_pagado']);
        $this->assertEquals(610.50, $balancesPorNombre['Ana']['total_consumido']);
        $this->assertEquals(85.50, $balancesPorNombre['Ana']['balance']);

        $this->assertEquals(525.00, $balancesPorNombre['Beto']['total_pagado']);
        $this->assertEquals(610.50, $balancesPorNombre['Beto']['total_consumido']);
        $this->assertEquals(-85.50, $balancesPorNombre['Beto']['balance']);

        $suma = collect($balances)->sum('balance');
        $this->assertEquals(0.00, $suma);
    }

    public function test_nuevo_participante_no_afecta_gastos_anteriores(): void
    {
        $user = User::factory()->create();
        $viaje = Viaje::factory()->for($user, 'user')->create();

        $ana = Participante::factory()->for($viaje)->create(['nombre' => 'Ana']);
        $beto = Participante::factory()->for($viaje)->create(['nombre' => 'Beto']);

        // Gasto creado cuando solo existían Ana y Beto
        $gasto = Gasto::factory()->for($viaje)->create([
            'concepto' => 'Almuerzo',
            'monto' => 100.00,
            'fecha' => '2026-09-01',
            'pagador_id' => $ana->id,
        ]);
        $gasto->participantes()->sync([$ana->id, $beto->id]);

        // Nuevo participante se une al viaje DESPUÉS del gasto
        $carla = Participante::factory()->for($viaje)->create(['nombre' => 'Carla']);

        // Recalcular balances: Carla NO debe verse afectada por el gasto anterior
        $viaje->unsetRelation('participantes');
        $viaje->unsetRelation('gastos');
        $balances = $this->service->calcularBalances($viaje);
        $balancesPorNombre = collect($balances)->keyBy('nombre');

        // Ana pagó 100, consumió 50 -> balance +50
        $this->assertEquals(100.00, $balancesPorNombre['Ana']['total_pagado']);
        $this->assertEquals(50.00, $balancesPorNombre['Ana']['total_consumido']);
        $this->assertEquals(50.00, $balancesPorNombre['Ana']['balance']);

        // Beto consumió 50 -> balance -50
        $this->assertEquals(0.00, $balancesPorNombre['Beto']['total_pagado']);
        $this->assertEquals(50.00, $balancesPorNombre['Beto']['total_consumido']);
        $this->assertEquals(-50.00, $balancesPorNombre['Beto']['balance']);

        // Carla: 0 en todo (no estaba cuando se creó el gasto)
        $this->assertEquals(0.00, $balancesPorNombre['Carla']['total_pagado']);
        $this->assertEquals(0.00, $balancesPorNombre['Carla']['total_consumido']);
        $this->assertEquals(0.00, $balancesPorNombre['Carla']['balance']);

        $suma = collect($balances)->sum('balance');
        $this->assertEquals(0.00, $suma);
    }

    public function test_gasto_55_40_anfitrion_y_un_participante(): void
    {
        $user = User::factory()->create();
        $viaje = Viaje::factory()->for($user, 'user')->create();
        $ana = Participante::factory()->for($viaje)->create(['nombre' => 'Ana', 'user_id' => $user->id]);
        $beto = Participante::factory()->for($viaje)->create(['nombre' => 'Beto']);

        $g = Gasto::factory()->for($viaje)->create([
            'concepto' => 'Cena',
            'monto' => 55.40,
            'fecha' => '2026-09-01',
            'pagador_id' => $ana->id,
        ]);
        $g->participantes()->sync([$ana->id, $beto->id]);

        $balances = $this->service->calcularBalances($viaje);
        $porNombre = collect($balances)->keyBy('nombre');

        $this->assertEquals(27.50, $porNombre['Ana']['total_consumido']);
        $this->assertEquals(28.00, $porNombre['Beto']['total_consumido']);
        $this->assertEquals(55.40, $g->fresh()->monto);
        $this->assertEquals(0.00, collect($balances)->sum('balance'));

        $desglose = $this->service->desgloseEfectivo($g->fresh(['participantes']), $viaje->load('participantes', 'gastos.participantes'));
        $this->assertEquals(55.40, $desglose['monto_original']);
        $cuotas = collect($desglose['cuotas_efectivo'])->keyBy('nombre');
        $this->assertEquals(27.50, $cuotas['Ana']['cuota_final']);
        $this->assertEquals(28.00, $cuotas['Beto']['cuota_final']);
    }

    public function test_ajuste_va_a_quien_tiene_mayor_deuda_previa(): void
    {
        $user = User::factory()->create();
        $viaje = Viaje::factory()->for($user, 'user')->create();
        $ana = Participante::factory()->for($viaje)->create(['nombre' => 'Ana', 'user_id' => $user->id]);
        $beto = Participante::factory()->for($viaje)->create(['nombre' => 'Beto']);
        $carla = Participante::factory()->for($viaje)->create(['nombre' => 'Carla']);

        $previo = Gasto::factory()->for($viaje)->create([
            'concepto' => 'Taxi',
            'monto' => 100.00,
            'fecha' => '2026-09-01',
            'pagador_id' => $ana->id,
        ]);
        $previo->participantes()->sync([$ana->id, $beto->id]);

        $siguiente = Gasto::factory()->for($viaje)->create([
            'concepto' => 'Snacks',
            'monto' => 11.00,
            'fecha' => '2026-09-02',
            'pagador_id' => $ana->id,
        ]);
        $siguiente->participantes()->sync([$ana->id, $beto->id, $carla->id]);

        $desglose = $this->service->desgloseEfectivo(
            $siguiente->fresh(['participantes']),
            $viaje->load(['participantes', 'gastos.participantes'])
        );
        $cuotas = collect($desglose['cuotas_efectivo'])->keyBy('nombre');

        $this->assertEquals(3.50, $cuotas['Ana']['cuota_final']);
        $this->assertEquals(4.00, $cuotas['Beto']['cuota_final']);
        $this->assertEquals(3.50, $cuotas['Carla']['cuota_final']);
    }

    public function test_desglose_no_cambia_al_consultar_de_nuevo(): void
    {
        $user = User::factory()->create();
        $viaje = Viaje::factory()->for($user, 'user')->create();
        $ana = Participante::factory()->for($viaje)->create(['nombre' => 'Ana', 'user_id' => $user->id]);
        $beto = Participante::factory()->for($viaje)->create(['nombre' => 'Beto']);
        $carla = Participante::factory()->for($viaje)->create(['nombre' => 'Carla']);

        $g = Gasto::factory()->for($viaje)->create([
            'concepto' => 'Almuerzo',
            'monto' => 11.00,
            'fecha' => '2026-09-01',
            'pagador_id' => $ana->id,
        ]);
        $g->participantes()->sync([$ana->id, $beto->id, $carla->id]);

        $viaje->load(['participantes', 'gastos.participantes']);
        $primero = $this->service->desgloseEfectivo($g->fresh(['participantes']), $viaje);
        $segundo = $this->service->desgloseEfectivo($g->fresh(['participantes']), $viaje);

        $this->assertSame($primero['cuotas_efectivo'], $segundo['cuotas_efectivo']);
        $this->assertEquals(11.00, collect($primero['cuotas_efectivo'])->sum('cuota_final'));
        $this->assertEquals(3.50, collect($primero['cuotas_efectivo'])->firstWhere('nombre', 'Ana')['cuota_final']);
    }
}
