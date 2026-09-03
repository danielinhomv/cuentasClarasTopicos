## Context

Ver motivación en `proposal.md` y especificaciones en `specs/interfaz-dark-neon/spec.md`, `specs/interfaz-gastos-exclusiones/spec.md` y `specs/interfaz-saldos-liquidacion/spec.md`.

El backend dispone de los modelos `User`, `Viaje`, `Participante`, `Gasto`, la tabla pivote `gasto_exclusiones` y los servicios `CalculoBalanceService` y `AlgoritmoLiquidacionService`. Este diseño técnico define la implementación del frontend en Inertia.js (Vue 3) con Tailwind CSS, organizando la interfaz en un tema **Dark Neon** de alta legibilidad, facilidad de uso y actualización reactiva.

## Goals / Non-Goals

**Goals:**
- Configurar la paleta de colores y clases utilitarias de Dark Neon en Tailwind CSS y estilos base.
- Enriquecer `ViajeController::show` para enviar a la vista Inertia el viaje con sus participantes, gastos (con pagador y excluidos), saldos y liquidación calculados.
- Adaptar `GastoController` para que al recibir peticiones web/Inertia redirija de vuelta a `viajes.show` con mensajes de éxito flash (`preserveScroll: true`), manteniendo además compatibilidad JSON si se invoca como API.
- Actualizar `resources/js/Layouts/AppLayout.vue` con tema oscuro coherente, navegación limpia y menú de usuario.
- Rediseñar `resources/js/Pages/Viajes/Index.vue` con tarjetas oscuras, estadísticas y buscador.
- Diseñar `resources/js/Pages/Viajes/Show.vue` con navegación por pestañas (*tabs*):
  - **Tab Participantes:** Lista, alta rápida con validación client-side, edición modal y confirmación de baja.
  - **Tab Gastos:** Lista de gastos con fecha, concepto, monto, pagador y badges de excluidos; modal reactivo para crear/editar gastos con selección de pagador y casillas de exclusión.
  - **Tab Saldos:** Tabla comparativa con total pagado, consumo, balance neto coloreado (verde/rojo neón) y verificación de $\sum = 0$.
  - **Tab Liquidación:** Tarjetas de transferencias optimizadas con flechas direccionales luminosas (*Diego $\rightarrow$ Ana Bs. 400*).

**Non-Goals:**
- No crear un framework de frontend externo ni cliente SPA desacoplado (se aprovecha el stack unificado de Inertia.js sin sobrecarga de APIs separadas).
- No modificar los algoritmos de división ni la base de datos PostgreSQL.

## Decisions

### 1. Paleta Cromática Dark Neon y Jerarquía Visual

Para lograr un aspecto visual moderno, limpio y con excelente contraste:
- **Fondo primario:** `bg-zinc-950` (`#09090b`), con texto base `text-zinc-100`.
- **Superficies y tarjetas elevadas:** `bg-zinc-900/90` con bordes sutiles `border-zinc-800` y `shadow-2xl`.
- **Acentos Neón Semánticos:**
  - *Acreedores / Dinero a favor:* Verde Esmeralda (`text-emerald-400`, `bg-emerald-950/30`, `border-emerald-500/30`, brillo sutil).
  - *Deudores / Dinero a pagar:* Rosa/Rojo Neón (`text-rose-400`, `bg-rose-950/30`, `border-rose-500/30`).
  - *Saldado / Neutro:* Gris Plata (`text-zinc-400`, `bg-zinc-800/40`, `border-zinc-700/30`).
  - *Liquidación y Transferencias:* Violeta/Cian (`text-violet-300`, `bg-violet-950/30`, `border-violet-500/30`).
  - *Acción Principal:* Botones cian/esmeralda con hover luminoso (`bg-cyan-500 hover:bg-cyan-400 text-zinc-950 font-semibold shadow-lg shadow-cyan-500/20`).

### 2. Flujo de Datos en `ViajeController::show`

En lugar de requerir que el cliente frontend realice múltiples llamadas fetch/axios secundarias para cargar saldos y liquidación, el controlador inyecta directamente el estado completo:

