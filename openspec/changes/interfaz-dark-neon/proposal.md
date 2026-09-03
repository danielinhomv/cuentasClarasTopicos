## Why

Hasta ahora, la aplicación ha completado de forma robusta la capa backend (autenticación, gestión de viajes y participantes, persistencia de gastos con exclusiones y los algoritmos de cálculo de saldos y liquidación mínima). Sin embargo, los usuarios carecen de una interfaz gráfica moderna, intuitiva y visualmente atractiva para interactuar con estas funciones desde el navegador.

Esta propuesta introduce el diseño y desarrollo de la interfaz de usuario en el frontend (Inertia.js + Vue 3 + Tailwind CSS), adoptando una estética limpia de **colores neón oscuros** (*dark neon* con fondo oscuro profundo `zinc-900/950`, tipografía nítida y acentos luminosos en cian, esmeralda, violeta y ámbar) diseñada para máxima facilidad de uso, visualización clara de transacciones y consulta inmediata de deudas y saldos.

## What Changes

- **Sistema de diseño Dark Neon en Tailwind CSS:**
  - Paleta base oscura elegante (`zinc-950` para fondo, `zinc-900/800` para tarjetas y contenedores elevados).
  - Acentos neón luminosos para estados clave:
    - *Neón Esmeralda / Cian (`emerald-400`/`cyan-400`)*: Saldos positivos / acreedores ("le deben dinero"), botones de acción principal y confirmaciones.
    - *Neón Ámbar / Naranja (`amber-400`/`orange-400`)*: Deudas pendientes, advertencias y exclusiones de participantes.
    - *Neón Rojo / Rosa (`rose-400`/`pink-400`)*: Saldos negativos ("debe dinero"), acciones destructivas y cancelaciones.
    - *Neón Violeta / Índigo (`violet-400`/`indigo-400`)*: Tarjetas de transferencias de liquidación y encabezados destacados.
  - Microinteracciones limpias, bordes sutiles con resplandor (*subtle neon glow* y `border-zinc-700/50`) y tipografía con alto contraste.

- **Diseño del Shell y Navegación Principal (`AppLayout.vue`):**
  - Barra de navegación superior oscura con logotipo con resplandor neón, enlaces de navegación limpios y menú de usuario.
  - Contenedores de ancho adaptable optimizados tanto para móviles como para pantallas de escritorio.

- **Vistas y Componentes para Viajes y Participantes:**
  - Actualización de `Viajes/Index.vue` con tarjetas de viaje en modo dark neon, buscador en tiempo real, estadísticas de participantes y badges temáticos.
  - Formularios modales y vistas `Create.vue` y `Edit.vue` estilizados.

- **Panel Integral de Detalle del Viaje (`Viajes/Show.vue`):**
  - Navegación por pestañas intuitivas (*tabs*) para alternar sin recargas entre:
    1. **Participantes:** Lista de integrantes con badges, buscador rápido, alta inline y edición/eliminación con modales oscuros.
    2. **Gastos:** Listado de gastos registrados con fecha, pagador, monto formateado, participantes excluidos y modal para registrar/editar gastos con selector de pagador y checklist de exclusiones.
    3. **Saldos:** Tabla visual de balances individuales indicando claramente quién pagó, cuánto consumió y el saldo neto con código de color neón (verde para acreedor, rojo para deudor, gris para saldo neutro).
    4. **Liquidación:** Panel de transferencias sugeridas en tarjetas con flechas luminosas (*"Diego le transfiere a Ana: Bs. 400.00"*), mostrando el estado final de las cuentas saldadas.

### Casos de uso cubiertos

Esta propuesta cubre a nivel de interfaz de usuario:
- **Caso de uso 1: Gestionar participantes** (interfaz de alta, listado, edición y baja con confirmación).
- **Caso de uso 2: Gestionar gastos** (interfaz para registrar, editar y listar gastos asociados a participantes).
- **Caso de uso 3: Excluir participantes de un gasto** (selector interactivo de exclusiones en el formulario de gasto).
- **Caso de uso 4: Calcular saldos** (vista visual de balances netos por participante).
- **Caso de uso 5: Calcular liquidación** (vista del plan de transferencias directas óptimas).
- **Caso de uso 7: Gestionar viaje** (interfaz de listado, creación, edición y detalle de viajes).

### Fuera de alcance

- Pasarelas de pago reales o integración con bancos (la app presenta el plan de transferencias para realizar en la vida real).
- Modificaciones a los algoritmos de cálculo backend (se reutilizan al 100% los servicios `CalculoBalanceService` y `AlgoritmoLiquidacionService`).

## Capabilities

### New Capabilities
- `interfaz-dark-neon`: Sistema de diseño global oscuro con acentos neón, componentes base estilizados (botones, inputs, modales, alertas, tabs) y navegación principal.
- `interfaz-gastos-exclusiones`: Componentes visuales para el registro, edición, listado de gastos y gestión intuitiva de exclusiones por checkbox.
- `interfaz-saldos-liquidacion`: Componentes visuales para visualización de balances netos y tarjetas de liquidación de transferencias sugeridas.

### Modified Capabilities
- *(ninguna; los requerimientos funcionales del backend no sufren alteraciones)*

## Impact

- **Frontend (Vue / Inertia):** Creación y rediseño de componentes en `resources/js/Pages/` y `resources/js/Components/`.
- **CSS / Estilos:** Configuración y clases de utilidad en `tailwind.config.js` y `resources/css/app.css` para el tema oscuro con acentos neón.
- **Controladores Inertia:** Enriquecimiento de `ViajeController::show` para proporcionar al frontend los datos de participantes, gastos, saldos y liquidación necesarios para el renderizado inmediato sin peticiones asíncronas redundantes.
