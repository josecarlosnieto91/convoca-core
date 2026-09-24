# Deuda conocida de PHPCS en Core

`phpcs` con el estándar del repo (`dev-tools/phpcs.xml.dist`) da **0 errores**.
Los avisos que quedan están **clasificados y justificados aquí**: no se silencian
ni se dan por resueltos, y ninguno indica un fallo funcional conocido.

Punto de partida de esta limpieza (2026-09-23): **292 avisos**.
Tras corregir los que podían ocultar problemas reales: **199 avisos**.

## Corregido (y por qué)

| Aviso | n | Qué era | Corrección |
|-------|---|---------|------------|
| `error_log` en `Admin_Templates` y `Signature` | 14 | Logs por `error_log` directo, con el prefijo `BDV` (nombre de un cliente) y sin pasar por el logger del plugin | `Logger::warning` / `Logger::error`, con contexto `Plantillas` / `PDF`. Logs centralizados y producto sin nombres de clientes |
| `UnnecessaryPrepare` | 3 | `$wpdb->prepare()` envolviendo consultas sin ningún marcador | Se quita el envoltorio (no había nada que preparar) |
| `SafeRedirect` | 3 | `wp_redirect( wp_get_referer() )`: el referer es entrada del visitante | `wp_safe_redirect`, que valida el host |
| `MissingTrueStrict` | 2 | `in_array` con comparación laxa | Tercer argumento `true` |
| `urlencode` | 2 | `urlencode()` construyendo a mano una URL de redirección | `add_query_arg()` |
| `NoSilencedErrors` | 3 | `@file_get_contents` / `@file_put_contents` **comprobando ya el retorno** | Sin `@` (el chequeo se mantiene) |
| `NoReservedKeywordParameterNames` | 2 | Parámetros llamados `new` y `default` | Renombrados a `$new_state`, `$old_state`, `$default_days` |
| `ForLoopWithTestFunctionCall` | 1 | `count()` en la condición del `for` | Se calcula una vez antes del bucle |
| `json_encode` | 3 | `json_encode()` directo | `wp_json_encode()` |
| Alineación y espacios (`MultipleStatementAlignment`, `PrecisionAlignment`) | 63 | Cosmético puro | `phpcbf`, sin cambios de comportamiento |

## Mantenido a propósito

| Aviso | n | Por qué se deja |
|-------|---|-----------------|
| `DirectDatabaseQuery.NoCaching` | 77 | Pide cachear las consultas directas. Muchas **no deben** cachearse: cerrojos, secuencias de numeración, colas de reintento y contadores de rate-limit (una caché ahí rompe la concurrencia). Envolver las demás en caché de objetos es un cambio de comportamiento en el instalador y el panel, con ganancia escasa: no se hace para bajar un contador |
| `DirectDatabaseQuery.DirectQuery` | 84 | Convoca tiene tablas propias (logs, colas, códigos de reserva, locks) para las que **no existe API de WordPress**. El aviso es informativo: la alternativa sería no tener esas tablas |
| `SlowDBQuery` (`meta_query`/`meta_key`/`meta_value`) | 8 | Consultas por metadatos: es el modelo de datos del plugin (miembros, inscripciones, turnos). Se apoyan en el índice de `postmeta` y están acotadas por post_type/estado |
| `SchemaChange` | 5 | `CREATE`/`ALTER TABLE` del instalador y de las migraciones: es su trabajo |
| `file_put_contents` | 7 | El `sniff` prefiere `WP_Filesystem`, que pide credenciales FTP/SSH y no sirve para escribir la firma del PDF ni los ficheros del vault. Se deja la escritura directa **comprobando el retorno** |
| `NoSilencedErrors` (`@set_time_limit`, `@ini_set`) | 3 | En hosting compartido esas funciones pueden estar deshabilitadas; sin `@` saltaría un aviso visible en mitad de un backup. El valor es un intento, no una garantía |
| `PreparedSQLPlaceholders.UnfinishedPrepare` | 2 | Falso positivo: el SQL se compone con marcadores y se pasa el array completo a `prepare()`. Llevan `phpcs:ignore` **con el motivo escrito** en la línea |
| `UnusedFunctionParameter` | 3 | Son callbacks de filtros de WordPress (`admin_footer_text`, `block_categories_all`): la firma la fija WordPress, no nosotros |
| `error_log` en `Logger` | 3 | Es el propio logger: `error_log` es su último recurso si no puede escribir donde toca |
| `AssignmentInCondition` (`while ( ( $x = fgetcsv(...) ) !== false )`) | 1 | Patrón deliberado y explícito: la comparación `!== false` está a la vista |
| `CommentedOutCode` | 1 | Falso positivo: es un comentario `/* translators: ... */` con ejemplos de marcadores |

## Volver a medir

```bash
vendor/bin/phpcs --standard=dev-tools/phpcs.xml.dist --report=summary
vendor/bin/phpcs --standard=dev-tools/phpcs.xml.dist --report=json -q   # desglose por sniff
```

Regla de la casa: **no se silencia un aviso para bajar el contador**. Si alguno
estorba de verdad, se corrige la causa o se documenta aquí.
