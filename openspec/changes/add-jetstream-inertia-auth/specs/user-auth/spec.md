## Purpose

Permite que una persona cree una cuenta, inicie y cierre sesión, recupere el acceso y actualice su perfil, de modo que la identidad quede persistida y el resto de Cuentas Claras pueda asociarse a un usuario autenticado.

Esta capacidad no calcula montos ni balances; no aplica redondeo de centavos.

## ADDED Requirements

### Requirement: Visitante puede registrarse

El sistema SHALL permitir a un visitante no autenticado crear una cuenta con nombre, correo electrónico y contraseña. El usuario SHALL persistirse en PostgreSQL (no solo en memoria del navegador). Tras un registro válido, el sistema SHALL dejar al usuario autenticado y mostrar el área autenticada.

#### Scenario: Registro exitoso

- **DADO** un visitante sin cuenta y un correo que no está registrado
- **WHEN** envía el formulario de registro con nombre, correo y contraseña válidos
- **THEN** el sistema crea el usuario en PostgreSQL, inicia sesión y muestra el área autenticada

#### Scenario: Correo duplicado

- **DADO** un usuario ya persistido con el correo `ana@example.com`
- **WHEN** un visitante intenta registrarse de nuevo con `ana@example.com`
- **THEN** el sistema no crea un segundo usuario y muestra un error de validación sobre el correo

#### Scenario: Campos vacíos o inválidos

- **DADO** un visitante en la pantalla de registro
- **WHEN** envía el formulario sin nombre, con correo mal formado o con contraseña que no cumple las reglas de complejidad del sistema
- **THEN** el sistema no crea usuario y muestra errores de validación en el formulario

#### Scenario: Persistencia al refrescar después de registrarse

- **DADO** un usuario que acaba de registrarse con éxito
- **WHEN** recarga la página en el área autenticada
- **THEN** sigue autenticado y los datos de su cuenta siguen existiendo en PostgreSQL

### Requirement: Usuario puede iniciar y cerrar sesión

El sistema SHALL autenticar a un usuario existente con correo y contraseña correctos. El sistema SHALL rechazar credenciales inválidas sin revelar si el correo existe. El sistema SHALL permitir cerrar sesión y, tras ello, bloquear el área autenticada hasta un nuevo inicio de sesión.

#### Scenario: Inicio de sesión exitoso

- **DADO** un usuario persistido con correo y contraseña conocidos
- **WHEN** envía esas credenciales en el formulario de acceso
- **THEN** el sistema lo autentica y muestra el área autenticada

#### Scenario: Contraseña incorrecta

- **DADO** un usuario persistido
- **WHEN** envía el correo correcto y una contraseña incorrecta
- **THEN** el sistema no inicia sesión y muestra un error genérico de credenciales

#### Scenario: Cierre de sesión

- **DADO** un usuario autenticado en el área autenticada
- **WHEN** elige cerrar sesión
- **THEN** el sistema termina la sesión y, si intenta abrir de nuevo el área autenticada, es redirigido al acceso

#### Scenario: Área autenticada protegida

- **DADO** un visitante no autenticado
- **WHEN** solicita una URL del área autenticada (por ejemplo el dashboard)
- **THEN** el sistema no muestra el contenido autenticado y lo envía al flujo de acceso

#### Scenario: Visitante autenticado no ve registro como destino principal

- **DADO** un usuario ya autenticado
- **WHEN** solicita la pantalla de registro o de acceso
- **THEN** el sistema lo redirige al área autenticada en lugar de pedirle de nuevo las credenciales

### Requirement: Usuario puede recuperar el acceso

El sistema SHALL ofrecer un flujo de restablecimiento de contraseña basado en el correo de la cuenta. Tras completar un restablecimiento válido, el usuario SHALL poder iniciar sesión con la nueva contraseña. La contraseña anterior SHALL dejar de ser válida.

#### Scenario: Solicitud de restablecimiento para correo existente

- **DADO** un usuario persistido con un correo válido
- **WHEN** solicita restablecer la contraseña indicando ese correo
- **THEN** el sistema acepta la solicitud y deja preparado el mecanismo de restablecimiento (enlace o token) asociado a esa cuenta

#### Scenario: Correo desconocido en restablecimiento

- **DADO** un visitante en el flujo de restablecimiento
- **WHEN** indica un correo que no corresponde a ningún usuario
- **THEN** el sistema no crea usuarios y no revela de forma explícita si el correo existe o no (respuesta no enumeradora)

#### Scenario: Nueva contraseña tras restablecimiento

- **DADO** un usuario que completó un restablecimiento válido
- **WHEN** inicia sesión con la nueva contraseña
- **THEN** el acceso es exitoso y la contraseña anterior ya no autentica

### Requirement: Usuario autenticado puede actualizar su perfil de cuenta

El sistema SHALL permitir a un usuario autenticado actualizar su nombre y su correo, y cambiar su contraseña, persistiendo los cambios en PostgreSQL. El sistema SHALL exigir la contraseña actual para cambiar la contraseña. El correo actualizado SHALL seguir siendo único.

#### Scenario: Actualizar nombre

- **DADO** un usuario autenticado
- **WHEN** cambia su nombre a un valor no vacío válido y guarda
- **THEN** el sistema persiste el nuevo nombre y lo muestra en el perfil tras refrescar la página

#### Scenario: Correo de perfil duplicado

- **DADO** dos usuarios persistidos con correos distintos
- **WHEN** el primero intenta cambiar su correo al del segundo
- **THEN** el sistema rechaza el cambio y mantiene el correo original

#### Scenario: Cambio de contraseña con contraseña actual correcta

- **DADO** un usuario autenticado que conoce su contraseña actual
- **WHEN** envía contraseña actual correcta y una nueva contraseña válida
- **THEN** el sistema persiste la nueva contraseña y la anterior deja de autenticar

#### Scenario: Cambio de contraseña con contraseña actual incorrecta

- **DADO** un usuario autenticado
- **WHEN** intenta cambiar la contraseña con la contraseña actual incorrecta
- **THEN** el sistema no cambia la contraseña y muestra un error de validación

### Requirement: Identidad persistida sin estado solo en el navegador

El sistema MUST persistir usuarios y sesiones de autenticación en el servidor (PostgreSQL y el mecanismo de sesión de la aplicación). MUST NOT usar `localStorage` ni estado transitorio del frontend como única fuente de verdad de la identidad.

Esta capacidad no maneja participantes ni gastos: los casos borde de 0 participantes, gasto en Bs. 0 o negativo, montos no divisibles, eliminar participante con gastos o nombres repetidos no aplican aquí.

#### Scenario: Recarga conserva la sesión

- **DADO** un usuario autenticado con datos ya guardados en PostgreSQL
- **WHEN** recarga el navegador
- **THEN** sigue autenticado y su nombre y correo coinciden con lo almacenado en la base de datos

#### Scenario: Sin usuarios registrados

- **DADO** una base sin filas de usuario
- **WHEN** un visitante intenta iniciar sesión con cualquier correo
- **THEN** el acceso falla y no se muestra el área autenticada
