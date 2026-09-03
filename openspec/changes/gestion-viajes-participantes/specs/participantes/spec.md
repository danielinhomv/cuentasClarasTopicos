## Purpose

Permite gestionar los participantes (amigos) que integran un viaje en Cuentas Claras, validando unicidad de nombres dentro del mismo viaje, integridad referencial y control de acceso.

## ADDED Requirements

### Requirement: Usuario autenticado puede agregar participantes a su viaje

El sistema SHALL permitir al propietario de un viaje registrar participantes indicando su nombre. El sistema SHALL exigir que el nombre no esté vacío y que no se dupliquen nombres de participantes dentro del mismo viaje. El participante creado mediante alta manual SHALL quedar con `user_id = NULL` (sin cuenta de usuario).

#### Scenario: Alta exitosa de participante
- **DADO** un usuario autenticado propietario de un viaje (ej. "Viaje a Samaipata")
- **WHEN** envía la solicitud para agregar un participante con nombre válido (ej. "Ana")
- **THEN** el sistema registra a "Ana" en PostgreSQL asociada a ese viaje con `user_id` nulo y devuelve los datos del participante

#### Scenario: Alta de participante sin cuenta
- **DADO** un usuario autenticado propietario de un viaje
- **WHEN** agrega un participante con nombre "Diego" sin vincularlo a ningún usuario
- **THEN** el sistema crea el participante con `user_id = NULL` y nombre "Diego", visible en el listado del viaje

#### Scenario: Nombre de participante vacío
- **DADO** un usuario autenticado gestionando los participantes de su viaje
- **WHEN** intenta agregar un participante con nombre en blanco
- **THEN** el sistema no crea el registro y devuelve un error de validación indicando que el nombre es obligatorio

#### Scenario: Nombre duplicado dentro del mismo viaje
- **DADO** un viaje que ya cuenta con un participante llamado "Ana"
- **WHEN** el usuario intenta agregar otro participante llamado "Ana" al mismo viaje
- **THEN** el sistema rechaza la creación y devuelve un error de validación indicando que ya existe un participante con ese nombre en este viaje

#### Scenario: Mismo nombre de participante en viajes distintos
- **DADO** dos viajes distintos del mismo usuario ("Viaje Samaipata" y "Viaje Tarija")
- **WHEN** el usuario agrega a "Ana" en ambos viajes
- **THEN** el sistema permite la creación en ambos viajes sin conflicto (la unicidad es por viaje)

### Requirement: Usuario autenticado puede listar los participantes de un viaje

El sistema SHALL permitir consultar todos los participantes registrados en un viaje propio, retornando la colección ordenada.

#### Scenario: Listado con participantes registrados
- **DADO** un viaje con los participantes "Ana", "Beto", "Carla" y "Diego"
- **WHEN** el usuario solicita el listado de participantes de ese viaje
- **THEN** el sistema retorna la lista con los 4 participantes persistidos

#### Scenario: Viaje recién creado sin participantes (caso borde: 0 participantes)
- **DADO** un viaje recién creado sin participantes asociados
- **WHEN** el usuario consulta la lista de participantes
- **THEN** el sistema retorna una lista vacía con código HTTP 200 sin arrojar excepciones

### Requirement: Usuario autenticado puede editar y eliminar participantes

El sistema SHALL permitir modificar el nombre de un participante existente o eliminarlo de un viaje propio. Al actualizar el nombre, el sistema SHALL validar que el nuevo nombre no colisione con otro participante del mismo viaje.

#### Scenario: Edición exitosa de nombre de participante
- **DADO** un participante existente llamado "Beto" en un viaje
- **WHEN** el usuario actualiza su nombre a "Alberto"
- **THEN** el sistema persiste el nuevo nombre en PostgreSQL y actualiza la información

#### Scenario: Edición con nombre colisionante
- **DADO** un viaje con "Ana" y "Beto"
- **WHEN** el usuario intenta renombrar a "Beto" como "Ana"
- **THEN** el sistema rechaza la actualización por conflicto de unicidad en el viaje

#### Scenario: Eliminación exitosa de participante
- **DADO** un participante registrado en un viaje, sin deudas pendientes, con saldo `0.00` y sin haber participado en ningún gasto
- **WHEN** el usuario solicita la eliminación del participante
- **THEN** el sistema elimina el registro del participante en PostgreSQL

