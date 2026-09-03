## Context

Ver motivación en `proposal.md` y requisitos de negocio en `specs/viajes/spec.md` y `specs/participantes/spec.md`.

Tras la implementación de la autenticación de usuarios (`User`), el sistema requiere las primeras entidades del dominio de Cuentas Claras: `Viaje` y `Participante`. Este diseño establece la persistencia en PostgreSQL, las relaciones Eloquent, las capas de validación y autorización, y la estructura de rutas web para los endpoints de backend sin incluir elementos visuales de frontend.

## Goals / Non-Goals

**Goals:**
- Diseñar el esquema de base de datos relacional en PostgreSQL para `viajes` y `participantes` con claves foráneas e integridad referencial.
- Implementar los modelos Eloquent con sus relaciones (`User` $\leftrightarrow$ `Viaje` $\leftrightarrow$ `Participante`).
- Definir Form Requests para validación de datos de entrada y unicidad de nombres de participantes por viaje.
- Implementar Policies de autorización para asegurar que solo el usuario propietario pueda gestionar sus viajes y participantes.
- Exponer controladores backend estructurados con rutas nombradas en `routes/web.php`.
- Proveer el seeder oficial del escenario de referencia **Samaipata** (Viaje con Ana, Beto, Carla y Diego).

**Non-Goals:**
- No modelar aún la tabla `gastos` ni la tabla pivote de exclusiones (se modelarán en el Módulo 3).
- No implementar lógica de cálculo de saldos ni algoritmo de liquidación (Módulo 4).
- No persistir un campo `estado` de viaje en PostgreSQL (el estado visible en UI es derivado).

> **Ajuste de alcance (apply):** el proposal original era solo backend. Por instrucción explícita del equipo en apply, este change también cubre la interfaz Inertia + Vue + Tailwind, alineada con Jetstream.

## Decisions

### 1. Modelo de Datos y Esquema PostgreSQL

Se definen dos tablas en PostgreSQL con claves foráneas e índices:

```
[users] (1) <──── (N) [viajes] (1) <──── (N) [participantes]
```

- **Tabla `viajes`:**
  - `id` (bigIncrements, PK)
  - `user_id` (bigInteger, FK a `users.id` con `ON DELETE CASCADE`)
  - `nombre` (string 150, NOT NULL)
  - `descripcion` (text, NULLABLE)
  - `created_at`, `updated_at` (timestamps)
  - *Índice:* `user_id` para búsquedas eficientes de viajes por usuario.

- **Tabla `participantes`:**
  - `id` (bigIncrements, PK)
  - `viaje_id` (bigInteger, FK a `viajes.id` con `ON DELETE CASCADE`)
  - `user_id` (bigInteger, FK a `users.id` nullable, `ON DELETE CASCADE`) — `NULL` indica participante sin cuenta
  - `nombre` (string 100, NOT NULL)
  - `created_at`, `updated_at` (timestamps)
  - *Restricción de unicidad:* `UNIQUE(viaje_id, nombre)` para evitar participantes con nombres duplicados dentro de un mismo viaje.

### 2. Modelos Eloquent y Relaciones

- **`App\Models\User`:**
  - `hasMany(Viaje::class)`: un usuario posee múltiples viajes.
- **`App\Models\Viaje`:**
  - `belongsTo(User::class)`: un viaje pertenece a su usuario creador.
  - `hasMany(Participante::class)`: un viaje tiene múltiples participantes.
  - `$fillable = ['nombre', 'descripcion', 'user_id']`.
- **`App\Models\Participante`:**
  - `belongsTo(Viaje::class)`: un participante pertenece a un viaje.
  - `belongsTo(User::class)` (nullable): vincula al usuario registrado cuando existe; `null` = participante sin cuenta.
  - `$fillable = ['nombre', 'viaje_id', 'user_id']`.

### 3. Distribución de Responsabilidades por Capas

Siguiendo la arquitectura limpia de Laravel:

