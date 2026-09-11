# Changelog — convoca-core

## v2.2.7 (2026-09-11)

### ✨ Mejoras
- Traducción completa y al día del plugin y su plantilla al inglés (en_US)

### 🐛 Correcciones
- La revalidación semanal de licencia se programa correctamente y solo se ejecuta cuando hay una licencia activa

## v2.2.6 (2026-09-10)

### ✨ Mejoras
- Botón "Reparar" en la página de Salud para las comprobaciones con corrección automática
- Retención de logs por nivel configurable + export a CSV
- Reintentos de webhook con retroceso (backoff) configurable + nivel de log configurable
- Tema claro/oscuro para documentos (claro por defecto) con selector en el Asistente de configuración

### 🐛 Correcciones
- La desinstalación ya no deja tablas, opciones ni tareas programadas huérfanas (con opción de conservar los datos)
- Notificaciones y Asistente de configuración vuelven a aparecer en el menú de administración
- Corregido un error de JavaScript que rompía el script de administración (SyntaxError por comillas mixtas)
- Los CSVs del export de copias de seguridad coinciden con los nombres que busca el import
- Los gráficos vacíos del dashboard muestran "Sin datos" y el PDF de memoria ya no incluye código PHP crudo ni emojis
- La página de Salud detecta correctamente Convoca Shifts activo
- El CSS de la página de Salud se carga en el panel de administración
- El dropdown de notificaciones de la barra de administración ya no se sale del marco ni desborda con textos largos
- El parámetro de versión de los assets comunes ya no queda congelado en una versión anterior

### 📦 Infraestructura
- Dependencias actualizadas (dompdf, PHPCS, WPCS)
- Preparación para WordPress.org: Plugin Check sin errores, Chart.js local y deuda estática a cero

## v2.2.5 (2026-09-05)

### 🐛 Correcciones
- El Security Monitor reporta la contención de bloqueos también cuando no hay filas afectadas

## v2.2.4 (2026-09-05)

### 🐛 Correcciones
- Corregidas la contención de bloqueos y el hook REST en el Security Monitor

## v2.2.3 (2026-09-05)

### ✨ Mejoras
- Security Monitor: observabilidad de eventos críticos y resumen diario

### 📦 Infraestructura
- CI 100 % bloqueante (estándares de código, análisis estático y tests)

## v2.2.2 (2026-09-05)

### 🔒 Seguridad
- Corregidos la exclusión mutua de bloqueos, las capacidades del Asistente y el inicio de sesión por AJAX

## v2.2.1 (2026-09-05)

### ✨ Mejoras
- El Asistente permite editar el ID corto (slug) de cada plan, con migración de los miembros

## v2.2.0 (2026-09-05)

### ✨ Mejoras
- El paso 3 del Asistente permite editar el nombre y la modalidad de cada plan

## v2.1.9 (2026-09-05)

### 🐛 Correcciones
- Navegación libre en el Asistente: pasos clicables y botón "Anterior" en el paso 7

## v2.1.8 (2026-09-05)

### ✨ Mejoras
- El paso 3 del Asistente permite activar o desactivar planes y editar su precio

## v2.1.7 (2026-09-05)

### ✨ Mejoras
- El paso 7 "Finalización" muestra un resumen de los 6 pasos configurados

### 🐛 Correcciones
- Botón "Siguiente" visible en el paso 6 (Ecosistema) para avanzar a Finalización

## v2.1.6 (2026-09-05)

### 🐛 Correcciones
- El Asistente detecta los módulos activos y aplica las clases CSS de WordPress en los campos

## v2.1.5 (2026-08-08)

### ✨ Mejoras
- Internacionalización completa: mensajes de error y respuestas REST traducibles
- Paso 6 "Ecosistema" del Asistente: explica módulos, estado de instalación e importación
- Registro y validación de módulos externos (marketplace)
- Historial de migraciones con validación posterior y posibilidad de revertir
- Perfilador de peticiones (rendimiento)

### 🐛 Correcciones
- El enlace de adquisición de licencia apunta a la tienda
- Corregida una comilla faltante en la consulta de socios activos y el alias en el resumen de los últimos 7 días
- El shortcode del Asistente de configuración es el correcto (`convoca_alta_socio`)
- Evitada una clave duplicada en instalaciones multisitio
- El endpoint REST de métricas usa el callback existente
- Eliminados los datos de Biodevas incrustados: planes, pistas, correos y URLs ahora se configuran mediante filtros
- El límite de intentos ya no queda enmascarado por la caché de objetos
- Modo oscuro: etiquetas de formulario en naranja e inputs con fondo oscuro
- Las peticiones de licencia se firman con nonce HMAC

### 🧪 Tests
- Tests unitarios ampliados (arranque, integración y utilidades)

### 📝 Documentación
- Referencia de hooks generada desde el código
- Readme preparado para WordPress.org
- Documentación unificada en la wiki

### 📦 Infraestructura
- Preparación para WordPress.org: readme en inglés, correcciones de Plugin Check, icono y banner
- Plantillas de issues y GitHub Discussions

---

## v2.1.4 (2026-06-24)

### ✨ Improvements
- Añadido publisher a PRO_FEATURES para habilitar licenciamiento completo
- Añadidas nuevas capabilities para diagnóstico y monitoreo del sistema
- Mejoras en diagnóstico y salud del sistema

### 🧪 Tests
- Añadidos tests unitarios para Utils::validate_dni (14 casos, validación DNI/NIE)
- Añadidos tests para Features (8) + Capabilities (5)

### 📦 Infrastructure
- Updated release ZIPs on getconvoca.app
- Demo environment synchronized

---

*Entradas nuevas reconstruidas a partir del historial de git.*
