## Purpose

Permite especificar qué participantes de un viaje quedan excluidos de la repartición de un gasto puntual, validando que al menos un participante permanezca incluido y asegurando la integridad referencial en PostgreSQL.

## ADDED Requirements

### Requirement: Usuario autenticado puede excluir participantes de un gasto

El sistema SHALL permitir al propietario de un viaje indicar una lista de identificadores de participantes que NO participarán en la división de un gasto específico. Por defecto (sin exclusiones), todos los participantes del viaje se consideran incluidos en el reparto.

#### Scenario: Exclusión exitosa de participantes en un gasto
- **DADO** un viaje con los participantes "Ana", "Beto", "Carla" y "Diego", y un gasto de "Gasolina" por `240.00`
- **WHEN** el usuario registra el gasto indicando que "Diego" está excluido
- **THEN** el sistema registra el gasto y persiste la exclusión de "Diego" en la tabla pivote de exclusiones en PostgreSQL

#### Scenario: Intento de excluir a todos los participantes (caso borde)
- **DADO** un viaje con 4 participantes registrados
- **WHEN** el usuario intenta registrar o editar un gasto marcando como excluidos a los 4 participantes simultáneamente
- **THEN** el sistema rechaza la solicitud con un error de validación indicando que al menos un participante debe quedar incluido en la división del gasto

#### Scenario: Exclusión de un participante que no pertenece al viaje (caso borde)
- **DADO** un participante "Zulma" que pertenece a un viaje diferente
- **WHEN** el usuario intenta asociar a "Zulma" como participante excluida en un gasto del viaje actual
- **THEN** el sistema rechaza la operación con un error de validación de clave foránea / pertenencia

### Requirement: Usuario autenticado puede actualizar o remover las exclusiones de un gasto

El sistema SHALL permitir modificar la lista de participantes excluidos al editar un gasto, permitiendo añadir nuevos excluidos o removerlos para que el gasto aplique nuevamente a todos los participantes del viaje.

#### Scenario: Actualización de la lista de participantes excluidos
- **DADO** un gasto que inicialmente no tenía excluidos
- **WHEN** el usuario edita el gasto agregando a "Carla" como excluida
- **THEN** el sistema sincroniza la tabla pivote de exclusiones para reflejar que "Carla" ahora está excluida

#### Scenario: Remover todas las exclusiones de un gasto
- **DADO** un gasto con "Beto" y "Carla" previamente excluidos
- **WHEN** el usuario edita el gasto enviando una lista de exclusiones vacía
- **THEN** el sistema elimina todas las relaciones en la tabla pivote y el gasto vuelve a aplicar a todos los integrantes del viaje

### Requirement: Integridad y persistencia de las exclusiones

El sistema MUST mantener la integridad referencial en PostgreSQL eliminando automáticamente las exclusiones cuando un gasto o un participante es eliminado de la base de datos.

#### Scenario: Eliminación de exclusiones al borrar un gasto
- **DADO** un gasto con participantes excluidos registrados en la tabla pivote
- **WHEN** el usuario elimina el gasto
- **THEN** el sistema elimina el gasto y todas sus filas de exclusión asociadas en PostgreSQL sin dejar registros huérfanos

#### Scenario: Persistencia al recargar
- **DADO** un gasto con exclusiones registradas
- **WHEN** se consulta el detalle del gasto en peticiones posteriores o tras recargar
- **THEN** las exclusiones retornadas coinciden con las filas almacenadas en la base de datos
