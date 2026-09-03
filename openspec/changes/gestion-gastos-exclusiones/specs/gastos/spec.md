## Purpose

Permite registrar, listar, editar y eliminar los gastos económicos efectuados durante un viaje en Cuentas Claras, validando montos positivos, asignación de pagador perteneciente al viaje y persistencia en PostgreSQL.

## ADDED Requirements

### Requirement: Usuario autenticado puede registrar un gasto en su viaje

El sistema SHALL permitir al propietario de un viaje registrar un gasto indicando concepto (descripción), monto monetario (con 2 decimales), fecha del gasto y el participante que realizó el pago (pagador). El monto MUST ser estrictamente mayor a cero (`> 0`). El pagador MUST ser un participante perteneciente al mismo viaje.

#### Scenario: Registro exitoso de gasto
- **DADO** un usuario autenticado y un viaje con el participante "Ana" registrado
- **WHEN** envía la solicitud para registrar un gasto con concepto "Cabaña", monto `800.00`, fecha válida y seleccionando a "Ana" como pagador
- **THEN** el sistema persiste el gasto en PostgreSQL asociado al viaje y al participante pagador, retornando código HTTP 201 y los datos del gasto

#### Scenario: Gasto con monto cero o negativo (caso borde)
- **DADO** un viaje con participantes registrados
- **WHEN** el usuario intenta registrar un gasto con monto `0.00` o un valor negativo como `-50.00`
- **THEN** el sistema rechaza la creación y responde con un error de validación indicando que el monto debe ser mayor a cero

#### Scenario: Concepto de gasto vacío
- **DADO** un viaje existente
- **WHEN** el usuario intenta registrar un gasto con el concepto en blanco
- **THEN** el sistema no crea el registro y devuelve un error de validación indicando que la descripción es requerida

#### Scenario: Pagador no perteneciente al viaje (caso borde de integridad)
- **DADO** dos viajes ("Viaje A" con "Ana" y "Viaje B" con "Zulma")
- **WHEN** el usuario intenta registrar un gasto en el "Viaje A" asignando como pagador a "Zulma"
- **THEN** el sistema rechaza la operación con un error de validación indicando que el pagador no es válido para este viaje

### Requirement: Usuario autenticado puede listar y ver el detalle de gastos de su viaje

El sistema SHALL permitir consultar todos los gastos registrados en un viaje propio, mostrando para cada gasto el concepto, monto, fecha, datos del pagador y la lista de participantes excluidos si los hubiera.

#### Scenario: Listado de gastos del viaje
- **DADO** un viaje con múltiples gastos registrados (Cabaña Bs. 800, Cena Bs. 400, Gasolina Bs. 240)
- **WHEN** el usuario solicita el listado de gastos de ese viaje
- **THEN** el sistema retorna la lista completa de gastos con sus montos y datos del pagador correspondiente

#### Scenario: Viaje sin gastos registrados (caso borde: 0 gastos)
- **DADO** un viaje recién creado con participantes pero sin gastos
- **WHEN** el usuario consulta el listado de gastos
- **THEN** el sistema retorna una lista vacía con código HTTP 200 sin errores

#### Scenario: Consulta de detalle de un gasto específico
- **DADO** un gasto existente en un viaje propio
- **WHEN** el usuario consulta el detalle del gasto por su identificador
- **THEN** el sistema retorna los datos del gasto incluyendo la información del pagador y las exclusiones asociadas

### Requirement: Usuario autenticado puede editar y eliminar gastos

El sistema SHALL permitir modificar los datos de un gasto existente (concepto, monto, pagador, fecha) o eliminarlo de un viaje propio.

#### Scenario: Edición exitosa de un gasto
- **DADO** un gasto existente con monto `400.00` y concepto "Cena"
- **WHEN** el usuario actualiza el monto a `450.00` y el concepto a "Cena especial"
- **THEN** el sistema persiste los cambios en PostgreSQL y retorna los datos actualizados

#### Scenario: Eliminación exitosa de un gasto
- **DADO** un gasto registrado en un viaje
- **WHEN** el usuario solicita la eliminación del gasto
- **THEN** el sistema elimina el registro del gasto y sus exclusiones asociadas en PostgreSQL, y los saldos y liquidaciones del viaje se recalculan a partir de los gastos que siguen existiendo (sin deudas huérfanas del gasto eliminado)

### Requirement: Seguridad y protección de gastos

El sistema MUST exigir que el usuario autenticado sea el dueño del viaje para cualquier operación sobre gastos e impedir el acceso a visitantes no autenticados o usuarios ajenos.

#### Scenario: Intento de registrar o modificar gasto en viaje ajeno
- **DADO** un usuario autenticado que intenta registrar, editar o eliminar un gasto en un viaje perteneciente a otro usuario
- **WHEN** envía la petición
- **THEN** el sistema bloquea la operación con un error HTTP 403 Forbidden

#### Scenario: Acceso de visitante no autenticado
- **DADO** un visitante sin sesión iniciada
- **WHEN** intenta acceder a los endpoints de gastos
- **THEN** el sistema deniega el acceso y redirige al flujo de login (HTTP 401 / 302)
