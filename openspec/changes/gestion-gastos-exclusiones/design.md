## Context

Ver motivación en `proposal.md` y especificaciones en `specs/gastos/spec.md` y `specs/exclusiones/spec.md`.

Con la estructura de `User`, `Viaje` y `Participante` establecida, este diseño define la gestión de transacciones económicas (`Gasto`) y su relación con los participantes beneficiarios y excluidos (`gasto_exclusiones`) en PostgreSQL, respetando las convenciones de Laravel y la separación estricta de capas.

## Goals / Non-Goals

**Goals:**
- Diseñar las tablas `gastos` y la tabla pivote `gasto_exclusiones` en PostgreSQL con integridad referencial y claves foráneas.
- Implementar el modelo Eloquent `Gasto` con casts de moneda (`decimal:2`), fecha y relaciones (`belongsTo`, `belongsToMany`).
- Implementar validaciones en Form Requests para asegurar montos positivos (`> 0`), pagador perteneciente al viaje y prevención de exclusión total de participantes.
- Proteger el acceso a los gastos mediante `GastoPolicy` basada en la propiedad del viaje.
- Exponer controladores y rutas web estructuradas bajo el middleware de autenticación.
- Sembrar los 4 gastos del escenario oficial de Samaipata en el `DatabaseSeeder`.

**Non-Goals:**
- No generar componentes de interfaz de usuario (Vue/Inertia/Blade).
- No implementar el algoritmo de saldos y liquidación mínima de deudas (se aborda en el Módulo 4).

## Decisions

### 1. Modelo de Datos y Esquema PostgreSQL

Se definen dos tablas en PostgreSQL:

```
[viajes] (1) <────── (N) [gastos] (1) ────> (N) [gasto_exclusiones] <──── (N) [participantes]
  ▲                        │ (pagador_id)                                         ▲
  └────────────────────────┼──────────────────────────────────────────────────────┘
```

- **Tabla `gastos`:**
  - `id` (bigIncrements, PK)
  - `viaje_id` (bigInteger, FK a `viajes.id` con `ON DELETE CASCADE`)
  - `pagador_id` (bigInteger, FK a `participantes.id` con `ON DELETE RESTRICT`)
  - `concepto` (string 200, NOT NULL)
  - `monto` (decimal(12, 2), NOT NULL con valor mínimo `0.01`)
  - `fecha` (date, NOT NULL)
  - `created_at`, `updated_at` (timestamps)
  - *Índices:* `viaje_id`, `pagador_id`.

- **Tabla `gasto_exclusiones` (pivote):**
  - `id` (bigIncrements, PK)
  - `gasto_id` (bigInteger, FK a `gastos.id` con `ON DELETE CASCADE`)
  - `participante_id` (bigInteger, FK a `participantes.id` con `ON DELETE CASCADE`)
  - `created_at`, `updated_at` (timestamps)
  - *Restricción única:* `UNIQUE(gasto_id, participante_id)`.

### 2. Modelos Eloquent y Relaciones

- **`App\Models\Gasto`:**
  - `belongsTo(Viaje::class)`: el gasto pertenece a un viaje.
  - `belongsTo(Participante::class, 'pagador_id')`: el participante que pagó el gasto.
  - `belongsToMany(Participante::class, 'gasto_exclusiones')->withTimestamps()`: participantes que NO participan del reparto.
  - `$fillable = ['concepto', 'monto', 'fecha', 'pagador_id', 'viaje_id']`.
  - `$casts = ['monto' => 'decimal:2', 'fecha' => 'date']`.
- **`App\Models\Viaje`:**
  - `hasMany(Gasto::class)`: un viaje tiene múltiples gastos.
- **`App\Models\Participante`:**
  - `hasMany(Gasto::class, 'pagador_id')`: gastos donde el participante fue el pagador.
  - `belongsToMany(Gasto::class, 'gasto_exclusiones')`: gastos donde el participante fue excluido.

### 3. Validación en Form Requests

En `StoreGastoRequest` y `UpdateGastoRequest`:

```php
public function rules(): array
{
    $viajeId = $this->route('viaje')?->id ?? $this->route('gasto')?->viaje_id;

    return [
        'concepto' => ['required', 'string', 'min:2', 'max:200'],
        'monto' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
        'fecha' => ['required', 'date'],
        'pagador_id' => [
            'required',
            'integer',
            Rule::exists('participantes', 'id')->where('viaje_id', $viajeId),
        ],
        'excluidos' => ['nullable', 'array'],
        'excluidos.*' => [
            'integer',
            Rule::exists('participantes', 'id')->where('viaje_id', $viajeId),
        ],
    ];
}
```

*Validación de negocio:* Se valida mediante un callback que `count($excluidos) < total_participantes_del_viaje` para impedir que se excluyan todos los participantes.

### 4. Capa de Autorización (`GastoPolicy`)

Se valida que `$user->id === $gasto->viaje->user_id` en las acciones `view`, `update`, `delete`, y en `create` que el usuario sea el dueño del `$viaje`.

### 5. Rutas Web en `routes/web.php`

```php
Route::middleware(['auth'])->group(function () {
    // Gastos dentro de un Viaje
    Route::get('/viajes/{viaje}/gastos', [GastoController::class, 'index'])->name('viajes.gastos.index');
    Route::post('/viajes/{viaje}/gastos', [GastoController::class, 'store'])->name('viajes.gastos.store');
    Route::get('/gastos/{gasto}', [GastoController::class, 'show'])->name('gastos.show');
    Route::put('/gastos/{gasto}', [GastoController::class, 'update'])->name('gastos.update');
    Route::delete('/gastos/{gasto}', [GastoController::class, 'destroy'])->name('gastos.destroy');
});
```

### 6. Seeder de Referencia (Samaipata)

El `DatabaseSeeder.php` registrará los 4 gastos asociados a los participantes creados en el Módulo 2:
1. Cabaña: `800.00` Bs. pagado por Ana.
2. Entradas a El Fuerte: `160.00` Bs. pagado por Ana.
3. Cena: `400.00` Bs. pagado por Beto.
4. Gasolina: `240.00` Bs. pagado por Carla.

## Risks / Trade-offs

- **[Monto con imprecisión de punto flotante]** $\rightarrow$ Mitigación: Uso estricto del tipo de columna `DECIMAL(12, 2)` en PostgreSQL y casteo `decimal:2` en Eloquent para evitar distorsiones por redondeo binario.
- **[Exclusión total de participantes]** $\rightarrow$ Mitigación: Validación en el Form Request que bloquea cualquier petición donde la cantidad de excluidos iguale al número total de integrantes del viaje.
- **[Eliminación de participante con gastos asociados]** $\rightarrow$ Mitigación: Clave foránea `pagador_id` con `ON DELETE RESTRICT` para evitar borrar accidentalmente a un participante que pagó un gasto activo.

## Migration Plan

1. Crear migraciones para `gastos` y `gasto_exclusiones`.
2. Crear modelo `Gasto`, Form Requests y `GastoPolicy`.
3. Crear `GastoController` y registrar rutas en `routes/web.php`.
4. Actualizar `DatabaseSeeder.php` con los 4 gastos de Samaipata.
5. Ejecutar suite de pruebas `tests/Feature/GastoTest.php`.
