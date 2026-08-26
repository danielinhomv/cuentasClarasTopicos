## Purpose

Garantiza la consistencia matemática absoluta de los cálculos en Cuentas Claras, validando la invariante estricta de balance cero y la absorción de centavos residuales por el pagador del gasto.

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

### Requirement: Manejo de redondeo con absorción de centavos por el pagador

Al dividir un gasto cuyo monto no es exactamente divisible entre el número de participantes incluidos (por ejemplo, Bs. 100.00 entre 3 personas), los centavos sobrantes MUST ser absorbidos por el participante que pagó el gasto originalmente, asegurando que la suma de las cuotas partes individuales iguale con exactitud el monto total del gasto.

#### Scenario: División no exacta con pagador incluido (ej. Bs. 100 entre 3)
- **DADO** un viaje con Ana, Beto y Carla, donde Ana paga un gasto de `100.00` Bs. compartido entre los 3
- **WHEN** el sistema calcula la cuota parte de cada uno
- **THEN** a los participantes deudores (Beto y Carla) se les asigna una cuota de `33.33` Bs. a cada uno, y Ana (la pagadora) absorbe el centavo restante asumiendo un consumo de `33.34` Bs., resultando en:
  - Total consumos: `33.33 + 33.33 + 33.34 = 100.00`
  - Balance Ana: `100.00 - 33.34 = +66.66`
  - Balance Beto: `0.00 - 33.33 = -33.33`
  - Balance Carla: `0.00 - 33.33 = -33.33`
  - Suma de balances: `66.66 - 33.33 - 33.33 = 0.00`

#### Scenario: Gasto indivisible con centavos impares (ej. Bs. 10 entre 3)
- **DADO** un gasto de `10.00` Bs. pagado por Beto entre 3 participantes
- **WHEN** se divide el monto
- **THEN** los dos participantes no pagadores reciben `3.33` Bs. c/u (`6.66` Bs. total) y Beto absorbe el centavo asumiendo `3.34` Bs. de consumo (`6.66 + 3.34 = 10.00`), con suma de balances `0.00`

### Requirement: Robustez ante casos borde numéricos

El sistema MUST procesar correctamente montos mínimos de 1 centavo y viajes con un solo participante sin generar divisiones por cero ni errores de desbordamiento.

#### Scenario: Gasto mínimo de un centavo (Bs. 0.01)
- **DADO** un gasto de `0.01` Bs. pagado por Ana entre Ana y Beto
- **WHEN** el sistema procesa la división
- **THEN** el sistema asigna el centavo a la pagadora Ana (`0.01` consumo) y `0.00` a Beto, manteniendo la suma en `0.01` y el balance en `0.00`

#### Scenario: Viaje con un único participante que paga su propio gasto
- **DADO** un viaje con un solo participante que registra un gasto de `50.00` Bs.
- **WHEN** se calculan los saldos
- **THEN** su total pagado es `50.00`, su consumo es `50.00`, su balance es `0.00` y la liquidación requiere 0 transferencias
