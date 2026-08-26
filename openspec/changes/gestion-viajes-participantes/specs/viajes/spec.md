## Purpose

Permite a los usuarios autenticados crear, listar, ver en detalle, actualizar y eliminar viajes en Cuentas Claras, asegurando el aislamiento entre cuentas y la persistencia en PostgreSQL.

## ADDED Requirements

### Requirement: Usuario autenticado puede registrar un nuevo viaje

El sistema SHALL permitir a un usuario autenticado crear un nuevo viaje proporcionando un nombre obligatorio y una descripción opcional. El viaje SHALL quedar asociado permanentemente al usuario autenticado como su propietario en PostgreSQL.

#### Scenario: Creación exitosa de viaje
- **DADO** un usuario autenticado en la plataforma
- **WHEN** envía el formulario de creación de viaje con un nombre válido (ej. "Viaje a Samaipata") y una descripción opcional
- **THEN** el sistema persiste el nuevo viaje en PostgreSQL asociado al ID del usuario y retorna los datos del viaje creado

#### Scenario: Nombre de viaje vacío o inválido
- **DADO** un usuario autenticado
- **WHEN** intenta crear un viaje con el nombre vacío o con solo espacios en blanco
- **THEN** el sistema no crea el registro y responde con un error de validación indicando que el nombre es obligatorio

### Requirement: Usuario autenticado puede listar y consultar el detalle de sus viajes

El sistema SHALL permitir al usuario autenticado consultar la lista de todos los viajes que le pertenecen y acceder al detalle de un viaje específico incluyendo sus participantes asociados. El sistema SHALL impedir el acceso a viajes pertenecientes a otros usuarios.

#### Scenario: Listado de viajes propios
- **DADO** un usuario autenticado con viajes creados previamente
- **WHEN** solicita el listado de viajes
- **THEN** el sistema retorna únicamente los viajes que pertenecen a dicho usuario

#### Scenario: Aislamiento entre usuarios
- **DADO** dos usuarios registrados ("Usuario A" y "Usuario B"), cada uno con sus propios viajes creados
- **WHEN** el "Usuario A" solicita su listado de viajes
- **THEN** el sistema no incluye ningún viaje creado por el "Usuario B"

#### Scenario: Consulta de detalle de viaje propio
- **DADO** un usuario autenticado propietario de un viaje existente
- **WHEN** consulta el detalle del viaje mediante su identificador
- **THEN** el sistema retorna la información del viaje y la lista de participantes vinculados

#### Scenario: Intento de consultar detalle de viaje ajeno
- **DADO** un usuario autenticado que intenta acceder al identificador de un viaje que pertenece a otro usuario
- **WHEN** envía la petición de consulta
- **THEN** el sistema deniega el acceso con un error de autorización HTTP 403 Forbidden o 404 Not Found

### Requirement: Usuario autenticado puede editar y eliminar sus viajes

El sistema SHALL permitir al usuario autenticado modificar el nombre o descripción de sus viajes, así como eliminarlos. La eliminación de un viaje SHALL eliminar en cascada todos los participantes registrados dentro de dicho viaje.

#### Scenario: Edición exitosa de viaje propio
- **DADO** un usuario autenticado y un viaje existente de su propiedad
- **WHEN** envía una solicitud de actualización con un nuevo nombre válido (ej. "Samaipata 2026")
- **THEN** el sistema actualiza los datos en PostgreSQL y retorna la información modificada

#### Scenario: Eliminación exitosa de viaje
- **DADO** un usuario autenticado y un viaje propio con participantes asociados
- **WHEN** solicita la eliminación del viaje
- **THEN** el sistema elimina el registro del viaje y sus participantes asociados en PostgreSQL

#### Scenario: Intento de modificar o eliminar viaje ajeno
- **DADO** un usuario autenticado que intenta editar o eliminar un viaje perteneciente a otro usuario
- **WHEN** envía la solicitud de modificación o eliminación
- **THEN** el sistema rechaza la operación con un error de autorización HTTP 403 Forbidden

### Requirement: Persistencia y protección de acceso a viajes

El sistema MUST persistir todos los cambios en PostgreSQL y exigir autenticación obligatoria para cualquier operación sobre viajes.

#### Scenario: Persistencia al refrescar
- **DADO** un viaje recién creado o modificado
- **WHEN** se consulta nuevamente la base de datos tras recargar o en peticiones posteriores
- **THEN** los datos reflejan exactamente el estado almacenado en PostgreSQL

#### Scenario: Acceso no autenticado
- **DADO** un visitante sin sesión activa
- **WHEN** intenta acceder a cualquier endpoint de gestión de viajes
- **THEN** el sistema bloquea la solicitud y redirige al flujo de inicio de sesión (HTTP 401 / 302 a login)
