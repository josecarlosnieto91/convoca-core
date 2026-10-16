## [2.3.37] — 2026-10-16

### Arreglado
- **El límite de intentos era solo por dirección IP: varios errores de UNA persona dejaban fuera a todas
  las demás de su misma conexión (members#46).** Una sede con una sola línea, un telecentro o una familia
  entrando por el mismo router se quedaban una hora sin poder darse de alta — y el siguiente leía
  «Demasiados intentos», sin saber que no era culpa suya.
  Ahora `check_rate_limit()` acepta **quién** lo intenta y lleva **dos** contadores: uno **por persona** (con
  el tope de siempre) y otro **por conexión** con un tope mucho más alto (`max(5×max, max+20)`), que frena a
  quien va cambiando de identidad sin cerrarle la puerta a una sede entera.
- **Se puede ver y levantar un bloqueo desde el panel**: `rate_limit_blocks()` lista los bloqueos activos
  (quién, acción, intentos y hasta cuándo) y `rate_limit_clear()` los levanta. Hasta ahora había que tocar la
  base de datos a mano.
- El bloqueo queda en el registro con la identidad **enmascarada** (`p***@dominio`, `1.2.3.x`): sirve para
  auditar sin dejar el correo o la IP entera en el registro.
- Los contadores caducados se limpian al listarlos, para que la pantalla no arrastre basura.

