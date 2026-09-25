# Contrato de `registro_hora` (horas de voluntariado)

**Ámbito:** todo Convoca. **Implementación:** `Convoca\Core\Hour_Ledger` (convoca-core).
**Origen:** auditoría de consumidores y productores tras `convoca-enroll#2` (2026-09-25).

## Qué representa un registro

Un `registro_hora` es **una acreditación de horas de un voluntario**, con su hecho de origen cuando
existe. Es la **fuente de verdad**: todo cálculo de horas de voluntariado (renovación, certificados,
paneles, correos) sale de aquí.

| Campo | Significado |
|---|---|
| `_convoca_member_id` | Ficha de socio a la que se acredita (clave compartida con Members). |
| `_convoca_usuario_id` | Usuario de WordPress que realizó el hecho. |
| `_convoca_horas` | Horas acreditadas (`DECIMAL(10,2)`). |
| `_convoca_fecha` | Fecha del hecho (`Y-m-d`); determina a qué periodo pertenece. |
| `_convoca_estado` | `pendiente` · `aprobada` · `anulada` (ver abajo). |
| `_convoca_origen` | `inscripcion` (Enroll) · `turno` (Shifts) · vacío en horas manuales. |
| `_convoca_origen_id` | ID del hecho (inscripción o turno). Hace el registro **reversible**. |
| `_convoca_actividad_id` | Actividad de Enroll (0 en turnos). |
| `_convoca_anulada_en` / `_convoca_anulada_motivo` | Cuándo y por qué se invalidó. |

## Estados

| Estado | Cuenta | Significado |
|---|---|---|
| `pendiente` | **No** | Registro a la espera de aprobación por la asociación. |
| `aprobada` | **Sí** | Acreditado. Es el **único** estado que suma. |
| `anulada` | **No** | El hecho se retiró (`Hour_Ledger::revoke`): se había acreditado y dejó de valer. Conserva el histórico. |
| `rechazada` | **No** | Horas manuales que la asociación no acepta (`Hours_Manager`): **nunca** llegaron a acreditarse. |

`anulada` y `rechazada` son cosas distintas —una acreditación retirada frente a una propuesta
denegada— pero comparten efecto: **no suman**. Ningún consumidor debe tratarlas como equivalentes
al mostrarlas: la primera tuvo horas y se retiraron; la segunda nunca las tuvo.

Regla de oro: **quien decide (renovación, certificados) exige `aprobada`**; nadie debe considerar
válido un estado distinto ni añadir estados nuevos sin actualizar este documento y a todos los
consumidores.

## Cuándo se crea, reactiva y anula

| Operación | Quién | Cuándo |
|---|---|---|
| **Crear** (`Hour_Ledger::credit`) | Enroll / Shifts | Se marca una asistencia o se da un turno por realizado. Si el hecho ya tenía registro, **reactiva ese mismo** (nunca crea otro). |
| **Anular** (`Hour_Ledger::revoke`) | Enroll / Shifts | Se retira la asistencia o el turno deja de estar realizado. No borra: invalida. |
| Crear/editar a mano | Members (`Hours_Manager`) | Horas que la asociación apunta directamente (sin hecho de origen). |

El permiso para **acreditar** lo decide `Admin_Voluntariado::puede_acreditar_horas()` (rol
`voluntario_aprobado` o `_convoca_voluntario_aprobado = 1`). **Retirar** una acreditación no requiere
ese permiso: es una corrección y debe funcionar siempre.

## Productores

| Productor | Origen | Vínculo | Reversible |
|---|---|---|---|
| convoca-enroll (`Volunteer_Hour_Tracker`) | asistencia a actividad | `inscripcion` + ID de inscripción | sí |
| convoca-shifts (`Hour_Sync`) | turno realizado | `turno` + ID de turno | sí |
| convoca-members (`Hours_Manager`) | horas manuales de la asociación | — | por edición del propio registro |

## Consumidores

Exigen `_convoca_estado = 'aprobada'` (todos ignoran `pendiente` y `anulada`):

- `Voluntariado_Manager::get_horas_aprobadas()` y `get_horas_aprobadas_desde()` — base del resto.
- `Cron_Manager` — renovación por horas (usa el periodo del ciclo).
- `Certificate_Generator` — certificados de voluntariado.
- `Email_Manager` — correos de voluntariado y de certificado.
- Admin: dashboard, listado de horas, metaboxes, REST (`/me/horas`, `/me/certificate`).
- `GDPR_Tools` — exportación/borrado por petición del interesado.
- `convoca-core/admin-appearance.php` — exportación a PDF de listados.

## Modelo antiguo: el agregado `_convoca_horas_voluntariado_total`

Es un **cache derivado** en el usuario (lo suman/restan Enroll y Shifts al acreditar y retirar).
**No decide nada de negocio**: la auditoría de horas de Shifts lo muestra y ofrece **recalcularlo**
desde los registros, que es exactamente su papel (reconciliación).

Consecuencia práctica: si el agregado y el ledger discrepan, **manda el ledger**. Cualquier código
nuevo debe leer las horas del ledger (`Voluntariado_Manager` o `Hour_Ledger`), no del agregado.

## Deduplicación entre Enroll y Shifts

No se implementa deduplicación automática: una actividad de Enroll y un turno de Shifts son hechos
**distintos** y no existe hoy ninguna relación semántica declarada entre ellos. Inventarla sería
inventar datos. Lo que sí garantiza el contrato es que cada acreditación es **trazable a su hecho**
(`_convoca_origen` + `_convoca_origen_id`), de modo que un duplicado real se puede **detectar**
(mismo voluntario, misma fecha y mismas horas con orígenes distintos) sin cambiar el esquema.

## Reglas para código nuevo

1. Acreditar o retirar horas: **siempre** `Hour_Ledger`, nunca escribir el `registro_hora` a mano.
2. Leer horas: `Voluntariado_Manager` (que filtra `aprobada`), nunca el agregado del usuario.
3. Un hecho nuevo necesita **identidad de origen** (`origen` + `origen_id`) para poder revertirse.
4. No borrar registros para "descontar": se anulan (el histórico es auditable).
