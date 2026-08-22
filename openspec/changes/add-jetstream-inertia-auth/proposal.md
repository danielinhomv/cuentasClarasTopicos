## Why

Cuentas Claras aún es un esqueleto de Laravel 13 sin autenticación: no hay Jetstream, Fortify, Inertia ni páginas Vue. Sin usuarios autenticados no se puede asociar viajes, participantes ni gastos a una cuenta persistente en PostgreSQL. Hay que instalar y configurar Laravel Jetstream con el stack Inertia ahora, como base de identidad, antes de los requisitos de negocio.

## What Changes

- Instalar `laravel/jetstream` (y Fortify, que Jetstream usa internamente) y ejecutar el instalador **Inertia** (Vue + Tailwind).
- Publicar y ajustar la configuración de Jetstream/Fortify para registro, inicio de sesión, cierre de sesión, recuperación de contraseña y perfil de cuenta.
- Reemplazar la vista Blade `welcome` como único punto de entrada: visitantes no autenticados verán login/registro; usuarios autenticados llegarán al dashboard de Jetstream.
- Extender el modelo `User` y las migraciones que Jetstream/Fortify requieran (p. ej. columnas de perfil/2FA si el instalador las añade).
- Instalar dependencias npm del stack Inertia (Vue, `@inertiajs/vue3`, plugins de Vite) y dejar el frontend compilable con Vite.
- Añadir tests de feature del scaffolding de autenticación que genera Jetstream.

**BREAKING:** la ruta `/` deja de devolver la vista `welcome` de Laravel; el flujo de entrada pasa a autenticación Inertia. No hay API REST pública previa que romper.

### Requisitos funcionales mínimos que cubre

Este cambio **no implementa** participantes, gastos, edición, saldos ni liquidación.

Cubre de forma **parcial y habilitadora** el requisito de **persistencia**: las cuentas de usuario (identidad) se guardan en PostgreSQL y sobreviven al refrescar la página. El dominio de gastos sigue fuera de este cambio.

### Fuera de alcance

- Equipos Jetstream (`--teams`): los “viajes” del producto no se modelan como Teams de Jetstream.
- SSR de Inertia (`--ssr`).
- Tokens de API / Laravel Sanctum como API REST separada (el producto usa Inertia, no API pública).
- Participantes, gastos, edición, saldos, liquidación y seeders del escenario Samaipata.
- Políticas de autorización sobre recursos de negocio (aún no existen).
- Verificación de correo obligatoria como bloqueo de producto (se deja la opción de Jetstream/Fortify, pero no se exige un flujo SMTP de producción).
- Personalización visual de marca más allá de lo que deja el scaffolding (nombres de app, copy mínimo).

### Supuestos

- Stack Inertia de Jetstream = **Vue 3**, no React.
- Sin `--teams` y sin `--ssr`.
- Características Jetstream habilitadas de forma conservadora: registro, login, logout, reset de contraseña y perfil (nombre, email, contraseña). Foto de perfil y 2FA se dejan como las deja el instalador/config por defecto, salvo que choquen con Laravel 13; no se promocionan como requisitos del producto.
- `apply` no ejecutará `migrate` ni `db:seed` sobre la base real; solo dejará archivos y comandos documentados.

## Capabilities

### New Capabilities

- `user-auth`: Registro, autenticación, cierre de sesión, recuperación de contraseña y gestión básica de perfil mediante Laravel Jetstream + Inertia, con persistencia de usuarios en PostgreSQL.

### Modified Capabilities

- *(ninguna; no hay specs principales aún)*

## Impact

- **PHP:** `composer.json` (`laravel/jetstream` y dependencias transitivas Fortify/Sanctum según el paquete), `app/Models/User.php`, `app/Actions/Fortify`, `app/Actions/Jetstream`, `app/Providers`, `config/jetstream.php`, `config/fortify.php`, rutas `web`/`fortify`.
- **Frontend:** `package.json` (Vue, Inertia, Tailwind según Jetstream), `resources/js` (páginas Vue de autenticación y layout), Vite.
- **Datos:** migraciones adicionales sobre `users` y tablas que publique Jetstream; PostgreSQL como único almacén (sin localStorage para sesión de producto; la sesión web sigue el mecanismo estándar de Laravel).
- **Tests:** suite de feature tests de Jetstream.
- **No impacta** modelos de Participante/Gasto (aún no existen).
