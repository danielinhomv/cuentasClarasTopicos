## Purpose

Permite que los viajes cuenten con un código de invitación único para que usuarios registrados se unan al grupo colaborativamente, eliminando la creación arbitraria de participantes sin cuenta.

## ADDED Requirements

### Requirement: Generación de código único de invitación al crear viaje
El sistema SHALL asignar automáticamente un código alfanumérico único de 8 caracteres a cada viaje recién creado y registrar al usuario creador como primer participante del viaje.

#### Scenario: Creación de viaje genera código y añade al creador
- **DADO** un usuario autenticado "Ana"
- **WHEN** crea un nuevo viaje "Samaipata"
- **THEN** el viaje se crea con un `codigo_invitacion` no nulo ni repetido, y existe un registro en `participantes` con el `user_id` de Ana y nombre "Ana"

### Requirement: Unión a un viaje mediante código de invitación
El sistema SHALL permitir que un usuario autenticado ingrese un código de invitación para unirse al viaje correspondiente como participante.

#### Scenario: Usuario registrado se une con éxito
- **DADO** un usuario "Beto" y un viaje existente con código `SAMA1234`
- **WHEN** Beto envía una petición para unirse al viaje con el código `SAMA1234`
- **THEN** el sistema añade a Beto a los `participantes` del viaje y el viaje aparece en su listado de viajes

#### Scenario: Intento de unirse a un viaje en el que ya participa
- **DADO** un usuario "Beto" que ya es participante del viaje con código `SAMA1234`
- **WHEN** intenta unirse de nuevo con el mismo código
- **THEN** el sistema rechaza la solicitud indicando que ya forma parte de este viaje

#### Scenario: Código de invitación inexistente o inválido
- **DADO** un usuario autenticado
- **WHEN** intenta unirse con un código que no pertenece a ningún viaje
- **THEN** el sistema rechaza la solicitud con un mensaje de error claro

### Requirement: Despliegue y copia del código de invitación en la interfaz
El sistema SHALL mostrar el código de invitación en la pantalla de detalle del viaje con un botón para copiarlo al portapapeles.

#### Scenario: Visualización del código en el panel del viaje
- **DADO** un participante o dueño en la pantalla `/viajes/{viaje}`
- **WHEN** visualiza el viaje
- **THEN** en la cabecera y en la sección de participantes se muestra el código de invitación con opción de copiado rápido
