## 1. Migraciones y Modelos

- [x] 1.1 Crear migración para agregar `codigo_invitacion` (string único de 8 caracteres) a `viajes` y `user_id` (foreignId nullable hacia `users`) a `participantes`. Verificar: `php artisan migrate` corre exitosamente. Spec: vinculada a `invitacion-viajes/spec.md`.

- [x] 1.2 Actualizar los modelos `Viaje`, `Participante` y `User` para incluir las relaciones y la generación automática de código de invitación. Verificar: las pruebas confirman generación de código alfanumérico único. Spec: vinculada a `invitacion-viajes/spec.md`.

## 2. Lógica de Unión y Políticas Multiusuario

- [x] 2.1 Implementar en `ViajeController` la acción `unirse` para validar y vincular usuarios por código, actualizar `store` para que el creador sea automáticamente el primer participante, y adaptar `index` para listar viajes creados o donde el usuario participe. Verificar: prueba feature de unión con código válido, código inválido y participante repetido. Spec: vinculada a `invitacion-viajes/spec.md`.

- [x] 2.2 Actualizar `ViajePolicy` y `GastoPolicy` para que los participantes de un viaje puedan visualizarlo y registrar gastos en él. Verificar: feature tests confirman acceso autorizado para participantes. Spec: vinculada a `invitacion-viajes/spec.md`.

- [x] 2.3 Registrar la ruta `POST /viajes/unirse` en `routes/web.php`. Verificar: la ruta nombrada `viajes.unirse` queda registrada. Spec: vinculada a `invitacion-viajes/spec.md`.

## 3. Eliminación de Dashboard y Enrutamiento Post-Login

- [x] 3.1 Actualizar `config/fortify.php` con `'home' => '/viajes'`, configurar `routes/web.php` para redirigir `/` y `/dashboard` a `/viajes`, y remover `Dashboard.vue` y el enlace "Dashboard" en `AppLayout.vue`. Verificar: post-login el usuario ingresa directo a `/viajes`. Spec: vinculada a `auth-dark-neon/spec.md`.

## 4. Rediseño Dark Neon de Autenticación

- [x] 4.1 Rediseñar `resources/js/Components/AuthenticationCard.vue`, `AuthenticationCardLogo.vue` y `Checkbox.vue` bajo la paleta Dark Neon. Verificar: el contenedor base de autenticación muestra fondo `zinc-950` y tarjeta `zinc-900`. Spec: vinculada a `auth-dark-neon/spec.md`.

- [x] 4.2 Rediseñar `resources/js/Pages/Auth/Login.vue` y `resources/js/Pages/Auth/Register.vue` con estética Dark Neon, bordes luminosos y botones neón cian/esmeralda. Verificar: `/login` y `/register` presentan la nueva estética limpia. Spec: vinculada a `auth-dark-neon/spec.md`.

- [x] 4.3 Rediseñar `resources/js/Pages/Auth/ForgotPassword.vue` y `ResetPassword.vue` armonizados con el tema Dark Neon. Verificar: recuperación de credenciales consistente con el resto del sistema. Spec: vinculada a `auth-dark-neon/spec.md`.

## 5. Integración en las Vistas de Viaje (UI de Invitación y Unión)

- [x] 5.1 En `resources/js/Pages/Viajes/Index.vue`, incorporar botón y modal interactivo *"Unirse a viaje con código"* que envíe el código a `viajes.unirse`. Verificar: el usuario puede unirse a viajes ajenos mediante su código de invitación. Spec: vinculada a `invitacion-viajes/spec.md`.

- [x] 5.2 En `resources/js/Pages/Viajes/Show.vue`, sustituir la creación arbitraria de nombres por un panel con el código de invitación único y botón de copiado rápido al portapapeles. Verificar: el código de 8 caracteres se visualiza y se copia con un clic. Spec: vinculada a `invitacion-viajes/spec.md`.

## 6. Verificación, Seeder y Tests en Docker

- [x] 6.1 Actualizar `database/seeders/DatabaseSeeder.php` para crear cuentas de usuario para Ana, Beto, Carla y Diego, asignar el viaje Samaipata con su código y asociar sus `user_id` a los `participantes`. Verificar: `db:seed` corre correctamente.

- [x] 6.2 Ejecutar `npm run build` en el host para verificar compilación sin errores.

- [x] 6.3 Ejecutar la suite completa de pruebas automatizadas en Docker (`php artisan test`). Verificar: 100% de las pruebas pasan con éxito.
