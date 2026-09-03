## Why

El valor central y objetivo principal de Cuentas Claras radica en resolver matemáticamente la división de gastos de un viaje y determinar cómo saldar las cuentas entre amigos mediante el menor número de transferencias posibles. Sin esta capa de servicios de cálculo y liquidación, la aplicación solo sería un registro pasivo de gastos sin capacidad de balancear deudas.

## What Changes

- Creación del servicio de dominio `App\Services\CalculoBalanceService` encargado de calcular el balance neto de cada participante (Monto total pagado menos cuota parte de los gastos en los que participa).
- Implementación del **ajuste automático a efectivo boliviano** (múltiplos de Bs 0,50): el anfitrión ingresa siempre el monto real; el sistema no permite cargar un recargo manual. Las cuotas de quienes deben se adaptan al efectivo disponible con el menor ajuste, sin asignar el sobrante al pagador/anfitrión cuando hay otros deudores. Se muestra el monto original y el ajuste aplicado.
- Validación de la invariante matemática obligatoria: la suma de todos los balances netos de los participantes de un viaje MUST ser SIEMPRE exactamente igual a 0 (`sum(balances) == 0.00`).
- Creación del servicio de dominio `App\Services\AlgoritmoLiquidacionService` que implementa un algoritmo voraz (*greedy matching*) para generar la lista óptima de transferencias (*quién le transfiere a quién y cuánto*) minimizando el número de transacciones.
- Creación de `LiquidacionController` con endpoints estructurados para consultar:
  - `GET /viajes/{viaje}/saldos`: Resumen detallado de balances por participante (total pagado, total consumido, saldo neto, descontando pagos de liquidación).
  - `GET /viajes/{viaje}/liquidacion`: Plan optimizado de transferencias de liquidación, enriquecido con monto original, pagado y pendiente.
- **Liquidaciones parciales:** persistencia de deudas por par `deudor-acreedor` y registro de uno o más abonos sobre cada deuda, sin romper la liquidación completa existente.
  - Un pago puede cubrir el total o solo una parte del pendiente.
  - No se permite pagar más de lo que se debe.
  - Cuando el pendiente llega a `0.00`, la deuda se marca como liquidada.
- **Recálculo al cambiar gastos:** al crear, editar o eliminar un gasto, los saldos se recalculan desde los gastos vigentes y las deudas persistidas se sincronizan con el nuevo plan: se actualiza el pendiente, se elimina el par si ya no existe y no hubo pagos, y se conservan los abonos del par si la deuda solo disminuye.
- Verificación formal contra el escenario de referencia de **Samaipata**:
  - Ana: Pagó Bs. 960 (Cabaña 800 + Entradas 160) $\rightarrow$ Consumo: Bs. 400 $\rightarrow$ Balance: **+Bs. 560.00**
  - Beto: Pagó Bs. 400 (Cena 400) $\rightarrow$ Consumo: Bs. 400 $\rightarrow$ Balance: **Bs. 0.00**
  - Carla: Pagó Bs. 240 (Gasolina 240) $\rightarrow$ Consumo: Bs. 400 $\rightarrow$ Balance: **-Bs. 160.00**
  - Diego: Pagó Bs. 0 $\rightarrow$ Consumo: Bs. 400 $\rightarrow$ Balance: **-Bs. 400.00**
  - Transferencias esperadas: **Diego $\rightarrow$ Ana: Bs. 400.00** y **Carla $\rightarrow$ Ana: Bs. 160.00**.
- Suite exhaustiva de pruebas unitarias y de feature que verifican la precisión decimal, la invariante de balance y casos borde de división.

### Casos de uso del backend que cubre

Esta propuesta cubre explícitamente:
- **Caso de uso 4: Calcular saldos** (cálculo de balances netos por participante).
- **Caso de uso 5: Calcular liquidación** (algoritmo de transferencias mínimas).
- **Caso de uso 6: Validar consistencia de cálculos** (invariante de balance $\sum \text{balances} = 0$, manejo de centavos indivisibles y casos borde).

### Fuera de alcance

- **Vistas / frontend Inertia / Blade / componentes Vue**: este cambio es **SOLO BACKEND**; no se generan artefactos visuales.
- **Pasarelas de pago externas / transferencias bancarias automatizadas**: la app registra abonos informativos sobre deudas calculadas; no ejecuta transferencias bancarias.
- **Recálculo automático del emparejamiento óptimo redistribuyendo pagos históricos hacia otros pares**: los abonos siguen anclados al par `deudor-acreedor`; lo que sí se recalcula es el `monto_original` / pendiente de ese par cuando cambian los gastos.

## Capabilities

### New Capabilities

- `saldos`: Cálculo de balances individuales por participante, cuota parte por gasto y total consumido/pagado.
- `liquidacion`: Algoritmo de liquidación mínima de deudas, persistencia de deudas por par y registro de pagos parciales o completos.
- `consistencia`: Garantía de la invariante matemática $\sum = 0$, ajuste a efectivo boliviano (Bs 0,50) sin recargo manual del anfitrión, y robustez ante casos borde.

### Modified Capabilities

- `liquidacion`: al mutar gastos, `reconciliar` sincroniza las deudas persistidas con el plan vigente (actualiza, cierra o elimina pares obsoletos) sin perder el historial de pagos del par.

## Impact

- **Capa de Servicios:** `app/Services/CalculoBalanceService.php`, `app/Services/AjusteEfectivoService.php`, `app/Services/AlgoritmoLiquidacionService.php` y `app/Services/RegistroLiquidacionService.php`.
- **Persistencia:** tablas `liquidaciones` y `liquidacion_pagos`.
- **Controladores y Rutas:** `app/Http/Controllers/LiquidacionController.php` y rutas protegidas en `routes/web.php`.
- **Tests:** pruebas unitarias de algoritmos en `tests/Unit/Services/` y pruebas de integración en `tests/Feature/LiquidacionTest.php`.
