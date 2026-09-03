## Purpose

Permite calcular de forma precisa y dinámica el saldo neto, total pagado y total consumido por cada participante de un viaje en Cuentas Claras, considerando exclusiones y reglas de reparto.

## ADDED Requirements

### Requirement: Sistema calcula el balance neto individual de cada participante

El sistema SHALL calcular para cada participante de un viaje: el total de dinero desembolsado (`total_pagado`), el total de gastos que le corresponden según su participación (`total_consumido`) y su saldo neto (`balance = total_pagado - total_consumido`). Un balance positivo SHALL indicar que le deben dinero al participante; un balance negativo SHALL indicar que el participante debe dinero.

#### Scenario: Cálculo de balances en el escenario oficial de Samaipata
- **DADO** el viaje de Samaipata con Ana (pagó Cabaña 800 y Entradas 160), Beto (pagó Cena 400), Carla (pagó Gasolina 240) y Diego (no pagó nada), todos incluidos en los 4 gastos
- **WHEN** el usuario solicita el reporte de saldos del viaje
- **THEN** el sistema calcula y retorna:
  - Ana: total pagado `960.00`, consumido `400.00`, balance `+560.00`
  - Beto: total pagado `400.00`, consumido `400.00`, balance `0.00`
  - Carla: total pagado `240.00`, consumido `400.00`, balance `-160.00`
  - Diego: total pagado `0.00`, consumido `400.00`, balance `-400.00`

#### Scenario: Participante con balance positivo (acreedor)
- **DADO** un participante que pagó un gasto de Bs. 300 repartido entre 3 personas
- **WHEN** se calculan los saldos
- **THEN** su total pagado es `300.00`, su consumo es `100.00` y su balance es `+200.00` (le deben Bs. 200)

#### Scenario: Participante con balance negativo (deudor)
- **DADO** un participante que no pagó ningún gasto pero participó de un gasto común de Bs. 300 entre 3 personas
- **WHEN** se calculan los saldos
- **THEN** su total pagado es `0.00`, su consumo es `100.00` y su balance es `-100.00` (debe Bs. 100)

#### Scenario: Participante con balance neutro
- **DADO** un participante donde lo que pagó equivale exactamente a su cuota parte de consumo
- **WHEN** se calculan los saldos
- **THEN** su balance resultante es exactamente `0.00`

#### Scenario: Viaje recién creado sin gastos (caso borde: 0 gastos)
- **DADO** un viaje con participantes pero sin ningún gasto registrado
- **WHEN** se solicitan los saldos del viaje
- **THEN** el sistema retorna para cada participante `total_pagado = 0.00`, `total_consumido = 0.00` y `balance = 0.00` sin errores

#### Scenario: Saldos consistentes después de eliminar un gasto
- **DADO** un gasto que generaba deuda entre participantes
- **WHEN** se elimina ese gasto y se consultan los saldos
- **THEN** los balances coinciden con los gastos restantes, su suma es `0.00` y no reflejan el gasto eliminado

### Requirement: El detalle del gasto muestra monto original y ajuste a efectivo

El sistema SHALL exponer, junto al monto ingresado por el anfitrión, el desglose de cuotas finales tras el ajuste a Bs 0,50 y el importe de ajuste por participante. El formulario de alta/edición MUST NOT incluir un campo para cargar recargo.

#### Scenario: Visualización de original y ajuste en Bs. 55,40
- **DADO** un gasto de `55.40` del anfitrión con otro participante
- **WHEN** el usuario ve el detalle del viaje
- **THEN** se muestra el monto original `55.40` y cuotas `27.50` / `28.00`, sin permitir editar el recargo

### Requirement: Cálculo de saldos con participantes excluidos

El sistema SHALL contemplar las exclusiones registradas en cada gasto al calcular la cuota parte de cada participante. Un participante excluido de un gasto no recibirá ningún cargo por dicho concepto (`cuota_parte = 0.00`).

#### Scenario: Participante excluido no acumula deuda por ese gasto
- **DADO** un viaje con Ana, Beto y Carla, y un gasto de Bs. 100 pagado por Ana donde Carla está excluida
- **WHEN** el sistema calcula los saldos
- **THEN** el gasto se divide únicamente entre Ana y Beto (Bs. 50 c/u); Carla tiene consumo `0.00` en este gasto y su balance no se ve afectado

#### Scenario: Pagador excluido del beneficio de su propio gasto
- **DADO** un gasto de Bs. 60 pagado por Ana pero exclusivo para Beto y Carla (Ana excluida de consumir)
- **WHEN** se calcula el saldo
- **THEN** Ana tiene pagado `60.00` y consumo `0.00` (+60.00), mientras Beto y Carla acumulan consumo de `30.00` cada uno
