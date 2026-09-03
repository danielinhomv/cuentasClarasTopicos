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

    public function test_gasto_45_35_entre_tres_deudores_empatados_no_recarga_al_pagador(): void
    {
        $ana = 1;
        $beto = 2;
        $carla = 3;

        $cuotas = $this->service->repartir(4535, [$ana, $beto, $carla], $ana);

        $this->assertSame(1535, $cuotas[$ana]);
        $this->assertSame(1500, $cuotas[$beto]);
        $this->assertSame(1500, $cuotas[$carla]);
        $this->assertSame(4535, array_sum($cuotas));
    }

    public function test_cien_entre_tres_reparte_ajuste_entre_deudores_empatados(): void
    {
        $ana = 1;
        $beto = 2;
        $carla = 3;

        $cuotas = $this->service->repartir(10000, [$ana, $beto, $carla], $ana);

        $this->assertSame(3300, $cuotas[$ana]);
        $this->assertSame(3350, $cuotas[$beto]);
        $this->assertSame(3350, $cuotas[$carla]);
        $this->assertSame(10000, array_sum($cuotas));
    }

    public function test_un_deudor_recibe_primero_la_unidad_extra_de_cincuenta_centavos(): void
    {
        $ana = 1;
        $beto = 2;
        $carla = 3;

        $cuotas = $this->service->repartir(1100, [$ana, $beto, $carla], $ana);

        $this->assertSame(350, $cuotas[$ana]);
        $this->assertSame(400, $cuotas[$beto]);
        $this->assertSame(350, $cuotas[$carla]);
        $this->assertSame(1100, array_sum($cuotas));
    }

    public function test_un_solo_participante_conserva_el_monto_real(): void
    {
        $cuotas = $this->service->repartir(4535, [7], 7);

        $this->assertSame([7 => 4535], $cuotas);
    }

    public function test_dos_participantes_deudor_queda_en_multiplo_de_cincuenta(): void
    {
        $ana = 10;
        $beto = 20;

        $cuotas = $this->service->repartir(4535, [$ana, $beto], $ana);

        $this->assertSame(2285, $cuotas[$ana]);
        $this->assertSame(2250, $cuotas[$beto]);
        $this->assertSame(4535, array_sum($cuotas));
        $this->assertSame(0, $cuotas[$beto] % 50);
    }

    public function test_reparto_es_deterministico_en_empate(): void
    {
        $primero = $this->service->repartir(10000, [1, 2, 3], 1);
        $segundo = $this->service->repartir(10000, [1, 2, 3], 1);

        $this->assertSame($primero, $segundo);
    }
}
