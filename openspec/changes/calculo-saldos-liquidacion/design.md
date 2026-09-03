## Context

Ver motivación en `proposal.md` y especificaciones en `specs/saldos/spec.md`, `specs/liquidacion/spec.md` y `specs/consistencia/spec.md`.

Este diseño define la capa de lógica de negocio pura (Services) responsable de calcular saldos netos y generar el plan óptimo de transferencias para liquidar las deudas de un viaje. Aplica aritmética entera en centavos para eliminar cualquier imprecisión de punto flotante y garantiza que la suma de balances siempre sea exactamente cero.

## Goals / Non-Goals

**Goals:**
- Implementar `CalculoBalanceService` para computar consumos, totales pagados y saldos netos por participante.
- Implementar `AlgoritmoLiquidacionService` con una estrategia voraz (*greedy matching*) que garantiza resolver todas las deudas con un máximo de $N - 1$ transferencias.
- Garantizar la precisión monetaria absoluta convirtiendo todos los montos a **centavos enteros** durante los cálculos intermedios.
- Adaptar las cuotas al efectivo boliviano (múltiplos de Bs 0,50): el anfitrión/creador siempre redondea hacia abajo y nunca recibe la penalización; el sistema elige automáticamente quién asume el ajuste.
- Exponer los endpoints estructurados en `LiquidacionController` protegidos por autenticación y policies.
- Validar matemáticamente los cálculos con pruebas unitarias y el caso oficial de Samaipata.

**Non-Goals:**
- No persistir saldos brutos por gasto (siguen siendo derivados de `gastos`).
- No integrar pasarelas de pago ni transferencias bancarias reales.
- No redistribuir pagos históricos entre pares distintos si cambian los gastos.

## Decisions

### 1. Estrategia de Precisión Monetaria (Aritmética en Centavos Enteros)

Para evitar los errores clásicos de imprecisión en números de punto flotante binario en PHP (ej. `0.1 + 0.2 !== 0.3`), todos los cálculos dentro de los servicios operan convirtiendo los montos monetarios a **centavos enteros** (`int`):

```php
$montoCentavos = (int) round($gasto->monto * 100);
```

Al finalizar el cálculo, los centavos se transforman de vuelta a decimales con 2 dígitos:
```php
$montoFinal = round($centavos / 100, 2);
```

### 2. Algoritmo de División de Gastos y Redondeo a Efectivo (Bs 0,50)

El **anfitrión** es el participante cuyo `user_id` coincide con `viaje.user_id` (creador del viaje), no necesariamente el pagador del gasto. Los gastos se procesan en orden `(fecha, id)` para que la deuda acumulada use solo gastos anteriores.

Para cada gasto $G$ con monto persistido $M$ (centavos consolidados) y $K$ beneficiarios:

1. **Unidad de efectivo:** $U = 50$ centavos. No deben quedar cuotas finales como 27,20 / 27,60 / 27,90.
2. **Un solo beneficiario:** conserva $M$ exacto (aunque no sea múltiplo de 50).
3. **Piso de todos:** cada beneficiario parte de $\mathrm{floor}_{50}(\mathrm{intdiv}(M, K))$. El anfitrión, si está incluido, se queda en ese piso y **nunca** recibe unidades extra.
4. **Hueco:** $\mathrm{gap} = M - \sum \mathrm{pisos}$. Se crean $N = \lceil \mathrm{gap}/U \rceil$ unidades extra de 50 centavos (si $\mathrm{gap}=0$, ninguna). Así un resto de 40 céntimos se convierte en Bs 0,50 (ej. 55,40 → 27,50 + 28,00).
5. **Quién asume el ajuste** (solo no-anfitriones, automático, sin campo manual):
   1. Mayor **deuda acumulada** antes de este gasto ($\max(0, -\mathrm{balance})$).
   2. Si empatan, la **deuda más antigua** (primera vez que el saldo corrió negativo y sigue debiendo).
   3. Si siguen empatados, **sorteo determinístico** `crc32(gasto.id + "|" + participante_id)` — no usa `random()`, así consultar de nuevo no cambia el resultado. El sorteo no corre si (1) o (2) ya eligen a uno.
6. Las $N$ unidades se reparte de a una, en ese orden (vuelta a empezar si hay más unidades que candidatos).
7. Si $\sum \mathrm{cuotas} = S > M$ (porque se redondeó el hueco hacia arriba), se acredita $S-M$ al **pagado del anfitrión** (o del pagador si no hay anfitrión-participante) para conservar $\sum \mathrm{balances} = 0$. El `gastos.monto` no se modifica.
8. Liquidaciones completas y parciales siguen reconciliando desde estos balances.

### 3. Algoritmo Voraz de Liquidación Mínima (Greedy Matching)

El servicio `AlgoritmoLiquidacionService` procesa la lista de balances de la siguiente manera:

1. Filtra los participantes en dos conjuntos:
   - **Deudores:** participantes con $\text{balance} < 0$, ordenados de mayor deuda a menor deuda.
   - **Acreedores:** participantes con $\text{balance} > 0$, ordenados de mayor crédito a menor crédito.
2. Mientras ambos conjuntos tengan elementos:
   - Toma al mayor deudor $D$ (deuda $|B_D|$) y al mayor acreedor $A$ (crédito $B_A$).
   - Determina el monto de transferencia: $T = \min(|B_D|, B_A)$.
   - Registra la transferencia:
     ```php
     [
         'deudor_id' => $D->id,
         'deudor_nombre' => $D->nombre,
         'acreedor_id' => $A->id,
         'acreedor_nombre' => $A->nombre,
         'monto' => round($T / 100, 2)
     ]
     ```
   - Reduce la deuda de $D$ en $T$ y el crédito de $A$ en $T$.
   - Si la deuda de $D$ llega a 0, se retira de la lista de deudores.
   - Si el crédito de $A$ llega a 0, se retira de la lista de acreedores.

