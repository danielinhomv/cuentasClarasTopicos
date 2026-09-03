<?php

namespace Tests\Feature;

use App\Models\Gasto;
use App\Models\GastoBitacora;
use App\Models\Participante;
use App\Models\User;
use App\Models\Viaje;
use App\Services\CalculoBalanceService;
use App\Services\ExportarViajePdfService;
use App\Services\RegistroLiquidacionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportacionViajePdfTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Mismo escenario de Samaipata que LiquidacionTest (no el seeder de DatabaseSeeder).
     */
    private function crearEscenarioSamaipata(User $user, array $viajeAttrs = []): Viaje
    {
        $viaje = Viaje::factory()->for($user, 'user')->create(array_merge([
            'nombre' => 'Viaje a Samaipata',
        ], $viajeAttrs));

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

    public function test_anfitrion_exporta_pdf_de_samaipata(): void
    {
        Carbon::setTestNow('2026-09-03 18:30:00');

        $user = User::factory()->create();
        $viaje = $this->crearEscenarioSamaipata($user);

        $response = $this->actingAs($user)->get(route('viajes.exportar-pdf', $viaje));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertStringContainsString(
            'cuentas-claras-viaje-a-samaipata-2026-09-03.pdf',
            (string) $response->headers->get('content-disposition')
        );

        $html = $this->htmlDelReporte($viaje);
        $this->assertStringContainsString('Ana', $html);
        $this->assertStringContainsString('Beto', $html);
        $this->assertStringContainsString('Carla', $html);
        $this->assertStringContainsString('Diego', $html);
        $this->assertStringContainsString('1600.00', $html);
        $this->assertStringContainsString('400.00', $html);
        $this->assertStringContainsString('160.00', $html);
        $this->assertStringContainsString('Resumen', $html);
        $this->assertStringContainsString('Participantes', $html);
        $this->assertStringContainsString('Resumen de gastos', $html);
        $this->assertStringContainsString('Detalle', $html);
        $this->assertStringContainsString('Saldos', $html);
        $this->assertStringContainsString('Liquidaciones', $html);
    }

    public function test_participante_con_cuenta_puede_exportar(): void
    {
        $anfitrion = User::factory()->create();
        $betoUser = User::factory()->create();
        $viaje = Viaje::factory()->for($anfitrion, 'user')->create(['nombre' => 'Viaje a Samaipata']);
        Participante::factory()->for($viaje)->create(['nombre' => 'Ana', 'user_id' => $anfitrion->id]);
        $beto = Participante::factory()->for($viaje)->create(['nombre' => 'Beto', 'user_id' => $betoUser->id]);

        $gasto = Gasto::factory()->for($viaje)->create([
            'concepto' => 'Cena',
            'monto' => 40.00,
            'fecha' => '2026-09-01',
            'pagador_id' => $beto->id,
        ]);
        $gasto->participantes()->sync($viaje->participantes()->pluck('id')->all());

        $this->actingAs($betoUser)
            ->get(route('viajes.exportar-pdf', $viaje))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_invitado_no_autenticado_es_denegado(): void
    {
        $user = User::factory()->create();
        $viaje = Viaje::factory()->for($user, 'user')->create();

        $this->getJson(route('viajes.exportar-pdf', $viaje))
            ->assertUnauthorized();
    }

    public function test_usuario_ajeno_recibe_403(): void
    {
        $owner = User::factory()->create();
        $otro = User::factory()->create();
        $viaje = $this->crearEscenarioSamaipata($owner);

        $this->actingAs($otro)
            ->get(route('viajes.exportar-pdf', $viaje))
            ->assertForbidden();
    }

    public function test_viaje_inexistente_responde_404(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('viajes.exportar-pdf', 999999))
            ->assertNotFound();
    }

    public function test_viaje_sin_gastos_exporta_total_cero(): void
    {
        $user = User::factory()->create();
        $viaje = Viaje::factory()->for($user, 'user')->create(['nombre' => 'Vacío']);
        Participante::factory()->for($viaje)->create(['nombre' => 'Ana']);

        $response = $this->actingAs($user)->get(route('viajes.exportar-pdf', $viaje));

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());

        $html = $this->htmlDelReporte($viaje);
        $this->assertStringContainsString('0.00', $html);
        $this->assertStringContainsString('aún no hay gastos', $html);
        $this->assertStringContainsString('Ana', $html);
    }

    public function test_liquidacion_parcial_aparece_en_el_pdf(): void
    {
        $user = User::factory()->create();
        $viaje = Viaje::factory()->for($user, 'user')->create();
        $ana = Participante::factory()->for($viaje)->create(['nombre' => 'Ana']);
        $diego = Participante::factory()->for($viaje)->create(['nombre' => 'Diego']);

        $gasto = Gasto::factory()->for($viaje)->create([
            'concepto' => 'Cena',
            'monto' => 80.00,
            'fecha' => '2026-09-01',
            'pagador_id' => $ana->id,
        ]);
        $gasto->participantes()->sync([$ana->id, $diego->id]);

        $deuda = collect($this->actingAs($user)->getJson(route('viajes.liquidacion', $viaje))->json())
            ->firstWhere('deudor', 'Diego');

        $this->actingAs($user)
            ->postJson(route('liquidaciones.pagos.store', $deuda['id']), ['monto' => 20.00])
            ->assertCreated();

        $html = $this->htmlDelReporte($viaje->fresh());
        $this->assertStringContainsString('parcial', $html);
        $this->assertStringContainsString('40.00', $html);
        $this->assertStringContainsString('20.00', $html);

        $reporte = app(ExportarViajePdfService::class)->armar($viaje->fresh());
        $liq = collect($reporte['liquidaciones'])->firstWhere('deudor', 'Diego');
        $this->assertSame('parcial', $liq['estado']);
        $this->assertEquals(40.00, $liq['monto_original']);
        $this->assertEquals(20.00, $liq['monto_pagado']);
        $this->assertEquals(20.00, $liq['monto_pendiente']);
        $this->assertEquals(20.00, $liq['abonos'][0]['monto']);
    }

    public function test_pdf_no_incluye_emails_ni_codigo_de_invitacion_ni_bitacora(): void
    {
        $user = User::factory()->create(['email' => 'ana.host@example.test']);
        $viaje = $this->crearEscenarioSamaipata($user, ['codigo_invitacion' => 'SAMAIPDF']);

        GastoBitacora::query()->create([
            'viaje_id' => $viaje->id,
            'gasto_id' => $viaje->gastos()->first()->id,
            'user_id' => $user->id,
            'actor_nombre' => 'Ana',
            'accion' => 'crear',
            'gasto_concepto' => 'MARCA-BITACORA-SECRETA',
            'datos_antes' => null,
            'datos_despues' => ['concepto' => 'MARCA-BITACORA-SECRETA'],
        ]);

        $this->actingAs($user)->get(route('viajes.exportar-pdf', $viaje))->assertOk();

        $html = $this->htmlDelReporte($viaje);
        $visible = preg_replace('/<(style|script)\b[^>]*>.*?<\/\1>/si', '', $html) ?? $html;
        $this->assertStringNotContainsString('ana.host@example.test', $visible);
        $this->assertStringNotContainsString('SAMAIPDF', $visible);
        $this->assertStringNotContainsString('MARCA-BITACORA-SECRETA', $visible);
        $this->assertStringNotContainsString('@', $visible);
    }

    public function test_montos_del_pdf_coinciden_con_los_servicios(): void
    {
        $user = User::factory()->create();
        $viaje = $this->crearEscenarioSamaipata($user);

        $reporte = app(ExportarViajePdfService::class)->armar($viaje->fresh());
        $balances = app(CalculoBalanceService::class)->calcularBalances(
            $viaje->fresh(['participantes', 'gastos.participantes'])
        );
        $saldos = app(RegistroLiquidacionService::class)->aplicarPagosABalances($viaje->fresh(), $balances);

        $this->assertEquals(
            collect($balances)->keyBy('nombre')->map(fn ($s) => $s['balance'])->all(),
            collect($reporte['saldos'])->keyBy('nombre')->map(fn ($s) => $s['balance'])->all()
        );
        $this->assertEquals(
            collect($saldos)->keyBy('nombre')->map(fn ($s) => $s['balance'])->all(),
            collect($reporte['saldos'])->keyBy('nombre')->map(fn ($s) => $s['balance_expuesto'])->all()
        );
        $this->assertEquals(0.00, round(collect($reporte['saldos'])->sum('balance'), 2));
    }

    public function test_viaje_con_muchos_gastos_genera_varias_paginas(): void
    {
        $user = User::factory()->create();
        $viaje = Viaje::factory()->for($user, 'user')->create(['nombre' => 'Largo']);
        $ana = Participante::factory()->for($viaje)->create(['nombre' => 'Ana', 'user_id' => $user->id]);
        $beto = Participante::factory()->for($viaje)->create(['nombre' => 'Beto']);

        for ($i = 1; $i <= 22; $i++) {
            $gasto = Gasto::factory()->for($viaje)->create([
                'concepto' => 'Gasto número '.$i,
                'monto' => 10.00,
                'fecha' => sprintf('2026-09-%02d', min($i, 28)),
                'pagador_id' => $ana->id,
            ]);
            $gasto->participantes()->sync([$ana->id, $beto->id]);
        }

        $this->actingAs($user)->get(route('viajes.exportar-pdf', $viaje))->assertOk();

        $reporte = app(ExportarViajePdfService::class)->armar($viaje->fresh());
        $pdf = Pdf::loadView('pdf.viaje', ['reporte' => $reporte])->setPaper('a4');
        $dompdf = $pdf->getDomPDF();
        $dompdf->render();

        $this->assertGreaterThan(1, $dompdf->getCanvas()->get_page_count());
    }

    public function test_detalle_del_viaje_ofrece_exportar_a_pdf(): void
    {
        $vista = file_get_contents(resource_path('js/Pages/Viajes/Show.vue'));

        $this->assertIsString($vista);
        $this->assertStringContainsString('Exportar a PDF', $vista);
        $this->assertStringContainsString("route('viajes.exportar-pdf'", $vista);
        $this->assertStringNotContainsString('<Link :href="route(\'viajes.exportar-pdf\'', $vista);
    }

    private function htmlDelReporte(Viaje $viaje): string
    {
        $reporte = app(ExportarViajePdfService::class)->armar($viaje->fresh());

        return view('pdf.viaje', ['reporte' => $reporte])->render();
    }
}