```php
public function show(
    Viaje $viaje,
    CalculoBalanceService $balanceService,
    AlgoritmoLiquidacionService $liquidacionService
): Response {
    $this->authorize('view', $viaje);

    $viaje->load([
        'participantes' => fn ($q) => $q->orderBy('nombre'),
        'gastos.pagador',
        'gastos.excluidos',
    ]);

    $saldos = $balanceService->calcularBalances($viaje);
    $liquidacion = $liquidacionService->calcularLiquidacion($saldos);

    return Inertia::render('Viajes/Show', [
        'viaje' => $viaje,
        'saldos' => $saldos,
        'liquidacion' => $liquidacion,
    ]);
}
```

*Ventaja:* Carga atómica en un único round-trip. Cambiar de pestaña en Vue es instantáneo (0 ms de latencia) y no genera parpadeos ni estados de carga adicionales.

### 3. Operaciones de Gastos en `GastoController` con Soporte Inertia

Al registrar, editar o eliminar un gasto desde el formulario de Inertia:
- Si la petición es estándar de Inertia (`!$request->wantsJson()` o `$request->inertia()`), el controlador redirige a `route('viajes.show', $viaje)` con mensaje flash de éxito.
- Si la petición es explícitamente JSON (`$request->wantsJson()`), continúa retornando el payload JSON para mantener 100% la compatibilidad con los tests de API existentes.

### 4. Estructura de Componentes en `Viajes/Show.vue`

La vista `Show.vue` se descompone limpiamente en subsecciones:
1. **Header del Viaje:** Título, descripción, acciones rápidas (editar/eliminar viaje) y resumen de métricas clave (Total Participantes, Total Gastado, Total Transferencias).
2. **Selector de Pestañas:** Tabs con indicador neón deslizante:
   - `participantes`
   - `gastos`
   - `saldos`
   - `liquidacion`
3. **Tab Participantes:** Lista con buscador, alta inline y modales de edición/eliminación.
4. **Tab Gastos:** Tabla/tarjetas con filtros, visualización de pagador y excluidos, y modal completo para registrar/editar gastos con cálculo en vivo de cuota parte estimada.
5. **Tab Saldos:** Tabla con desglose `(Pagado, Consumido, Balance)` y badge visual de estado.
6. **Tab Liquidación:** Tarjetas de transferencias directas con íconos de flechas neón y estado de cuentas saldadas.

## Risks / Trade-offs

- **[Pérdida de pestaña activa tras enviar un formulario]** $\rightarrow$ Mitigación: El componente recuerda la pestaña seleccionada en el estado reactivo (`currentTab = ref('gastos')`), manteniéndola visible al recibir la respuesta de Inertia con `preserveScroll: true`.
- **[Exclusión errónea del 100% de participantes en el modal]** $\rightarrow$ Mitigación: Doble validación: deshabilitación visual y alerta en tiempo real en Vue cuando el usuario marca a todos los integrantes, respaldada por el `StoreGastoRequest` en el backend.
- **[Imprecisión en pantallas pequeñas]** $\rightarrow$ Mitigación: Las tablas cuentan con scroll horizontal protegido o colapsan a tarjetas verticales en breakpoints móviles (`sm:` y `md:`).

## Migration Plan

1. Actualizar configuración de Tailwind y estilos base para tema oscuro con acentos neón.
2. Adaptar `ViajeController::show` y `GastoController` para pasar datos y soportar redirecciones Inertia.
3. Actualizar `AppLayout.vue` y componentes base (botones, inputs, modales).
4. Rediseñar `Viajes/Index.vue`, `Viajes/Create.vue` y `Viajes/Edit.vue`.
5. Implementar `Viajes/Show.vue` con las 4 pestañas interactivas completas.
6. Ejecutar compilación de assets con `npm run build`.
7. Ejecutar migraciones y seeder en Docker para visualizar el escenario de Samaipata completo.
8. Validar con pruebas automatizadas que no existen regresiones.
