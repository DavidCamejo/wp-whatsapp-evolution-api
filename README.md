# WP WhatsApp Evolution API

Un plugin de WordPress que integra la gestión de WhatsApp para vendedores de Dokan, utilizando **n8n como intermediario** para comunicarse con **Evolution API**. Simplifica la lógica del plugin al delegar la complejidad de la API a flujos de trabajo de n8n, ofreciendo una solución robusta y escalable para la conexión de WhatsApp.

---

## 🚀 Características Principales (Fase 1)

- **Integración n8n:** Conecta tu instancia de WordPress de forma segura con tus flujos de trabajo de n8n mediante una URL de webhook y un secreto compartido.
- **Gestión de WhatsApp por Vendedor:** Permite a cada vendedor de Dokan gestionar su propia conexión de WhatsApp desde su panel de control.
- **Generación de QR y Estado:** Los vendedores pueden generar y escanear códigos QR para vincular su cuenta de WhatsApp y ver el estado de su conexión en tiempo real.
- **Lógica de Plugin Simplificada:** El plugin se enfoca en la interfaz de usuario y la comunicación segura con n8n, mientras que n8n maneja las interacciones directas con Evolution API.
- **Actualizaciones de Estado en Tiempo Real:** Recibe actualizaciones de estado de la conexión de WhatsApp desde n8n a través de un endpoint REST API dedicado en WordPress.

---

## 🛠️ Requisitos

Para que este plugin funcione correctamente, necesitas lo siguiente:

- **WordPress 4.9 o superior.**
- **Plugin Dokan Multivendor Marketplace** (versión 3.0 o superior) activo.
- Una **instancia de n8n** funcionando y accesible públicamente (autohospedada o en la nube).
- Acceso a una **instancia de Evolution API** para la gestión de WhatsApp.

---

## 📦 Instalación

1. **Descarga** el archivo ZIP de la última versión del plugin o clona este repositorio en tu directorio de plugins de WordPress (`wp-content/plugins/`).
2. **Descomprime** el archivo (si lo descargaste) en una carpeta llamada `wp-whatsapp-evolution-api`.
3. **Activa** el plugin desde el panel de administración de WordPress.

---

## ⚙️ Configuración

### 1. Configuración del Plugin en WordPress

Después de activar el plugin:

- Ve a **Ajustes > WhatsApp n8n** en tu panel de administración de WordPress.
- **n8n Base Webhook URL:** Introduce la URL base de tus webhooks de n8n (ej., `https://your-n8n.com/webhook/`). Asegúrate de que termine con una barra `/`.
- **n8n Shared Secret:** Define un secreto compartido fuerte y aleatorio (ej., una cadena de 32 caracteres). Este mismo secreto lo configurarás en tus nodos de webhook de n8n para autenticar las comunicaciones.

### 2. Configuración de Workflows en n8n

Debes crear los siguientes flujos de trabajo en tu instancia de n8n. Asegúrate de configurar los nodos de `Webhook` con la opción "Header Auth" y la clave `X-WWEA-SECRET` usando el **mismo secreto compartido** que definiste en WordPress.

#### a. `get-qr-code` Workflow (Webhook POST)

- **Trigger:** `Webhook` (Método: `POST`, Autenticación: `Header Auth` con `X-WWEA-SECRET`).
- **Entrada esperada:** `vendor_id` (int), `instance_name` (string) en el cuerpo JSON.
- **Acción:** Llama a tu Evolution API para obtener el código QR de la instancia (`GET /instance/qrcode/{instance_name}`).
- **Salida esperada:** Devuelve una respuesta JSON al plugin de WordPress con el código QR, como `{"qr_code": "data:image/png;base64,..."}` o `{"qr_code": "https://url.to/qr.png"}`.

#### b. `get-status` Workflow (Webhook POST)

- **Trigger:** `Webhook` (Método: `POST`, Autenticación: `Header Auth` con `X-WWEA-SECRET`).
- **Entrada esperada:** `vendor_id` (int), `instance_name` (string) en el cuerpo JSON.
- **Acción:** Llama a tu Evolution API para obtener el estado de conexión de la instancia (`GET /instance/connectionState/{instance_name}`).
- **Salida esperada:** Devuelve una respuesta JSON al plugin de WordPress con el estado, como `{"status": "connected", "connection_info": {"device": "...", "phone": "..."}}`.

#### c. `evolution-api-webhook-listener` Workflow (Webhook POST)

- **Trigger:** `Webhook` (Método: `POST`, esta URL será la que configures en Evolution API para los eventos de webhook).
- **Entrada esperada:** El payload de evento de Evolution API (que contiene `instance_name`, `status`, etc.).
- **Acción:** Extrae `instance_name` y el nuevo `status` (y cualquier `connection_info` relevante) del payload de Evolution API.
- **Salida:** Realiza una solicitud HTTP POST al endpoint REST API de WordPress: `https://your-wordpress-site.com/wp-json/dokan-whatsapp/v1/status-update`. Asegúrate de incluir el encabezado `X-WWEA-SECRET` con tu secreto compartido en esta solicitud.
  - **Cuerpo de la solicitud a WP:** `{"instance_name": "...", "status": "...", "connection_info": {...}}`

---

## 🚀 Uso para Vendedores de Dokan

Una vez configurado, los vendedores de Dokan verán una nueva pestaña de **"WhatsApp"** en su panel de control:

1. **Guarda tu número de WhatsApp:** Introduce el número de teléfono de WhatsApp (con código de país, ej., `+1234567890`) que deseas conectar.
2. **Conectar WhatsApp / Generar QR Code:** Haz clic en este botón para solicitar un nuevo código QR.
3. **Escanea el QR:** Utiliza la aplicación móvil de WhatsApp en tu teléfono para escanear el código QR. ¡Asegúrate de que no haya ninguna sesión de WhatsApp activa en tu teléfono al momento de escanear!
4. **Estado de Conexión:** El plugin mostrará el estado actual de tu conexión (Conectado, Desconectado, Escaneando, Error). Puedes hacer clic en "Refrescar Estado" para obtener la información más reciente.

---

## 🤝 Contribución

¡Las contribuciones son bienvenidas! Si encuentras un error, tienes una sugerencia de mejora o quieres añadir una nueva característica, por favor, abre un "Issue" o envía un "Pull Request".

---

## 📄 Licencia

Este plugin está licenciado bajo la [GPL-2.0+ License](http://www.gnu.org/licenses/gpl-2.0.txt).

---

## ✉️ Contacto (Español - English - Português)

Para preguntas o soporte, puedes contactar a https://brasdrive.com.br

---