| Capa | Responsabilidad | Clases / Archivos |
| :--- | :--- | :--- |
| **Modelos** | Estructura, relaciones Eloquent y casts | `Viaje.php`, `Participante.php` |
| **Validación** | Reglas de entrada y unicidad contextual | `StoreViajeRequest`, `UpdateViajeRequest`, `StoreParticipanteRequest`, `UpdateParticipanteRequest` |
| **Autorización** | Control de acceso por propiedad del recurso | `ViajePolicy`, `ParticipantePolicy` |
| **Controladores** | Coordinación HTTP y respuesta estructurada | `ViajeController`, `ParticipanteController` |
| **Rutas** | Definición de endpoints protegidos por auth | `routes/web.php` |

### 4. Reglas de Validación en Form Requests

- `StoreViajeRequest` / `UpdateViajeRequest`:
  - `nombre`: `['required', 'string', 'min:2', 'max:150']`
  - `descripcion`: `['nullable', 'string', 'max:1000']`
- `StoreParticipanteRequest`:
  - `nombre`: `['required', 'string', 'min:2', 'max:100', Rule::unique('participantes')->where('viaje_id', $viajeId)]`
- `UpdateParticipanteRequest`:
  - `nombre`: `['required', 'string', 'min:2', 'max:100', Rule::unique('participantes')->where('viaje_id', $viajeId)->ignore($participanteId)]`

### 5. Rutas Web Agrupadas y Nombradas

En `routes/web.php` bajo middleware `['auth']`:

```php
Route::middleware(['auth'])->group(function () {
    // Viajes
    Route::get('/viajes', [ViajeController::class, 'index'])->name('viajes.index');
    Route::post('/viajes', [ViajeController::class, 'store'])->name('viajes.store');
    Route::get('/viajes/{viaje}', [ViajeController::class, 'show'])->name('viajes.show');
    Route::put('/viajes/{viaje}', [ViajeController::class, 'update'])->name('viajes.update');
    Route::delete('/viajes/{viaje}', [ViajeController::class, 'destroy'])->name('viajes.destroy');

    // Participantes anidados en Viaje
    Route::get('/viajes/{viaje}/participantes', [ParticipanteController::class, 'index'])->name('viajes.participantes.index');
    Route::post('/viajes/{viaje}/participantes', [ParticipanteController::class, 'store'])->name('viajes.participantes.store');
    Route::put('/participantes/{participante}', [ParticipanteController::class, 'update'])->name('participantes.update');
    Route::delete('/participantes/{participante}', [ParticipanteController::class, 'destroy'])->name('participantes.destroy');
});
```

### 6. Dos vías de alta de participantes

El sistema admite **dos mecanismos complementarios** para incorporar participantes a un viaje:

| Mecanismo | Quién lo usa | `user_id` | Endpoint |
| :--- | :--- | :--- | :--- |
| **Alta manual por nombre** | Propietario del viaje | `NULL` | `POST /viajes/{viaje}/participantes` |
| **Unión por código de invitación** | Usuario registrado | ID del usuario | `POST /viajes/unirse` |

Reglas:
- Solo el **propietario** del viaje puede agregar participantes manualmente (policy `ParticipantePolicy::create`).
- Los participantes sin cuenta (`user_id = NULL`) participan en gastos, exclusiones, saldos y liquidación de forma **idéntica** a los registrados.
- El flujo de invitación por código (`invitacion-viajes-auth-neon`) se mantiene intacto y no se ve afectado.
- Al crear un gasto, los participantes incluidos se registran en `gasto_participantes` (snapshot); los participantes sin cuenta se incluyen igual que los demás.

### 7. Seeder y Datos de Referencia

El `DatabaseSeeder.php` poblará:
1. Usuario propietario de prueba (`ana@example.com`).
2. Viaje: `"Viaje a Samaipata"` con descripción `"Fin de semana con amigos"`.
3. 4 Participantes: `"Ana"`, `"Beto"`, `"Carla"`, `"Diego"`.

### 8. Presentación Inertia (UI)

