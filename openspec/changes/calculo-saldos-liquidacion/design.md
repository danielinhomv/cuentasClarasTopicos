## Context

Ver motivación en `proposal.md` y especificaciones en `specs/saldos/spec.md`, `specs/liquidacion/spec.md` y `specs/consistencia/spec.md`.

Este diseño define la capa de lógica de negocio pura (Services) responsable de calcular saldos netos y generar el plan óptimo de transferencias para liquidar las deudas de un viaje. Aplica aritmética entera en centavos para eliminar cualquier imprecisión de punto flotante y garantiza que la suma de balances siempre sea exactamente cero.

## Goals / Non-Goals

**Goals:**
- Implementar `CalculoBalanceService` para computar consumos, totales pagados y saldos netos por participante.
- Implementar `AlgoritmoLiquidacionService` con una estrategia voraz (*greedy matching*) que garantiza resolver todas las deudas con un máximo de $N - 1$ transferencias.
- Garantizar la precisión monetaria absoluta convirtiendo todos los montos a **centavos enteros** durante los cálculos intermedios.
- Adaptar las cuotas al efectivo boliviano (múltiplos de Bs 0,50) con el menor ajuste automático, sin que el anfitrión cargue un recargo ni se quede con el sobrante cuando hay deudores.
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

### 2. Algoritmo de División de Gastos y Absorción de Centavos

Para cada gasto $G$ con monto $M$ (en centavos consolidados) y $K$ participantes beneficiarios:

1. **Unidad de efectivo:** $U = 50$ centavos (Bs 0,50). En Bolivia no se usan en la práctica monedas de Bs 0,20 ni Bs 0,30.
2. **Cuota teórica:** $C_{base} = \text{intdiv}(M, K)$ (solo para ranking y para mostrar original vs ajuste).
3. **Reparto en unidades de 50 centavos:** $Q = \text{intdiv}(M, U)$, $R_{<50} = M \bmod U$. Cada beneficiario recibe $\text{intdiv}(Q, K)$ unidades. Las $Q \bmod K$ unidades extra se asignan de a una, de forma determinística, a quienes **deben** (no pagadores), priorizando al que más debe; si empatan, se reparte en orden de `participante_id` (colaborativo, no aleatorio). El pagador/anfitrión no recibe esas unidades extra si hay al menos un deudor.
4. **Residuo menor a 50 centavos:** si el pagador está incluido, se le asigna $R_{<50}$ (ya pagó el monto real en caja). Si no está incluido, el residuo se asigna al deudor de menor `id` para no inventar recargos. La suma de cuotas es exactamente $M$.
5. **Balance individual:** $\text{balance} = \text{pagado\_centavos} - \text{consumo\_centavos}$. El `monto` persistido del gasto no cambia.
6. **Garantía:** $\sum \text{consumos} = M$ y $\sum \text{balances} = 0$. Las liquidaciones parciales siguen usando deudas persistidas; el plan se recalcula desde balances brutos sin borrar abonos.

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
  - `public function repartir(int $montoCentavos, array $beneficiarioIds, int $pagadorId): array`
  - Devuelve `participante_id => consumo_centavos` con ajuste a Bs 0,50.
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
