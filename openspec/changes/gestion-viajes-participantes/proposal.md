## Why

Para que los usuarios puedan registrar gastos compartidos y calcular saldos en Cuentas Claras, primero es indispensable contar con la estructura base de un **Viaje** (contenedor del evento) y sus **Participantes** (amigos que forman parte del viaje). Sin esta entidad de dominio, no existe un contexto al cual asociar quién paga o quién debe dinero.

## What Changes

- Creación de la migración, modelo Eloquent y relaciones para la entidad `Viaje` (asociada al `User` autenticado propietario).
- Creación de la migración, modelo Eloquent y relaciones para la entidad `Participante` (asociada a un `Viaje`).
- **Participantes sin cuenta:** el propietario del viaje puede agregar participantes indicando únicamente su nombre, sin requerir que tengan una cuenta de usuario. El campo `user_id` en `participantes` es nullable; los participantes sin cuenta participan en gastos, saldos y liquidaciones de forma idéntica a los registrados.
- **Coexistencia con invitación por código:** los usuarios registrados siguen pudiendo unirse al viaje mediante el código de invitación (flujo de `invitacion-viajes-auth-neon`). Ambos mecanismos conviven: alta manual por nombre (sin cuenta) y unión por código (con cuenta).
- Creación de Form Requests para validación de datos de entrada (`StoreViajeRequest`, `UpdateViajeRequest`, `StoreParticipanteRequest`, `UpdateParticipanteRequest`).
- Creación de Policies (`ViajePolicy`, `ParticipantePolicy`) para garantizar que un usuario solo pueda gestionar sus propios viajes y participantes.
- Creación de Controladores backend (`ViajeController`, `ParticipanteController`) con respuestas en formato JSON / datos estructurados para los endpoints.
- Definición de rutas web agrupadas y nombradas bajo middleware `auth`.
- Seeder con los datos base del escenario de referencia de **Samaipata** (Viaje "Viaje a Samaipata" con los participantes: Ana, Beto, Carla y Diego).
- **Restricción de eliminación:** no se puede eliminar un participante si tiene deudas pendientes (como deudor o acreedor, o saldo distinto de `0.00`) o si ya participó en un gasto (pagador o incluido en la división). Si no tiene deudas y no participó en gastos, la eliminación sigue permitida. El rechazo se comunica con un mensaje flash claro.

### Casos de uso del backend que cubre

Esta propuesta cubre explícitamente:
- **Caso de uso 7: Gestionar viaje** (alta, listado, detalle, edición y eliminación de viajes).
- **Caso de uso 1: Gestionar participantes** (alta, listado, edición y eliminación de participantes dentro de un viaje).

### Fuera de alcance

- **Vistas / frontend Inertia / Blade / componentes Vue**: este cambio es **SOLO BACKEND**; no se generan artefactos de presentación visual.
- **Gastos y exclusiones** (Casos de uso 2 y 3): el registro de gastos, montos y participantes excluidos se abordará en el siguiente módulo.
- **Cálculo de saldos y liquidación** (Casos de uso 4, 5 y 6): los algoritmos de balances y transferencias mínimas se implementarán en su propio módulo.
- **Invitaciones públicas o multi-usuario colaborativo en tiempo real**: cada viaje es administrado por el usuario autenticado propietario.

## Capabilities

### New Capabilities

- `viajes`: Alta, listado, detalle, edición y eliminación de viajes pertenecientes al usuario autenticado, con persistencia en PostgreSQL y protección mediante policies.
- `participantes`: Alta, listado, edición y eliminación de participantes asociados a un viaje, validando unicidad de nombres dentro del mismo viaje.

### Modified Capabilities

- `participantes`: ampliado para soportar participantes sin cuenta (`user_id` nullable), manteniendo la unicidad de nombres por viaje y la paridad funcional en gastos y cálculos. La eliminación queda condicionada a no tener deudas pendientes ni haber participado en gastos.

## Impact

- **Modelos PHP:** creación de `app/Models/Viaje.php` y `app/Models/Participante.php`, extensión de relaciones en `app/Models/User.php`.
- **Base de datos:** nuevas migraciones para las tablas `viajes` y `participantes` con claves foráneas e integridad referencial en PostgreSQL.
- **Seguridad / Autorización:** `app/Policies/ViajePolicy.php` y `app/Policies/ParticipantePolicy.php`.
- **Validación:** `app/Http/Requests/Viaje/` y `app/Http/Requests/Participante/`.
- **Rutas:** nuevas rutas agrupadas bajo el prefijo `viajes` y `viajes/{viaje}/participantes` en `routes/web.php`.
- **Seeders y Tests:** actualización de `DatabaseSeeder.php` con el escenario de Samaipata y nuevos tests de Feature.
