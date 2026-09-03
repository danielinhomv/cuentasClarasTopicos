## Why

En viajes grupales es habitual que los participantes incurran en gastos en diversas monedas: bolivianos en efectivo o QR (`BOB` / `Bs`), dólares estadounidenses en billete (`USD`), y transferencias en dólares digitales (`USDT`). Debido a la volatilidad y la coexistencia de tasas de cambio diferenciadas en la economía boliviana y regional, el tipo de cambio no puede ser rígido ni automático: debe poder **gestionarse de manera manual**. 

Asimismo, para mantener la armonía y evitar disputas entre integrantes, **únicamente el creador del viaje** debe tener el permiso para configurar y ajustar los tipos de cambio aplicables al viaje. Todo el motor de cálculo de saldos y plan óptimo de liquidación debe **consolidar con exactitud matemática** los gastos en la moneda base (`BOB`), garantizando que la suma neta de saldos sea exactamente cero ($\sum \text{balances} = 0$) y sin pérdidas de centavos por redondeos imprecisos.

## What Changes

- **Modelo de Tipo de Cambio en Viaje:**
  - Agregar a la tabla `viajes` las columnas `tipo_cambio_usd` (decimal 10,4, defecto 6.9600) y `tipo_cambio_usdt` (decimal 10,4, defecto 10.5000).
  - Endpoint dedicado `PUT /viajes/{viaje}/tipo-cambio` protegido por `ViajePolicy::update` para que **solo el creador del viaje** pueda modificar estas tasas.

- **Moneda en Gastos:**
  - Agregar a la tabla `gastos` la columna `moneda` (string, valores permitidos: `'BOB'`, `'USD'`, `'USDT'`, por defecto `'BOB'`).
  - Cada gasto registra su moneda de origen y el tipo de cambio utilizado para su conversión a la moneda base.

- **Motor de Consolidación Exacta:**
  - Actualización de `CalculoBalanceService` para calcular el valor consolidado de cada gasto en la divisa base:
    - Si `moneda == 'BOB'`: factor 1.0.
    - Si `moneda == 'USD'`: factor `tipo_cambio_usd`.
    - Si `moneda == 'USDT'`: factor `tipo_cambio_usdt`.
  - El monto consolidado se convierte a centavos enteros en la moneda base para ejecutar el reparto equitativo y la asignación del residuo al pagador, preservando el invariante de balance cero en la divisa consolidada.

- **Interfaz de Usuario (Dark Neon):**
  - **Selector de Moneda en Gastos:** En el modal de creación y edición de gastos en `Viajes/Show.vue`, botones tipo toggle o selector estilizado para elegir entre `BOB (Bs)`, `USD ($)` y `USDT (₮)`, mostrando en tiempo real la equivalencia aproximada en Bs.
  - **Badge Multidivisa en Listado de Gastos:** Identificación clara del monto original con su divisa (ej. `100.00 USD`) y el monto consolidado en bolivianos (ej. `≈ 696.00 Bs`).
  - **Panel de Tipo de Cambio del Grupo:** Card informativa en la vista del viaje con las cotizaciones actuales de USD y USDT.
  - **Modal de Ajuste de Tipo de Cambio:** Exclusivo para el creador del viaje, permitiéndole actualizar las cotizaciones de USD y USDT en cualquier momento.
  - **Pestañas de Saldos y Liquidación:** Muestran los balances netos y acuerdos de pago consolidados en la moneda base oficial del viaje.

### Casos de uso cubiertos

- **Caso de uso 2: Registrar y editar gastos** (ahora con selector de divisa BOB, USD, USDT).
- **Caso de uso 5: Consultar saldos y liquidación** (consolidando múltiples divisas con precisión de centavos).
- **Caso de uso 7: Gestionar viaje** (configuración manual de cotizaciones de cambio por el creador).

### Fuera de alcance

- Conexión a APIs de cotizaciones bancarias externas o del BCB en tiempo real (el tipo de cambio se gestiona manualmente según lo acordado por el grupo).
- Liquidaciones en múltiples divisas separadas sin consolidar (se consolida todo a la divisa base para minimizar transacciones cruzadas).

## Capabilities

### New Capabilities
- `multidivisa-gastos`: Registro de gastos en monedas `BOB`, `USD` y `USDT`, y consolidación aritmética en la divisa base para balance de liquidación.
- `gestion-tipo-cambio`: Configuración y edición manual del tipo de cambio por parte del creador del viaje.

### Modified Capabilities
- *(ninguna)*

## Impact

- **Base de datos:** Nuevas columnas en `viajes` (`tipo_cambio_usd`, `tipo_cambio_usdt`) y en `gastos` (`moneda`, `tipo_cambio`).
- **Servicios Backend:** `CalculoBalanceService` adapta el cálculo de centavos utilizando la tasa de cambio para consolidación.
- **Controladores y Políticas:** `ViajeController` (método `actualizarTipoCambio`), `GastoController`, `StoreGastoRequest`, `UpdateGastoRequest`, `ViajePolicy`.
- **Frontend:** `Viajes/Show.vue`, modal de gastos, componente de tipo de cambio y listados.
