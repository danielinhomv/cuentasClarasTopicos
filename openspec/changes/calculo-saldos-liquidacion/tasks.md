## 1. Servicios de Dominio y Lógica de Negocio

- [x] 1.1 Alcance: solo Service. Tipo: funcional (Caso de uso 4: Calcular saldos y Caso de uso 6: Consistencia). Crear la clase de servicio `App\Services\CalculoBalanceService.php` que compute para un viaje: total pagado, consumo por cuota parte con exclusiones y balance neto por participante en centavos enteros (`int`), aplicando la regla de absorción de centavos residuales por el pagador del gasto y validando la invariante $\sum \text{balances} = 0$. Verificar: el servicio retorna la lista de balances calculada con exactitud decimal. Spec: vinculada a `saldos/spec.md` (Cálculo de balances en Samaipata) y `consistencia/spec.md` (Invariante estricta y Absorción de centavos).

- [x] 1.2 Alcance: solo Service. Tipo: funcional (Caso de uso 5: Calcular liquidación). Crear la clase de servicio `App\Services\AlgoritmoLiquidacionService.php` que implemente el algoritmo voraz (*greedy matching*) para emparejar deudores y acreedores a partir de los balances calculados, produciendo la lista mínima de transferencias directas `(deudor, acreedor, monto)`. Verificar: el servicio genera transferencias que saldan todas las deudas sin ciclos redundantes. Spec: vinculada a `liquidacion/spec.md` (Liquidación óptima en Samaipata).

## 2. Controladores y Rutas Web

- [x] 2.1 Alcance: solo controlador y rutas. Tipo: funcional (Casos de uso 4 y 5). Crear `App\Http\Controllers\LiquidacionController.php` con métodos `saldos(Viaje $viaje)` y `liquidacion(Viaje $viaje)` inyectando los servicios de cálculo y aplicando `ViajePolicy` para autorizar la consulta. Registrar las rutas nombradas `viajes.saldos` y `viajes.liquidacion` bajo middleware `auth` en `routes/web.php`. Verificar: `php artisan route:list` expone los endpoints de saldos y liquidación. Spec: vinculada a `saldos/spec.md` y `liquidacion/spec.md`.

## 3. Pruebas Unitarias de Servicios (Unit Tests)

- [x] 3.1 Alcance: solo pruebas. Tipo: funcional (Caso de uso 4 y 6: Aritmética y Redondeo). Crear `tests/Unit/Services/CalculoBalanceServiceTest.php` verificando: cálculo de saldos con gastos simples, gastos con exclusiones, división no exacta (ej. Bs. 100 entre 3 personas con centavo sobrante absorbido por el pagador), caso de 0 gastos y validación matemática de $\sum \text{balances} = 0.00$. Verificar: la suite de tests unitarios pasa con éxito. Spec: vinculada a `saldos/spec.md` y `consistencia/spec.md` (División no exacta con pagador incluido).

- [x] 3.2 Alcance: solo pruebas. Tipo: funcional (Caso de uso 5: Algoritmo de Liquidación). Crear `tests/Unit/Services/AlgoritmoLiquidacionServiceTest.php` verificando: liquidación óptima del escenario de Samaipata (Diego $\rightarrow$ Ana Bs. 400 y Carla $\rightarrow$ Ana Bs. 160), caso de viaje con saldos en cero (0 transferencias), un deudor hacia múltiples acreedores y múltiples deudores hacia un acreedor. Verificar: la suite de tests unitarios de liquidación pasa con éxito. Spec: vinculada a `liquidacion/spec.md` (Liquidación óptima en el escenario oficial de Samaipata).

## 4. Pruebas de Integración Backend (Feature Tests)

- [x] 4.1 Alcance: solo pruebas. Tipo: funcional (Casos de uso 4, 5 y 6 integrados). Crear `tests/Feature/LiquidacionTest.php` ejecutando peticiones HTTP autenticadas a los endpoints `GET /viajes/{viaje}/saldos` y `GET /viajes/{viaje}/liquidacion` sembrados con los datos de Samaipata, validando los códigos HTTP 200, estructura JSON y rechazo con HTTP 403 a usuarios que no son dueños del viaje. Verificar: los tests de feature validan la integración completa del backend. Spec: vinculada a `saldos/spec.md` y `liquidacion/spec.md`.

## 5. Cierre de Apply (Verificación de Invariante)

- [x] 5.1 Alcance: no modificar código. Tipo: no funcional (verificación matemática). Documentar en el resumen de apply que el equipo debe verificar con el escenario sembrado de Samaipata que la suma de balances dé exactamente 0.00 y que las transferencias calculadas coincidan con `Diego -> Ana: Bs. 400` y `Carla -> Ana: Bs. 160`. Verificar: el resumen final incluye el recordatorio de verificación de la invariante. Spec: vinculada a `consistencia/spec.md` (Invariante en el escenario de Samaipata).
