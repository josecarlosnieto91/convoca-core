# Spec — Horas de voluntariado reversibles (asistencia ↔ registro_hora)

**Estado:** aprobada · **Componentes:** convoca-core (mecanismo), convoca-enroll y convoca-shifts (productores)
**Origen:** `convoca-enroll#2`, reproducido en la E2E de Lugg (2026-09-25).

## Problema (reproducido)

Ciclo real medido en Lugg con un voluntario aprobado y una actividad de 1 h:

| Paso | registros_hora | usermeta total | Members ve |
|---|---|---|---|
| Asistencia `si` | #12498 (24 h) + **#12501** (1 h) | 25.00 | **25 h** |
| Asistencia `no` (retirada) | los dos **intactos** (estado `aprobada`) | 24.00 | **25 h** ← no baja |
| Asistencia `si` otra vez | + **#12503** (1 h) | 25.00 | **26 h** ← sube de más |

## Causa raíz

Los dos productores de horas repiten el **mismo defecto estructural**:

|  | convoca-enroll | convoca-shifts |
|---|---|---|
| Hecho de origen | inscripción a una actividad | turno del centro |
| Marcador de idempotencia | `_convoca_horas_contadas='1'` **en la inscripción** | `_convoca_shifts_horas_contabilizadas=<h>` **en el turno** |
| Al acreditar | crea `registro_hora` | crea `registro_hora` |
| **Vínculo registro → origen** | **ninguno** | **ninguno** |
| Al retirar | resta el usermeta y borra el marcador; **no toca el registro** | ídem |
| Al volver a marcar | **crea otro registro** | **crea otro registro** |

El `registro_hora` **no guarda a qué inscripción o turno pertenece**, así que la retirada no puede
encontrarlo y la re-marcación no puede reactivarlo. Y como Members no lee el usermeta ni el marcador
—suma los `registro_hora` con `_convoca_estado='aprobada'`—, el resultado es que retirar no descuenta
y marcar/desmarcar acumula horas sin límite.

## Decisión de arquitectura: mecanismo común en convoca-core

La lógica de acreditar/retirar horas estaba **duplicada** en los dos plugins, y esa duplicación ya
costó un bug anterior (la clave `_convoca_miembro_id` se corrigió en Shifts y no en Enroll, así que
durante meses las horas de Enroll no contaron). Para que no vuelva a divergir, el ciclo de vida del
`registro_hora` pasa a una **única pieza compartida** en Core — no es una capa paralela: es la
operación que ambos productores necesitan.

`Convoca\Core\Hour_Ledger`:

| Método | Qué hace |
|---|---|
| `credit( $origen, $origen_id, $user_id, $hours, $args )` | **Un solo registro por hecho**: si ya existe el del origen lo **reactiva** (estado, horas, fecha); si no, lo crea con su vínculo. Devuelve el ID. |
| `revoke( $origen, $origen_id, $motivo )` | **Invalida** la acreditación de ese origen: `_convoca_estado='anulada'` + `_convoca_anulada_en` + `_convoca_anulada_motivo`. **No borra** el registro. |
| `find( $origen, $origen_id )` | Registro vigente de un origen. |
| `credits_for_user( $user_id )` | Registros acreditados de un usuario (para análisis y migración). |

**Identidad común del hecho** (metas aditivas, no cambia ninguna clave existente):

- `_convoca_origen` → `inscripcion` | `turno`
- `_convoca_origen_id` → ID de la inscripción o del turno

Se conserva `_convoca_member_id` como clave compartida con Members (sin cambios) y se sigue respetando
el marcador de idempotencia de cada productor (que ahora además comparte la misma forma del registro).

## Por qué invalidar y no borrar

