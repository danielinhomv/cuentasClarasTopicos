## Context

Ver motivación en `proposal.md` y especificaciones en `specs/gestion-tipo-cambio/spec.md` y `specs/multidivisa-gastos/spec.md`.

Este documento detalla la arquitectura técnica para soportar múltiples divisas (`BOB`, `USD`, `USDT`) en el registro de gastos, la administración manual de las cotizaciones por parte del creador del viaje, y la consolidación aritmética exacta en la divisa base para saldos y liquidaciones.

## Goals / Non-Goals

**Goals:**
- Agregar a la tabla `viajes` las columnas `tipo_cambio_usd` (decimal 10,4, defecto: `6.9600`) y `tipo_cambio_usdt` (decimal 10,4, defecto: `10.5000`).
- Agregar a la tabla `gastos` la columna `moneda` (string, enum: `'BOB'`, `'USD'`, `'USDT'`, defecto: `'BOB'`) y `tipo_cambio` (decimal 10,4 nullable).
- Exponer endpoint `PUT /viajes/{viaje}/tipo-cambio` (`viajes.tipo-cambio.update`) restringido por `ViajePolicy::update` exclusivamente al creador del viaje.
- Adaptar `CalculoBalanceService` para que cada gasto se convierta a la moneda base:
  $$\text{montoConsolidado} = \text{round}(\text{monto} \times \text{tasa}, 2)$$
  $$\text{montoCentavos} = \text{int}(\text{round}(\text{montoConsolidado} \times 100))$$
  manteniendo la asignación exacta de residuo al pagador o beneficiario y garantizando el invariante $\sum \text{balances} = 0$.
- Diseñar en `Viajes/Show.vue` la interfaz de selección de divisa en el formulario de gasto, la card de cotizaciones vigentes del viaje con modal de ajuste para el creador, y el desglose de importes originales y consolidados.

**Non-Goals:**
- Liquidaciones separadas por cada moneda independiente (se consolida todo en la moneda base para simplificar transferencias cruzadas en un único plan óptimo de pagos).
- Consumo de APIs bancarias externas en vivo (se gestiona 100% de forma manual por el creador).

## Decisions

### 1. Columnas y Valores por Defecto
En migración:
- `viajes`:
  - `tipo_cambio_usd`: `decimal('tipo_cambio_usd', 10, 4)->default(6.9600)`
  - `tipo_cambio_usdt`: `decimal('tipo_cambio_usdt', 10, 4)->default(10.5000)`
- `gastos`:
  - `moneda`: `string('moneda', 5)->default('BOB')`
  - `tipo_cambio`: `decimal('tipo_cambio', 10, 4)->nullable()`

### 2. Algoritmo de Consolidación en `CalculoBalanceService`
```php
foreach ($gastos as $gasto) {
    $tasa = 1.0;
    if ($gasto->moneda === 'USD') {
        $tasa = (float) ($gasto->tipo_cambio ?: ($viaje->tipo_cambio_usd ?: 6.96));
    } elseif ($gasto->moneda === 'USDT') {
        $tasa = (float) ($gasto->tipo_cambio ?: ($viaje->tipo_cambio_usdt ?: 10.50));
    }

    $montoConsolidado = round(((float) $gasto->monto) * $tasa, 2);
    $montoCentavos = (int) round($montoConsolidado * 100);

    // Suma al pagador en centavos consolidados
    if (isset($pagadoCentavos[$gasto->pagador_id])) {
        $pagadoCentavos[$gasto->pagador_id] += $montoCentavos;
    }

    // Reparto equitativo entre beneficiarios y absorción del residuo
    $cuotaBase = intdiv($montoCentavos, $k);
    $residuo = $montoCentavos % $k;
    // ...
}
```
**Garantía matemática:** Al calcular la cuota base y el residuo sobre `$montoCentavos` consolidado, se cumple siempre:
$$k \cdot \text{cuotaBase} + \text{residuo} = \text{montoCentavos}$$
Por ende:
$$\sum_{i} \text{pagado}_i = \sum_{j} \text{consumo}_j \implies \sum \text{balance}_i = 0$$

### 3. Permisos de Modificación de Tipo de Cambio
En `ViajeController::actualizarTipoCambio`:
```php
public function actualizarTipoCambio(Request $request, Viaje $viaje): RedirectResponse
{
    $this->authorize('update', $viaje);

    $validated = $request->validate([
        'tipo_cambio_usd' => ['required', 'numeric', 'min:0.0001', 'max:999.9999'],
        'tipo_cambio_usdt' => ['required', 'numeric', 'min:0.0001', 'max:999.9999'],
    ]);

    $viaje->update($validated);

    return back()->with('flash.banner', 'Tipos de cambio actualizados correctamente.');
}
```
Solo el creador del viaje (`user_id === auth()->id()`) tiene autorización para ejecutar esta acción (`ViajePolicy::update`).

### 4. UI y Experiencia de Usuario
- Selector interactivo de moneda en el modal de gasto: botones tipo pestaña `BOB (Bs)`, `USD ($)`, `USDT (₮)` con colores distintivos (Esmeralda para BOB, Cian para USD, Violeta para USDT).
- Visualización de conversión estimada en tiempo real debajo del campo monto.
- En la tabla de gastos: etiqueta de la divisa utilizada y monto consolidado entre paréntesis si difiere de BOB.
- En la cabecera: widget con las cotizaciones `1 USD = X Bs` y `1 USDT = Y Bs`, con botón editable solo si el usuario autenticado es el creador.

## Risks / Trade-offs

- **[Variación retrospectiva si se cambia el tipo de cambio del viaje]** $\rightarrow$ Si el creador ajusta la cotización, los saldos se recalculan de inmediato con la nueva tasa acordada, garantizando transparencia para todo el grupo.
