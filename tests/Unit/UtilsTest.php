<?php
namespace Convoca\Core\Tests;

use PHPUnit\Framework\TestCase;

class UtilsTest extends TestCase
{
    private function loadClass(): void
    {
        require_once dirname(__DIR__, 2) . '/includes/Utils.php';
    }

    protected function setUp(): void
    {
        $this->loadClass();
    }

    // ── DNI validation ──────────────────────────

    public function test_valid_dni(): void
    {
        $this->assertTrue(\Convoca\Core\Utils::validate_dni('12345678Z'));
        $this->assertTrue(\Convoca\Core\Utils::validate_dni('00000000T'));
    }

    public function test_dni_with_lowercase(): void
    {
        $this->assertTrue(\Convoca\Core\Utils::validate_dni('12345678z'));
    }

    public function test_dni_with_spaces(): void
    {
        $this->assertTrue(\Convoca\Core\Utils::validate_dni(' 12345678 Z '));
    }

    public function test_invalid_dni_letter(): void
    {
        $this->assertFalse(\Convoca\Core\Utils::validate_dni('12345678A'));
        $this->assertFalse(\Convoca\Core\Utils::validate_dni('12345678B'));
    }

    public function test_dni_too_short(): void
    {
        $this->assertFalse(\Convoca\Core\Utils::validate_dni('1234567Z'));
    }

    public function test_dni_too_long(): void
    {
        $this->assertFalse(\Convoca\Core\Utils::validate_dni('123456789Z'));
    }

    public function test_dni_empty(): void
    {
        $this->assertFalse(\Convoca\Core\Utils::validate_dni(''));
        $this->assertFalse(\Convoca\Core\Utils::validate_dni('   '));
    }

    // ── NIE validation ──────────────────────────

    public function test_valid_nie_x(): void
    {
        $this->assertTrue(\Convoca\Core\Utils::validate_dni('X1234567L'));
    }

    public function test_valid_nie_y(): void
    {
        $this->assertTrue(\Convoca\Core\Utils::validate_dni('Y1234567X'));
    }

    public function test_valid_nie_z(): void
    {
        $this->assertTrue(\Convoca\Core\Utils::validate_dni('Z1234567R'));
    }

    public function test_invalid_nie_letter(): void
    {
        $this->assertFalse(\Convoca\Core\Utils::validate_dni('X1234567A'));
    }

    // ── Edge cases ──────────────────────────────

    public function test_nie_lowercase(): void
    {
        $this->assertTrue(\Convoca\Core\Utils::validate_dni('x1234567l'));
    }

    public function test_dni_with_hyphen(): void
    {
        $this->assertTrue(\Convoca\Core\Utils::validate_dni('12345678-Z'));
    }

    public function test_bad_format(): void
    {
        $this->assertFalse(\Convoca\Core\Utils::validate_dni('ABC'));
        $this->assertFalse(\Convoca\Core\Utils::validate_dni('12.345.678Z'));
    }

    // ── Document theme (light/dark) ─────────────

    private function stubThemeOption(string $value): void
    {
        // El bootstrap unit (tests/bootstrap-unit.php) mockea get_option (con _wp_stores)
        // y apply_filters (paso-identidad). Aquí solo fijamos la opción a probar.
        $GLOBALS['_wp_stores']['options']['convoca_document_theme'] = $value;
    }

    public function test_document_theme_defaults_to_light(): void
    {
        // Sin opción seteada → default 'light'.
        unset($GLOBALS['_wp_stores']['options']['convoca_document_theme']);
        $this->assertSame('light', \Convoca\Core\Utils::get_document_theme());
    }

    public function test_document_theme_dark(): void
    {
        $this->stubThemeOption('dark');
        $this->assertSame('dark', \Convoca\Core\Utils::get_document_theme('card'));
    }

    public function test_document_theme_invalid_falls_back_to_light(): void
    {
        $this->stubThemeOption('neon');
        $this->assertSame('light', \Convoca\Core\Utils::get_document_theme());
    }
}
