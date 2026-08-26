## Why

Una vez que un viaje cuenta con sus participantes registrados, los usuarios necesitan registrar las transacciones económicas del viaje: quién pagó cada concepto, qué monto se desembolsó y qué participantes forman parte del gasto. Asimismo, se requiere la capacidad de excluir a participantes específicos de gastos en los que no participaron (por ejemplo, si alguien no asistió a una cena o no consumió cierto producto).

## What Changes

- Creación de la migración y modelo Eloquent `Gasto` vinculado a un `Viaje` y a un `Participante` (el pagador), con campos para concepto/descripción, monto (con precisión decimal para moneda) y fecha.
- Creación de la tabla pivote de base de datos `gasto_exclusiones` (o `gasto_participante_excluido`) para gestionar la exclusión de participantes en gastos específicos.
- Creación de Form Requests (`StoreGastoRequest`, `UpdateGastoRequest`) con validaciones estrictas: monto estrictamente positivo (`> 0`), pagador perteneciente al mismo viaje, participantes excluidos pertenecientes al mismo viaje y validación de que no se excluya al total de participantes del viaje.
- Creación de `GastoPolicy` para autorizar que solo el usuario propietario del viaje pueda registrar, ver, editar o eliminar gastos y sus exclusiones.
- Creación de `GastoController` para manejar las operaciones CRUD de gastos asociadas al viaje.
- Definición de rutas web agrupadas bajo `auth` en `routes/web.php`.
- Actualización de `DatabaseSeeder.php` con los 4 gastos reales del escenario de referencia de **Samaipata**:
  1. Cabaña: Bs. 800 (pagado por Ana, aplica a todos).
  2. Entradas a El Fuerte: Bs. 160 (pagado por Ana, aplica a todos).
  3. Cena: Bs. 400 (pagado por Beto, aplica a todos).
  4. Gasolina: Bs. 240 (pagado por Carla, aplica a todos).
- Suite de pruebas automatizadas (Feature Tests) verificando registro, validaciones de montos, asignación de pagadores, exclusiones y casos borde.

### Casos de uso del backend que cubre

Esta propuesta cubre explícitamente:
- **Caso de uso 2: Gestionar gastos** (alta, edición, listado, detalle y eliminación de gastos en un viaje).
- **Caso de uso 3: Excluir participantes de un gasto** (asociar exclusiones para que ciertos participantes no absorban la cuota parte de un gasto).

### Fuera de alcance

- **Vistas / frontend Inertia / Blade / componentes Vue**: este cambio es **SOLO BACKEND**; no se generan interfaces gráficas.
- **Cálculo de saldos netos y algoritmo de liquidación** (Casos de uso 4, 5 y 6): el procesamiento de balances individuales y cálculo de transferencias mínimas se implementa en el siguiente módulo (`calculo-saldos-liquidacion`).
- **Múltiples monedas simultáneas / conversión de divisas**: todos los montos se registran en la moneda local (Bs.) con dos decimales.

## Capabilities

### New Capabilities

- `gastos`: Registro, listado, edición y eliminación de gastos asociados a un viaje, validando montos positivos, asignación de pagador y persistencia en PostgreSQL.
- `exclusiones`: Asignación y gestión de participantes excluidos de un gasto particular, garantizando que al menos un participante permanezca incluido en el reparto del gasto.

### Modified Capabilities

- *(ninguna; los módulos `user-auth` y `gestion-viajes-participantes` se mantienen sin alteraciones de requisitos)*

## Impact

- **Modelos PHP:** creación de `app/Models/Gasto.php`, relaciones en `app/Models/Viaje.php` y `app/Models/Participante.php`.
- **Base de datos:** migraciones para `gastos` y tabla pivote `gasto_exclusiones` con claves foráneas e integridad referencial en PostgreSQL.
- **Validación y Autorización:** `app/Http/Requests/Gasto/` y `app/Policies/GastoPolicy.php`.
- **Controladores y Rutas:** `app/Http/Controllers/GastoController.php` y endpoints agrupados en `routes/web.php`.
- **Seeders y Tests:** enriquecimiento de `DatabaseSeeder.php` con los gastos de Samaipata y tests en `tests/Feature/GastoTest.php`.