### Requirement: No se elimina un participante con deuda pendiente o participación en gastos

El sistema MUST rechazar la eliminación de un participante cuando ocurre cualquiera de estas condiciones:

1. **Deuda pendiente:** el participante debe dinero a otro, o alguien le debe dinero. Esto incluye deudas persistidas con `monto_pendiente > 0` (como deudor o acreedor) y un saldo neto del viaje distinto de `0.00`.
2. **Participación en un gasto:** el participante es pagador de un gasto o está incluido en la división (`gasto_participantes`).

En ambos casos el sistema MUST NOT borrar el registro y SHALL devolver al detalle del viaje con un mensaje claro que explique el motivo. Un participante sin deudas pendientes, con saldo `0.00` y sin participación en gastos SHALL poder eliminarse.

#### Scenario: Eliminación bloqueada porque el participante debe dinero
- **DADO** el viaje a Samaipata y una deuda pendiente de Diego hacia Ana
- **WHEN** el propietario intenta eliminar a Diego
- **THEN** el sistema no elimina a Diego y muestra un mensaje indicando que no puede eliminarse porque tiene una deuda pendiente

#### Scenario: Eliminación bloqueada porque le deben dinero al participante
- **DADO** una deuda pendiente de Diego hacia Ana
- **WHEN** el propietario intenta eliminar a Ana
- **THEN** el sistema no elimina a Ana y muestra un mensaje indicando que no puede eliminarse porque tiene una deuda pendiente

#### Scenario: Eliminación bloqueada porque el participante ya participó en un gasto
- **DADO** un gasto en el que Ana pagó y quedó incluida en la división, aunque su saldo neto sea `0.00`
- **WHEN** el propietario intenta eliminar a Ana
- **THEN** el sistema no elimina a Ana y muestra un mensaje indicando que no puede eliminarse porque ya participó en un gasto

#### Scenario: Eliminación permitida sin deudas ni gastos
- **DADO** un participante "Zulma" agregado al viaje, sin aparecer en ningún gasto y sin deudas
- **WHEN** el propietario solicita su eliminación
- **THEN** el sistema elimina el registro y confirma el éxito

### Requirement: Autorización y aislamiento de participantes

El sistema MUST verificar que el usuario autenticado sea el dueño del viaje antes de permitir cualquier operación de lectura, creación, edición o eliminación de participantes.

#### Scenario: Intento de agregar o modificar participante en viaje ajeno
- **DADO** un usuario autenticado que intenta agregar, editar o eliminar un participante en un viaje perteneciente a otro usuario
- **WHEN** envía la solicitud al endpoint correspondiente
- **THEN** el sistema rechaza la petición con un error HTTP 403 Forbidden

#### Scenario: Acceso de visitante no autenticado
- **DADO** un visitante sin sesión iniciada
- **WHEN** intenta realizar cualquier operación sobre participantes
- **THEN** el sistema bloquea el acceso y redirige a la pantalla de login (HTTP 401 / 302)

### Requirement: Participantes sin cuenta participan en gastos y cálculos

Los participantes creados sin cuenta (`user_id = NULL`) SHALL poder ser seleccionados como pagadores de gastos, incluidos o excluidos de la división de un gasto, y SHALL aparecer en los cálculos de saldos y liquidación con la misma lógica que los participantes registrados.

#### Scenario: Participante sin cuenta como pagador de gasto
- **DADO** un viaje con un participante sin cuenta "Diego" (`user_id = NULL`)
- **WHEN** el propietario registra un gasto pagado por "Diego"
- **THEN** el gasto se crea correctamente con `pagador_id` de Diego y Diego aparece en los saldos

#### Scenario: Participante sin cuenta en cálculo de saldos
- **DADO** un viaje con participantes con y sin cuenta, y gastos registrados que los incluyen
- **WHEN** se calculan los saldos del viaje
- **THEN** todos los participantes (con y sin cuenta) aparecen en el desglose de balances y la suma de balances es exactamente 0

#### Scenario: Coexistencia con participantes registrados por código
- **DADO** un viaje con participantes agregados manualmente (sin cuenta) y participantes que se unieron con código de invitación (con cuenta)
- **WHEN** se listan los participantes del viaje
- **THEN** ambos tipos aparecen en la misma lista, diferenciados por la presencia o ausencia de `user_id`
