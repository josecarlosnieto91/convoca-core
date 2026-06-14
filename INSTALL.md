# Manual de Instalación y Puesta en Marcha: Convoca Core

Este documento es la guía técnica para instalar la base del ecosistema Convoca. **Este plugin debe ser el primero en instalarse y activarse.**

## 📥 1. Instalación del Plugin

1. Sube la carpeta `convoca-core` al directorio `/wp-content/plugins/` de tu WordPress.
2. Activa el plugin desde el panel de **Plugins**.
   - *Nota:* Al activarse, se creará automáticamente la tabla `{prefix}bdv_logs` en la base de datos para el registro centralizado de eventos.

## 🛠 2. Configuración y Utilidades

Este plugin no tiene una interfaz de usuario extensa, ya que actúa como una librería de servicios para los demás. Sus funciones principales son:
- **Logging Centralizado:** Permite ver qué ocurre en el ecosistema (puedes consultar la tabla de logs si hay errores).
- **Gestión de Webhooks:** Proporciona la infraestructura para enviar datos a sistemas externos (Slack, CRM, etc.).
- **Utilidades de Validación:** Incluye validadores de DNI/NIE que usan los plugins de socios e inscripciones.

## ⚙️ 3. Tareas de Mantenimiento

- **Revisión de Logs:** Es recomendable limpiar la tabla `{prefix}bdv_logs` periódicamente si el volumen de actividad es muy alto, aunque el plugin está optimizado para no penalizar el rendimiento.
- **Webhooks:** Si se integran servicios externos, asegúrate de configurar los *secrets* para que la firma HMAC-SHA256 sea válida.

---

## 🔍 Checklist de Verificación Final

Antes de instalar los plugins de Socios o Inscripciones, realiza las siguientes comprobaciones:

- [ ] **Activación sin errores:** El plugin se activa correctamente sin generar "Fatal Errors" o avisos de cabeceras ya enviadas.
- [ ] **Tabla de base de datos:** Verifica (vía phpMyAdmin o similar) que la tabla `wp_bdv_logs` (o tu prefijo correspondiente) ha sido creada.
- [ ] **Accesibilidad del Logger:** (Solo para técnicos) Al ejecutar `Convoca\Core\Logger::info('Test', 'System')` en un entorno de pruebas, el registro debe aparecer en la base de datos.
- [ ] **Dependencia detectada:** Intenta activar `convoca-members` sin activar `convoca-core`. Debes ver un aviso de administración indicando que la librería base es necesaria.
- [ ] **Compatibilidad PHP:** Asegúrate de que el servidor corre PHP 8.1 o superior, ya que el uso de Namespaces y Tipado estricto lo requiere.

¡Base lista! Ahora puedes proceder con la instalación de los módulos funcionales.
