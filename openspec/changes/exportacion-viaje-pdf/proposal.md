## Why

Los participantes de un viaje necesitan un documento portable para revisar gastos, cuotas y deudas fuera de la app (WhatsApp, impresión). Hoy esas cifras solo viven en la pantalla del viaje; quien no tiene cuenta o no está logueado no puede reconstruir las cuentas por su cuenta.

## What Changes

- Nuevo endpoint autenticado que genera un PDF del viaje con los **mismos** saldos, cuotas, redondeos y liquidaciones que ya calcula la aplicación (sin un motor de cálculo paralelo).
- El PDF es un reporte de lectura: resumen del viaje, participantes, gastos (quién pagó, monto, cómo se dividió, cuota de cada uno), saldos/deudas, liquidaciones hechas, parciales y pendientes, y redondeo a Bs 0,50 cuando aplique.
- Fecha de generación, varias páginas sin cortar filas/secciones a la mitad, tipografía y márgenes aptos para celular e impresión.
- Quien puede **ver** el viaje puede exportarlo (anfitrión o participante con cuenta).
- Un control **Exportar a PDF** en el detalle de viaje existente, para descargar el archivo y compartirlo.
- **No es BREAKING**: no altera persistencia ni fórmulas de gastos, saldos o liquidaciones.

### Casos de uso del backend que cubre

- **Caso de uso 4: Calcular saldos** (el PDF solo refleja esos saldos).
- **Caso de uso 5: Calcular liquidación** (deudas, pagos parciales y pendientes).
- **Caso de uso 7: Gestionar viaje** (exportar el detalle de un viaje propio o en el que se participa).

### Fuera de alcance

- **Nuevas pantallas / rediseño Inertia o Blade** de consulta de viajes: no se crea un módulo visual nuevo.
- **Excepción de UI (pedida en este cambio):** un único botón o enlace "Exportar a PDF" en `Viajes/Show.vue`. El resto de la interfaz no se rediseña. La instrucción de proyecto "solo backend / sin Inertia" entra en conflicto con esa excepción; se respeta la petición explícita de poder exportar desde el viaje.
- Bitácora de auditoría, IDs internos, emails, contraseñas, tokens, código de invitación y datos de autenticación: no van en el PDF.
- Envío automático por WhatsApp, email o almacenamiento permanente del PDF en disco/S3: solo descarga en el momento.
- Recalcular deudas con otra fórmula "para que se vea más simple" en el PDF.

## Capabilities

### New Capabilities

- `exportacion-pdf`: Generación y descarga de un reporte PDF del estado actual de un viaje (participantes, gastos, cuotas, redondeos, saldos y liquidaciones), autorizado con `ViajePolicy::view`.

### Modified Capabilities

- (ninguna: no hay specs principales en `openspec/specs/`; el cálculo existente no cambia de requisitos)

## Impact

- **Dependencia:** `barryvdh/laravel-dompdf` (HTML/CSS → PDF en PHP, sin Chrome/Node).
- **Backend:** servicio que arma el DTO del reporte reutilizando `CalculoBalanceService`, `AjusteEfectivoService` / `desgloseEfectivo` y `RegistroLiquidacionService`; controlador + ruta nombrada `viajes.exportar-pdf`.
- **Vista de plantilla:** Blade **solo** como layout del PDF (no es UI de la app).
- **Frontend:** un control de descarga en el detalle de viaje.
- **Pruebas:** feature de autorización, contenido mínimo (Samaipata), viaje vacío, liquidaciones parciales y que el PDF no invente cifras distintas a los servicios.
