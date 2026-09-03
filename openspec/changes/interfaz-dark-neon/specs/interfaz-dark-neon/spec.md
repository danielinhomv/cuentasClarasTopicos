## Purpose

Proporciona la experiencia visual base del sistema Cuentas Claras bajo una estética oscura moderna con acentos de color neón (Dark Neon), facilitando la navegación accesible, legible y eficiente en dispositivos móviles y de escritorio.

## ADDED Requirements

### Requirement: Interfaz visual en modo Dark Neon
El sistema SHALL presentar un tema visual oscuro profundo con fondo `zinc-950` y tarjetas `zinc-900/800`, con acentos luminosos en tonos neón (cian, esmeralda, violeta y ámbar), manteniendo contraste suficiente para lectura óptima.

#### Scenario: Visualización del panel principal de viajes
- **DADO** un usuario autenticado que accede a la ruta `/viajes`
- **WHEN** se carga la página
- **THEN** la vista se renderiza con el tema dark neon, mostrando las tarjetas de viajes con bordes sutiles, títulos destacados y botones con acentos luminosos

#### Scenario: Visualización responsiva en dispositivos móviles
- **DADO** un usuario navegando desde un dispositivo móvil o pantalla estrecha
- **WHEN** visualiza el listado o detalle de viajes
- **THEN** el diseño se adapta verticalmente sin desbordamientos horizontales, con áreas táctiles cómodas para botones y modales

### Requirement: Navegación del viaje organizada por pestañas
El sistema SHALL organizar la información del viaje (`/viajes/{viaje}`) mediante pestañas interactivas de cambio instantáneo: Participantes, Gastos, Saldos y Liquidación.

#### Scenario: Cambio dinámico entre secciones del viaje
- **DADO** un usuario en la pantalla de detalle de un viaje
- **WHEN** hace clic en la pestaña "Gastos" o "Liquidación"
- **THEN** la vista muestra el contenido de la pestaña seleccionada con animación de resaltado neón activo sin recargar la página completa
