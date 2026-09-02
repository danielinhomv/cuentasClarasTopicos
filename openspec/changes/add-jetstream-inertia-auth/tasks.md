## 1. Dependencias y scaffolding Jetstream

- [x] 1.1 Alcance: solo dependencias Composer. Tipo: no funcional (infraestructura). Añadir `laravel/jetstream` compatible con Laravel 13 en `composer.json` / lockfile (`composer require laravel/jetstream`). No ejecutar `jetstream:install` ni migraciones. Verificar: `composer.json` declara el paquete y `vendor/laravel/jetstream` existe. Spec: habilita todos los requisitos de `user-auth` (aún no observables).

- [x] 1.2 Alcance: scaffolding oficial (el instalador toca migraciones, modelo, Actions, rutas y páginas Vue de una vez; no adelantar config custom ni tests extra). Tipo: no funcional (instalación). Ejecutar `php artisan jetstream:install inertia` **sin** `--teams` y **sin** `--ssr`. Si el comando pregunta por re-ejecutar migraciones, responder **no**. Verificar: existen `config/jetstream.php`, `config/fortify.php`, páginas Vue de auth bajo `resources/js/Pages`, y `package.json` incluye Vue/Inertia. Spec: base de registro, login y perfil.

## 2. Migraciones y modelo User

- [x] 2.1 Alcance: solo migraciones (archivos). Tipo: no funcional (persistencia). Revisar las migraciones que añadió Jetstream (p. ej. columnas 2FA / foto de perfil en `users`) y dejarlas listas para PostgreSQL. **No** ejecutar `php artisan migrate` ni `migrate:fresh`. Verificar: los archivos de migración existen y son coherentes con la tabla `users` actual. Spec: `Identidad persistida sin estado solo en el navegador` (Registro persistencia al refrescar, Recarga conserva la sesión).

- [x] 2.2 Alcance: solo modelo y relaciones. Tipo: no funcional (persistencia / seguridad de atributos). Ajustar `app/Models/User.php` a lo que Jetstream espera (traits, fillable/hidden, `password` hashed). No crear modelos Participante ni Gasto ni relaciones de negocio. Verificar: `User` sigue siendo `Authenticatable` y no referencia tablas de gastos. Spec: persistencia de cuenta; escenarios de registro e inicio de sesión.

## 3. Configuración Fortify/Jetstream y rutas

- [x] 3.1 Alcance: solo configuración (no nuevas páginas). Tipo: no funcional (seguridad / alcance de features). En `config/fortify.php` y `config/jetstream.php`: registro, login, reset y perfil **on**; `teams`, `api` y términos **off**; verificación de email **no exigida**. Verificar: esas features coinciden con `php artisan config:show` o lectura de los archivos. Spec: Visitante puede registrarse; Usuario puede recuperar el acceso; Usuario autenticado puede actualizar su perfil.

- [x] 3.2 Alcance: solo rutas y middleware (no nuevas páginas Vue). Tipo: funcional (acceso al área autenticada). En `routes/web.php`: `/` deja de servir `welcome`; no autenticado → login; autenticado → dashboard. Quitar middleware `verified` de las rutas autenticadas si el email verification está off. Verificar: `route:list` muestra login/register/logout/dashboard y no hay `welcome` como home. Spec: Área autenticada protegida; Visitante autenticado no ve registro como destino principal; Cierre de sesión.

## 4. Actions de validación (equivalente Form Request)

- [x] 4.1 Alcance: solo Actions Fortify (`CreateNewUser` y validación de registro). Tipo: no funcional (validación de datos). Confirmar correo único, nombre requerido y reglas de contraseña. No añadir Form Requests paralelos. Verificar: el Action rechaza correo duplicado y campos vacíos (inspección + tests en la tarea 6.x). Spec: Correo duplicado; Campos vacíos o inválidos.

- [x] 4.2 Alcance: solo Actions Fortify de perfil y contraseña (`UpdateUserProfileInformation`, `UpdateUserPassword`, `ResetUserPassword`). Tipo: no funcional (validación de datos). Exigir contraseña actual para el cambio; correo único en perfil. Verificar: los Actions implementan esas reglas. Spec: Actualizar nombre; Correo de perfil duplicado; Cambio de contraseña (correcta e incorrecta); Nueva contraseña tras restablecimiento; Correo desconocido en restablecimiento.

## 5. Páginas Inertia y frontend

- [x] 5.1 Alcance: solo páginas Inertia / Vite (no lógica PHP nueva). Tipo: funcional (UI de autenticación). Dejar las páginas Vue generadas (Login, Register, Forgot/Reset password, Profile, Dashboard). Si Laravel 13 deja `import './bootstrap'` roto, quitarlo. Alinear Tailwind para que el build compile. Verificar: existen las páginas y `npm run build` (o el equivalente del proyecto) termina sin error de import. Spec: escenarios de formularios de registro, acceso, reset y perfil (UI observable).

- [x] 5.2 Alcance: solo middleware Inertia / props compartidas. Tipo: no funcional (sesión en servidor). `HandleInertiaRequests` comparte el usuario autenticado; no usar `localStorage` como fuente de identidad. Verificar: el middleware no escribe la sesión en `localStorage`. Spec: Recarga conserva la sesión; Identidad persistida sin estado solo en el navegador.

## 6. Pruebas (sin seeder de Samaipata)

- [x] 6.1 Alcance: solo tests de feature de registro y sesión. Tipo: funcional. Ajustar o completar tests generados por Jetstream para: registro exitoso, correo duplicado, login ok, contraseña incorrecta, logout, guest no entra al dashboard, autenticado no permanece en login/register, login con base vacía falla. **No** crear seeder de Samaipata (no es caso de uso de gastos). Verificar: `php artisan test` (filtros de auth) pasa esos casos. Spec: Visitante puede registrarse; Usuario puede iniciar y cerrar sesión; Sin usuarios registrados.

- [x] 6.2 Alcance: solo tests de feature de reset y perfil. Tipo: funcional. Cubrir solicitud de reset, reset que invalida la contraseña anterior, update de nombre, email duplicado en perfil, cambio de contraseña con actual correcta e incorrecta. Verificar: esos tests pasan. Spec: Usuario puede recuperar el acceso; Usuario autenticado puede actualizar su perfil de cuenta.

## 7. Cierre de apply (confirmación humana)

- [x] 7.1 Alcance: no modificar código. Tipo: no funcional (persistencia). Documentar en el resumen de apply que el equipo debe correr **manualmente** `php artisan migrate` contra PostgreSQL cuando lo autorice, luego registrar y recargar para validar persistencia. No ejecutar migrate/seed desde apply. Verificar: el mensaje de cierre de apply incluye esa advertencia. Spec: Persistencia al refrescar; Recarga conserva la sesión.
