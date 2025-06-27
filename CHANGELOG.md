# Registro de Cambios (Changelog)

Todas las modificaciones notables a este proyecto serán documentadas en este archivo.

## [1.1.0] - 2023-06-10
### Añadido
- Sistema de seguridad para encriptar datos sensibles (tokens API, credenciales)
- Sistema de caché inteligente para reducir llamadas a la API
- Validador avanzado de números de teléfono con soporte internacional
- Sistema de eventos para extensibilidad y mejor integración con otros plugins
- Documentación mejorada y comentarios en el código

### Mejorado
- Optimización general de rendimiento mediante caché
- Mayor seguridad para tokens y datos confidenciales
- Mejor manejo de errores en la comunicación con n8n
- Validación más precisa de números de teléfono
- Código refactorizado para mejor mantenimiento

### Corregido
- Problemas de validación en números de teléfono internacionales
- Múltiples llamadas redundantes a la API
- Potenciales vulnerabilidades en el almacenamiento de tokens

## [1.0.1] - 2023-04-15
### Corregido
- Error en la generación de QR cuando falta configuración n8n
- Problemas de compatibilidad con PHP 8.0
- Mensajes de error más claros cuando falla la conexión

## [1.0.0] - 2023-03-01
### Añadido
- Lanzamiento inicial
- Integración de WhatsApp para vendedores Dokan
- Generación de códigos QR para vincular cuentas
- Verificación de estado de sesión
- Envío de mensajes de prueba
- Configuración centralizada de n8n
- Opciones de desinstalación segura