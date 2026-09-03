## Purpose

Permite al anfitrión de un viaje consultar un historial inmutable de quién creó, editó o eliminó gastos, incluyendo valores anteriores y nuevos cuando hay una edición.

## ADDED Requirements

### Requirement: El sistema registra automáticamente crear, editar y eliminar un gasto

El sistema SHALL persistir una entrada de bitácora en PostgreSQL cada vez que un usuario autenticado cree, edite o elimine un gasto. Cada entrada MUST incluir: identificador del actor, identificador del viaje, identificador del gasto (si aún existe), tipo de acción (`crear`, `editar` o `eliminar`), fecha y hora, y un snapshot del gasto suficiente para reconstruir lo ocurrido. El sistema MUST NOT permitir que un usuario cree, edite o elimine entradas de bitácora de forma manual.

#### Scenario: Alta de gasto genera entrada de crear
- **DADO** un viaje "Viaje a Samaipata" cuyo anfitrión es Ana y un participante Beto autenticado
- **WHEN** Beto registra un gasto con concepto "Cena", monto `400.00`, moneda `BOB` y pagador Beto
- **THEN** el sistema persiste una entrada con acción `crear`, actor Beto, el gasto afectado y un snapshot posterior con concepto, monto, moneda, tipo de cambio, fecha, pagador y participantes incluidos/excluidos

#### Scenario: Edición de monto registra valor anterior y valor nuevo
- **DADO** un gasto existente con monto `100.00` en el viaje de Samaipata
- **WHEN** un participante autenticado modifica el monto a `150.00`
- **THEN** el sistema persiste una entrada con acción `editar` que incluye el valor anterior `100.00` y el valor nuevo `150.00` para el campo monto, además de actor y fecha/hora

#### Scenario: Edición sin cambio de monto no inventa un delta de monto
- **DADO** un gasto de `100.00` que solo cambia de concepto
- **WHEN** el usuario guarda la edición
- **THEN** la entrada de `editar` refleja el cambio de concepto (anterior y nuevo) y no reporta un cambio de monto

#### Scenario: Eliminación de gasto genera entrada de eliminar
- **DADO** un gasto persistido "Gasolina" de `240.00` pagado por Carla
- **WHEN** un participante autenticado elimina ese gasto
- **THEN** el sistema persiste una entrada con acción `eliminar`, snapshot del estado previo (concepto, monto, moneda, fecha, pagador, exclusiones) y conserva la entrada aunque el gasto deje de existir

#### Scenario: Persistencia al refrescar
- **DADO** entradas de bitácora ya persistidas para un viaje
- **WHEN** el anfitrión consulta de nuevo la bitácora
- **THEN** las entradas coinciden con lo almacenado en PostgreSQL (no se pierden al recargar)

### Requirement: El snapshot guarda solo lo necesario para reconstruir el gasto

El snapshot SHALL incluir los campos de negocio del gasto: `concepto`, `monto` (2 decimales), `moneda`, `tipo_cambio`, `fecha`, `pagador` (id y nombre) y listas de participantes incluidos y excluidos (id y nombre). En una edición, el sistema SHALL guardar `antes` y `despues` únicamente de los campos que cambiaron, más un identificador estable del gasto. El sistema MUST NOT guardar contraseñas, tokens, ni copias completas irrelevantes del resto del viaje.

Los montos en el snapshot MUST usarse con dos decimales; la bitácora no recalcula saldos ni aplica la regla de absorción de centavos (eso permanece en el cálculo de balances).

#### Scenario: Snapshot de creación incluye campos de negocio
- **DADO** un gasto nuevo de `160.00` USD con pagador Ana y un participante excluido
- **WHEN** se registra la creación
- **THEN** el snapshot posterior incluye concepto, monto `160.00`, moneda, tipo de cambio, fecha, pagador Ana y la exclusión

#### Scenario: Snapshot de edición de exclusiones
- **DADO** un gasto que incluye a Diego y luego se lo excluye
- **WHEN** se guarda la edición
- **THEN** la bitácora muestra el conjunto de excluidos anterior y el nuevo, sin duplicar el resto de campos que no cambiaron

#### Scenario: Gasto mínimo y montos inválidos no generan bitácora extra
- **DADO** un intento de crear un gasto con monto `0` o negativo
- **WHEN** la validación rechaza la solicitud
- **THEN** no se persiste ninguna entrada de bitácora

### Requirement: Solo el anfitrión puede consultar la bitácora

El sistema SHALL exponer la bitácora de un viaje únicamente al usuario autenticado cuyo `id` coincide con el `user_id` del viaje (anfitrión/creador). La consulta MUST devolver las entradas ordenadas de más reciente a más antigua. Cualquier otro participante autenticado MUST recibir HTTP 403. Un visitante no autenticado MUST ser redirigido al login. No SHALL existir operación de escritura pública sobre la bitácora.

#### Scenario: Anfitrión consulta la bitácora del viaje
- **DADO** Ana como creadora del viaje Samaipata y al menos una entrada persistida
- **WHEN** Ana solicita la bitácora del viaje
- **THEN** el sistema responde HTTP 200 con las entradas (actor, acción, fecha/hora, gasto afectado y deltas) ordenadas por fecha descendente

#### Scenario: Participante no anfitrión no puede ver la bitácora
- **DADO** Beto autenticado como participante del viaje cuyo anfitrión es Ana
- **WHEN** Beto solicita la bitácora del viaje
- **THEN** el sistema rechaza con HTTP 403 y no revela las entradas

#### Scenario: Visitante no autenticado
- **DADO** un visitante sin sesión
- **WHEN** intenta consultar la bitácora
- **THEN** el sistema bloquea el acceso y redirige al login (HTTP 401 / 302)

#### Scenario: Viaje sin eventos de gasto (lista vacía)
- **DADO** un viaje recién creado sin gastos
- **WHEN** el anfitrión consulta la bitácora
- **THEN** el sistema retorna una lista vacía con HTTP 200 sin errores

#### Scenario: Intento de mutar la bitácora
- **DADO** un anfitrión autenticado
- **WHEN** intenta crear, editar o eliminar una entrada de bitácora mediante un endpoint de mutación
- **THEN** el sistema no ofrece esa operación (404 o método no permitido) y las entradas existentes permanecen intactas
