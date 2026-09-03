## 1. Dependencia

- [x] 1.1 Alcance: solo Service (infraestructura). Tipo: no funcional. Añadir `barryvdh/laravel-dompdf` al proyecto y publicar config si hace falta. No ejecutar `migrate`. Verificar: `composer show barryvdh/laravel-dompdf` lista el paquete. Spec: Usuario autorizado puede exportar el viaje a PDF.

## 2. Service de proyección

- [x] 2.1 Alcance: solo Service. Tipo: funcional (Casos de uso 4, 5 y 7). Crear `ExportarViajePdfService` que arme el DTO del reporte reutilizando `CalculoBalanceService`, `desgloseEfectivo` y `RegistroLiquidacionService` (sin nueva fórmula). Incluir participantes (con/sin cuenta, anfitrión), gastos, cuotas, redondeo Bs 0,50, saldos (Σ = 0), liquidaciones completas/parciales/pendientes y `generado_en`. Excluir IDs, emails, bitácora y código de invitación. Verificar: un test unitario del DTO contra Samaipata (total `1600.00`, Diego→Ana `400`, Carla→Ana `160`) y un gasto `55.40` → cuotas `27.50`/`28.00`. Spec: Las cifras del PDF coinciden con el cálculo de la aplicación.

## 3. Controlador, ruta y plantilla PDF

- [x] 3.1 Alcance: solo controlador y rutas. Tipo: funcional (Caso de uso 7). Crear `ViajePdfController::exportar` que autorice con `ViajePolicy::view`, genere el PDF al vuelo y lo descargue. Registrar `GET /viajes/{viaje}/exportar-pdf` como `viajes.exportar-pdf` en el grupo auth de `web.php`. Verificar: `php artisan route:list` muestra la ruta. Spec: Usuario autorizado puede exportar el viaje a PDF.

- [x] 3.2 Alcance: solo Service (plantilla de render, no UI de la app). Tipo: no funcional (presentación del archivo). Crear la plantilla HTML/CSS del PDF con secciones Resumen, Participantes, Resumen de gastos, Detalle, Saldos y Liquidaciones; fecha de generación; `page-break-inside: avoid` en bloques de gasto; A4. Verificar: un viaje con 20+ gastos produce PDF de más de una página sin error. Spec: Viajes vacíos y listados largos se exportan de forma usable.

## 4. Pruebas

- [x] 4.1 Alcance: solo pruebas. Tipo: funcional (Casos de uso 4, 5, 7 y 8). Crear `tests/Feature/ExportacionViajePdfTest.php` usando el seeder/escenario de Samaipata ya existente (no duplicar seed): 200 + `application/pdf` + cabecera `%PDF`; participante con cuenta 200; invitado 401; ajeno 403; viaje inexistente 404; viaje sin gastos 200 y total `0.00`; liquidación parcial visible; texto sin emails ni código de invitación; montos del PDF iguales a los services. Verificar: la suite pasa. Spec: todos los escenarios de `exportacion-pdf`.

## 5. Control de descarga (excepción de UI)

- [x] 5.1 Alcance: control único en detalle de viaje. Tipo: funcional (Caso de uso 7). Añadir la acción "Exportar a PDF" en `Viajes/Show.vue` apuntando a `route('viajes.exportar-pdf')` sin rediseñar el resto de la página. La instrucción de proyecto pide no hacer vistas; esta tarea existe porque el usuario pidió exportar desde el viaje. Verificar: el control está visible para quien puede ver el viaje. Spec: El detalle del viaje ofrece exportar.
