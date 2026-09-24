<?php
namespace Convoca\Core\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Regresión del precio del evento (issue #5).
 *
 * El schema.org publicaba todo evento como gratuito porque el modelo no tenía
 * precio. Ahora el precio vive en `_convoca_event_price` y se normaliza aquí:
 * si un valor inválido se colara, un curso de pago volvería a anunciarse como
 * gratuito, que es exactamente el fallo que se está corrigiendo.
 */
class EventPriceSanitizeTest extends TestCase
{
    private function loadFunctions(): void
    {
        require_once dirname(__DIR__, 2) . '/includes/event-meta.php';
    }

    protected function setUp(): void
    {
        $this->loadFunctions();
    }

    public function test_la_clave_canonica_esta_en_el_inventario(): void
    {
        $this->assertContains('_convoca_event_price', \Convoca\Core\event_meta_keys());
    }

    /**
     * @dataProvider precios
     */
    public function test_normaliza_el_precio(string $entrada, string $esperado): void
    {
        $this->assertSame($esperado, \Convoca\Core\event_price_sanitize($entrada));
    }

    public static function precios(): array
    {
        return [
            'vacío es evento gratuito'        => ['', ''],
            'solo espacios'                   => ['   ', ''],
            'entero'                          => ['60', '60.00'],
            'con punto decimal'               => ['60.5', '60.50'],
            'con dos decimales'               => ['60.50', '60.50'],
            'con coma decimal (lo de aquí)'   => ['60,50', '60.50'],
            'cero es válido (aunque gratis)'  => ['0', '0.00'],
            'negativo no vale'                => ['-5', ''],
            'texto no vale'                   => ['sesenta', ''],
            'texto con número no vale'        => ['60 euros', ''],
            'coma suelta no vale'             => [',', ''],
        ];
    }
}
