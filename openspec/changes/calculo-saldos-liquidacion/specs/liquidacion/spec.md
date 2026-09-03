## Purpose

Genera el plan óptimo de transferencias directas de dinero entre deudores y acreedores de un viaje, minimizando el número total de transacciones requeridas para saldar todas las cuentas.

## ADDED Requirements

### Requirement: Algoritmo de liquidación calcula el número mínimo de transferencias

El sistema SHALL implementar un algoritmo de liquidación de deudas que tome los balances netos calculados y genere una lista de transferencias directas de la forma `(deudor, acreedor, monto)`. Tras ejecutar todas las transferencias del plan, el saldo de todos los participantes SHALL quedar en `0.00`.

#### Scenario: Liquidación óptima en el escenario oficial de Samaipata
- **DADO** los balances netos calculados de Samaipata (Ana: `+560.00`, Beto: `0.00`, Carla: `-160.00`, Diego: `-400.00`)
- **WHEN** el usuario solicita el plan de liquidación del viaje
- **THEN** el sistema retorna exactamente 2 transferencias ordenadas:
  1. `deudor: "Diego"`, `acreedor: "Ana"`, `monto: 400.00`
  2. `deudor: "Carla"`, `acreedor: "Ana"`, `monto: 160.00`
  quedando todas las deudas del viaje completamente saldadas

#### Scenario: Viaje con todas las cuentas saldadas (0 transferencias)
- **DADO** un viaje donde todos los participantes tienen balance `0.00` (o viaje sin gastos)
- **WHEN** se solicita el plan de liquidación
- **THEN** el sistema retorna una lista vacía de transferencias con código HTTP 200 (nadie debe pagar a nadie)

#### Scenario: Un deudor hacia múltiples acreedores
- **DADO** un balance con Diego (`-300.00`), Ana (`+200.00`) y Beto (`+100.00`)
- **WHEN** el sistema calcula la liquidación
- **THEN** genera 2 transferencias directas:
  1. `deudor: "Diego"`, `acreedor: "Ana"`, `monto: 200.00`
  2. `deudor: "Diego"`, `acreedor: "Beto"`, `monto: 100.00`

#### Scenario: Múltiples deudores hacia un único acreedor
- **DADO** un balance con Ana (`+300.00`), Beto (`-150.00`) y Carla (`-150.00`)
- **WHEN** el sistema calcula la liquidación
- **THEN** genera 2 transferencias directas:
  1. `deudor: "Beto"`, `acreedor: "Ana"`, `monto: 150.00`
  2. `deudor: "Carla"`, `acreedor: "Ana"`, `monto: 150.00`

#### Scenario: Deuda cancelada de monto exacto prioritario
- **DADO** un balance con Ana (`+200.00`), Beto (`+50.00`), Carla (`-200.00`) y Diego (`-50.00`)
- **WHEN** el algoritmo empareja deudores y acreedores
- **THEN** empareja montos idénticos directamente: Carla $\rightarrow$ Ana por `200.00` y Diego $\rightarrow$ Beto por `50.00` (minimizando transferencias fraccionadas)

### Requirement: Usuario puede liquidar una deuda de forma completa o parcial

El sistema SHALL persistir cada transferencia sugerida como una deuda entre un deudor y un acreedor, exponiendo `monto_original`, `monto_pagado` y `monto_pendiente`. El sistema SHALL permitir registrar uno o más pagos sobre la misma deuda siempre que cada pago sea mayor a cero y no exceda el pendiente. Cuando el pendiente llegue a `0.00`, el sistema SHALL marcar la deuda como completamente liquidada.

#### Scenario: Liquidación completa de una deuda
- **DADO** una deuda persistida de Diego hacia Ana por `40.00` sin pagos previos
- **WHEN** se registra un pago de `40.00`
- **THEN** `monto_original` es `40.00`, `monto_pagado` es `40.00`, `monto_pendiente` es `0.00` y la deuda queda en estado liquidada

#### Scenario: Liquidación parcial de una deuda
- **DADO** una deuda persistida de Diego hacia Ana por `40.00` sin pagos previos
- **WHEN** se registra un pago de `20.00`
- **THEN** `monto_original` es `40.00`, `monto_pagado` es `20.00`, `monto_pendiente` es `20.00` y la deuda permanece pendiente

#### Scenario: Múltiples pagos parciales sobre la misma deuda
- **DADO** una deuda de `40.00` con un primer pago de `20.00`
- **WHEN** se registra un segundo pago de `20.00`
- **THEN** `monto_pagado` es `40.00`, `monto_pendiente` es `0.00` y la deuda queda liquidada

#### Scenario: Rechazo de sobrepago
- **DADO** una deuda con pendiente `20.00`
- **WHEN** se intenta registrar un pago de `25.00`
- **THEN** el sistema rechaza el pago, no modifica montos y responde con error de validación

#### Scenario: Saldos se actualizan después de un pago parcial
- **DADO** balances brutos Diego `-40.00` y Ana `+40.00`, y un pago de `20.00` de Diego a Ana
- **WHEN** se consultan los saldos del viaje
- **THEN** el saldo expuesto de Diego es `-20.00` y el de Ana es `+20.00`, manteniendo la suma de balances en `0.00`

### Requirement: Las deudas se sincronizan cuando se elimina o cambia un gasto

Al eliminar (o editar) un gasto, el sistema SHALL recalcular saldos y el plan de liquidación a partir de los gastos que siguen existiendo, y SHALL actualizar las deudas persistidas. MUST NOT dejar liquidaciones pendientes que correspondan a un gasto ya inexistente. Si la deuda de un par disminuye, `monto_pendiente` MUST reflejar el nuevo original menos los pagos ya registrados. Si el par deja de existir y no hubo pagos, la fila MUST eliminarse. El frontend MUST mostrar el mismo conjunto de saldos y deudas que el backend tras la mutación.

#### Scenario: Crear gasto, ver deuda y eliminarlo
- **DADO** Ana y Diego en un viaje, y un gasto de `80.00` pagado por Ana e incluido ambos
- **WHEN** se consulta la liquidación y luego se elimina el gasto
- **THEN** primero Diego debe `40.00` a Ana; después no hay deudas pendientes, los saldos son `0.00` y no queda ninguna liquidación huérfana

#### Scenario: Eliminar un gasto que genera una deuda completa
- **DADO** una única deuda persistida originada por un gasto
- **WHEN** se elimina ese gasto
- **THEN** el plan queda vacío y la liquidación de ese par desaparece

#### Scenario: Eliminar un gasto que afecta parcialmente una deuda existente
- **DADO** dos gastos de `80.00` pagados por Ana e incluidos Ana y Diego (Diego debe `80.00`)
- **WHEN** se elimina uno de esos gastos
- **THEN** la deuda Diego → Ana queda en `40.00` de original y pendiente, y los saldos coinciden con un solo gasto restante

#### Scenario: Eliminar un gasto con liquidación parcial previa
- **DADO** dos gastos de `80.00` como arriba y un abono de `30.00` de Diego a Ana
- **WHEN** se elimina uno de los gastos
- **THEN** `monto_original` es `40.00`, `monto_pagado` sigue `30.00`, `monto_pendiente` es `10.00` y la suma de saldos expuestos es `0.00`
