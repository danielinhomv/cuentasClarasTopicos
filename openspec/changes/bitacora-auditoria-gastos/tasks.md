## 1. Migración y modelo

- [x] 1.1 Alcance: solo migración. Tipo: no funcional (persistencia). Crear la migración de `gasto_bitacoras` con `viaje_id` (FK cascade), `gasto_id` (FK nullable `nullOnDelete`), `user_id` (FK nullable `nullOnDelete`), `actor_nombre`, `accion`, `gasto_concepto`, `datos_antes` json nullable, `datos_despues` json nullable y `created_at`. No ejecutar `php artisan migrate` contra PostgreSQL real. Verificar: el archivo existe en `database/migrations/`. Spec: persistencia al refrescar; conservación tras eliminar gasto.

- [x] 1.2 Alcance: solo modelo y relaciones. Tipo: funcional (Caso de uso 2). Crear `App\Models\GastoBitacora` con `$table = 'gasto_bitacoras'`, casts JSON, relaciones `belongsTo` Viaje/Gasto/User, y `hasMany` desde `Viaje` y `Gasto`. Verificar: las relaciones Eloquent están declaradas. Spec: alta/edición/eliminación generan entrada.

## 2. Policy y servicio de escritura

- [x] 2.1 Alcance: solo Form Request/Policy. Tipo: no funcional (autorización). Crear `GastoBitacoraPolicy` con `viewAny(User, Viaje)` verdadero solo si `$user->id === $viaje->user_id`. No definir create/update/delete para usuarios. Verificar: un participante no anfitrión no está autorizado. Spec: solo el anfitrión consulta; intento de mutar no existe.

- [x] 2.2 Alcance: solo Service. Tipo: funcional (Casos de uso 2 y 3). Crear `RegistroBitacoraGastoService` que arme el snapshot (concepto, monto 2 decimales, moneda, tipo_cambio, fecha, pagador, incluidos, excluidos), calcule deltas en ediciones e inserte `crear`/`editar`/`eliminar`. Verificar: un test unitario o de feature comprueba monto `100.00` → `150.00` en antes/después. Spec: snapshot mínimo; edición de monto y de exclusiones.

## 3. Controladores y rutas

- [x] 3.1 Alcance: solo controlador y rutas. Tipo: funcional (Casos de uso 2 y 8). Enganchar el service en `GastoController` store/update/destroy (después de persistir el gasto; en destroy, registrar antes de borrar o con snapshot previo). Añadir listado `GET /viajes/{viaje}/bitacora` nombrado `viajes.bitacora.index` bajo `auth`, autorizando `viewAny`. No registrar rutas de mutación. Verificar: `php artisan route:list` muestra solo el GET. Spec: registro automático; lista vacía HTTP 200; guest redirigido.

- [x] 3.2 Alcance: solo controlador y rutas. Tipo: funcional (Caso de uso 7). En `ViajeController::show`, cargar bitácora ordenada desc **solo** para el anfitrión; para el resto no enviar entradas. Verificar: la prop Inertia `bitacora` llega al anfitrión y queda vacía/ausente para un participante. Spec: anfitrión consulta; participante 403 en el endpoint JSON.

## 4. Interfaz de consulta (anfitrión)

- [x] 4.1 Alcance: frontend de consulta (ajuste explícito de este change). Tipo: funcional. Añadir pestaña Bitácora en `Viajes/Show.vue` visible solo para el creador, mostrando actor, acción, fecha/hora, gasto y valores anterior/nuevo; empty state si no hay entradas; sin formularios de edición. Verificar: el anfitrión ve el panel y un participante no. Spec: consulta exclusiva del anfitrión.

## 5. Pruebas

- [x] 5.1 Alcance: solo pruebas. Tipo: funcional (Casos de uso 2, 3 y 8). Crear `tests/Feature/BitacoraGastoTest.php` cubriendo: crear gasto registra `crear`; editar `100.00`→`150.00` registra ambos valores; eliminar conserva la fila con `gasto_id` null; validación de monto 0 no escribe bitácora; anfitrión 200; participante 403; guest 401/302; no existen rutas POST/PUT/DELETE de bitácora. Usar el viaje Samaipata cuando aplique. Verificar: la suite de ese archivo pasa. Spec: todos los escenarios de `bitacora-gastos/spec.md`.
