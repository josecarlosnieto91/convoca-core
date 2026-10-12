<?php
/**
 * Comprobación de contrato: el repo tiene UN solo estándar de PHPCS.
 *
 * Por qué existe: convoca-core llegó a tener dos configuraciones de phpcs —la que
 * usaba el CI (dev-tools/phpcs.xml.dist) y una copia obsoleta en la raíz— y daban
 * veredictos distintos para el mismo código: 0 avisos frente a 194. Peor: `phpcs`
 * sin argumentos auto-descubre la de la raíz, así que quien auditaba a mano veía
 * 194 avisos mientras el CI decía verde. Dos varas para el mismo código dejan el
 * verde sin significado.
 *
 * Prueba en negativo: si se añade un segundo fichero de reglas (o el CI apunta a
 * otro distinto del único que hay), esta comprobación falla.
 */

namespace Convoca\Core\Tests;

use PHPUnit\Framework\TestCase;

class PhpcsUnSoloEstandarTest extends TestCase
{
    private const NOMBRES = ['.phpcs.xml', '.phpcs.xml.dist', 'phpcs.xml', 'phpcs.xml.dist'];

    private function raiz(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * Recorre el repo buscando ficheros de reglas, saltándose vendor/ y node_modules/.
     *
     * @return string[] rutas relativas a la raíz, ordenadas.
     */
    private function estandares(): array
    {
        $raiz = $this->raiz();
        $encontrados = [];
        $iter = new \RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($raiz, \FilesystemIterator::SKIP_DOTS),
                static function (\SplFileInfo $f): bool {
                    return !$f->isDir() || !in_array($f->getFilename(), ['vendor', 'node_modules', '.git', 'build'], true);
                }
            )
        );
        foreach ($iter as $f) {
            if ($f->isFile() && in_array($f->getFilename(), self::NOMBRES, true)) {
                $encontrados[] = ltrim(str_replace($raiz, '', $f->getPathname()), '/');
            }
        }
        sort($encontrados);
        return $encontrados;
    }

    public function test_hay_un_solo_estandar_de_phpcs(): void
    {
        $estandares = $this->estandares();
        $this->assertSame(
            ['.phpcs.xml.dist'],
            $estandares,
            'El repo debe tener un único estándar de PHPCS, en la raíz. '
            . 'Encontrados: ' . implode(', ', $estandares)
        );
    }

    public function test_el_ci_no_apunta_a_otro_estandar(): void
    {
        $yml = glob($this->raiz() . '/.github/workflows/*.yml') ?: [];
        $this->assertNotEmpty($yml, 'el repo debe tener workflows de CI');

        $vistos = [];
        foreach ($yml as $f) {
            $txt = (string) file_get_contents($f);
            // Solo los pasos que ejecutan phpcs.
            if (preg_match_all('/phpcs[^\n]*/', $txt, $m)) {
                foreach ($m[0] as $linea) {
                    if (preg_match('/--standard=([^\s]+)/', $linea, $std)) {
                        $vistos[basename($f) . ': ' . $std[1]] = $std[1];
                    }
                }
            }
        }

        foreach ($vistos as $donde => $ruta) {
            $this->assertSame(
                '.phpcs.xml.dist',
                ltrim($ruta, './'),
                "El CI ($donde) debe usar el estándar único del repo, no otro fichero de reglas"
            );
        }
    }
}
