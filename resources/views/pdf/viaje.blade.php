<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Cuentas Claras — {{ $reporte['viaje']['nombre'] }}</title>
    <style>
        @page { margin: 16mm 14mm 18mm 14mm; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10.5pt;
            color: #1a1a1a;
            line-height: 1.35;
        }
        h1 { font-size: 16pt; margin: 0 0 4px; }
        h2 { font-size: 12pt; margin: 18px 0 8px; border-bottom: 1px solid #333; padding-bottom: 3px; }
        h3 { font-size: 11pt; margin: 0 0 6px; }
        .meta { font-size: 9pt; color: #444; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; vertical-align: top; }
        th { background: #f0f0f0; font-size: 9pt; }
        .num { text-align: right; font-family: DejaVu Sans, sans-serif; white-space: nowrap; }
        .gasto { page-break-inside: avoid; margin-bottom: 10px; padding: 8px; border: 1px solid #ddd; }
        .badge { font-size: 8.5pt; color: #333; }
        .muted { color: #555; font-size: 9.5pt; }
        .footer {
            position: fixed;
            bottom: -12mm;
            left: 0;
            right: 0;
            font-size: 8.5pt;
            color: #666;
            border-top: 1px solid #ccc;
            padding-top: 3px;
        }
    </style>
</head>
<body>
    <script type="text/php">
        if (isset($pdf)) {
            $pdf->page_script('
                $font = $fontMetrics->get_font("DejaVu Sans", "normal");
                $pdf->text(500, 820, "Página " . $PAGE_NUM . " de " . $PAGE_COUNT, $font, 8);
            ');
        }
    </script>

    <div class="footer">
        Cuentas Claras · {{ $reporte['viaje']['nombre'] }} · {{ $reporte['viaje']['generado_en'] }}
    </div>

    <h1>Cuentas Claras</h1>
    <div class="meta">
        <strong>{{ $reporte['viaje']['nombre'] }}</strong>
        @if (!empty($reporte['viaje']['descripcion']))
            — {{ $reporte['viaje']['descripcion'] }}
        @endif
        <br>
        Generado el {{ $reporte['viaje']['generado_en'] }}
    </div>

    <h2>Resumen</h2>
    <table>
        <tr>
            <th>Total gastado</th>
            <td class="num">Bs {{ number_format($reporte['resumen']['total_gastado_bs'], 2, '.', '') }}</td>
        </tr>
        <tr>
            <th>Participantes</th>
            <td>{{ $reporte['resumen']['cantidad_participantes'] }}</td>
        </tr>
        <tr>
            <th>Gastos</th>
            <td>{{ $reporte['resumen']['cantidad_gastos'] }}</td>
        </tr>
        <tr>
            <th>Pendiente por liquidar</th>
            <td class="num">Bs {{ number_format($reporte['resumen']['suma_pendientes'], 2, '.', '') }}</td>
        </tr>
    </table>

    <h2>Participantes</h2>
    <table>
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Rol</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($reporte['participantes'] as $participante)
                <tr>
                    <td>{{ $participante['nombre'] }}</td>
                    <td class="badge">
                        @if ($participante['anfitrion'])
                            Anfitrión
                        @elseif ($participante['sin_cuenta'])
                            Sin cuenta
                        @else
                            Participante
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Resumen de gastos</h2>
    @if ($reporte['resumen']['sin_gastos'])
        <p class="muted">En este viaje aún no hay gastos registrados.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Concepto</th>
                    <th>Pagador</th>
                    <th>Fecha</th>
                    <th>Monto</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($reporte['gastos'] as $gasto)
                    <tr>
                        <td>{{ $gasto['concepto'] }}</td>
                        <td>{{ $gasto['pagador'] }}</td>
                        <td>{{ $gasto['fecha'] }}</td>
                        <td class="num">
                            Bs {{ number_format($gasto['monto_original'], 2, '.', '') }}
                            @if (($gasto['moneda'] ?? 'BOB') !== 'BOB')
                                ({{ $gasto['moneda'] }} · Bs {{ number_format($gasto['monto_bs'], 2, '.', '') }})
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Detalle de gastos</h2>
    @if ($reporte['resumen']['sin_gastos'])
        <p class="muted">En este viaje aún no hay gastos para detallar.</p>
    @else
        @foreach ($reporte['gastos'] as $gasto)
            <div class="gasto">
                <h3>{{ $gasto['concepto'] }}</h3>
                <p class="muted">
                    {{ $gasto['fecha'] }} · Pagó {{ $gasto['pagador'] }} ·
                    Bs {{ number_format($gasto['monto_original'], 2, '.', '') }}
                    @if (($gasto['moneda'] ?? 'BOB') !== 'BOB')
                        ({{ $gasto['moneda'] }} · consolidado Bs {{ number_format($gasto['monto_bs'], 2, '.', '') }})
                    @endif
                </p>
                <p class="muted">Incluidos: {{ implode(', ', $gasto['incluidos']) }}</p>
                @if ($gasto['tiene_ajuste_efectivo'])
                    <p class="badge">Redondeo a efectivo Bs 0,50</p>
                @endif
                <table>
                    <thead>
                        <tr>
                            <th>Participante</th>
                            <th>Cuota teórica</th>
                            <th>Cuota final</th>
                            <th>Ajuste</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($gasto['cuotas_efectivo'] as $cuota)
                            <tr>
                                <td>{{ $cuota['nombre'] }}</td>
                                <td class="num">Bs {{ number_format($cuota['cuota_teorica'], 2, '.', '') }}</td>
                                <td class="num">Bs {{ number_format($cuota['cuota_final'], 2, '.', '') }}</td>
                                <td class="num">Bs {{ number_format($cuota['ajuste'], 2, '.', '') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach
    @endif

    <h2>Saldos y deudas</h2>
    <p class="muted">Balance bruto del cálculo y saldo expuesto tras abonos. Leyenda: le deben / debe / al día.</p>
    <table>
        <thead>
            <tr>
                <th>Participante</th>
                <th>Pagado</th>
                <th>Consumido</th>
                <th>Balance</th>
                <th>Saldo expuesto</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($reporte['saldos'] as $saldo)
                @php
                    $expuesto = (float) $saldo['balance_expuesto'];
                    $leyenda = $expuesto > 0 ? 'Le deben' : ($expuesto < 0 ? 'Debe' : 'Al día');
                @endphp
                <tr>
                    <td>{{ $saldo['nombre'] }}</td>
                    <td class="num">Bs {{ number_format($saldo['total_pagado'], 2, '.', '') }}</td>
                    <td class="num">Bs {{ number_format($saldo['total_consumido'], 2, '.', '') }}</td>
                    <td class="num">Bs {{ number_format($saldo['balance'], 2, '.', '') }}</td>
                    <td class="num">Bs {{ number_format($saldo['balance_expuesto'], 2, '.', '') }}</td>
                    <td>{{ $leyenda }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Liquidaciones</h2>
    @if (count($reporte['liquidaciones']) === 0)
        <p class="muted">No hay transferencias pendientes ni registradas.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Deudor</th>
                    <th>Acreedor</th>
                    <th>Original</th>
                    <th>Pagado</th>
                    <th>Pendiente</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($reporte['liquidaciones'] as $liq)
                    <tr>
                        <td>{{ $liq['deudor'] }}</td>
                        <td>{{ $liq['acreedor'] }}</td>
                        <td class="num">Bs {{ number_format($liq['monto_original'], 2, '.', '') }}</td>
                        <td class="num">Bs {{ number_format($liq['monto_pagado'], 2, '.', '') }}</td>
                        <td class="num">Bs {{ number_format($liq['monto_pendiente'], 2, '.', '') }}</td>
                        <td>{{ $liq['estado'] }}</td>
                    </tr>
                    @if (!empty($liq['abonos']))
                        <tr>
                            <td colspan="6" class="muted">
                                Abonos:
                                @foreach ($liq['abonos'] as $abono)
                                    Bs {{ number_format($abono['monto'], 2, '.', '') }} ({{ $abono['fecha'] }})@if (!$loop->last), @endif
                                @endforeach
                            </td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
