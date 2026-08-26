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
- No crear componentes de vista (Blade, Inertia/Vue, CSS).
- No modelar aún la tabla `gastos` ni la tabla pivote de exclusiones (se modelarán en el Módulo 3).
- No implementar lógica de cálculo de saldos ni algoritmo de liquidación (Módulo 4).

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
  - `$fillable = ['nombre', 'viaje_id']`.

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

### 6. Seeder y Datos de Referencia

El `DatabaseSeeder.php` poblará:
1. Usuario propietario de prueba (`ana@example.com`).
2. Viaje: `"Viaje a Samaipata"` con descripción `"Fin de semana con amigos"`.
3. 4 Participantes: `"Ana"`, `"Beto"`, `"Carla"`, `"Diego"`.

## Risks / Trade-offs

- **[Acceso no autorizado a viajes ajenos]** $\rightarrow$ Mitigación: Uso estricto de `ViajePolicy` y `ParticipantePolicy` invocadas mediante `$this->authorize(...)` en cada acción del controlador.
- **[Nombres duplicados en el mismo viaje]** $\rightarrow$ Mitigación: Restricción `UNIQUE(viaje_id, nombre)` a nivel de base de datos PostgreSQL más validación en Form Request con `Rule::unique()`.
- **[Eliminación accidental de datos vinculados]** $\rightarrow$ Mitigación: Clave foránea con `CASCADE` para mantener la base de datos consistente al eliminar un viaje.

## Migration Plan

1. Crear archivos de migración para las tablas `viajes` y `participantes`.
2. Crear modelos Eloquent, Form Requests, Policies y Controladores.
3. Configurar rutas en `routes/web.php`.
4. Actualizar `DatabaseSeeder.php` con el caso Samaipata.
5. Ejecutar suite de pruebas de feature para viajes y participantes.
6. *(Confirmación humana requerida antes de aplicar migraciones reales en PostgreSQL)*.
