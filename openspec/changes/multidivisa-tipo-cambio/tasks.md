## 1. Migraciones y Modelos

- [x] 1.1 Crear migración para agregar `tipo_cambio_usd` y `tipo_cambio_usdt` en la tabla `viajes`, y `moneda` y `tipo_cambio` en la tabla `gastos`. Verificar: `php artisan migrate` corre exitosamente. Spec: vinculada a `gestion-tipo-cambio/spec.md` y `multidivisa-gastos/spec.md`.

- [x] 1.2 Actualizar `$fillable` y atributos en los modelos `Viaje` y `Gasto`. Verificar: atributos `moneda`, `tipo_cambio_usd` y `tipo_cambio_usdt` son asignables y cuentan con valores por defecto. Spec: vinculada a `gestion-tipo-cambio/spec.md`.

## 2. Lógica Backend de Tipo de Cambio y Autorización

- [x] 2.1 Implementar acción `actualizarTipoCambio` en `ViajeController` con validación numérica estricta y protección por `ViajePolicy::update` (exclusivo para el creador). Verificar: solo el creador puede modificar cotizaciones; otros usuarios reciben 403 Forbidden. Spec: vinculada a `gestion-tipo-cambio/spec.md`.

- [x] 2.2 Registrar ruta `PUT /viajes/{viaje}/tipo-cambio` en `routes/web.php`. Verificar: la ruta nombrada `viajes.tipo-cambio.update` queda registrada. Spec: vinculada a `gestion-tipo-cambio/spec.md`.

## 3. Motor de Consolidación Multidivisa en Servicios

- [x] 3.1 Actualizar `CalculoBalanceService` para calcular el monto consolidado en divisa base (`round(monto * tasa, 2)`) y centavos enteros, garantizando la suma cero invariante ($\sum \text{balances} = 0$). Verificar: pruebas unitarias con gastos mixtos en BOB, USD y USDT arrojan balance neto = 0 exacto. Spec: vinculada a `multidivisa-gastos/spec.md`.

- [x] 3.2 Actualizar `StoreGastoRequest` y `UpdateGastoRequest` para validar `moneda` (`in:BOB,USD,USDT`). Verificar: rechazo de monedas no soportadas y aceptación de las tres divisas. Spec: vinculada a `multidivisa-gastos/spec.md`.

## 4. Interfaz de Usuario Multidivisa en Dark Neon

- [x] 4.1 En `resources/js/Pages/Viajes/Show.vue`, agregar selector interactivo de moneda (`BOB`, `USD`, `USDT`) en el modal de gasto con cálculo dinámico de equivalencia aproximada en Bs. Verificar: el formulario permite cambiar divisa y muestra el equivalente en Bs. Spec: vinculada a `multidivisa-gastos/spec.md`.

- [x] 4.2 En `resources/js/Pages/Viajes/Show.vue`, incorporar widget de Tipo de Cambio en la cabecera y modal de edición de tasas disponible únicamente para el creador del viaje. Verificar: el creador puede abrir el modal y actualizar cotizaciones de USD y USDT. Spec: vinculada a `gestion-tipo-cambio/spec.md`.

- [x] 4.3 En la pestaña de Gastos de `Show.vue`, mostrar badges de divisa y montos consolidados en Bs. Verificar: cada gasto en USD o USDT muestra su importe original y su equivalente en Bs. Spec: vinculada a `multidivisa-gastos/spec.md`.

## 5. Verificación, Tests y Docker

- [x] 5.1 Crear pruebas unitarias y de integración para validar la consolidación multidivisa y los permisos de tipo de cambio.

- [x] 5.2 Ejecutar `npm run build` en el host para verificar compilación sin errores.

- [x] 5.3 Ejecutar `php artisan test` en el contenedor Docker. Verificar: 100% de las pruebas pasan con éxito.
