## Purpose

Permite descargar un PDF portable del estado actual de un viaje para que cualquier participante pueda revisar gastos, cuotas, redondeos y liquidaciones fuera de la aplicación.

## ADDED Requirements

### Requirement: Usuario autorizado puede exportar el viaje a PDF

El sistema SHALL permitir a un usuario autenticado que tenga permiso de ver el viaje (`anfitrión` o participante con cuenta) descargar un archivo PDF del viaje. Un invitado no autenticado MUST ser redirigido o recibir 401. Un usuario que no participa en el viaje MUST recibir 403. El PDF MUST generarse en el momento a partir de PostgreSQL y de los mismos servicios de cálculo que usa la app; MUST NOT persistirse como archivo permanente.

#### Scenario: Exportación exitosa del viaje de Samaipata
- **DADO** el viaje de Samaipata con los 4 gastos oficiales y el anfitrión autenticado
- **WHEN** solicita la exportación a PDF
- **THEN** recibe un archivo PDF (`Content-Type: application/pdf`) con nombre que incluye el viaje, y el documento muestra Ana/Beto/Carla/Diego, total gastado `1600.00`, transferencias Diego → Ana `400.00` y Carla → Ana `160.00`

#### Scenario: Participante con cuenta puede exportar
- **DADO** Beto autenticado, participante del viaje (no anfitrión)
- **WHEN** solicita la exportación
- **THEN** el sistema entrega el mismo tipo de PDF (HTTP 200)

#### Scenario: Acceso sin autenticar
- **DADO** un visitante sin sesión
- **WHEN** llama al endpoint de exportación
- **THEN** el sistema deniega el acceso (HTTP 401 o redirección a login)

#### Scenario: Usuario ajeno al viaje
- **DADO** un usuario autenticado que no es anfitrión ni participante
- **WHEN** solicita exportar ese viaje
- **THEN** el sistema responde HTTP 403

#### Scenario: El PDF refleja lo persistido al refrescar
- **DADO** un viaje cuyos gastos o abonos acaban de cambiar en PostgreSQL
- **WHEN** se vuelve a exportar el PDF
- **THEN** el documento muestra las cifras actualizadas, no un caché anterior

### Requirement: El PDF contiene solo la información necesaria para entender las cuentas

El PDF SHALL incluir, y solo eso: nombre del viaje; fecha/hora de generación; lista de participantes (nombre; se MAY marcar anfitrión o “sin cuenta” sin exponer emails ni IDs); total gastado; cada gasto (concepto, fecha, pagador, monto original, moneda si no es BOB, incluidos, cuota de cada uno, si hubo redondeo a Bs 0,50); saldos (pagado, consumido, balance); liquidaciones con original, pagado, pendiente y estado (hecha / parcial / pendiente). MUST NOT incluir IDs internos, bitácora, emails, contraseñas, tokens, código de invitación ni datos de autenticación.

#### Scenario: Secciones mínimas presentes
- **DADO** un viaje con al menos un gasto
- **WHEN** se genera el PDF
- **THEN** aparecen las secciones Resumen, Participantes, Resumen de gastos, Detalle de gastos, Saldos y deudas, y Liquidaciones, más la fecha de generación

#### Scenario: No se filtran datos internos
- **DADO** un viaje con bitácora, código de invitación y usuarios con email
- **WHEN** se genera el PDF
- **THEN** el texto extraído del PDF no contiene emails, `user_id`, IDs numéricos de filas, el código de invitación ni entradas de bitácora

### Requirement: Las cifras del PDF coinciden con el cálculo de la aplicación

El PDF MUST reutilizar el cálculo vigente de cuotas (división entre incluidos, exclusiones, participantes con o sin cuenta) y el redondeo a múltiplos de Bs 0,50 que favorece al anfitrión. MUST NOT implementar otra fórmula. Los saldos y liquidaciones del PDF MUST coincidir con los que devolverían las consultas de saldos/liquidación del mismo viaje en el mismo instante. La suma de balances en el PDF MUST ser `0.00`. Un gasto con monto `<= 0` no existe en el dominio (la persistencia ya lo rechaza); el PDF MUST omitir cualquier fila inválida si apareciera.

#### Scenario: Cuotas y redondeo de un gasto 55,40
- **DADO** un gasto de `55.40` entre anfitrión y otro participante
- **WHEN** se exporta el PDF
- **THEN** el detalle muestra monto original `55.40`, cuotas `27.50` y `28.00` (múltiplos de `0.50`) y no inventa un recargo manual

#### Scenario: Liquidación parcial visible
- **DADO** una deuda Diego → Ana de `40.00` con un abono de `20.00`
- **WHEN** se exporta el PDF
- **THEN** la liquidación figura como parcial: original `40.00`, pagado `20.00`, pendiente `20.00`

#### Scenario: Liquidación completa visible
- **DADO** Diego liquidó por completo `400.00` a Ana en Samaipata
- **WHEN** se exporta el PDF
- **THEN** esa deuda aparece como realizada (pendiente `0.00`) y las demás pendientes se listan aparte

### Requirement: Viajes vacíos y listados largos se exportan de forma usable

Si no hay gastos, el PDF SHALL generarse igual (HTTP 200) con total `0.00`, saldos en cero, sin transferencias y un mensaje de que aún no hay gastos. Si no hay participantes más que el anfitrión y no hay gastos, SHALL listar a ese participante y totales en cero. Nombres repetidos SHALL distinguirse por el nombre mostrado (sin IDs). Un listado largo de gastos o participantes MUST fluir a páginas siguientes sin cortar una fila de tabla a la mitad. El layout MUST ser A4, márgenes legibles en teléfono e impresión.

#### Scenario: Viaje sin gastos
- **DADO** un viaje con participantes y cero gastos
- **WHEN** se exporta el PDF
- **THEN** el archivo es PDF válido, el total es `0.00`, no hay deudas y se indica que no hay gastos

#### Scenario: Viaje con muchos gastos
- **DADO** un viaje con más gastos de los que caben en una página
- **WHEN** se exporta el PDF
- **THEN** el documento tiene más de una página y cada gasto completo (concepto, pagador, monto, cuotas) permanece junto, sin una fila partida entre páginas

#### Scenario: Nombres de participantes repetidos
- **DADO** dos participantes llamados "Ana"
- **WHEN** se exporta el PDF
- **THEN** ambos aparecen en la lista y en los gastos que les correspondan, sin usar IDs internos como distintivo obligatorio en el cuerpo (si hace falta, se usa el orden de aparición)

### Requirement: El detalle del viaje ofrece exportar

El detalle del viaje SHALL mostrar una acción "Exportar a PDF" visible para quien puede ver el viaje. Al activarla, el navegador MUST descargar el PDF generado. Eliminar un participante o el viaje con gastos asociados sigue las reglas ya existentes de la app; el PDF MUST NO exportarse si el viaje ya no existe (404).

#### Scenario: Acción visible en el viaje
- **DADO** el anfitrión en el detalle del viaje
- **WHEN** mira las acciones del viaje
- **THEN** ve "Exportar a PDF"

#### Scenario: Viaje eliminado
- **DADO** un `viaje_id` que ya no existe en PostgreSQL
- **WHEN** se solicita la exportación
- **THEN** el sistema responde 404
