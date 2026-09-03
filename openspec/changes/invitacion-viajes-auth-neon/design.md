## Context

Ver motivación en `proposal.md` y especificaciones en `specs/invitacion-viajes/spec.md` y `specs/auth-dark-neon/spec.md`.

Este documento detalla la arquitectura técnica para implementar la membresía de viajes mediante **códigos de invitación**, la eliminación de la pantalla intermedia de dashboard y la integración de la suite de autenticación en la estética Dark Neon.

## Goals / Non-Goals

**Goals:**
- Añadir la columna `codigo_invitacion` en la tabla `viajes` (cadena única de 8 caracteres alfanuméricos, mayúsculas).
- Añadir la columna `user_id` (clave foránea nullable hacia `users`) en la tabla `participantes`.
- Registrar automáticamente al usuario creador como participante al momento de guardar un nuevo viaje.
- Exponer la ruta y acción `POST /viajes/unirse` (`viajes.unirse`) para que los usuarios autenticados se unan a un viaje ingresando su código.
- Actualizar la lógica de consulta para que un usuario vea en su listado tanto los viajes que creó como aquellos en los que participa.
- Adaptar las políticas (`ViajePolicy`, `GastoPolicy`) para permitir que cualquier participante del viaje pueda visualizarlo y registrar gastos en él.
- En `Viajes/Show.vue`, sustituir el formulario de alta manual de texto libre por un componente destacado que exhiba el código de invitación con acción de copiado al portapapeles.
- En `Viajes/Index.vue`, agregar un modal o acción accesible *"Unirse a un viaje con código"*.
- Eliminar el dashboard por defecto (`Dashboard.vue`), redirigir `/` y `/dashboard` hacia `/viajes` y actualizar `'home' => '/viajes'` en `config/fortify.php`.
- Rediseñar todas las vistas de autenticación (`Login.vue`, `Register.vue`, `ForgotPassword.vue`, `ResetPassword.vue`, `AuthenticationCard.vue`, `Checkbox.vue`) bajo el tema Dark Neon.

**Non-Goals:**
- No implementar códigos de expiración temporal compleja ni límites de uso (el código del viaje permanece activo mientras el viaje exista).
- No implementar envío de correos transaccionales para las invitaciones.

## Decisions

### 1. Generación del Código de Invitación
Al crear un viaje en Eloquent (`Viaje::booted` o en `StoreViajeRequest` / `ViajeController::store`):
```php
do {
    $codigo = strtoupper(Str::random(8));
} while (Viaje::where('codigo_invitacion', $codigo)->exists());
$viaje->codigo_invitacion = $codigo;
```

### 2. Auto-registro del Creador y Prohibición de Nombres Libres
- Al llamar a `store()` en `ViajeController`:
  1. Se crea el viaje con su `codigo_invitacion`.
  2. Se crea un registro en `participantes` con `viaje_id = $viaje->id`, `user_id = $user->id`, y `nombre = $user->name`.
- La ruta `POST /viajes/{viaje}/participantes` se deshabilita o restringe, eliminando el formulario donde el creador inventaba nombres libres. Los participantes ingresan únicamente a través de su cuenta de usuario y el código de invitación.

### 3. Acción de Unión a Viaje (`viajes.unirse`)
```php
public function unirse(Request $request): RedirectResponse
{
    $request->validate([
        'codigo_invitacion' => ['required', 'string', 'size:8'],
    ]);

    $viaje = Viaje::where('codigo_invitacion', strtoupper($request->codigo_invitacion))->first();

    if (! $viaje) {
        return back()->withErrors(['codigo_invitacion' => 'El código de invitación ingresado no existe.']);
    }

    $user = $request->user();

    if ($viaje->participantes()->where('user_id', $user->id)->exists()) {
        return back()->withErrors(['codigo_invitacion' => 'Ya eres participante de este viaje.']);
    }

    $viaje->participantes()->create([
        'user_id' => $user->id,
        'nombre' => $user->name,
    ]);

    return redirect()->route('viajes.show', $viaje)
        ->with('flash.banner', "¡Te has unido exitosamente al viaje {$viaje->nombre}!")
        ->with('flash.bannerStyle', 'success');
}
```

### 4. Consultas y Políticas Multiusuario
En `ViajeController::index`:
```php
$query = Viaje::query()
    ->where(function ($q) use ($user) {
        $q->where('user_id', $user->id)
          ->orWhereHas('participantes', fn ($p) => $p->where('user_id', $user->id));
    })
    ->withCount('participantes')
    ->latest();
```
En `ViajePolicy::view`:
```php
return $user->id === $viaje->user_id
    || $viaje->participantes()->where('user_id', $user->id)->exists();
```

### 5. Rediseño de Vistas de Autenticación
- `AuthenticationCard.vue`: `bg-zinc-950 text-zinc-100 flex flex-col sm:justify-center items-center pt-6 sm:pt-0`, tarjeta interna en `bg-zinc-900 border border-zinc-800 rounded-2xl shadow-2xl shadow-cyan-950/30`.
- `Login.vue` y `Register.vue`: campos de entrada con `TextInput` estilizados, etiquetas claras, enlaces en `text-cyan-400 hover:text-cyan-300`, botón `PrimaryButton` con gradiente neón.
- `Checkbox.vue`: casillas con `bg-zinc-900 border-zinc-700 text-cyan-500 rounded focus:ring-cyan-400/20`.

## Risks / Trade-offs

- **[Colisión de códigos]** $\rightarrow$ Mitigación: Con 8 caracteres alfanuméricos mayúsculas ($36^8 \approx 2.8 \times 10^{12}$ combinaciones) y validación en bucle, la probabilidad de colisión es prácticamente nula.
- **[Migración de datos existentes]** $\rightarrow$ Mitigación: El seeder se actualiza creando usuarios para Ana, Beto, Carla y Diego, asociando sus `user_id` correspondientes a los registros de `participantes` para mantener 100% de coherencia en los cálculos de Samaipata.
