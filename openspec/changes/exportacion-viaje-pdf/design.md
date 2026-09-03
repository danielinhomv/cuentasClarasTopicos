## Context

Ver motivación en `proposal.md` y comportamiento en `specs/exportacion-pdf/spec.md`.

Hoy el detalle del viaje (`ViajeController::show`) ya arma `saldos` y `liquidacion` con `CalculoBalanceService`, `AlgoritmoLiquidacionService`, `RegistroLiquidacionService` y `desgloseEfectivo`. No hay librería PDF en `composer.json`. La autorización de lectura es `ViajePolicy::view` (anfitrión o participante con `user_id`). Los saldos no se persisten: se derivan de `viajes`, `participantes`, `gastos`, `gasto_participantes` y `liquidaciones` / `liquidacion_pagos` en PostgreSQL.

## Goals / Non-Goals

**Goals:**
- Un único flujo GET autenticado que descarga un PDF generado al vuelo.
- Reutilizar los servicios de cálculo existentes; el reporte es una proyección, no un segundo motor.
- Plantilla HTML→PDF con secciones fijas, paginación segura y tipografía simple.
- Misma policy que ver el viaje.

**Non-Goals:**
- Cola de jobs, S3 o histórico de exportaciones.
- Envío por WhatsApp/email desde el servidor.
- Cambiar fórmulas de redondeo, exclusiones o liquidación.
- Rediseñar `Viajes/Show.vue` (solo el control de descarga).

## Decisions

### 1. Motor PDF: DomPDF (`barryvdh/laravel-dompdf`)

| Alternativa | Por qué no |
|---|---|
| `spatie/laravel-pdf` + Browsershot | Requiere Chrome en el servidor; frágil en hosting PHP típico. |
| Snappy / wkhtmltopdf | Binario extra, más ops. |
| TCPDF / FPDF a mano | Layout de tablas y saltos de página más costoso. |

DomPDF corre en PHP 8.3, genera A4 desde HTML/CSS limitado, y encaja en Laravel 13. La “vista” Blade `pdf/viaje.blade.php` **no es UI de la app**: es el contrato de render del archivo. La instrucción de diseño “sin Blade/Inertia” aplica a pantallas de consulta; aquí Blade es imprescindible para DomPDF.

### 2. Flujo de generación

```
GET /viajes/{viaje}/exportar-pdf
        │
        ├── Policy: view (auth)
        ├── ExportarViajePdfService::armar(Viaje)
        │     ├── participantes (nombre, es_anfitrion, sin_cuenta)
        │     ├── gastos + desgloseEfectivo (cuotas, ajuste)
        │     ├── CalculoBalanceService::calcularBalances
        │     ├── AlgoritmoLiquidacionService + RegistroLiquidacionService::reconciliar
        │     └── totales (suma de montos consolidados a Bs)
        └── DomPDF → stream/download
```

Capa de cada regla:

| Regla | Capa |
|---|---|
| Quién puede exportar | `ViajePolicy::view` |
| Auth de la ruta | middleware `auth` en `web.php` |
| Cifras (cuotas, redondeo Bs 0,50, invariante Σ=0) | Services existentes (`CalculoBalanceService`, `AjusteEfectivoService`, `RegistroLiquidacionService`) |
| Qué campos van al PDF (sin IDs/emails/bitácora) | `ExportarViajePdfService` (DTO) |
| Layout, páginas, fecha de generación | plantilla PDF + CSS `page-break-inside: avoid` en bloques |
| Control "Exportar a PDF" | un enlace en el detalle existente (`route('viajes.exportar-pdf')`) |

El controlador no recalcula: solo autoriza, pide el DTO y responde el binario.

### 3. Modelo de datos (solo lectura)

```
User 1 ──< Viaje (user_id = anfitrión)
Viaje 1 ──< Participante (user_id nullable)
Viaje 1 ──< Gasto
Gasto belongsTo Participante (pagador)
Gasto belongsToMany Participante (incluidos)
Viaje 1 ──< Liquidacion (deudor / acreedor)
Liquidacion 1 ──< LiquidacionPago

Balance / deuda neta: NO persistidos; se calculan en Service.
```