*Complejidad:* $O(N \log N)$ debido al ordenamiento inicial, requiriendo a lo sumo $N - 1$ transferencias.

### 4. Estructura de Clases y Capas

```
[HTTP Request] ──> [LiquidacionController]
                         │
                         ├──> [CalculoBalanceService] ──> [Participante / Gasto Eloquent]
                         │           │
                         │           └── (Calcula balances con centavos enteros)
                         │
                         └──> [AlgoritmoLiquidacionService]
                                     │
                                     └── (Genera plan óptimo de transferencias)
```

- **`App\Services\AjusteEfectivoService`:**
  - `repartir(monto, beneficiarios, anfitrionId, contextoDeuda, sorteoSeed)` → cuotas en centavos, todas múltiplo de 50 salvo el caso de un único beneficiario.
- **`App\Services\CalculoBalanceService`:**
  - `public function calcularBalances(Viaje $viaje): array`
  - Retorna un array estructurado con `participante_id`, `nombre`, `total_pagado`, `total_consumido`, `balance`.
- **`App\Services\AlgoritmoLiquidacionService`:**
  - `public function calcularLiquidacion(array $balances): array`
  - Retorna la colección de transferencias `deudor`, `acreedor`, `monto`.
- **`App\Services\RegistroLiquidacionService`:**
  - Reconcilia el plan calculado con deudas persistidas por par `deudor-acreedor`.
  - Aplica abonos parciales o completos, rechaza sobrepagos y marca `liquidada` cuando el pendiente es 0.
  - Ajusta los saldos expuestos descontando los pagos registrados.

### 5. Persistencia de deudas y pagos parciales

El plan óptimo se sigue calculando en memoria a partir de balances **brutos** (solo gastos). Cada transferencia sugerida se materializa como una deuda persistida:

```
[viajes] (1) <──── (N) [liquidaciones] (1) <──── (N) [liquidacion_pagos]
                          │
                          ├── deudor_id   → participantes
                          └── acreedor_id → participantes
```

- **Tabla `liquidaciones`:**
  - `viaje_id`, `deudor_id`, `acreedor_id`
  - `monto_original`, `monto_pagado`, `monto_pendiente` (decimal 12,2)
  - `estado` (`pendiente` | `liquidada`)
  - `UNIQUE(viaje_id, deudor_id, acreedor_id)`
- **Tabla `liquidacion_pagos`:**
  - `liquidacion_id`, `monto`, `fecha_pago`, timestamps

Reglas:
- `monto_original` = monto sugerido por el plan bruto **vigente** para ese par (se actualiza si cambian los gastos).
- `monto_pagado` = suma de abonos del par (no se borra ni se mueve a otro par).
- `monto_pendiente = max(0, monto_original - monto_pagado)`.
- Un abono MUST ser `> 0` y MUST NOT superar el pendiente **actual**.
- Si `monto_pendiente = 0`, `estado = liquidada`.
- Liquidar el total de una vez sigue siendo válido (un único pago igual al original).
- Tras crear, editar o eliminar un gasto, se vuelve a calcular el plan y se sincronizan las filas:
  - Par vigente: se actualiza `monto_original` y el pendiente.
  - Par que ya no está en el plan y `monto_pagado = 0`: se elimina (no deja deudas huérfanas).
  - Par que ya no está en el plan y tiene pagos: se cierra (`pendiente = 0`, `original` efectivo 0 para no distorsionar saldos) conservando `liquidacion_pagos`.
- Los saldos expuestos descuentan abonos **como máximo** hasta el `monto_original` vigente.

### 6. Rutas Web en `routes/web.php`

```php
Route::middleware(['auth'])->group(function () {
    Route::get('/viajes/{viaje}/saldos', [LiquidacionController::class, 'saldos'])->name('viajes.saldos');
    Route::get('/viajes/{viaje}/liquidacion', [LiquidacionController::class, 'liquidacion'])->name('viajes.liquidacion');
    Route::post('/liquidaciones/{liquidacion}/pagos', [LiquidacionController::class, 'registrarPago'])->name('liquidaciones.pagos.store');
});
```

## Risks / Trade-offs

- **[Incompatibilidad de punto flotante]** $\rightarrow$ Mitigación: Toda la matemática interna se ejecuta en centavos enteros (`int`).
- **[Viaje sin gastos o con saldos balanceados]** $\rightarrow$ Mitigación: El algoritmo maneja explícitamente listas vacías retornando colecciones vacías con código 200 sin excepciones.
- **[Acceso no autorizado]** $\rightarrow$ Mitigación: `LiquidacionController` invoca `ViajePolicy` para asegurar que solo el dueño del viaje pueda consultar sus saldos y liquidaciones.

## Migration Plan

1. Crear `App\Services\CalculoBalanceService.php` y `App\Services\AlgoritmoLiquidacionService.php`.
2. Crear `App\Http\Controllers\LiquidacionController.php` y registrar rutas en `routes/web.php`.
3. Crear pruebas unitarias (`CalculoBalanceServiceTest`, `AlgoritmoLiquidacionServiceTest`).
4. Crear pruebas de integración Feature (`LiquidacionTest`) verificando el caso de Samaipata.
5. Crear migraciones/modelos de `liquidaciones` y `liquidacion_pagos`, servicio de registro de abonos y tests de pagos parciales.
