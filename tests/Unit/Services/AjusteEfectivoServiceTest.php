<?php

namespace Tests\Unit\Services;

use App\Services\AjusteEfectivoService;
use PHPUnit\Framework\TestCase;

class AjusteEfectivoServiceTest extends TestCase
{
    private AjusteEfectivoService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AjusteEfectivoService();
    }

    public function test_anfitrion_y_un_participante_en_55_40(): void
    {
        $cuotas = $this->service->repartir(5540, [1, 2], 1, [], 10);

        $this->assertSame(2750, $cuotas[1]);
        $this->assertSame(2800, $cuotas[2]);
        $this->assertTrue($this->todasMultiploDeCincuenta($cuotas));
    }

    public function test_anfitrion_y_varios_con_teorica_27_60(): void
    {
        $cuotas = $this->service->repartir(8280, [1, 2, 3], 1, [], 10);

        $this->assertSame(2750, $cuotas[1]);
        $this->assertContains(2800, [$cuotas[2], $cuotas[3]]);
        $this->assertContains(2750, [$cuotas[2], $cuotas[3]]);
        $this->assertSame(8300, array_sum($cuotas));
        $this->assertTrue($this->todasMultiploDeCincuenta($cuotas));
    }

    public function test_27_60_y_27_90_terminan_en_multiplo_de_cincuenta(): void
    {
        $desde2760 = $this->service->repartir(8280, [1, 2, 3], 1, [], 1);
        $desde2790 = $this->service->repartir(8370, [1, 2, 3], 1, [], 1);

        foreach ([$desde2760[2], $desde2760[3], $desde2790[2], $desde2790[3]] as $cuota) {
            $this->assertSame(0, $cuota % 50);
            $this->assertNotSame(2760, $cuota);
            $this->assertNotSame(2790, $cuota);
        }
        $this->assertSame(2750, $desde2760[1]);
        $this->assertSame(2750, $desde2790[1]);
    }

    public function test_anfitrion_siempre_redondea_hacia_abajo_y_nunca_paga_mas(): void
    {
        $cuotas = $this->service->repartir(5540, [1, 2], 1, [
            1 => ['deuda' => 99999, 'desde' => '2020-01-01'],
            2 => ['deuda' => 0, 'desde' => null],
        ], 99);

        $this->assertSame(2750, $cuotas[1]);
        $this->assertLessThanOrEqual(2770, $cuotas[1]);
        $this->assertSame(2800, $cuotas[2]);
    }

    public function test_mayor_deuda_acumulada_recibe_el_ajuste(): void
    {
        $cuotas = $this->service->repartir(1100, [1, 2, 3], 1, [
            2 => ['deuda' => 4000, 'desde' => '2026-09-02'],
            3 => ['deuda' => 1000, 'desde' => '2026-09-01'],
        ], 5);

        $this->assertSame(350, $cuotas[1]);
        $this->assertSame(400, $cuotas[2]);
        $this->assertSame(350, $cuotas[3]);
    }

    public function test_deuda_mas_antigua_desempata(): void
    {
        $cuotas = $this->service->repartir(1100, [1, 2, 3], 1, [
            2 => ['deuda' => 2000, 'desde' => '2026-09-01'],
            3 => ['deuda' => 2000, 'desde' => '2026-09-03'],
        ], 5);

        $this->assertSame(400, $cuotas[2]);
        $this->assertSame(350, $cuotas[3]);
        $this->assertSame(350, $cuotas[1]);
    }

    public function test_sorteo_es_estable_en_empate_completo(): void
    {
        $primero = $this->service->repartir(1100, [1, 2, 3], 1, [], 42);
        $segundo = $this->service->repartir(1100, [1, 2, 3], 1, [], 42);

        $this->assertSame($primero, $segundo);
        $this->assertSame(350, $primero[1]);
        $this->assertSame(1100, array_sum($primero));
        $this->assertNotSame($primero[2], $primero[3]);
    }

    public function test_anfitrion_nunca_es_seleccionado(): void
    {
        $orden = $this->service->ordenarCandidatos(
            [1, 2, 3],
            1,
            [
                1 => ['deuda' => 9000, 'desde' => '2020-01-01'],
                2 => ['deuda' => 0, 'desde' => null],
                3 => ['deuda' => 0, 'desde' => null],
            ],
            1
        );

        $this->assertNotContains(1, $orden);

        $cuotas = $this->service->repartir(1100, [1, 2, 3], 1, [
            1 => ['deuda' => 9000, 'desde' => '2020-01-01'],
        ], 1);

        $this->assertSame(350, $cuotas[1]);
    }

    public function test_cien_entre_tres_anfitriona_no_recibe_ajuste(): void
    {
        $cuotas = $this->service->repartir(10000, [1, 2, 3], 1, [], 1);

        $this->assertSame(3300, $cuotas[1]);
        $this->assertSame(3350, $cuotas[2]);
        $this->assertSame(3350, $cuotas[3]);
        $this->assertSame(10000, array_sum($cuotas));
        $this->assertTrue($this->todasMultiploDeCincuenta($cuotas));
    }

    public function test_un_solo_participante_conserva_el_monto_real(): void
    {
        $cuotas = $this->service->repartir(4535, [7], 7);

        $this->assertSame([7 => 4535], $cuotas);
    }

    /**
     * @param  array<int, int>  $cuotas
     */
    private function todasMultiploDeCincuenta(array $cuotas): bool
    {
        foreach ($cuotas as $cuota) {
            if ($cuota % 50 !== 0) {
                return false;
            }
        }

        return true;
    }
}
