# WP WhatsApp Evolution API (Integración para Vendedores Dokan)

Un plugin de WordPress que integra la gestión de WhatsApp directamente en el panel de control de cada vendedor de Dokan, utilizando n8n como intermediario para comunicarse con la Evolution API. Permite a cada vendedor gestionar su propia conexión de WhatsApp, incluyendo la generación y escaneo de códigos QR para vincular su cuenta y ver el estado de su conexión en tiempo real.

## Tabla de Contenidos

- [Descripción](#descripción)
- [Características](#características)
- [Requisitos](#requisitos)
- [Instalación](#instalación)
  - [Instalación del Plugin de WordPress](#instalación-del-plugin-de-wordpress)
  - [Configuración de n8n](#configuración-de-n8n)
    - [Configuración de Evolution API](#configuración-de-evolution-api)
    - [Ejemplos de Workflows de n8n](#ejemplos-de-workflows-de-n8n)
- [Uso](#uso)
  - [Para Administradores de WordPress](#para-administradores-de-wordpress)
  - [Para Vendedores de Dokan](#para-vendedores-de-dokan)
- [Desinstalación Segura](#desinstalación-segura)
- [Desarrollo](#desarrollo)
- [Licencia](#licencia)
- [Contribuciones](#contribuciones)

---

## Descripción

Este plugin resuelve el desafío de integrar WhatsApp para múltiples vendedores en una plataforma Dokan. A diferencia de una integración global, esta refactorización permite que **cada vendedor Dokan** tenga su propia cuenta de WhatsApp conectada a través de Evolution API, todo gestionado desde su propio panel de control. n8n actúa como la capa de abstracción, simplificando la comunicación entre WordPress y la API de WhatsApp, y ofreciendo flexibilidad para futuras automatizaciones.

---

## Características

- **Gestión Individual de WhatsApp**: Cada vendedor de Dokan puede conectar y gestionar su propia cuenta de WhatsApp.
- **Generación de QR para Conexión**: Los vendedores pueden generar y escanear códigos QR directamente desde su panel de control Dokan para vincular su número de WhatsApp.
- **Estado de Conexión en Tiempo Real**: Visualización del estado actual de la conexión de WhatsApp (conectado, desconectado, escaneando QR) desde el panel del vendedor.
- **Envío de Mensajes de Prueba**: Un formulario simple para que los vendedores envíen mensajes de prueba y confirmen la funcionalidad.
- **Intermediario n8n Flexible**: Utiliza n8n para orquestar las llamadas a Evolution API, lo que permite una personalización y expansión sencilla de los flujos de trabajo sin modificar el código del plugin.
- **Configuración Centralizada de n8n**: El administrador del sitio configura la URL base y el token de autenticación de n8n una sola vez.
- **Sistema de Seguridad Avanzado**: Encriptación de datos sensibles como tokens y claves API.
- **Sistema de Caché Inteligente**: Reducción de llamadas a la API almacenando temporalmente respuestas frecuentes.
- **Validación Avanzada de Números**: Detecta y formatea correctamente números de teléfono para diferentes países.
- **Sistema de Eventos**: Arquitectura basada en eventos que permite a otros plugins y temas conectar con la funcionalidad de WhatsApp.
- **Limpieza Segura de Datos**: Opción para eliminar todos los datos del plugin (configuraciones y datos de conexión de vendedores) al desinstalarlo.

---

## Requisitos

Para que este plugin funcione correctamente, necesitas lo siguiente:

- **WordPress**: Versión 5.8 o superior.
- **Dokan Multivendor Marketplace**: Plugin Dokan activo y configurado.
- **PHP**: Versión 7.4 o superior.
- **n8n**: Una instancia de n8n (self-hosted o en la nube) configurada y accesible desde tu servidor de WordPress.
- **Evolution API**: Una cuenta activa y una instancia de Evolution API configurada para tus sesiones de WhatsApp.

---

## Instalación

### Instalación del Plugin de WordPress

1. **Descarga el plugin**: Obtén la última versión del plugin desde el repositorio de GitHub.
2. **Sube el plugin**:
   - Ve a tu panel de administración de WordPress.
   - Navega a **Plugins > Añadir nuevo**.
   - Haz clic en el botón **Subir Plugin**.
   - Selecciona el archivo `.zip` que descargaste y haz clic en **Instalar ahora**.
3. **Activa el plugin**: Una vez instalado, haz clic en **Activar Plugin**.
4. **Configura las opciones de n8n**:
   - Ve a **Ajustes > WP WhatsApp Evolution API** en tu panel de administración de WordPress.
   - Introduce la **URL Base de tu Webhook de n8n** (ej. `https://your-n8n-instance.com/webhook/`). Asegúrate de que termine con una barra (`/`).
   - Si tu webhook de n8n requiere un token de autenticación (ej. para autenticación Bearer), introdúcelo en el campo **Token de Autenticación n8n**.
   - Configura la opción **Eliminar datos al desinstalar** según tu preferencia.

### Configuración de n8n

Este plugin se comunica con n8n a través de **Webhooks**. Necesitarás crear **tres (o más) workflows separados** en n8n que respondan a los eventos enviados desde WordPress:

1. `qr_generation`: Para iniciar el proceso de obtención de un código QR.
2. `session_status`: Para verificar el estado de una sesión de WhatsApp.
3. `message_send`: Para enviar mensajes desde una sesión específica.
4. **(Opcional pero recomendado) Evolution API Event Listener**: Un cuarto workflow que recibe eventos de Evolution API (ej. `CONNECTED`, `DISCONNECTED`, `QRCODE`) y actualiza el estado en WordPress.

#### Configuración de Evolution API

Asegúrate de que tu instancia de Evolution API esté funcionando. Necesitarás:

- La **URL de tu instancia de Evolution API**.
- Un **Token de Evolution API** (si lo requiere tu configuración).

#### Ejemplos de Workflows de n8n

A continuación, se describen los componentes clave para cada workflow.

**Workflow 1: `qr_generation`**

- **Nodo Webhook**:
  - **Listen on URL**: `[Tu_URL_Base_n8n]/qr_generation`
  - **HTTP Method**: `POST`
  - **JSON Body**: Esperará `{"eventType": "qr_generation", "sessionName": "dokan_vendor_XYZ", "vendorId": XYZ}`
- **Nodo HTTP Request (Evolution API - Get QR)**:
  - **Method**: `GET`
  - **URL**: `[Tu_Evolution_API_URL]/auth/qr?session={{ $json.sessionName }}`
  - **Headers**: `Authorization: Bearer [Tu_Evolution_API_Token]`
  - **Response Format**: `JSON`
- **Nodo If (Check QR Response)**:
  - **Condition**: `{{ $json.data.qrcode }}` is not empty.
- **Nodo Respond to Webhook (Success)**:
  - **Response Mode**: `JSON`
  - **JSON Body**: `{ "success": true, "message": "QR code generated successfully.", "data": { "status": "QRCODE", "qrCodeUrl": "{{ $json.data.qrcode }}" } }`
  - **HTTP Status Code**: `200`
- **Nodo WordPress (Update User Meta - Opcional)**: Si deseas que n8n actualice el estado directamente en WordPress, puedes añadir un nodo WordPress aquí para actualizar `dokan_whatsapp_qr_code_url` y `dokan_whatsapp_session_status` para el `vendorId`.
- **Nodo Respond to Webhook (Error)**:
  - **Response Mode**: `JSON`
  - **JSON Body**: `{ "success": false, "message": "Failed to retrieve QR code from Evolution API.", "data": {{ $json.data }} }`
  - **HTTP Status Code**: `500`

**Workflow 2: `session_status`**

- **Nodo Webhook**:
  - **Listen on URL**: `[Tu_URL_Base_n8n]/session_status`
  - **HTTP Method**: `GET`
  - **JSON Body**: Esperará `{"eventType": "session_status", "sessionName": "dokan_vendor_XYZ", "vendorId": XYZ}`
- **Nodo HTTP Request (Evolution API - Get Status)**:
  - **Method**: `GET`
  - **URL**: `[Tu_Evolution_API_URL]/session/status?session={{ $json.sessionName }}`
  - **Headers**: `Authorization: Bearer [Tu_Evolution_API_Token]`
- **Nodo Respond to Webhook**:
  - **Response Mode**: `JSON`
  - **JSON Body**: `{ "success": true, "message": "Session status retrieved.", "data": { "status": "{{ $json.data.status }}" } }` (Asegúrate de que Evolution API devuelve un campo `status`).
  - **HTTP Status Code**: `200`
- **Nodo WordPress (Update User Meta - Opcional)**: Actualiza `dokan_whatsapp_session_status` y `dokan_whatsapp_qr_code_url` (borrar si el estado no es QRCODE).

**Workflow 3: `message_send`**

- **Nodo Webhook**:
  - **Listen on URL**: `[Tu_URL_Base_n8n]/message_send`
  - **HTTP Method**: `POST`
  - **JSON Body**: Esperará `{"eventType": "message_send", "sessionName": "dokan_vendor_XYZ", "to": "...", "message": "...", "vendorId": XYZ}`
- **Nodo HTTP Request (Evolution API - Send Message)**:
  - **Method**: `POST`
  - **URL**: `[Tu_Evolution_API_URL]/message/send?session={{ $json.sessionName }}`
  - **Headers**: `Content-Type: application/json`, `Authorization: Bearer [Tu_Evolution_API_Token]`
  - **Body**: `{ "number": "{{ $json.to }}", "text": "{{ $json.message }}" }`
- **Nodo Respond to Webhook**:
  - **Response Mode**: `JSON`
  - **JSON Body**: `{ "success": true, "message": "Message sent.", "data": {{ $json.data }} }`
  - **HTTP Status Code**: `200`

**Workflow 4 (Opcional): Evolution API Event Listener**

- **Nodo Webhook**:
  - **Listen on URL**: `[Tu_URL_Base_n8n]/evolution_api_events` (Configura Evolution API para enviar eventos a esta URL).
  - **HTTP Method**: `POST`
- **Nodo Set**: Para extraer el `vendorId` de `session` (ej. `dokan_vendor_123` -> `123`) y el tipo de evento/estado.
- **Nodo WordPress (Update User Meta)**: Actualiza `dokan_whatsapp_session_status` y `dokan_whatsapp_qr_code_url` (si aplica) para el `vendorId` correspondiente.

---

## Uso

### Para Administradores de WordPress

Una vez instalado, el administrador del sitio solo necesita configurar la URL base y el token de n8n en **Ajustes > WP WhatsApp Evolution API**. Todas las configuraciones específicas por vendedor se gestionan automáticamente a través de Dokan.

### Para Vendedores de Dokan

1. **Accede al Panel de Vendedor**: Inicia sesión en tu cuenta de vendedor de Dokan.
2. **Navega a WhatsApp**: En el menú lateral del dashboard de vendedor, verás una nueva opción de menú llamada "**WhatsApp**".
3. **Gestiona tu Conexión**:
   - **Ver Estado**: El estado actual de tu conexión de WhatsApp se mostrará automáticamente.
   - **Generar QR**: Haz clic en el botón "**Generate New QR Code**" para solicitar un nuevo código QR. Escanéalo con tu aplicación de WhatsApp en tu teléfono para vincular tu cuenta.
   - **Verificar Estado**: Utiliza el botón "**Check Status**" para actualizar manualmente el estado de tu conexión.
   - **Enviar Mensaje de Prueba**: Usa la sección "Send a Test Message" para enviar un mensaje a un número específico y confirmar que tu conexión funciona.

---

## Desinstalación Segura

El plugin incluye un archivo `uninstall.php` que te permite controlar la eliminación de los datos al desinstalarlo.

1. **Configuración de Desinstalación**: Antes de desinstalar, ve a **Ajustes > WP WhatsApp Evolution API**.
2. **Opción "Eliminar datos al desinstalar"**:
   - Si **marcas** esta opción, al desinstalar el plugin se eliminarán todas las opciones globales (URL de n8n, token) y todos los metadatos de usuario (estado de sesión y QR) asociados a las conexiones de WhatsApp de los vendedores. **¡Esta acción es irreversible!**
   - Si **no marcas** esta opción (valor por defecto), al desinstalar el plugin, las configuraciones y los metadatos de usuario permanecerán en tu base de datos. Esto es útil si planeas reinstalar el plugin más tarde o si quieres conservar un registro de los datos.
3. **Desinstala el plugin**: Desde la sección **Plugins instalados**, desactiva el plugin y luego haz clic en "Borrar".

---

## Desarrollo

Este plugin está diseñado para ser extensible. Si deseas contribuir o personalizarlo:

- Clona el repositorio de GitHub.
- Utiliza los hooks de WordPress y Dokan para añadir o modificar funcionalidades.
- Amplía los flujos de n8n para incorporar automatizaciones más complejas (ej. notificaciones de pedidos, respuestas automáticas, etc.).

---

## Licencia

Este plugin está bajo la licencia GPL-2.0 o posterior. Consulta el archivo `LICENSE` para más detalles.

---

## Contribuciones

Las contribuciones son bienvenidas. Si encuentras un error o tienes una sugerencia de mejora, por favor, abre un "Issue" o envía un "Pull Request" en el repositorio de GitHub.

---
