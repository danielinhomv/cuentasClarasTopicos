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

        // 1. Cabaña 800 (Ana)
        Gasto::factory()->for($viaje)->create([
            'concepto' => 'Cabaña',
            'monto' => 800.00,
            'fecha' => '2026-09-01',
            'pagador_id' => $ana->id,
        ]);
        // 2. Entradas El Fuerte 160 (Ana)
        Gasto::factory()->for($viaje)->create([
            'concepto' => 'Entradas a El Fuerte',
            'monto' => 160.00,
            'fecha' => '2026-09-02',
            'pagador_id' => $ana->id,
        ]);
        // 3. Cena 400 (Beto)
        Gasto::factory()->for($viaje)->create([
            'concepto' => 'Cena',
            'monto' => 400.00,
            'fecha' => '2026-09-02',
            'pagador_id' => $beto->id,
        ]);
        // 4. Gasolina 240 (Carla)
        Gasto::factory()->for($viaje)->create([
            'concepto' => 'Gasolina',
            'monto' => 240.00,
            'fecha' => '2026-09-03',
            'pagador_id' => $carla->id,
        ]);

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

    public function test_division_no_exacta_con_absorcion_de_centavos_por_pagador(): void
    {
        $user = User::factory()->create();
        $viaje = Viaje::factory()->for($user, 'user')->create();

        $ana = Participante::factory()->for($viaje)->create(['nombre' => 'Ana']);
        $beto = Participante::factory()->for($viaje)->create(['nombre' => 'Beto']);
        $carla = Participante::factory()->for($viaje)->create(['nombre' => 'Carla']);

        Gasto::factory()->for($viaje)->create([
            'concepto' => 'Gasto indivisible 100 entre 3',
            'monto' => 100.00,
            'fecha' => '2026-09-01',
            'pagador_id' => $ana->id,
        ]);

        $balances = $this->service->calcularBalances($viaje);
        $balancesPorNombre = collect($balances)->keyBy('nombre');

        $this->assertEquals(33.34, $balancesPorNombre['Ana']['total_consumido']);
        $this->assertEquals(66.66, $balancesPorNombre['Ana']['balance']);

        $this->assertEquals(33.33, $balancesPorNombre['Beto']['total_consumido']);
        $this->assertEquals(-33.33, $balancesPorNombre['Beto']['balance']);

        $this->assertEquals(33.33, $balancesPorNombre['Carla']['total_consumido']);
        $this->assertEquals(-33.33, $balancesPorNombre['Carla']['balance']);

        $suma = collect($balances)->sum('balance');
        $this->assertEquals(0.00, $suma);
    }

    public function test_gasto_indivisible_con_centavos_impares(): void
    {
        $user = User::factory()->create();
        $viaje = Viaje::factory()->for($user, 'user')->create();

        $ana = Participante::factory()->for($viaje)->create(['nombre' => 'Ana']);
        $beto = Participante::factory()->for($viaje)->create(['nombre' => 'Beto']);
        $carla = Participante::factory()->for($viaje)->create(['nombre' => 'Carla']);

        Gasto::factory()->for($viaje)->create([
            'concepto' => 'Gasto 10 entre 3 pagado por Beto',
            'monto' => 10.00,
            'fecha' => '2026-09-01',
            'pagador_id' => $beto->id,
        ]);

        $balances = $this->service->calcularBalances($viaje);
        $balancesPorNombre = collect($balances)->keyBy('nombre');

        $this->assertEquals(3.34, $balancesPorNombre['Beto']['total_consumido']);
        $this->assertEquals(3.33, $balancesPorNombre['Ana']['total_consumido']);
        $this->assertEquals(3.33, $balancesPorNombre['Carla']['total_consumido']);

        $suma = collect($balances)->sum('balance');
        $this->assertEquals(0.00, $suma);
    }

    public function test_gasto_minimo_un_centavo(): void
    {
        $user = User::factory()->create();
        $viaje = Viaje::factory()->for($user, 'user')->create();

        $ana = Participante::factory()->for($viaje)->create(['nombre' => 'Ana']);
        $beto = Participante::factory()->for($viaje)->create(['nombre' => 'Beto']);

        Gasto::factory()->for($viaje)->create([
            'concepto' => 'Centavo',
            'monto' => 0.01,
            'fecha' => '2026-09-01',
            'pagador_id' => $ana->id,
        ]);

        $balances = $this->service->calcularBalances($viaje);
        $balancesPorNombre = collect($balances)->keyBy('nombre');

        $this->assertEquals(0.01, $balancesPorNombre['Ana']['total_consumido']);
        $this->assertEquals(0.00, $balancesPorNombre['Ana']['balance']);
        $this->assertEquals(0.00, $balancesPorNombre['Beto']['total_consumido']);
        $this->assertEquals(0.00, $balancesPorNombre['Beto']['balance']);

        $suma = collect($balances)->sum('balance');
        $this->assertEquals(0.00, $suma);
    }

    public function test_viaje_con_unico_participante(): void
    {
        $user = User::factory()->create();
        $viaje = Viaje::factory()->for($user, 'user')->create();

        $ana = Participante::factory()->for($viaje)->create(['nombre' => 'Ana']);

        Gasto::factory()->for($viaje)->create([
            'concepto' => 'Gasto solo',
            'monto' => 50.00,
            'fecha' => '2026-09-01',
            'pagador_id' => $ana->id,
        ]);

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

        // Ana paga 100 USD (696.00 Bs)
        Gasto::factory()->for($viaje)->create([
            'concepto' => 'Alojamiento USD',
            'monto' => 100.00,
            'moneda' => 'USD',
            'tipo_cambio' => 6.9600,
            'fecha' => '2026-09-01',
            'pagador_id' => $ana->id,
        ]);

        // Beto paga 50 USDT (525.00 Bs)
        Gasto::factory()->for($viaje)->create([
            'concepto' => 'Actividad USDT',
            'monto' => 50.00,
            'moneda' => 'USDT',
            'tipo_cambio' => 10.5000,
            'fecha' => '2026-09-02',
            'pagador_id' => $beto->id,
        ]);

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
}
