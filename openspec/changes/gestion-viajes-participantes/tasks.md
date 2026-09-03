## 1. Migraciones de Base de Datos

- [x] 1.1 Alcance: solo migración. Tipo: no funcional (persistencia). Crear el archivo de migración para la tabla `viajes` con columnas `id`, `user_id` (FK a `users` con onDelete cascade), `nombre` (string 150), `descripcion` (text nullable) y timestamps. No ejecutar `php artisan migrate`. Verificar: el archivo de migración existe en `database/migrations/` con la sintaxis correcta. Spec: vinculada a `viajes/spec.md` (Persistencia y protección de acceso a viajes).

- [x] 1.2 Alcance: solo migración. Tipo: no funcional (persistencia e integridad). Crear el archivo de migración para la tabla `participantes` con columnas `id`, `viaje_id` (FK a `viajes` con onDelete cascade), `nombre` (string 100), timestamps y restricción única compuesta `unique(['viaje_id', 'nombre'])`. No ejecutar `php artisan migrate`. Verificar: el archivo de migración existe en `database/migrations/` con la restricción de unicidad. Spec: vinculada a `participantes/spec.md` (Nombre duplicado dentro del mismo viaje).

## 2. Modelos Eloquent y Relaciones

- [x] 2.1 Alcance: solo modelo y relaciones. Tipo: funcional (Caso de uso 7). Crear el modelo `App\Models\Viaje.php` con fillable `['nombre', 'descripcion', 'user_id']`, relación `belongsTo(User::class)` y relación `hasMany(Participante::class)`. Añadir la relación `hasMany(Viaje::class)` en `App\Models\User.php`. Verificar: las clases declaran las relaciones Eloquent correspondientes. Spec: vinculada a `viajes/spec.md` (Creación exitosa de viaje).

- [x] 2.2 Alcance: solo modelo y relaciones. Tipo: funcional (Caso de uso 1). Crear el modelo `App\Models\Participante.php` con fillable `['nombre', 'viaje_id']` y relación `belongsTo(Viaje::class)`. Verificar: la clase declara la relación de pertenencia al viaje. Spec: vinculada a `participantes/spec.md` (Alta exitosa de participante).

## 3. Form Requests de Validación y Policies de Autorización

- [x] 3.1 Alcance: solo Form Request/Policy. Tipo: no funcional (validación y seguridad de viajes). Crear `StoreViajeRequest.php`, `UpdateViajeRequest.php` y la policy `ViajePolicy.php` para autorizar operaciones únicamente al usuario propietario (`$user->id === $viaje->user_id`). Verificar: la policy valida la propiedad del viaje y los requests validan obligatoriedad de nombre. Spec: vinculada a `viajes/spec.md` (Nombre de viaje vacío o inválido, Intento de consultar/modificar viaje ajeno).

- [x] 3.2 Alcance: solo Form Request/Policy. Tipo: no funcional (validación y seguridad de participantes). Crear `StoreParticipanteRequest.php`, `UpdateParticipanteRequest.php` (con regla `Rule::unique` scoped a `viaje_id`) y `ParticipantePolicy.php` (verificando que el usuario sea dueño del viaje al que pertenece el participante). Verificar: los requests impiden nombres duplicados en el mismo viaje y la policy previene acceso ajeno. Spec: vinculada a `participantes/spec.md` (Nombre duplicado dentro del mismo viaje, Intento de agregar o modificar participante en viaje ajeno).

## 4. Controladores y Rutas Web

- [x] 4.1 Alcance: solo controlador y rutas. Tipo: funcional (Caso de uso 7: Gestionar viaje). Crear `ViajeController.php` con métodos `index`, `store`, `show`, `update`, `destroy` invocando `ViajePolicy`. Registrar las rutas agrupadas con nombres (`viajes.index`, `viajes.store`, `viajes.show`, `viajes.update`, `viajes.destroy`) bajo middleware `auth` en `routes/web.php`. Verificar: `php artisan route:list` muestra las 5 rutas de viajes asociadas al controlador. Spec: vinculada a `viajes/spec.md` (Listado de viajes propios, Consulta de detalle, Edición y Eliminación).

- [x] 4.2 Alcance: solo controlador y rutas. Tipo: funcional (Caso de uso 1: Gestionar participantes). Crear `ParticipanteController.php` con métodos `index`, `store`, `update`, `destroy` aplicando autorización de viaje/participante. Registrar las rutas anidadas nombradas (`viajes.participantes.index`, `viajes.participantes.store`, `participantes.update`, `participantes.destroy`) bajo middleware `auth` en `routes/web.php`. Verificar: `php artisan route:list` muestra las rutas de participantes. Spec: vinculada a `participantes/spec.md` (Listado con participantes registrados, Alta, Edición y Eliminación).

## 5. Seeder de Referencia (Samaipata)

