# Plan técnico — 001-cobertura-integracion

## Enfoque

Una sola invocación de PHPUnit con las dos suites (`--testsuite unit,integration`) sobre
`phpunit.xml.dist`, que ya declara ambas y el filtro de cobertura. Un único clover, un único porcentaje.
No se inventa un doble de WordPress: la integración se mide tal como corre en CI, y los casos que
necesitan WordPress se saltan con motivo escrito en el propio test (no se ocultan).

Antes de tocar la configuración se midió el estado: con cobertura, las 14 anotaciones de
`tests/Integration/` estaban sin cualificar y PHPUnit 10 las declaraba inválidas, así que el trabajo
caía (`rc=1`) y el clover salía en `0.0 % (0/6432)` — la puerta no medía nada.

## Decisiones

- **Cualificar, no reescribir**: `@covers ::metodo` → `@covers \Clase::metodo`. La anotación
  `@coversDefaultClass` estaba en el docblock del **fichero**, donde PHPUnit no la lee; se retira.
- **El suelo sube**: de 15 % (solo unitaria) a 16,9 % (combinada, dos puntos por debajo de 18,9 %).
- **No se toca `ci.yml`**: la medición vive en su propio trabajo (`coverage.yml`), como pedía el
  alcance de la spec.

## Riesgos y resultado

| Riesgo | Qué pasó |
|---|---|
| La integración tarda más | 349 tests en 0,5 s: irrelevante |
| Falta un doble de WordPress | No hace falta: 8 casos se saltan con motivo declarado y el job de integración real sigue siendo el de `ci.yml` |
| `main` se pone rojo al subir el suelo | Se fijó después de medir, y el run de CI lo confirmó (19,2 % > 16,9 %) |
