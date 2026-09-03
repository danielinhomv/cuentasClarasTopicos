## Context

Ver motivación en `proposal.md` y requisitos en `specs/bitacora-gastos/spec.md`.

Hoy `GastoPolicy` permite a **cualquier** miembro del viaje (anfitrión o participante con cuenta) crear, editar y eliminar gastos. `GastoController` muta `gastos`, `gasto_exclusiones` y `gasto_participantes` sin historial. Los saldos y deudas (`CalculoBalanceService`, `liquidaciones`) son derivados; la bitácora no los recalcula.

Relaciones existentes:

```
User 1—N Viaje 1—N Participante
Viaje 1—N Gasto N—N Participante (gasto_participantes, gasto_exclusiones)
Gasto belongsTo pagador (Participante)
Balances / liquidación: calculados, no son fuente de la bitácora
```

Persistencia: PostgreSQL.

> El contexto genérico dice “no vistas”. Este change incluye UI de consulta porque el usuario lo pidió; el contrato de datos sale del controlador.

## Goals / Non-Goals

**Goals:**
- Insertar filas de auditoría en la misma transacción que el create/update/delete del gasto.
- Conservar entradas tras borrar el gasto (`gasto_id` nullable).
- Autorizar lectura solo si `$user->id === $viaje->user_id`.
- Snapshot mínimo de campos de negocio del gasto (ver decisión 2).

**Non-Goals:**
- No auditar participantes, invitaciones, tipo de cambio del viaje ni pagos de liquidación.
- No implementar undo/restore.
- No añadir mutaciones HTTP sobre la bitácora.

## Decisions

### 1. Modelo `GastoBitacora` y relaciones Eloquent

Tabla `gasto_bitacoras` en PostgreSQL:

| Columna | Tipo | Notas |
| :--- | :--- | :--- |
| `id` | PK | |
| `viaje_id` | FK `viajes` CASCADE | siempre presente |
| `gasto_id` | FK `gastos` NULL, `nullOnDelete` | se anula al borrar el gasto |
| `user_id` | FK `users` NULL, `nullOnDelete` | actor; se anula si se borra el usuario |
| `actor_nombre` | string | snapshot del nombre al momento de la acción |
| `accion` | string | `crear` \| `editar` \| `eliminar` |
| `gasto_concepto` | string | etiqueta estable si el gasto desaparece |
| `datos_antes` | json nullable | |
| `datos_despues` | json nullable | |
| `created_at` | timestamp | fecha/hora de la acción; sin `updated_at` de negocio |

Relaciones:

- `Viaje` `hasMany(GastoBitacora::class)`
- `Gasto` `hasMany(GastoBitacora::class)`
- `GastoBitacora` `belongsTo` Viaje, Gasto (nullable), User (nullable)

**Alternativa rechazada:** `gasto_id` con CASCADE — perdería el historial al eliminar, contradice el spec.

**Alternativa rechazada:** observers Eloquent globales — más difícil inyectar el `User` autenticado y las listas de excluidos; se registra desde el controlador/servicio después de mutar.

### 2. Qué guardar en el snapshot (y qué no)

Snapshot de un gasto (objeto plano):

```json
{
  "concepto": "Cena",
  "monto": "100.00",
  "moneda": "BOB",
  "tipo_cambio": 1.0,
  "fecha": "2026-09-02",
  "pagador_id": 2,
  "pagador_nombre": "Beto",
  "incluidos": [{"id": 1, "nombre": "Ana"}, {"id": 2, "nombre": "Beto"}],
  "excluidos": [{"id": 4, "nombre": "Diego"}]
}
```

- **Crear:** `datos_antes = null`, `datos_despues = snapshot completo`.
- **Eliminar:** `datos_antes = snapshot completo`, `datos_despues = null`.
- **Editar:** `datos_antes` y `datos_despues` solo con claves que cambiaron (p. ej. `{ "monto": "100.00" }` → `{ "monto": "150.00" }`). Si cambian exclusiones, comparar conjuntos de ids.

**No guardar:** password/email del actor, descripción del viaje, balances, liquidaciones, payloads HTTP crudos, ni el recálculo de centavos. La bitácora documenta el gasto, no el algoritmo de saldos (los montos van con 2 decimales tal como se persistieron).

### 3. Capas de responsabilidad

| Capa | Responsabilidad |
| :--- | :--- |
| **Form Request** | Validación de gastos (existente). Si falla, no hay bitácora. |
| **Policy `GastoBitacoraPolicy`** | `viewAny(User, Viaje)`: solo anfitrión. Sin create/update/delete para usuarios. |
| **Service `RegistroBitacoraGastoService`** | Arma snapshot, calcula delta, inserta la fila. Única vía de escritura. |
| **Controlador `GastoController`** | Tras store/update/destroy exitoso, llama al service con `auth()->user()`. |
| **Controlador de lectura** | `GET /viajes/{viaje}/bitacora` → lista JSON o prop Inertia. |
| **Modelo** | Relación e inmutabilidad: no `$fillable` masivo público; sin rutas de update. |

Rutas (auth, nombradas):

```php
Route::get('/viajes/{viaje}/bitacora', [...])->name('viajes.bitacora.index');
```

No hay `bitacora.store` / `update` / `destroy`.

Contrato de salida de cada entrada: `id`, `accion`, `actor_nombre`, `user_id`, `gasto_id`, `gasto_concepto`, `datos_antes`, `datos_despues`, `created_at`. Orden: `created_at desc`.

### 4. Frontend (consulta)

En `Viajes/Show.vue`, pestaña **Bitácora** visible solo si `auth.user.id === viaje.user_id`. El controlador `show` del viaje pasa `bitacora` vacío (o omitido) a no-anfitriones para no filtrar datos al cliente. Empty state si no hay entradas.

### 5. Tests

Feature tests con escenario Samaipata: create registra `crear`; update de `100.00` → `150.00` registra ambos montos; destroy conserva la fila con `gasto_id` null; Beto recibe 403 al listar; guest redirigido; no existe ruta de mutación.

## Risks / Trade-offs

- **[Pérdida de `user_id` si se borra la cuenta]** → Mitigación: `actor_nombre` denormalizado.
- **[Pérdida de `gasto_id` al eliminar]** → Mitigación: `nullOnDelete` + `gasto_concepto` + snapshot `datos_antes`.
- **[Escritura omitida si se olvida el controller]** → Mitigación: un solo service y tests de feature sobre las 3 acciones.
- **[JSON vs columnas]** → JSON permite exclusiones variables sin migrar columnas por cada campo; se valida la forma en el service.

## Migration Plan

1. Migración `gasto_bitacoras` + modelo + policy + service.
2. Enganchar `GastoController` y ruta de índice; pasar datos al detalle Inertia.
3. UI de solo lectura para el anfitrión.
4. Tests de feature.
5. Rollback: drop de tabla (no hay backfill). El equipo ejecuta `php artisan migrate` cuando lo autorice; no correr migrate automático contra PostgreSQL real en apply.

## Open Questions

Ninguna que altere el spec: visibilidad exclusiva del anfitrión y alcance solo a gastos ya están decididos.
