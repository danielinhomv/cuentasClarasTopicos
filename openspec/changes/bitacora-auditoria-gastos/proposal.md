## Why

Hoy cualquier participante de un viaje puede crear, editar o eliminar gastos, pero el anfitrión no tiene un historial de quién lo hizo ni qué cambió. Sin bitácora, un monto alterado (por ejemplo de Bs. 100 a Bs. 150) no deja rastro auditable y se pierde la confianza en los saldos.

## What Changes

- Registro automático e inmutable de acciones sobre gastos: `crear`, `editar` y `eliminar`.
- Cada entrada SHALL guardar: actor (quién), gasto afectado, acción, fecha/hora y un snapshot suficiente para reconstruir el cambio (antes/después en ediciones).
- La bitácora es **solo consulta**: no existen endpoints ni UI para crear, editar o borrar entradas de forma manual.
- Solo el **anfitrión** (creador del viaje, `viajes.user_id`) puede consultar la bitácora del viaje. El resto de participantes recibe 403.
- Consulta en el detalle del viaje (pestaña o panel de bitácora) y endpoint de lectura protegido.
- Pruebas de feature que cubren registro en create/update/delete, snapshot de edición, inmutabilidad y restricción al anfitrión.

### Casos de uso del backend que cubre

- **Caso de uso 2: Gestionar gastos** (auditoría de alta, edición y eliminación).
- **Caso de uso 3: Excluir participantes de un gasto** (si la edición cambia exclusiones, el snapshot lo refleja).
- **Caso de uso 8: Autenticar usuario** (identidad del actor y del anfitrión para autorización).

### Fuera de alcance

- Auditoría de participantes, viajes, tipos de cambio o liquidaciones.
- Restaurar un gasto a un estado anterior desde la bitácora (no es un undo).
- Exportación CSV/PDF.
- Pasarelas de pago o notificaciones.

> **Ajuste de alcance:** el contexto genérico del proyecto marca frontend como fase posterior. Por instrucción explícita de este change, **sí se incluye UI Inertia** de consulta (solo anfitrión).

## Capabilities

### New Capabilities

- `bitacora-gastos`: Registro inmutable de crear/editar/eliminar gastos, consulta exclusiva del anfitrión y presentación de actor, acción, fecha y valores anterior/nuevo.

### Modified Capabilities

- *(ninguna en `openspec/specs/`; la bitácora es una capacidad nueva que se engancha a los flujos existentes de gastos sin cambiar sus criterios de aceptación de negocio)*

## Impact

- **Base de datos:** nueva tabla `gasto_bitacoras` (PostgreSQL) con actor, viaje, gasto (nullable post-delete), acción, snapshots JSON y timestamps.
- **Backend:** modelo, policy, servicio de escritura interna, enganche en `GastoController`, ruta de listado, sin Form Request de mutación.
- **Frontend:** panel de bitácora en `Viajes/Show.vue` visible solo para el creador.
- **Tests:** `tests/Feature/BitacoraGastoTest.php` y cobertura de autorización.
