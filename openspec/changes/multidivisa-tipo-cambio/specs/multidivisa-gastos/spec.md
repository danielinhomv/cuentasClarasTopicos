## Purpose

Permite registrar gastos en distintas divisas (BOB, USD, USDT) y consolida sus montos en la moneda base para el cálculo exacto de balances individuales y liquidaciones sin discrepancias de centavos.

## ADDED Requirements

### Requirement: Registro y edición de gastos con selección de moneda
El sistema SHALL permitir indicar la divisa del gasto entre `BOB`, `USD` o `USDT`. Si no se especifica, por defecto será `BOB`.

#### Scenario: Registro exitoso de gasto en USD
- **DADO** un participante en un viaje con `tipo_cambio_usd = 6.96`
- **WHEN** registra un gasto de `100.00` con moneda `USD`
- **THEN** el gasto se almacena con `monto = 100.00`, `moneda = 'USD'` y tasa aplicada `6.96`

#### Scenario: Registro de gasto con moneda no permitida
- **DADO** un participante en un viaje
- **WHEN** intenta enviar un gasto con moneda `EUR`
- **THEN** el sistema rechaza la solicitud indicando que la divisa no está soportada

### Requirement: Consolidación matemática exacta de saldos y liquidación
El sistema SHALL convertir el importe de cada gasto a centavos en la moneda base aplicando la tasa de cambio correspondiente, y ejecutar la repartición y absorción de residuo sobre dicho monto consolidado.

#### Scenario: Consolidación y balance neutro con gastos mixtos
- **DADO** un viaje con gastos en `BOB`, `USD` y `USDT`
- **WHEN** se calculan los saldos con `CalculoBalanceService`
- **THEN** cada gasto se consolida en `BOB`, la suma de todos los balances individuales es exactamente cero ($\sum \text{balances} = 0$), y la liquidación propuesta salda todas las deudas en moneda consolidada
