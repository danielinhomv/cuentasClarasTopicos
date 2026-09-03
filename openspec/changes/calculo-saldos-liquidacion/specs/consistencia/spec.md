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

El anfitrión/creador MUST ingresar el monto real; MUST NOT poder elegir ni editar el redondeo. El `monto` persistido SHALL permanecer igual. Toda cuota final de un gasto con 2+ beneficiarios MUST ser múltiplo de Bs 0,50. El anfitrión (participante con `user_id = viaje.user_id`), si está incluido, MUST redondear hacia abajo y MUST NOT recibir unidades extra. El sistema SHALL elegir automáticamente al no-anfitrión que asume el ajuste: (1) mayor deuda acumulada previa, (2) deuda pendiente más antigua, (3) sorteo determinístico `crc32(gasto.id|participante_id)` solo si (1) y (2) empatan. Consultar de nuevo el mismo gasto MUST devolver la misma asignación. Si la suma de cuotas de efectivo supera $M$ por el techo del hueco, esa diferencia se acredita al pagado del anfitrión para $\sum \mathrm{balances} = 0$. Liquidaciones completas y parciales MUST seguir funcionando. Gastos enteros tipo Samaipata MUST no cambiar.

#### Scenario: Anfitrión y un participante (Bs. 55,40)
- **DADO** un gasto de `55.40` compartido por el anfitrión y otro participante
- **WHEN** se calculan las cuotas
- **THEN** el anfitrión consume `27.50`, el otro `28.00`, el monto persistido sigue `55.40` y ambos finales son múltiplos de `0.50`

#### Scenario: Anfitrión y varios participantes
- **DADO** un gasto compartido por anfitrión, A y B cuya teórica dejaría a A y B en `27.60`
- **WHEN** se asigna el ajuste
- **THEN** el anfitrión queda en `27.50` y uno de A o B asume `28.00`; el anfitrión no sube

#### Scenario: 27,60 y 27,90 se convierten en efectivo
- **DADO** cuotas teóricas de `27.60` o `27.90` para no-anfitriones
- **WHEN** se aplica el redondeo
- **THEN** el monto final de quien asume el ajuste es `28.00` (múltiplo de `0.50`), nunca `27.60` ni `27.90`

#### Scenario: División no exacta entre 3 (Bs. 100) — el ajuste no va al anfitrión
- **DADO** Ana (anfitriona) paga `100.00` con Beto y Carla, sin deudas previas
- **WHEN** el sistema calcula las cuotas
- **THEN** Ana consume `33.00`, Beto y Carla `33.50` cada uno, la suma es `100.00`

#### Scenario: Un deudor con mayor deuda acumulada recibe el ajuste
- **DADO** Beto debe más que Carla antes de un gasto que genera una unidad extra de `0.50`
- **WHEN** se asigna el ajuste
- **THEN** Beto recibe la unidad extra y el anfitrión no

#### Scenario: Empate de deuda → gana la más antigua
- **DADO** Beto y Carla con la misma deuda acumulada, y Beto lleva más tiempo debiendo
- **WHEN** se asigna el ajuste
- **THEN** Beto recibe el ajuste

#### Scenario: Empate total → sorteo estable
- **DADO** no-anfitriones empatados en deuda y antigüedad
- **WHEN** se calcula el gasto dos veces
- **THEN** el mismo participante recibe el ajuste ambas veces (semilla `gasto.id`)

#### Scenario: Un solo participante
- **DADO** Ana como única incluida en un gasto de `45.35` que ella pagó
- **WHEN** se calcula el saldo
- **THEN** consume `45.35`, balance `0.00` y el monto original no cambia

#### Scenario: Liquidación parcial no se rompe
- **DADO** una deuda persistida con un abono parcial
- **WHEN** se recalculan saldos y el plan de liquidación
- **THEN** el abono permanece y el pendiente se ajusta contra el `monto_original` reconciliado sin borrar pagos
