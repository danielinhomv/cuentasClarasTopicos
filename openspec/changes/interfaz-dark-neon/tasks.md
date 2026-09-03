## 1. Configuración de Estilos y Base Dark Neon

- [x] 1.1 Configurar la paleta Dark Neon en Tailwind CSS y estilos base (`resources/css/app.css` y `tailwind.config.js`), definiendo colores oscuros de fondo (`zinc-950`), contenedores (`zinc-900/80`) y acentos luminosos en esmeralda, cian, violeta y ámbar. Verificar: `npm run build` compila los estilos correctamente. Spec: vinculada a `interfaz-dark-neon/spec.md`.

- [x] 1.2 Adaptar `resources/js/Layouts/AppLayout.vue` y componentes base (botones primarios/secundarios, modales y entradas de texto) al tema Dark Neon con navegación limpia y contraste accesible. Verificar: la navegación principal se visualiza en modo oscuro con acentos neón. Spec: vinculada a `interfaz-dark-neon/spec.md`.

## 2. Adaptación de Controladores para Enriquecimiento de Datos

- [x] 2.1 Actualizar `ViajeController::show` para inyectar a la vista Inertia los participantes, gastos (con pagador y excluidos), saldos calculados con `CalculoBalanceService` y transferencias de liquidación con `AlgoritmoLiquidacionService`. Verificar: la respuesta de Inertia incluye los props `saldos` y `liquidacion`. Spec: vinculada a `interfaz-saldos-liquidacion/spec.md`.

- [x] 2.2 Adaptar `GastoController` (`store`, `update`, `destroy`) para redirigir a `viajes.show` con mensajes flash cuando la petición proviene de Inertia, manteniendo compatibilidad con respuestas JSON en llamadas API. Verificar: `GastoTest` mantiene 100% de tests aprobados y las peticiones web redirigen correctamente. Spec: vinculada a `interfaz-gastos-exclusiones/spec.md`.

## 3. Vistas de Viajes (Index, Create, Edit)

- [x] 3.1 Rediseñar `resources/js/Pages/Viajes/Index.vue` con tarjetas oscuras estilizadas, buscador en tiempo real, estadísticas de participantes y badges neón. Verificar: `/viajes` renderiza con temática Dark Neon limpia y responsiva. Spec: vinculada a `interfaz-dark-neon/spec.md`.

- [x] 3.2 Rediseñar `resources/js/Pages/Viajes/Create.vue` y `resources/js/Pages/Viajes/Edit.vue` para armonizar con el tema oscuro y los botones luminosos. Verificar: creación y edición de viajes operan sin errores visuales. Spec: vinculada a `interfaz-dark-neon/spec.md`.

## 4. Vista Integral del Viaje (Show.vue con Pestañas)

- [x] 4.1 Implementar en `resources/js/Pages/Viajes/Show.vue` el encabezado con métricas y el selector de pestañas interactivas (*Tabs: Participantes, Gastos, Saldos, Liquidación*). Verificar: cambio dinámico entre pestañas sin recarga de página. Spec: vinculada a `interfaz-dark-neon/spec.md`.

- [x] 4.2 Integrar el tab de **Participantes** con buscador, alta inline rápida, modal de edición y modal de confirmación de baja en estilo oscuro. Verificar: alta y edición de participantes operan con validación inmediata. Spec: vinculada a `interfaz-dark-neon/spec.md`.

- [x] 4.3 Integrar el tab de **Gastos** con listado ordenado, desglose de pagador y excluidos, y modal reactivo para registrar/editar gastos con selección de pagador y checklist de exclusiones. Verificar: registro de gasto persiste y actualiza la lista. Spec: vinculada a `interfaz-gastos-exclusiones/spec.md`.

- [x] 4.4 Integrar el tab de **Saldos** con tabla comparativa de total pagado, consumo individual y balance neto con códigos de color verde/rojo neón y badge de invariante $\sum = 0$. Verificar: refleja los balances de Samaipata (+560 Ana, 0 Beto, -160 Carla, -400 Diego). Spec: vinculada a `interfaz-saldos-liquidacion/spec.md`.

- [x] 4.5 Integrar el tab de **Liquidación** con tarjetas de transferencias óptimas directas y flechas direccionales neón. Verificar: muestra exactamente las 2 transferencias calculadas para Samaipata (Diego $\rightarrow$ Ana Bs. 400 y Carla $\rightarrow$ Ana Bs. 160). Spec: vinculada a `interfaz-saldos-liquidacion/spec.md`.

## 5. Compilación y Verificación en Docker

- [x] 5.1 Ejecutar `npm run build` en el host para generar el bundle de producción de Vite en `public/build/`. Verificar: compilación limpia sin advertencias de sintaxis.

- [x] 5.2 Ejecutar `php artisan migrate:fresh --seed` en el contenedor de Docker para asegurar que la base de datos PostgreSQL cuente con todos los participantes y los 4 gastos de Samaipata sembrados. Verificar: `Gasto::count()` arroja 4 en PostgreSQL.

- [x] 5.3 Ejecutar la suite completa de pruebas automatizadas dentro de Docker (`php artisan test`). Verificar: todas las pruebas de backend y feature continúan pasando al 100%.
