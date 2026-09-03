## Purpose

Proporciona una experiencia de autenticación visualmente cohesionada en estilo Dark Neon y orienta el flujo de navegación directo a la gestión de viajes, suprimiendo pantallas intermedias innecesarias.

## ADDED Requirements

### Requirement: Interfaz de Login y Registro en Dark Neon
El sistema SHALL presentar los formularios de inicio de sesión, registro y recuperación de credenciales sobre fondo `zinc-950`, tarjetas `zinc-900`, campos de entrada estilizados y botones con acento neón.

#### Scenario: Visualización del formulario de inicio de sesión
- **DADO** un usuario visitante no autenticado
- **WHEN** accede a la ruta `/login`
- **THEN** la pantalla se muestra con fondo oscuro, tarjeta `zinc-900`, logotipo de Cuentas Claras, inputs con acento cian en foco y botón primario con gradiente luminoso

#### Scenario: Visualización del formulario de registro
- **DADO** un visitante
- **WHEN** accede a la ruta `/register`
- **THEN** la pantalla se renderiza con el mismo diseño Dark Neon limpio y formulario responsivo

### Requirement: Redirección directa a Viajes y eliminación del dashboard
El sistema SHALL dirigir a los usuarios autenticados directamente a `/viajes`, redirigiendo cualquier acceso a `/dashboard` hacia `/viajes`.

#### Scenario: Redirección post-autenticación
- **DADO** un usuario que inicia sesión exitosamente
- **WHEN** completa el formulario de login
- **THEN** el sistema lo redirige directamente a la ruta `/viajes`

#### Scenario: Acceso a la ruta /dashboard
- **DADO** un usuario autenticado
- **WHEN** navega a la URL `/dashboard` o `/`
- **THEN** el sistema lo redirige automáticamente a `/viajes`
