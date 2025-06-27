=== WP WhatsApp Evolution API ===
Contributors: tuautorwordpress
Tags: whatsapp, dokan, marketplace, vendor, evolution-api, n8n
Requires at least: 5.8
Tested up to: 6.3
Stable tag: 1.1.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Integra WhatsApp con Dokan Multivendor Marketplace permitiendo a cada vendedor gestionar su propia cuenta de WhatsApp desde su panel de control.

== Description ==

WP WhatsApp Evolution API resuelve el desafío de integrar WhatsApp para múltiples vendedores en una plataforma Dokan. A diferencia de una integración global, este plugin permite que **cada vendedor Dokan** tenga su propia cuenta de WhatsApp conectada a través de Evolution API, todo gestionado desde su propio panel de control. n8n actúa como la capa de abstracción, simplificando la comunicación entre WordPress y la API de WhatsApp, y ofreciendo flexibilidad para futuras automatizaciones.

### Características Principales

* **Gestión Individual de WhatsApp**: Cada vendedor de Dokan puede conectar y gestionar su propia cuenta de WhatsApp.
* **Generación de QR para Conexión**: Los vendedores pueden generar y escanear códigos QR directamente desde su panel de control Dokan para vincular su número de WhatsApp.
* **Estado de Conexión en Tiempo Real**: Visualización del estado actual de la conexión de WhatsApp (conectado, desconectado, escaneando QR) desde el panel del vendedor.
* **Envío de Mensajes de Prueba**: Un formulario simple para que los vendedores envíen mensajes de prueba y confirmen la funcionalidad.
* **Intermediario n8n Flexible**: Utiliza n8n para orquestar las llamadas a Evolution API, lo que permite una personalización y expansión sencilla de los flujos de trabajo sin modificar el código del plugin.

### Nuevas Características (v1.1.0)

* **Sistema de Seguridad Avanzado**: Encriptación de datos sensibles como tokens y claves API.
* **Sistema de Caché Inteligente**: Reducción de llamadas a la API almacenando temporalmente respuestas frecuentes.
* **Validación Avanzada de Números**: Detecta y formatea correctamente números de teléfono para diferentes países.
* **Sistema de Eventos**: Arquitectura basada en eventos que permite a otros plugins y temas conectar con la funcionalidad de WhatsApp.

== Installation ==

1. **Descarga e Instala el Plugin**:
   * Descarga el archivo .zip del plugin.
   * Ve a tu panel de administración de WordPress > Plugins > Añadir nuevo.
   * Haz clic en "Subir Plugin" y selecciona el archivo .zip descargado.
   * Haz clic en "Instalar ahora" y luego en "Activar Plugin".

2. **Configura las Opciones de n8n**:
   * Ve a Ajustes > WP WhatsApp Evolution API en tu panel de administración de WordPress.
   * Introduce la URL Base de tu Webhook de n8n (ej. `https://your-n8n-instance.com/webhook/`). Asegúrate de que termine con una barra (`/`).
   * Si tu webhook de n8n requiere un token de autenticación, introdúcelo en el campo correspondiente.
   * Configura la opción "Eliminar datos al desinstalar" según tu preferencia.

3. **Configura n8n**:
   * Necesitarás crear tres (o más) workflows separados en n8n:
     - `qr_generation`: Para iniciar el proceso de obtención de un código QR.
     - `session_status`: Para verificar el estado de una sesión de WhatsApp.
     - `message_send`: Para enviar mensajes desde una sesión específica.
     - (Opcional pero recomendado) Un cuarto workflow que recibe eventos de Evolution API y actualiza el estado en WordPress.

== Frequently Asked Questions ==

= ¿Necesito tener Dokan instalado para usar este plugin? =

Sí, este plugin está diseñado específicamente para trabajar con Dokan Multivendor Marketplace. Es necesario tener Dokan instalado y configurado para que los vendedores puedan gestionar sus conexiones de WhatsApp.

= ¿Qué es n8n y por qué lo necesito? =

n8n es una plataforma de automatización de código abierto que actúa como intermediario entre WordPress y Evolution API. Esta arquitectura proporciona flexibilidad, permitiéndote crear flujos de trabajo personalizados para tus necesidades específicas sin modificar el código del plugin.

= ¿Cómo se asegura la privacidad de las conversaciones de WhatsApp? =

Este plugin solo proporciona la infraestructura para conectar las cuentas de WhatsApp de los vendedores. Las conversaciones se manejan directamente a través de la API de WhatsApp y no se almacenan en WordPress. Los tokens y datos sensibles se almacenan de forma encriptada.

= ¿Puedo personalizar los mensajes automáticos o crear respuestas predefinidas? =

Sí, esto se puede implementar mediante flujos de trabajo personalizados en n8n. El plugin proporciona la infraestructura básica, y puedes ampliar la funcionalidad utilizando n8n para crear respuestas automáticas, plantillas de mensaje, etc.

= ¿Es compatible con la API oficial de WhatsApp Business? =

No, este plugin está diseñado específicamente para trabajar con Evolution API, una solución no oficial. Si necesitas integración con la API oficial de WhatsApp Business, deberás buscar otra solución.

== Screenshots ==

1. Dashboard de WhatsApp para vendedores de Dokan
2. Página de configuración de administrador
3. Generación y escaneo de código QR
4. Envío de mensajes de prueba

== Changelog ==

= 1.1.0 =
* Añadido: Sistema de seguridad para encriptar datos sensibles
* Añadido: Sistema de caché inteligente para reducir llamadas a la API
* Añadido: Validador avanzado de números de teléfono con soporte internacional
* Añadido: Sistema de eventos para extensibilidad y mejor integración
* Mejorado: Optimización general de rendimiento mediante caché
* Mejorado: Mayor seguridad para tokens y datos confidenciales
* Mejorado: Mejor manejo de errores en la comunicación con n8n
* Mejorado: Validación más precisa de números de teléfono
* Corregido: Problemas con números de teléfono internacionales
* Corregido: Múltiples llamadas redundantes a la API

= 1.0.1 =
* Corregido: Error en la generación de QR cuando falta configuración n8n
* Corregido: Problemas de compatibilidad con PHP 8.0
* Corregido: Mensajes de error más claros cuando falla la conexión

= 1.0.0 =
* Lanzamiento inicial del plugin

== Upgrade Notice ==

= 1.1.0 =
Actualización importante con mejoras de seguridad, rendimiento y nuevas características. Actualizar es altamente recomendado.

== Arbitrary section ==

### Requisitos técnicos

* **WordPress**: Versión 5.8 o superior.
* **Dokan Multivendor Marketplace**: Plugin Dokan activo y configurado.
* **PHP**: Versión 7.4 o superior.
* **n8n**: Una instancia de n8n (self-hosted o en la nube) configurada y accesible desde tu servidor de WordPress.
* **Evolution API**: Una cuenta activa y una instancia de Evolution API configurada para tus sesiones de WhatsApp.