Persistencia sigue siendo **PostgreSQL**. El PDF no escribe tablas nuevas.

### 4. Datos que se exportan (DTO)

- Viaje: `nombre`, `descripcion` (si hay), `generado_en` (timezone de la app).
- Participantes: `nombre`, flags `anfitrion` / `sin_cuenta`.
- Totales: `total_gastado_bs` (misma consolidación USD/USDT que los saldos).
- Cada gasto: concepto, fecha, pagador (nombre), monto original, moneda, monto en Bs si aplica, nombres de incluidos, `cuotas_efectivo` (nombre, teórica, final, ajuste), `tiene_ajuste_efectivo`.
- Saldos: nombre, total pagado, total consumido, balance (los **brutos** del servicio de balance, y además el saldo **expuesto** tras `aplicarPagosABalances` para “cuánto falta”).
- Liquidaciones: deudor, acreedor, original, pagado, pendiente, estado (pendiente / parcial / liquidada). Abonos: monto y fecha, sin IDs.

Prohibido en el DTO: `id`, `user_id`, email, password, tokens, `codigo_invitacion`, snapshots de bitácora.

### 5. Estructura visual del PDF

1. Encabezado: “Cuentas Claras” + nombre del viaje + fecha de generación.
2. Resumen: total gastado, cantidad de participantes/gastos, suma de pendientes.
3. Participantes.
4. Tabla resumen de gastos (concepto, pagador, monto, fecha).
5. Detalle por gasto (bloque `page-break-inside: avoid`): división y redondeo.
6. Saldos y deudas (balance; leyenda + le deben / debe / al día).
7. Liquidaciones: hechas, parciales (pagado/pendiente) y pendientes.

A4, márgenes ≥ 12 mm, fuente sans-serif 10–11 pt, montos con 2 decimales y prefijo `Bs`. Encabezado/pie con número de página.

### 6. Ruta

```php
Route::middleware(['auth:sanctum', config('jetstream.auth_session')])->group(function () {
    Route::get('/viajes/{viaje}/exportar-pdf', [ViajePdfController::class, 'exportar'])
        ->name('viajes.exportar-pdf');
});
```

Respuesta: `download` con filename `cuentas-claras-{slug-nombre}-{Y-m-d}.pdf`.

### 7. Pruebas (sin abrir un visor)

- Feature: 200 + `application/pdf` + `%PDF` en el cuerpo para Samaipata; 401/403/404.
- Extraer texto (DomPDF o parser) y afirmar totales, nombres y `Diego`/`Ana`/`400`.
- Comparar montos del DTO contra `calcularBalances` / `reconciliar` en el mismo request de test.
- Viaje sin gastos: 200 y total 0.
- Liquidación parcial: aparece pagado/pendiente.
- El texto no contiene `@` de emails ni el código de invitación.

## Risks / Trade-offs

- **[DomPDF CSS limitado]** → Tablas simples, evitar flex/grid moderno; probar saltos con 30+ gastos en test de humo.
- **[PDF “casi igual” pero distinto por redondeo visual]** → El DTO copia floats ya redondeados a 2 decimales de los services; no volver a dividir en la plantilla.
- **[Instrucción “solo backend / sin Inertia”]** → El botón es excepción explícita del usuario; el apply no debe rediseñar el resto de Show.
- **[Viajes enormes / timeout]** → Generación síncrona; si en el futuro hay cientos de gastos, pasar a job. Fuera de este change.

## Migration Plan

1. Añadir `barryvdh/laravel-dompdf` vía Composer (no `migrate`).
2. Service DTO + plantilla PDF + ruta + test.
3. Enlace de descarga en el detalle.
4. Rollback: quitar ruta, control y paquete; no hay migración de esquema.

## Open Questions

Ninguna que bloquee el apply: moneda de totales = Bs consolidados (igual que saldos); participantes sin cuenta se listan por nombre.