`_convoca_estado` es el filtro único que usan **Members** (`get_horas_aprobadas_desde`,
`get_horas_aprobadas`), los **certificados** (`Certificate_Generator`) y la **renovación**
(`Cron_Manager`): todos exigen `'aprobada'`. Pasar el registro a `'anulada'` deja de contarlo en todos
ellos **sin tocar convoca-members** y sin destruir el histórico (queda con fecha y motivo de anulación,
auditable y reversible: volver a marcar lo reactiva).

## Riesgo de doble acreditación entre Enroll y Shifts

- Hoy cada productor tiene su marcador **en su propia entidad** y **no comparten clave de origen**, así
  que el **mismo hecho registrado como actividad y como turno** se acreditaría dos veces. Es un riesgo
  real pero **de modelado**, no de datos: son entidades distintas y no hay hoy ningún vínculo declarado
  entre una actividad y un turno.
- Con `_convoca_origen`/`_convoca_origen_id` cada acreditación queda **trazable a su hecho**, lo que
  permite auditar duplicados (mismo `_convoca_usuario_id` + misma fecha + mismas horas con orígenes
  distintos) sin cambiar el esquema.
- **No se modifica ninguna clave compartida ni el esquema de Shifts** más allá de usar el mismo
  mecanismo. Un dedupe automático entre ambos productores requiere que exista una relación declarada
  actividad↔turno, que hoy no existe: se documenta en el issue y se deja preparado el vínculo.

## Migración (explícita, idempotente, auditable)

Los registros históricos no tienen vínculo. `Upgrade_Manager` de cada plugin rellena
`_convoca_origen`/`_convoca_origen_id` **solo cuando la correspondencia es inequívoca**:

- Enroll: registro de actividad con `_convoca_actividad_id` y usuario conocido → si existe
  **exactamente una** inscripción de ese usuario a esa actividad, se enlaza.
- Shifts: registro de turno (`_convoca_actividad_id = 0`, título «Horas Turno CS #N») → si el turno
  existe y su responsable es el usuario, se enlaza.
- 0 candidatas o ≥2 → **no se toca** y se registra en el log (contadores al final: enlazados,
  ambiguos, omitidos). Nada se borra y ningún estado cambia.

Además, `Hour_Ledger::find()` incluye un **segundo criterio** para registros sin vínculo (mismo
usuario + misma actividad + misma fecha + mismas horas): si encuentra **exactamente uno**, lo reutiliza
y le añade el vínculo; si encuentra varios, no reutiliza y avisa por el log. Así el primer re-marcado
de un histórico tampoco duplica.

## Compatibilidad

- `get_horas_aprobadas_desde()` sigue funcionando igual: los registros acreditados siguen `'aprobada'`.
- Los registros anulados dejan de contar en Members, certificados y renovación.
- No cambia ninguna clave existente ni el esquema de base de datos.

## Criterios de aceptación

1. Marcar → desmarcar → volver a marcar, N veces: **un único** `registro_hora` por hecho y las horas
   del estado final contadas **una vez**.
2. Retirar la asistencia retira **solo** la acreditación de esa asistencia (las horas de otras
   actividades del mismo voluntario no se tocan).
3. Members muestra siempre las horas de las asistencias actualmente acreditadas.
4. La renovación por horas usa exactamente esas horas.
5. Dos asistencias independientes del mismo voluntario suman sus horas; dos inscripciones distintas no
   se pisan.
6. Horas de miembros distintos no se mezclan.
7. Registros históricos legítimos: no se borran ni cambian de estado; solo se les añade el vínculo
   cuando es inequívoco.
8. Migración idempotente (segunda ejecución: 0 cambios) y auditada.
9. `phpunit`, PHPStan, PHPCS y PCP en verde en los tres plugins afectados.
10. E2E real en Lugg tras el despliegue.

## Fuera de alcance

- Dedupe automático entre una actividad y un turno que representen el mismo hecho (necesita una
  relación declarada que hoy no existe).
- Recalcular el usermeta agregado `_convoca_horas_voluntariado_total` de forma retroactiva (sigue el
  ciclo del productor; Members no lo usa).
