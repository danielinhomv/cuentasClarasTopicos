## Purpose

Permite a los usuarios visualizar de forma clara y atractiva la tabla de balances netos por participante y el plan optimizado de transferencias de liquidación con códigos de color e indicadores neón.

## ADDED Requirements

### Requirement: Tabla visual de saldos individuales con código de color neón
El sistema SHALL presentar una tabla o conjunto de tarjetas donde cada participante muestre: total pagado, total consumido y saldo neto con distinción cromática neón (verde neón para acreedor, rojo neón para deudor, gris para saldado).

#### Scenario: Visualización de saldos en el escenario de Samaipata
- **DADO** el viaje de Samaipata con los 4 gastos oficiales registrados
- **WHEN** el usuario ingresa a la pestaña "Saldos"
- **THEN** se muestra:
  - Ana con balance `+Bs. 560.00` en verde neón con badge "Le deben"
  - Beto con balance `Bs. 0.00` en neutro con badge "Al día"
  - Carla con balance `-Bs. 160.00` en rojo neón con badge "Debe"
  - Diego con balance `-Bs. 400.00` en rojo neón con badge "Debe"
  - Indicador de consistencia destacando que la suma de balances es `Bs. 0.00`

### Requirement: Panel visual de liquidación de cuentas
El sistema SHALL renderizar las transferencias directas óptimas en tarjetas con flechas direccionales iluminadas, indicando claramente quién debe pagar a quién y qué monto exacto.

#### Scenario: Visualización de transferencias calculadas de Samaipata
- **DADO** el viaje de Samaipata con sus balances calculados
- **WHEN** el usuario visualiza la pestaña "Liquidación"
- **THEN** se muestran tarjetas destacadas con acento violeta/cian:
  - *"Diego paga a Ana: Bs. 400.00"*
  - *"Carla paga a Ana: Bs. 160.00"*
  con estado de cuentas totalmente equilibradas tras esas 2 operaciones

#### Scenario: Liquidación de viaje sin deudas
- **DADO** un viaje recién creado o donde todos los balances están en 0.00
- **WHEN** el usuario consulta la liquidación
- **THEN** se muestra un mensaje amigable con ilustración o ícono neón indicando *"¡Todas las cuentas están al día! No se requieren transferencias."*

#### Scenario: Saldos y liquidación se actualizan al eliminar un gasto
- **DADO** un viaje cuya pestaña de liquidación muestra deudas derivadas de un gasto
- **WHEN** el usuario elimina ese gasto y vuelve a ver el detalle del viaje
- **THEN** las pestañas de saldos y liquidación muestran los montos recalculados, sin deudas ni referencias del gasto eliminado
