<?php

namespace Tests\Unit\Services;

use App\Services\AlgoritmoLiquidacionService;
use PHPUnit\Framework\TestCase;

class AlgoritmoLiquidacionServiceTest extends TestCase
{
    private AlgoritmoLiquidacionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AlgoritmoLiquidacionService();
    }

    public function test_liquidacion_optima_escenario_samaipata(): void
    {
        $balances = [
            ['participante_id' => 1, 'nombre' => 'Ana', 'balance' => 560.00],
            ['participante_id' => 2, 'nombre' => 'Beto', 'balance' => 0.00],
            ['participante_id' => 3, 'nombre' => 'Carla', 'balance' => -160.00],
            ['participante_id' => 4, 'nombre' => 'Diego', 'balance' => -400.00],
        ];

        $transferencias = $this->service->calcularLiquidacion($balances);

        $this->assertCount(2, $transferencias);

        $this->assertEquals('Diego', $transferencias[0]['deudor']);
        $this->assertEquals('Ana', $transferencias[0]['acreedor']);
        $this->assertEquals(400.00, $transferencias[0]['monto']);

        $this->assertEquals('Carla', $transferencias[1]['deudor']);
        $this->assertEquals('Ana', $transferencias[1]['acreedor']);
        $this->assertEquals(160.00, $transferencias[1]['monto']);
    }

    public function test_liquidacion_con_todos_los_saldos_en_cero(): void
    {
        $balances = [
            ['participante_id' => 1, 'nombre' => 'Ana', 'balance' => 0.00],
            ['participante_id' => 2, 'nombre' => 'Beto', 'balance' => 0.00],
        ];

        $transferencias = $this->service->calcularLiquidacion($balances);

        $this->assertEmpty($transferencias);
    }

    public function test_un_deudor_hacia_multiples_acreedores(): void
    {
        $balances = [
            ['participante_id' => 1, 'nombre' => 'Diego', 'balance' => -300.00],
            ['participante_id' => 2, 'nombre' => 'Ana', 'balance' => 200.00],
            ['participante_id' => 3, 'nombre' => 'Beto', 'balance' => 100.00],
        ];

        $transferencias = $this->service->calcularLiquidacion($balances);

        $this->assertCount(2, $transferencias);
        $this->assertEquals('Diego', $transferencias[0]['deudor']);
        $this->assertEquals('Ana', $transferencias[0]['acreedor']);
        $this->assertEquals(200.00, $transferencias[0]['monto']);

        $this->assertEquals('Diego', $transferencias[1]['deudor']);
        $this->assertEquals('Beto', $transferencias[1]['acreedor']);
        $this->assertEquals(100.00, $transferencias[1]['monto']);
    }

    public function test_multiples_deudores_hacia_un_unico_acreedor(): void
    {
        $balances = [
            ['participante_id' => 1, 'nombre' => 'Ana', 'balance' => 300.00],
            ['participante_id' => 2, 'nombre' => 'Beto', 'balance' => -150.00],
            ['participante_id' => 3, 'nombre' => 'Carla', 'balance' => -150.00],
        ];

        $transferencias = $this->service->calcularLiquidacion($balances);

        $this->assertCount(2, $transferencias);

        $deudores = array_column($transferencias, 'deudor');
        $this->assertContains('Beto', $deudores);
        $this->assertContains('Carla', $deudores);

        foreach ($transferencias as $t) {
            $this->assertEquals('Ana', $t['acreedor']);
            $this->assertEquals(150.00, $t['monto']);
        }
    }

    public function test_deuda_cancelada_de_monto_exacto_prioritario(): void
    {
        $balances = [
            ['participante_id' => 1, 'nombre' => 'Ana', 'balance' => 200.00],
            ['participante_id' => 2, 'nombre' => 'Beto', 'balance' => 50.00],
            ['participante_id' => 3, 'nombre' => 'Carla', 'balance' => -200.00],
            ['participante_id' => 4, 'nombre' => 'Diego', 'balance' => -50.00],
        ];

        $transferencias = $this->service->calcularLiquidacion($balances);

        $this->assertCount(2, $transferencias);

        // Debe emparejar Carla -> Ana 200 y Diego -> Beto 50
        $t1 = $transferencias[0];
        $t2 = $transferencias[1];

        $this->assertEquals('Carla', $t1['deudor']);
        $this->assertEquals('Ana', $t1['acreedor']);
        $this->assertEquals(200.00, $t1['monto']);

        $this->assertEquals('Diego', $t2['deudor']);
        $this->assertEquals('Beto', $t2['acreedor']);
        $this->assertEquals(50.00, $t2['monto']);
    }
}
