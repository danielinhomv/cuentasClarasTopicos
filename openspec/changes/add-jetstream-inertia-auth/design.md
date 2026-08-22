## Context

Estado actual: Laravel 13.17, PHP 8.3+, PostgreSQL, `app.js` vacío, `routes/web.php` sirve `welcome` Blade, modelo `User` estándar sin Jetstream. No hay Inertia, Vue ni Fortify. Motivación: ver `proposal.md`. Comportamiento esperado: `specs/user-auth/spec.md`.

Restricciones: MVC + Inertia (controladores Laravel → páginas Vue, sin API REST de producto). Persistencia solo en PostgreSQL. Jetstream debe instalarse en una app “nueva”; este repositorio aún no tiene UI de producto, así que el riesgo de colisión es bajo pero hay que preservar `.env` PostgreSQL y no sobrescribir secretos.

## Goals / Non-Goals

**Goals:**

- Encajar Jetstream **Inertia (Vue 3)** como capa de identidad sobre Fortify.
- Dejar registro, login, logout, reset de contraseña y perfil persistidos y testeables.
- Alinear Vite/Tailwind con el scaffolding de Jetstream sin romper PostgreSQL.
- Dejar `User` listo para relaciones futuras (viajes/participantes/gastos), sin crearlas aún.

**Non-Goals:**

- No modelar Participante, Gasto ni liquidación en este diseño.
- No activar Teams ni SSR.
- No diseñar SMTP de producción; el reset puede usar el mailer local/log de Laravel.

## Decisions

### 1. Jetstream Inertia en lugar de Breeze o Fortify a mano

- **Elección:** `composer require laravel/jetstream` y `php artisan jetstream:install inertia` **sin** `--teams` y **sin** `--ssr`.
- **Por qué:** el `openspec/config.yaml` ya apunta a autenticación tipo Breeze/Fortify dentro de Inertia; Jetstream aporta Fortify + páginas Vue + perfil en un solo scaffolding, alineado con la petición explícita.
- **Alternativas:** Breeze Inertia (más liviano, menos perfil/2FA); Fortify + Inertia a mano (más control, más trabajo); Livewire (rompe el stack Inertia acordado).

### 2. Fortify como motor; Jetstream como UX y features

- **Elección:** no reimplementar login/registro. Fortify expone las rutas y Actions; Jetstream aporta layouts Vue (`Login`, `Register`, `Dashboard`, `Profile`) y `config/jetstream.php` / `config/fortify.php`.
- **Capa de reglas:**
  - **Validación de autenticación:** Actions de Fortify (`CreateNewUser`, `UpdateUserProfileInformation`, `UpdateUserPassword`, `ResetUserPassword`) — equivalentes a Form Requests del dominio; no crear Form Requests extra salvo que el Action generado no cubra un criterio del spec.
  - **Controladores:** los de Jetstream/Fortify publicados o del paquete; `routes/web.php` solo dashboard/`/` autenticado.
  - **Policies:** ninguna en este cambio (no hay recursos de negocio).
  - **Modelo `User`:** casts de password hashed, fillable, traits que inyecte Jetstream (`HasProfilePhoto`, `TwoFactorAuthenticatable` si el instalador los añade). Sin lógica de balances.
  - **Service:** no hace falta un servicio de dominio de auth.

### 3. Modelo de datos (Eloquent) en este cambio

Solo existe identidad. Los modelos de negocio **no se crean**.

| Modelo | Rol ahora | Relaciones ahora |
| --- | --- | --- |
| `User` | Cuenta autenticable (`users`) | Ninguna de negocio |
| Participante | Fuera de alcance | — |
| Gasto | Fuera de alcance | — |
| Deuda/Balance | Fuera de alcance (se calculará después; no es tabla obligatoria aquí) | — |

Migraciones: conservar `users` / `password_reset_tokens` / `sessions` existentes; aplicar las que añada Jetstream (p. ej. `two_factor_*`, `profile_photo_path`) **como archivos**. No ejecutar `migrate` en apply sin confirmación del equipo.

Sesión: driver configurado en Laravel (tabla `sessions` o el que ya use `.env`); **nunca** `localStorage` como fuente de verdad.

