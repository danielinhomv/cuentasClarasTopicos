## Purpose

Permite que el creador del viaje configure y actualice de forma manual las tasas de cambio de USD y USDT hacia la moneda base del viaje (Bs).

## ADDED Requirements

### Requirement: Configuración manual de tipos de cambio por el creador
El sistema SHALL permitir únicamente al usuario creador del viaje actualizar las tasas de cambio de `tipo_cambio_usd` y `tipo_cambio_usdt`.

#### Scenario: Creador actualiza las tasas de cambio con éxito
- **DADO** el usuario creador del viaje
- **WHEN** envía una solicitud `PUT` a `/viajes/{viaje}/tipo-cambio` con `tipo_cambio_usd = 6.96` y `tipo_cambio_usdt = 10.80`
- **THEN** el sistema guarda las nuevas tasas y redirige al detalle del viaje con mensaje de éxito

#### Scenario: Participante no creador intenta modificar el tipo de cambio
- **DADO** un participante del viaje que no es su creador
- **WHEN** intenta enviar una solicitud `PUT` a `/viajes/{viaje}/tipo-cambio`
- **THEN** el sistema responde con error 403 Prohibido

### Requirement: Validación de tasas de cambio
El sistema SHALL exigir que los tipos de cambio sean valores numéricos estrictamente mayores a cero con hasta cuatro decimales de precisión.

#### Scenario: Envío de tasa inválida o negativa
- **DADO** el creador del viaje
- **WHEN** envía un `tipo_cambio_usd <= 0` o un valor no numérico
- **THEN** el sistema rechaza la solicitud con errores de validación y mantiene las tasas anteriores