Stack: Laravel Jetstream + Inertia.js + Vue 3 + Tailwind, reutilizando `AppLayout`, `FormSection`, `TextInput`, `PrimaryButton`, `DangerButton`, `SecondaryButton`, `InputLabel`, `InputError` y `ConfirmationModal`. Sin paleta ni tipografía distintas a las del resto de la app.

**Rutas de pantalla (además de las mutaciones del backend):**
- `GET /viajes/create` → `viajes.create` (formulario de alta)
- `GET /viajes/{viaje}/edit` → `viajes.edit` (formulario de edición)
- Los `GET` de listado y detalle (`viajes.index`, `viajes.show`) renderizan páginas Inertia; `POST`/`PUT`/`DELETE` redirigen con flash Jetstream (`flash.banner` / `flash.bannerStyle`).
- La gestión de participantes vive en el detalle del viaje. `viajes.participantes.index` redirige a `viajes.show` para no duplicar pantallas.

**Pantallas:**
1. **Listado (`Viajes/Index`)** — búsqueda por nombre (debounce), filtro de estado derivado, paginación (10 por página). Desktop: tabla. Mobile: cards. Badge de estado: `Sin participantes` (0) / `Con participantes` (≥1).
2. **Alta/edición (`Viajes/Create`, `Viajes/Edit`)** — `nombre` obligatorio (min 2, max 150) y `descripcion` opcional. Validación en cliente al escribir; errores de servidor vía `useForm`. CTA primario alineado a Jetstream.
3. **Detalle (`Viajes/Show`)** — resumen del viaje, conteo, acciones rápidas (editar, eliminar, agregar participante). Lista filtrable de participantes con badge "Creador" (usuario propietario) o "Sin cuenta" (`user_id` null). Formulario de alta manual por nombre (solo propietario), panel de invitación por código (usuarios registrados), edición en modal, eliminación con `ConfirmationModal`. Duplicados: el backend rechaza; la UI deshabilita agregar si el nombre (trim, case-insensitive) ya está en la lista cargada.
4. **Estados UX** — empty state con CTA; skeleton/`processing` en envíos; confirmación destructiva de viaje (cascada de participantes) y de participante; banner de éxito/error; labels asociados, foco visible (`focus:ring`) y layout mobile-first (`px-4`, tabla → cards).

**Controladores:** respuestas Inertia (no JSON API). Las policies y Form Requests del backend no cambian.

## Risks / Trade-offs

- **[Acceso no autorizado a viajes ajenos]** $\rightarrow$ Mitigación: Uso estricto de `ViajePolicy` y `ParticipantePolicy` invocadas mediante `$this->authorize(...)` en cada acción del controlador.
- **[Nombres duplicados en el mismo viaje]** $\rightarrow$ Mitigación: Restricción `UNIQUE(viaje_id, nombre)` a nivel de base de datos PostgreSQL más validación en Form Request con `Rule::unique()`.
- **[Eliminación accidental de datos vinculados]** $\rightarrow$ Mitigación: Clave foránea con `CASCADE` para mantener la base de datos consistente al eliminar un viaje.
- **[Eliminar un participante con deudas o gastos]** $\rightarrow$ Mitigación: `ParticipanteController::destroy` consulta un servicio de validación que bloquea el borrado si hay `monto_pendiente > 0` (deudor o acreedor), saldo neto distinto de `0.00`, o participación en un gasto (pagador o `gasto_participantes`). El usuario ve un flash `danger` con el motivo; el registro permanece.

## Migration Plan

1. Crear archivos de migración para las tablas `viajes` y `participantes`.
2. Crear modelos Eloquent, Form Requests, Policies y Controladores.
3. Configurar rutas en `routes/web.php`.
4. Actualizar `DatabaseSeeder.php` con el caso Samaipata.
5. Ejecutar suite de pruebas de feature para viajes y participantes.
6. *(Confirmación humana requerida antes de aplicar migraciones reales en PostgreSQL)*.
