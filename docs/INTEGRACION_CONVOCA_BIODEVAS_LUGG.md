# Guía de Integración — Convoca en biodevas.org y lugg.biodevas.org

> Cómo desplegar los plugins Convoca en los sitios existentes sin romper nada.

## Principios

1. **No romper lo que funciona.** Ambos sitios usan Astra + Elementor. Los plugins Convoca deben coexistir sin conflictos.
2. **Probar en local primero.** `localhost:8080` (entorno convoca-dev) o crear un subdominio de pruebas en el mismo servidor.
3. **Activación progresiva.** Un plugin cada vez, verificar que el frontend sigue funcionando.
4. **Shortcodes, no templates.** Usar shortcodes de Convoca dentro de páginas/posts existentes en lugar de reemplazar temas.

## Plugins relevantes por sitio

### biodevas.org — Asociación socioambiental

| Plugin | ¿Instalar? | Uso |
|--------|-----------|-----|
| convoca-core | ✅ Necesario | Base para cualquier otro plugin |
| convoca-members | 🔴 Prioritario | Gestión de socios, voluntariado, cuotas |
| convoca-enroll | 🟡 Útil | Inscripciones a actividades (rutas, talleres, plantaciones) |
| convoca-gateway | 🟡 Útil | Pagos de cuotas y donaciones |
| convoca-shifts | 🟢 Opcional | Turnos de voluntariado (si se implanta) |
| convoca-publisher | 🟢 Opcional | Publicar actividades en redes |
| convoca-theme | 🔴 No inmediato | **No activar** — reemplazaría el tema Astra actual |

### lugg.biodevas.org — Centro Social Los Lugg

| Plugin | ¿Instalar? | Uso |
|--------|-----------|-----|
| convoca-core | ✅ Necesario | Base |
| convoca-shifts | 🔴 Prioritario | Turnos de voluntariado del centro |
| convoca-members | 🟡 Útil | Socios del centro (tipo Lugg) |
| convoca-enroll | 🟡 Útil | Inscripciones a talleres y cursos |
| convoca-publisher | 🟢 Opcional | Publicar actividades en redes |
| convoca-theme | 🔴 No inmediato | **No activar** |

## Plan de despliegue

### Fase 1 — Local (sin riesgo)

1. Activar convoca-core en `localhost:8080` (entorno convoca-dev)
2. Verificar que el menú **Convoca** aparece en el admin
3. Activar convoca-members
4. Verificar que los CPTs (`miembro`, `proyecto`) no colisionan con otros plugins
5. Probar shortcodes en páginas de prueba

### Fase 2 — Members en biodevas.org

1. **Backup completo** antes de instalar
2. Activar convoca-core + convoca-members
3. Crear páginas:
   - `/area-socio/` con `[convoca_mi_perfil]`
   - `/verificar-socio/` con `[convoca_verificar_socio]`
4. Migrar datos de socios desde el Excel actual (`Libro de socios.xlsx`)
5. Verificar que el frontend (Astra) no se rompe — los shortcodes son seguros

### Fase 3 — Enroll en biodevas.org

1. Activar convoca-enroll
2. Las actividades existentes (CPT `actividad`) deben mantenerse — verificar compatibilidad
3. Añadir `[convoca_inscripcion]` en las páginas de actividad existentes
4. Probar el flujo completo: ver actividad → inscribirse → email confirmación

### Fase 4 — Shifts en lugg.biodevas.org

1. Activar convoca-shifts
2. Crear página `/turnos/` con `[convoca_turnos]`
3. Configurar los turnos tipo del centro

## Compatibilidad técnica

### Temas

| Tema actual | ¿Compatible? | Notas |
|-------------|-------------|-------|
| Astra (biodevas) | ✅ | Los shortcodes de Convoca funcionan en cualquier tema. El CSS de convoca-core (`convoca-common.css`) es ligero y no debería interferir |
| Astra (lugg) | ✅ | Ídem |
| Convoca Theme | ⚠️ | Solo activar cuando se quiera migrar completamente. Reemplaza plantillas y estilos |

### Plugins activos

| Plugin | Riesgo | Mitigación |
|--------|--------|------------|
| Elementor | Bajo | Los shortcodes funcionan en widgets de Elementor (usar widget "Shortcode") |
| LiteSpeed Cache | Bajo | Añadir `/?convoca_*=*` a las exclusiones de caché |
| Yoast SEO | Nulo | No interfiere. Los CPTs de Convoca son detectados por Yoast |
| WooCommerce | Bajo | CPT `pago` de Gateway es independiente de WooCommerce |
| Forminator | Bajo | Los formularios de Convoca usan sus propios handlers |

### Prefijos y namespaces

- **DB tables**: `wp_conv_*` — sin colisión con otros plugins
- **CPT slugs**: `miembro`, `actividad`, `inscripcion`, `pago`, `centro_turno` — verificar que no existan slugs iguales
- **PHP namespace**: `Convoca\*` — PSR-4, aislado
- **Options**: `conv_*` — prefijado

## Rollback

Si algo falla:

1. Desactivar el plugin problemático desde **Plugins → Plugins instalados**
2. Los datos creados (CPTs, opciones) se conservan al desactivar
3. Para eliminar completamente, usar **Plugins → Desinstalar** (ejecuta `uninstall.php`)
4. Restaurar backup si es necesario

## No hacer

- ❌ Activar convoca-theme en producción sin probar en local
- ❌ Instalar todos los plugins a la vez — uno por uno, verificando
- ❌ Modificar el `functions.php` de Astra para integrar Convoca — usar shortcodes
- ❌ Ejecutar migraciones de datos sin backup previo
