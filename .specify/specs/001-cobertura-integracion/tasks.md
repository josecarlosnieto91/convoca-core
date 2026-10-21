# Tareas — 001-cobertura-integracion

Todas con su comprobación. Ninguna se da por buena sin el comando.

| # | Tarea | Se verifica con | Estado |
|---|---|---|---|
| 1 | Cualificar las 14 anotaciones de `tests/Integration/` y retirar `@coversDefaultClass` del docblock de fichero | `grep -rn '@covers ::' tests/ \| wc -l` → **0** | hecha |
| 2 | Medir las dos suites en una sola invocación | `vendor/bin/phpunit -c phpunit.xml.dist --testsuite unit,integration --coverage-clover build/coverage.xml` → 349 tests, sin avisos | hecha |
| 3 | Que el clover ya no salga vacío | `php -r` sobre `build/coverage.xml` → 18,9 % (1.204/6.371), antes `0,0 % (0/6.432)` | hecha |
| 4 | Fijar el suelo combinado por debajo del número real | `cat .coverage-floor` → 16,9; run de Cobertura en `main` → «por encima del suelo» | hecha |
| 5 | Prueba en negativo de la puerta | suelo a 99 % en rama + PR → run `39647472503` **failure** (17,7 %); `cobertura-local.sh . --suelo 99` → rc=1 | hecha |
| 6 | Dejar `main` intacta tras la prueba | PR cerrado sin fusionar, rama borrada, `git rev-list --count origin/main..HEAD` → 0 | hecha |
| 7 | CHANGELOG, versión y tag | `bin/verificar-version.sh .` → rc=0; `git tag` → v2.3.49 | hecha |