- [x] 5.1 Alcance: solo seeder. Tipo: no funcional (datos de prueba para verificación). Actualizar `DatabaseSeeder.php` para crear un usuario de prueba, el viaje `"Viaje a Samaipata"` y los 4 participantes del escenario oficial: `"Ana"`, `"Beto"`, `"Carla"` y `"Diego"`. No ejecutar `db:seed` automáticamente. Verificar: `DatabaseSeeder.php` contiene la lógica para sembrar el viaje a Samaipata y sus 4 participantes. Spec: escenario de referencia del contexto del proyecto.

## 6. Pruebas Automatizadas (Feature Tests)

- [x] 6.1 Alcance: solo pruebas. Tipo: funcional (Caso de uso 7: Viajes). Crear `tests/Feature/ViajeTest.php` cubriendo: creación de viaje, validación de nombre requerido, listado solo de viajes propios, detalle de viaje propio vs ajeno (403), edición y eliminación en cascada. Verificar: la suite de tests de viajes ejecuta y valida los escenarios de `viajes/spec.md`.

- [x] 6.2 Alcance: solo pruebas. Tipo: funcional (Caso de uso 1: Participantes). Crear `tests/Feature/ParticipanteTest.php` cubriendo: alta de participante, rechazo de nombre duplicado en el mismo viaje, permitir mismo nombre en viajes distintos, caso borde de 0 participantes, edición y eliminación, y bloqueo de acceso no autorizado. Verificar: la suite de tests de participantes ejecuta y valida los escenarios de `participantes/spec.md`.

## 7. Cierre de Apply (Verificación Manual)

- [x] 7.1 Alcance: no modificar código. Tipo: no funcional (persistencia). Documentar en el resumen de apply que el equipo debe ejecutar manualmente `php artisan migrate` contra PostgreSQL cuando lo autorice, y verificar la siembra del viaje Samaipata y sus 4 participantes. Verificar: el resumen final incluye la advertencia de ejecución manual de migraciones. Spec: vinculada a `viajes/spec.md` y `participantes/spec.md` (Persistencia al refrescar).

## 8. Participantes sin cuenta

- [x] 8.1 Alcance: documentación OpenSpec. Tipo: no funcional. Actualizar `proposal.md`, `design.md` y `specs/participantes/spec.md` para documentar participantes sin cuenta (`user_id` nullable), coexistencia con invitación por código y paridad en gastos/saldos. Verificar: los artefactos reflejan el nuevo comportamiento. Spec: vinculada a `participantes/spec.md` (Alta de participante sin cuenta, Participantes sin cuenta participan en gastos y cálculos).

- [x] 8.2 Alcance: backend. Tipo: funcional (Caso de uso 1). Asegurar que `ParticipanteController::store` cree participantes con `user_id = NULL` explícitamente. Verificar: `POST /viajes/{viaje}/participantes` persiste `user_id` nulo. Spec: vinculada a `participantes/spec.md` (Alta de participante sin cuenta).

- [x] 8.3 Alcance: frontend. Tipo: funcional (Caso de uso 1). Restaurar formulario de alta manual por nombre en `Viajes/Show.vue` (solo propietario), actualizar copy del panel de invitación para distinguir ambos mecanismos, y mostrar badge "Sin cuenta" en participantes sin `user_id`. Verificar: el propietario puede agregar participantes por nombre desde la UI. Spec: vinculada a `participantes/spec.md` (Alta exitosa de participante).

- [x] 8.4 Alcance: pruebas. Tipo: funcional. Agregar tests en `ParticipanteTest`, `GastoTest` y/o `CalculoBalanceServiceTest` que validen: alta con `user_id` null, participante sin cuenta como pagador, participante sin cuenta en saldos, y que el flujo de invitación por código sigue funcionando. Verificar: la suite pasa. Spec: vinculada a `participantes/spec.md` (Participantes sin cuenta participan en gastos y cálculos, Coexistencia con participantes registrados por código).

## 9. Restricción de eliminación de participantes

- [x] 9.1 Alcance: documentación OpenSpec. Tipo: no funcional. Actualizar proposal, design y `specs/participantes/spec.md` para exigir que no se elimine un participante con deuda pendiente (deudor o acreedor, o saldo ≠ 0) ni si ya participó en un gasto. Verificar: los artefactos describen el bloqueo y el mensaje. Spec: No se elimina un participante con deuda pendiente o participación en gastos.

- [x] 9.2 Alcance: backend. Tipo: funcional (Caso de uso 1). En `ParticipanteController::destroy`, rechazar la eliminación cuando hay deuda pendiente o participación en gastos, con flash claro; permitirla solo si saldo 0 y sin gastos. Verificar: el participante permanece si está bloqueado. Spec: escenarios de bloqueo por deuda y por gasto.

- [x] 9.3 Alcance: pruebas. Tipo: funcional. Extender `ParticipanteTest` con: bloqueo si debe dinero; bloqueo si le deben; bloqueo si participó en un gasto; eliminación permitida sin deudas ni gastos; mensaje flash. Verificar: la suite de ese archivo pasa. Spec: No se elimina un participante con deuda pendiente o participación en gastos.
