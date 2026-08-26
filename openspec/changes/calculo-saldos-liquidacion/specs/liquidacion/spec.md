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
