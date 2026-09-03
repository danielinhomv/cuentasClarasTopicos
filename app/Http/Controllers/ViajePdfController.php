<?php

namespace App\Http\Controllers;

use App\Models\Viaje;
use App\Services\ExportarViajePdfService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ViajePdfController extends Controller
{
    public function exportar(Viaje $viaje, ExportarViajePdfService $exportar): Response
    {
        $this->authorize('view', $viaje);

        $reporte = $exportar->armar($viaje);
        $filename = sprintf(
            'cuentas-claras-%s-%s.pdf',
            Str::slug($viaje->nombre),
            now()->timezone(config('app.timezone'))->format('Y-m-d')
        );

        return Pdf::loadView('pdf.viaje', ['reporte' => $reporte])
            ->setPaper('a4')
            ->setOption('isPhpEnabled', true)
            ->download($filename);
    }
}
