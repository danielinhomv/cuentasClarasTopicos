## Purpose

Garantiza la consistencia matemática absoluta de los cálculos en Cuentas Claras, validando la invariante estricta de balance cero y el ajuste automático de cuotas al efectivo boliviano.

## ADDED Requirements

### Requirement: Invariante estricta de suma de balances igual a cero

La suma algebraica de todos los balances de los participantes de un viaje MUST ser SIEMPRE exactamente igual a 0 (`sum(balances) == 0.00`). Si la suma no resulta en cero, el sistema MUST considerarlo un error crítico de cálculo y rechazar el estado inconsistente.

#### Scenario: Verificación de invariante con múltiples gastos y exclusiones
- **DADO** un viaje con cualquier combinación válida de participantes, gastos y exclusiones
- **WHEN** se ejecutan los servicios de cálculo de saldos
- **THEN** la suma matemática `sum(balance_i)` de todos los participantes arroja exactamente `0.00` con precisión de dos decimales

#### Scenario: Invariante en el escenario de Samaipata
- **DADO** los saldos de Samaipata: Ana (`+560.00`), Beto (`0.00`), Carla (`-160.00`) y Diego (`-400.00`)
- **WHEN** se suma algebraicamente `560.00 + 0.00 + (-160.00) + (-400.00)`
- **THEN** el resultado es exactamente `0.00`

### Requirement: Ajuste automático a efectivo boliviano sin recargo manual

El anfitrión MUST ingresar siempre el monto real del gasto; el sistema MUST NOT permitir agregar dinero extra a mano. El monto persistido SHALL permanecer igual al ingresado. Tras calcular la cuota teórica, el sistema SHALL aplicar el menor ajuste automático para que las cuotas de quienes deben se puedan pagar con efectivo boliviano (múltiplos de Bs 0,50; no se usan monedas de Bs 0,20 ni Bs 0,30). El pagador/anfitrión MUST NOT elegir el ajuste ni recibirlo cuando hay deudores. Si un deudor debe más que los demás, recibe primero las unidades extra de Bs 0,50. Si varios deben exactamente lo mismo, el ajuste se reparte entre ellos de forma determinística (por `participante_id`). La suma de cuotas finales MUST igualar el monto original. Gastos enteros ya existentes y liquidaciones parciales persistidas MUST seguir funcionando.

#### Scenario: División no exacta entre 3 (Bs. 100) — el ajuste no va al pagador
- **DADO** un viaje con Ana, Beto y Carla, donde Ana paga `100.00` compartido entre los 3
- **WHEN** el sistema calcula las cuotas
- **THEN** Ana (pagadora) consume `33.00`, Beto y Carla (misma deuda) consumen `33.50` cada uno, la suma es `100.00`, y Ana no absorbe el sobrante

#### Scenario: Gasto real Bs. 45,35 con varios deudores empatados
- **DADO** un gasto de `45.35` pagado por Ana e incluido Ana, Beto y Carla
- **WHEN** se calculan las cuotas
- **THEN** el monto original sigue siendo `45.35`, Beto y Carla pagan `15.00` (efectivo), Ana queda con el residuo `15.35` porque ya desembolsó el monto real, y la suma de cuotas es `45.35`

#### Scenario: Un deudor debe más y recibe primero el ajuste de 0,50
- **DADO** un gasto de `11.00` pagado por Ana entre Ana, Beto y Carla
- **WHEN** se asignan las unidades extra de Bs 0,50
- **THEN** un deudor (el de menor `id` entre Beto y Carla, que empatan) recibe `4.00` y el otro `3.50`; Ana consume `3.50`; suma `11.00`

#### Scenario: Un solo participante
- **DADO** Ana como única incluida en un gasto de `45.35` que ella pagó
- **WHEN** se calcula el saldo
- **THEN** consume `45.35`, balance `0.00` y no hay recargo inventado

#### Scenario: Gasto mínimo de un centavo (Bs. 0.01)
- **DADO** un gasto de `0.01` pagado por Ana entre Ana y Beto
- **WHEN** el sistema procesa la división
- **THEN** Ana consume `0.01`, Beto `0.00`, suma `0.01` y balances en `0.00`

#### Scenario: Liquidación parcial no se rompe
- **DADO** una deuda persistida con un abono parcial
- **WHEN** se recalculan saldos y el plan de liquidación
- **THEN** el abono permanece y el pendiente se ajusta contra el `monto_original` reconciliado sin borrar pagos
