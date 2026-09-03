## Why

Actualmente, el creador de un viaje puede añadir participantes escribiendo cualquier nombre de texto libre de forma arbitraria, sin vincular a usuarios reales registrados en el sistema. Para convertir Cuentas Claras en una plataforma multiusuario auténtica y colaborativa, los viajes deben tener un **código de invitación único** que permita a otros usuarios registrados unirse al grupo. Además, el dashboard por defecto de Jetstream resulta redundante, y las pantallas de autenticación (Login, Registro, Recuperación de contraseña) aún conservan el tema blanco original, desentonando con la experiencia Dark Neon del resto de la aplicación.

## What Changes

- **Código de Invitación para Viajes:**
  - Migración para agregar la columna `codigo_invitacion` (string alfanumérico único de 8 caracteres, ej. `SAM78X9A`) a la tabla `viajes`.
  - Migración para vincular `user_id` (foreign key hacia `users`) a la tabla `participantes`.
  - Al crear un viaje, el usuario creador se añade automáticamente como su primer participante oficial.
  - **Corrección de flujo:** Se elimina la potestad del creador de escribir nombres arbitrarios libremente. En su lugar, el creador visualiza y copia el código de invitación del viaje para compartirlo con sus amigos.
  - **Flujo de Unión:** Los usuarios registrados cuentan con la opción *"Unirse a un viaje con código"* en su panel de viajes. Al ingresar un código válido, se registran como participantes del viaje y este pasa a figurar en su lista de viajes.
  - Actualización de autorizaciones: un usuario puede visualizar y registrar gastos en viajes donde sea el creador o donde se haya unido como participante.

- **Eliminación del Dashboard por Defecto:**
  - Configuración de `config/fortify.php` para redirigir a `/viajes` tras autenticarse (`'home' => '/viajes'`).
  - Redirección de `/dashboard` y `/` directamente hacia `/viajes`.
  - Retiro del enlace "Dashboard" en la barra de navegación de `AppLayout.vue`.
  - Eliminación de la vista obsoleta `Dashboard.vue`.

- **Rediseño Dark Neon de Autenticación:**
  - Rediseño de `Login.vue`: fondo oscuro `zinc-950`, tarjeta `zinc-900`, inputs con borde cian en foco, botón principal neón y checkbox estilizado.
  - Rediseño de `Register.vue`: formulario de registro con la misma línea visual Dark Neon.
  - Rediseño de `ForgotPassword.vue`, `ResetPassword.vue`, `AuthenticationCard.vue` y `Checkbox.vue`.

### Casos de uso cubiertos

- **Caso de uso 1: Gestionar participantes** (adaptado al modelo de usuarios registrados que se unen mediante código de invitación).
- **Caso de uso 7: Gestionar viaje** (código de invitación único y listado de viajes propios o donde participa).
- **Caso de uso 8: Autenticar usuario** (rediseño completo de login/registro con tema Dark Neon y redirección directa al núcleo de la app).

### Fuera de alcance

- Envío de correos electrónicos automáticos con links de invitación (se comparte el código alfanumérico directamente por WhatsApp, mensaje o copia al portapapeles).
- Roles jerárquicos complejos o permisos granulares de viaje (se mantiene modelo creador / participante).

## Capabilities

### New Capabilities
- `invitacion-viajes`: Generación de código de invitación alfanumérico único por viaje y flujo de unión para usuarios registrados.
- `auth-dark-neon`: Interfaz de inicio de sesión, registro y restablecimiento de contraseña bajo el sistema de diseño Dark Neon, suprimiendo el dashboard por defecto y navegando directo a `/viajes`.

### Modified Capabilities
- *(ninguna)*

## Impact

- **Base de datos:** Migraciones sobre `viajes` (`codigo_invitacion`) y `participantes` (`user_id`).
- **Backend:** `ViajeController`, `ViajePolicy`, `Viaje` y `Participante` models.
- **Rutas y Configuración:** `routes/web.php` y `config/fortify.php`.
- **Frontend:** `Login.vue`, `Register.vue`, `AuthenticationCard.vue`, `Viajes/Index.vue`, `Viajes/Show.vue`.
