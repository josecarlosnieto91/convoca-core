# Especificación de funcionalidad: la puerta de cobertura mide también la suite de integración

**Rama**: `001-cobertura-integracion`
**Creada**: 2026-10-21
**Estado**: Borrador
**Entrada**: petición de JC — «un gate de cobertura en el CI que no solo mida, sino que exija».

Sostienen esta spec los principios **II (una sola vara por medida)** y **III (la evidencia manda)** de la
constitución del ecosistema.

## Contexto y problema

La puerta de cobertura entró el 21-10-2026 midiendo **solo la suite unitaria**: 17,7 % de líneas sobre
`includes/`, suelo fijado en 15 %. La suite de integración —que es donde vive la mitad del valor del
núcleo— **no la mide nadie**. Además, al activar la cobertura apareció un defecto de forma: los tests de
integración usan anotaciones de cobertura en forma corta (archivos `tests/Integration/*.php`), que PHPUnit 10
marca como inválidas al medir.

Consecuencia: el suelo actual premia escribir tests unitarios fáciles y no dice nada de lo que de verdad se
ejercita contra WordPress.

## Historias de uso

### Historia 1 - El suelo cubre todas las suites (prioridad: P1)

Como responsable del ecosistema, quiero que el porcentaje del CI salga de **todas** las suites, para que el
suelo no se pueda cumplir ignorando la integración.

**Por qué esta prioridad**: sin esto, el número del CI es una verdad a medias.

**Prueba independiente**: en una rama de prueba se borra un test unitario y el porcentaje baja aunque la
integración siga igual; con las dos suites medidas, el número refleja ambas.

**Escenarios de aceptación**

1. **Dado** el repo en `main`, **cuando** corre la puerta de cobertura, **entonces** el porcentaje sale de la
   suma de unitarios e integración y queda escrito en el resumen del trabajo.
2. **Dado** un cambio que quita tests de integración, **cuando** corre la puerta, **entonces** el porcentaje
   baja y, si cruza el suelo, el trabajo falla.

### Historia 2 - Los tests de integración declaran cobertura válida (prioridad: P1)

Como mantenedor, quiero que las anotaciones de cobertura de la integración sean válidas, para que medir no
genere avisos que tumben el trabajo.

**Por qué esta prioridad**: hoy es el bloqueo técnico que impide medirlas.

**Prueba independiente**: correr la integración con cobertura termina con código 0 y sin avisos de anotación.

**Escenarios de aceptación**

1. **Dado** `tests/Integration/`, **cuando** se ejecuta con cobertura, **entonces** no aparece ningún aviso
   de anotación inválida.
2. **Dado** el mismo comando sin cobertura, **entonces** el resultado de tests no cambia respecto a hoy.

### Historia 3 - El suelo es explícito y se puede subir (prioridad: P2)

Como responsable, quiero ver el suelo y el número en el resumen del CI, para decidir cuándo subirlo sin
adivinar.

**Prueba independiente**: el resumen del trabajo muestra las dos cifras y el veredicto.

**Escenarios de aceptación**

1. **Dado** el fichero `.coverage-floor` con un valor, **cuando** el número lo supera, **entonces** el
   trabajo termina en verde y lo dice.
2. **Dado** el número por debajo del suelo, **entonces** el trabajo falla nombrando las dos cifras.
3. **Dado** que no hay fichero de suelo, **entonces** el trabajo solo informa y no bloquea.

## Requisitos

- **RF-1**: la puerta ejecuta las dos suites de PHPUnit con cobertura en un solo paso de medición, y el
  porcentaje que se compara es el total del proyecto.
- **RF-2**: las anotaciones de cobertura de `tests/Integration/` se cualifican con su clase, como ya se hizo
  en `tests/Unit/` el 21-10-2026.
- **RF-3**: el suelo vive en `.coverage-floor`; su ausencia significa «solo informar».
- **RF-4**: el trabajo escribe en el resumen las dos cifras y el veredicto.

## Criterios de éxito (medibles)

- [ ] El registro del trabajo de cobertura muestra el porcentaje total y **no** un aviso de anotación
      inválida.
- [ ] `grep -c '@covers ::' tests/` devuelve **0**.
- [ ] El suelo se puede subir por encima del número actual y el trabajo **falla**; se prueba en una rama y se
      revierte.
- [ ] Los tests unitarios y de integración siguen pasando: mismo recuento o más, cero fallos nuevos.

## Fuera de alcance

- Cambiar qué se considera cubierto (sigue siendo líneas de `includes/`).
- Medir ramas o condiciones.
- Tocar el job de tests normal (`ci.yml`): la medición va en su propio trabajo.

## Riesgos

| Riesgo | Mitigación |
|---|---|
| La integración necesita WordPress y tarda más | Medir y, si pasa de unos minutos, dejarla en un trabajo aparte que se ejecute en `main` y en PR |
| Subir cobertura de integración puede pedir un doble de WordPress más completo | Se mide primero; si falta el doble, se abre issue en vez de inventarlo |
| El suelo combinado cambia y `main` se pone rojo | Se fija después de ver el número real, igual que se hizo con el 15 % |
