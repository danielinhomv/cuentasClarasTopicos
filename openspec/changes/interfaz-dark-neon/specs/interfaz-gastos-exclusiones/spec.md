## Purpose

Proporciona la interfaz interactiva para el registro, edición, listado y exclusión de participantes en gastos económicos de un viaje con validaciones visuales inmediatas.

## ADDED Requirements

### Requirement: Formulario visual interactivo de registro de gastos
El sistema SHALL proveer una interfaz limpia en modal o panel para ingresar concepto, monto, fecha, selección del participante pagador y casillas de verificación para excluir a participantes del gasto.

#### Scenario: Apertura del modal y registro exitoso de gasto
- **DADO** un viaje con participantes "Ana", "Beto", "Carla" y "Diego"
- **WHEN** el usuario pulsa "Registrar Gasto", llena concepto "Cena", monto `400.00`, fecha válida, selecciona a "Beto" como pagador y guarda
- **THEN** el modal se cierra, el nuevo gasto aparece en la lista con su monto y pagador, y los saldos del viaje se actualizan

#### Scenario: Selección de participantes excluidos en el formulario
- **DADO** un viaje con 4 participantes y un gasto de "Gasolina" por `240.00`
- **WHEN** el usuario marca la casilla de exclusión de "Diego" y guarda el gasto
- **THEN** el sistema registra el gasto mostrando visualmente una etiqueta neón ámbar *"Excluidos: Diego"* en el item de la lista

#### Scenario: Prevención visual de exclusión del total de integrantes
- **DADO** un viaje con 4 integrantes
- **WHEN** el usuario intenta marcar a los 4 integrantes como excluidos simultáneamente
- **THEN** el formulario bloquea el botón de envío o muestra una alerta neón indicando que al menos un integrante debe participar en el reparto

### Requirement: Listado de gastos con acciones de edición y eliminación
El sistema SHALL mostrar la lista de gastos registrados en el viaje con formato monetario claro (`Bs. X.XX`), fecha, nombre del pagador, y opciones para editar o eliminar con confirmación.

#### Scenario: Visualización del listado ordenado de gastos
- **DADO** un viaje con gastos existentes
- **WHEN** el usuario visualiza la pestaña "Gastos"
- **THEN** cada gasto se muestra en una tarjeta oscura con el monto en relieve neón, pagador claramente identificado y opciones para editar o eliminar
