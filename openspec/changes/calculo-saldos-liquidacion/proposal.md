## Why

El valor central y objetivo principal de Cuentas Claras radica en resolver matemáticamente la división de gastos de un viaje y determinar cómo saldar las cuentas entre amigos mediante el menor número de transferencias posibles. Sin esta capa de servicios de cálculo y liquidación, la aplicación solo sería un registro pasivo de gastos sin capacidad de balancear deudas.

## What Changes

- Creación del servicio de dominio `App\Services\CalculoBalanceService` encargado de calcular el balance neto de cada participante (Monto total pagado menos cuota parte de los gastos en los que participa).
- Implementación de la regla de negocio crítica de redondeo: cuando un gasto no es exactamente divisible entre los deudores (ej. Bs. 100 entre 3 personas = Bs. 33,33 c/u), los centavos sobrantes son absorbidos automáticamente por el participante que pagó el gasto originalmente.
- Validación de la invariante matemática obligatoria: la suma de todos los balances netos de los participantes de un viaje MUST ser SIEMPRE exactamente igual a 0 (`sum(balances) == 0.00`).
- Creación del servicio de dominio `App\Services\AlgoritmoLiquidacionService` que implementa un algoritmo voraz (*greedy matching*) para generar la lista óptima de transferencias (*quién le transfiere a quién y cuánto*) minimizando el número de transacciones.
- Creación de `LiquidacionController` con endpoints estructurados para consultar:
  - `GET /viajes/{viaje}/saldos`: Resumen detallado de balances por participante (total pagado, total consumido, saldo neto).
  - `GET /viajes/{viaje}/liquidacion`: Plan optimizado de transferencias de liquidación.
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
- **Registro de pagos reales / pasarelas de pago externas / transferencias bancarias automatizadas**: la app calcula las sugerencias de transferencias para que los usuarios las realicen en la vida real.
- **Persistencia innecesaria de saldos**: los saldos y liquidaciones son calculados dinámicamente en tiempo de ejecución a partir de los gastos registrados en PostgreSQL, evitando inconsistencias por datos desactualizados.

## Capabilities

### New Capabilities

- `saldos`: Cálculo de balances individuales por participante, cuota parte por gasto y total consumido/pagado.
- `liquidacion`: Algoritmo de liquidación mínima de deudas que genera las transferencias óptimas entre deudores y acreedores.
- `consistencia`: Garantía de la invariante matemática $\sum = 0$, absorción de centavos residuales por el pagador y robustez ante casos borde.

### Modified Capabilities

- *(ninguna; los módulos anteriores se mantienen estables)*

## Impact

- **Capa de Servicios:** creación de `app/Services/CalculoBalanceService.php` y `app/Services/AlgoritmoLiquidacionService.php`.
- **DTOs / Respuestas estructuradas:** clases de soporte para representar transferencias y resúmenes de saldos.
- **Controladores y Rutas:** `app/Http/Controllers/LiquidacionController.php` y rutas protegidas en `routes/web.php`.
- **Tests:** pruebas unitarias de algoritmos en `tests/Unit/Services/` y pruebas de integración en `tests/Feature/LiquidacionTest.php`.
