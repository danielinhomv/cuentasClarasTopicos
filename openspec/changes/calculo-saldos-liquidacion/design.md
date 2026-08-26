## Context

Ver motivación en `proposal.md` y especificaciones en `specs/saldos/spec.md`, `specs/liquidacion/spec.md` y `specs/consistencia/spec.md`.

Este diseño define la capa de lógica de negocio pura (Services) responsable de calcular saldos netos y generar el plan óptimo de transferencias para liquidar las deudas de un viaje. Aplica aritmética entera en centavos para eliminar cualquier imprecisión de punto flotante y garantiza que la suma de balances siempre sea exactamente cero.

## Goals / Non-Goals

**Goals:**
- Implementar `CalculoBalanceService` para computar consumos, totales pagados y saldos netos por participante.
- Implementar `AlgoritmoLiquidacionService` con una estrategia voraz (*greedy matching*) que garantiza resolver todas las deudas con un máximo de $N - 1$ transferencias.
- Garantizar la precisión monetaria absoluta convirtiendo todos los montos a **centavos enteros** durante los cálculos intermedios.
- Aplicar la regla de negocio de absorción de centavos residuales por el pagador del gasto.
- Exponer los endpoints estructurados en `LiquidacionController` protegidos por autenticación y policies.
- Validar matemáticamente los cálculos con pruebas unitarias y el caso oficial de Samaipata.

**Non-Goals:**
- No persistir tablas de saldos en la base de datos (los saldos son derivados y calculados en memoria bajo demanda a partir de `gastos`).
- No generar interfaces de usuario ni vistas (Inertia/Vue/Blade).

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

Para cada gasto $G$ con monto $M$ (en centavos) y $K$ participantes beneficiarios (total de participantes del viaje menos los excluidos):

1. **Cuota base entera:** $C_{base} = \text{intdiv}(M, K)$.
2. **Centavos sobrantes (residuo):** $R = M \pmod K$.
3. **Distribución del consumo:**
   - Cada participante beneficiario acumula $C_{base}$ a su `consumo_centavos`.
   - Si el pagador del gasto está dentro de los beneficiarios, absorbe los $R$ centavos acumulando $C_{base} + R$.
   - Si el pagador está excluido del beneficio del gasto, los $R$ centavos se asignan al primer beneficiario.
4. **Balance individual:** $\text{balance} = \text{pagado\_centavos} - \text{consumo\_centavos}$.
5. **Garantía:** La suma de consumos asignados a cada participante es estrictamente igual a $M$, lo que matemáticamente asegura que:
   $$\sum_{i=1}^{N} \text{balance}_i = \sum \text{pagados} - \sum \text{consumos} = \text{Total Gastos} - \text{Total Gastos} = 0$$

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

- **`App\Services\CalculoBalanceService`:**
  - `public function calcularBalances(Viaje $viaje): array`
  - Retorna un array estructurado con `participante_id`, `nombre`, `total_pagado`, `total_consumido`, `balance`.
- **`App\Services\AlgoritmoLiquidacionService`:**
  - `public function calcularLiquidacion(array $balances): array`
  - Retorna la colección de transferencias `deudor`, `acreedor`, `monto`.

### 5. Rutas Web en `routes/web.php`

```php
Route::middleware(['auth'])->group(function () {
    Route::get('/viajes/{viaje}/saldos', [LiquidacionController::class, 'saldos'])->name('viajes.saldos');
    Route::get('/viajes/{viaje}/liquidacion', [LiquidacionController::class, 'liquidacion'])->name('viajes.liquidacion');
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