### 4. Features Jetstream habilitadas

- **Fortify:** registro, login, reset de contraseña, actualización de perfil y de contraseña. Verificación de email **deshabilitada** como requisito de producto (Fortify `Features::emailVerification()` apagado o no exigido), para no bloquear el desarrollo sin SMTP.
- **Jetstream:** perfil de cuenta. `Features::termsAndPrivacyPolicy()`, `Features::api()`, `Features::teams()` **off**. Foto de perfil y 2FA: dejar el default del instalador si no obliga infraestructura extra; si 2FA exige migraciones, aceptarlas pero no documentar 2FA como criterio de aceptación del spec.
- **Por qué:** cubre el spec sin Teams ni API REST.

### 5. Rutas y páginas Inertia

- Visitante: páginas Vue de Fortify/Jetstream (`/login`, `/register`, `/forgot-password`, etc.).
- Autenticado: `Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified' opcional])` según lo que genere el instalador. **Quitar `'verified'`** si la verificación de email está off, para no bloquear el dashboard.
- `/`: redirigir a dashboard si hay sesión, o a login si no (en lugar de `welcome`).
- Middleware Inertia `HandleInertiaRequests` comparte el `User` autenticado a Vue.

### 6. Frontend y Laravel 13

- Usar la versión más reciente de Jetstream compatible con Laravel 13 (el instalador Inertia debe omitir `import './bootstrap'` si Laravel 13 ya no trae `bootstrap.js`).
- Tras el instalador: `npm install` (archivos `package.json`); **no** asumir que apply corre `npm run build` sobre un entorno roto — sí dejar el proyecto compilable.
- Tailwind: Jetstream trae su setup; unificar con el Tailwind 4 ya presente, resolviendo conflictos a favor del stack que deje `npm run build` verde.

### 7. Tests y seeder

- Conservar/ajustar los feature tests que genera Jetstream (registro, login, password, perfil) como evidencia de los escenarios del spec.
- **No** seeder de Samaipata: este cambio no es un caso de uso de gastos. Opcional: `UserFactory` ya existente para tests; no seed de Ana/Beto/Carla/Diego.

### 8. Compatibilidad con apply

Orden de capas (reflejado en `tasks.md`): dependencias Composer → `jetstream:install inertia` → config features → modelo `User`/migraciones publicadas → rutas → páginas Inertia (las genera el instalador; solo ajustar `/` y `verified`) → tests. No migrate automático.

## Risks / Trade-offs

- [Jetstream en app no vacía] → El repo aún es skeleton; revisar diffs de `routes/web.php`, `package.json` y Vite después del instalador y no reintroducir `welcome` como home.
- [Laravel 13 vs stubs Jetstream (`bootstrap.js`, Tailwind 4)] → Instalar Jetstream actualizado; si el build falla por imports, quitar `bootstrap` y alinear Vite según el PR de Jetstream para Laravel 13+.
- [Middleware `verified` con email verification off] → Quitar `verified` de las rutas autenticadas o los usuarios no entrarían al dashboard.
- [Sanctum incluido aunque no haya API de producto] → Aceptar la dependencia de sesión SPA de Jetstream; no publicar tokens de API.
- [2FA/fotos extra en `User`] → Columnas de más no rompen el spec; no implementar UI de producto alrededor de ellas.
- [Reset de contraseña sin SMTP] → Usar `MAIL_MAILER=log` en desarrollo; el spec exige el mecanismo, no un proveedor de correo real.

## Migration Plan

1. Instalar paquetes y scaffolding en una rama.
2. Revisar `config/fortify.php` y `config/jetstream.php` (features).
3. Confirmar con el equipo antes de `php artisan migrate` contra PostgreSQL.
4. `npm install` y `npm run build` / `npm run dev`.
5. Correr tests de autenticación.

**Rollback:** revertir el commit de scaffolding; si ya se migró, revertir migraciones de Jetstream (`migrate:rollback` solo con confirmación) y restaurar `welcome` si hiciera falta. No hay datos de negocio que migrar.

## Open Questions

- Si más adelante el “viaje” se parece a un Team de Jetstream, se evaluará en otro change; no se decide aquí.
