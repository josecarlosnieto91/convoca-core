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
        $this->assertTrue(\Convoca\Core\Utils::validate_dni('Z1234567M'));
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
}
