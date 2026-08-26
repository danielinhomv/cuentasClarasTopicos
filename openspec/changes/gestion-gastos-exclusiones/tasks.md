## 1. Migraciones de Base de Datos

- [ ] 1.1 Alcance: solo migración. Tipo: no funcional (persistencia). Crear el archivo de migración para la tabla `gastos` con columnas `id`, `viaje_id` (FK a `viajes` con onDelete cascade), `pagador_id` (FK a `participantes` con onDelete restrict), `concepto` (string 200), `monto` (decimal 12,2) y `fecha` (date). No ejecutar `php artisan migrate`. Verificar: el archivo de migración existe en `database/migrations/` con la estructura de tipos y claves foráneas. Spec: vinculada a `gastos/spec.md` (Registro exitoso de gasto).

- [ ] 1.2 Alcance: solo migración. Tipo: no funcional (persistencia e integridad). Crear el archivo de migración para la tabla pivote `gasto_exclusiones` con columnas `id`, `gasto_id` (FK a `gastos` con onDelete cascade), `participante_id` (FK a `participantes` con onDelete cascade) y restricción única compuesta `unique(['gasto_id', 'participante_id'])`. No ejecutar `php artisan migrate`. Verificar: el archivo de migración existe con la restricción de unicidad de exclusiones. Spec: vinculada a `exclusiones/spec.md` (Exclusión exitosa de participantes).

## 2. Modelos Eloquent y Relaciones

- [ ] 2.1 Alcance: solo modelo y relaciones. Tipo: funcional (Caso de uso 2: Gastos). Crear el modelo `App\Models\Gasto.php` con fillable `['concepto', 'monto', 'fecha', 'pagador_id', 'viaje_id']`, casts `['monto' => 'decimal:2', 'fecha' => 'date']`, relación `belongsTo(Viaje::class)` y relación `belongsTo(Participante::class, 'pagador_id')`. Añadir la relación `hasMany(Gasto::class)` en `Viaje.php` y `hasMany(Gasto::class, 'pagador_id')` en `Participante.php`. Verificar: los modelos definen las relaciones y casts correspondientes. Spec: vinculada a `gastos/spec.md` (Registro exitoso de gasto).

- [ ] 2.2 Alcance: solo modelo y relaciones. Tipo: funcional (Caso de uso 3: Exclusiones). Añadir la relación `belongsToMany(Participante::class, 'gasto_exclusiones')->withTimestamps()` en `Gasto.php` y la relación inversa en `Participante.php`. Verificar: la relación de exclusiones permite sincronizar y consultar participantes excluidos. Spec: vinculada a `exclusiones/spec.md` (Actualización de la lista de participantes excluidos).

## 3. Form Requests de Validación y Policies de Autorización

- [ ] 3.1 Alcance: solo Form Request/Policy. Tipo: no funcional (validación y reglas de negocio). Crear `StoreGastoRequest.php` y `UpdateGastoRequest.php` validando: concepto requerido, monto mayor a cero (`min:0.01`), fecha válida, pagador perteneciente al viaje y validación personalizada de que los participantes excluidos pertenezcan al viaje y no representen el 100% de los integrantes. Verificar: los Form Requests rechazan montos en 0, pagadores ajenos y exclusiones totales. Spec: vinculada a `gastos/spec.md` (Gasto con monto cero o negativo, Pagador no perteneciente al viaje) y `exclusiones/spec.md` (Intento de excluir a todos los participantes).

- [ ] 3.2 Alcance: solo Form Request/Policy. Tipo: no funcional (seguridad y autorización). Crear `GastoPolicy.php` para validar que el usuario autenticado sea el propietario del viaje al que pertenece el gasto en operaciones `view`, `create`, `update` y `delete`. Verificar: la policy rechaza el acceso a usuarios que no son dueños del viaje. Spec: vinculada a `gastos/spec.md` (Intento de registrar o modificar gasto en viaje ajeno).

## 4. Controladores y Rutas Web

- [ ] 4.1 Alcance: solo controlador y rutas. Tipo: funcional (Casos de uso 2 y 3: Gastos y Exclusiones). Crear `GastoController.php` con métodos `index`, `store`, `show`, `update`, `destroy`, manejando la persistencia del gasto y la sincronización de la relación de exclusiones (`sync`). Registrar las rutas nombradas (`viajes.gastos.index`, `viajes.gastos.store`, `gastos.show`, `gastos.update`, `gastos.destroy`) bajo middleware `auth` en `routes/web.php`. Verificar: `php artisan route:list` expone las rutas de gastos debidamente nombradas. Spec: vinculada a `gastos/spec.md` (Listado de gastos, Consulta de detalle, Edición y Eliminación).

## 5. Seeder de Referencia (Samaipata)

- [ ] 5.1 Alcance: solo seeder. Tipo: no funcional (datos oficiales de prueba). Actualizar `DatabaseSeeder.php` para registrar los 4 gastos del escenario de Samaipata: Cabaña Bs. 800 (Ana), Entradas El Fuerte Bs. 160 (Ana), Cena Bs. 400 (Beto) y Gasolina Bs. 240 (Carla). No ejecutar `db:seed` automáticamente. Verificar: `DatabaseSeeder.php` contiene la creación de los 4 gastos oficiales asociados a los participantes de Samaipata. Spec: escenario de referencia de Samaipata del context.

## 6. Pruebas Automatizadas (Feature Tests)

- [ ] 6.1 Alcance: solo pruebas. Tipo: funcional (Caso de uso 2: Gastos). Crear `tests/Feature/GastoTest.php` cubriendo: registro de gasto exitoso, rechazo de monto 0 o negativo, validación de pagador obligatorio y perteneciente al viaje, listado de gastos, detalle, edición y eliminación de gastos. Verificar: los tests ejecutan y validan los escenarios de `gastos/spec.md`.

- [ ] 6.2 Alcance: solo pruebas. Tipo: funcional (Caso de uso 3: Exclusiones). Agregar pruebas en `GastoTest.php` para: registrar gasto con participantes excluidos, rechazar exclusión del 100% de los participantes, actualizar exclusiones al editar y verificar la eliminación en cascada de exclusiones al borrar un gasto. Verificar: los tests validan los escenarios de `exclusiones/spec.md`.

## 7. Cierre de Apply (Verificación Manual)

- [ ] 7.1 Alcance: no modificar código. Tipo: no funcional (persistencia). Documentar en el resumen de apply que el equipo debe ejecutar manualmente `php artisan migrate` contra PostgreSQL cuando lo autorice, y verificar la persistencia de los gastos y exclusiones en la base de datos. Verificar: el resumen final contiene las instrucciones y recordatorio de ejecución manual. Spec: vinculada a `gastos/spec.md` y `exclusiones/spec.md` (Persistencia al recargar).
