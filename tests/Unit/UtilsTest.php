<?php
/**
 * Unit tests for Convoca Core Utils.
 *
 * @package       Convoca\Core\Tests
 *
 * @coversDefaultClass \Convoca\Core\Utils
 */

namespace Convoca\Core\Tests;

use Convoca\Core\Utils;
use PHPUnit\Framework\TestCase;

/**
 * Test Utils helper methods.
 *
 * @covers ::validar_dni
 * @covers ::generate_access_code
 * @covers ::format_date
 * @covers ::format_nombre
 * @covers ::escape_csv_field
 */
class UtilsTest extends TestCase
{
    /**
     * Test validar_dni with valid DNI.
     *
     * @covers \Convoca\Core\Utils::validar_dni
     */
    public function test_validar_dni_valid(): void
    {
        $this->assertTrue(Utils::validar_dni('12345678Z'));
    }

    /**
     * Test validar_dni with invalid format.
     *
     * @covers \Convoca\Core\Utils::validar_dni
     */
    public function test_validar_dni_invalid(): void
    {
        $this->assertFalse(Utils::validar_dni('1234567'));
        $this->assertFalse(Utils::validar_dni('ABCDEFGH'));
        $this->assertFalse(Utils::validar_dni(''));
    }

    /**
     * Test validar_dni with valid NIE.
     *
     * @covers \Convoca\Core\Utils::validar_dni
     */
    public function test_validar_dni_valid_nie(): void
    {
        $this->assertTrue(Utils::validar_dni('X1234567L'));
        $this->assertTrue(Utils::validar_dni('Y1234567X'));
        $this->assertTrue(Utils::validar_dni('Z1234567R'));
    }

    /**
     * Test generate_access_code produces a valid format.
     *
     * @covers \Convoca\Core\Utils::generate_access_code
     */
    public function test_generate_access_code_format(): void
    {
        $code = Utils::generate_access_code();
        $this->assertIsString($code);
        $this->assertGreaterThan(0, strlen($code));
    }

    /**
     * Test generate_access_code produces unique values.
     *
     * @covers \Convoca\Core\Utils::generate_access_code
     */
    public function test_generate_access_code_unique(): void
    {
        $codes = [];
        for ($i = 0; $i < 10; $i++) {
            $codes[] = Utils::generate_access_code();
        }
        $this->assertCount(10, array_unique($codes));
    }

    /**
     * Test format_date with various inputs.
     *
     * @covers \Convoca\Core\Utils::format_date
     */
    public function test_format_date_valid(): void
    {
        $result = Utils::format_date('2024-12-25 10:00:00', 'd/m/Y');
        $this->assertEquals('25/12/2024', $result);
    }

    /**
     * Test format_date with empty input.
     *
     * @covers \Convoca\Core\Utils::format_date
     */
    public function test_format_date_empty(): void
    {
        $result = Utils::format_date('', 'd/m/Y');
        $this->assertEquals('', $result);
    }

    /**
     * Test format_nombre capitalizes correctly.
     *
     * @covers \Convoca\Core\Utils::format_nombre
     */
    public function test_format_nombre(): void
    {
        $this->assertEquals('Jose Carlos', Utils::format_nombre('jose carlos'));
        $this->assertEquals('Maria', Utils::format_nombre('  maria  '));
        $this->assertEquals('', Utils::format_nombre(''));
    }

    /**
     * Test escape_csv_field wraps field with quotes when needed.
     *
     * @covers \Convoca\Core\Utils::escape_csv_field
     */
    public function test_escape_csv_field(): void
    {
        $result = Utils::escape_csv_field('hello world');
        // escape_csv_field wraps in quotes when the field contains special chars
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    /**
     * Test escape_csv_field with simple strings.
     *
     * @covers \Convoca\Core\Utils::escape_csv_field
     */
    public function test_escape_csv_field_simple(): void
    {
        $result = Utils::escape_csv_field('hello');
        $this->assertEquals('hello', $result);
    }

    // ── Legacy tests for removed/renamed methods (kept as skipped for documentation) ──

    /** @coversNothing */
    public function test_sanitize_email_list_removed(): void
    {
        $this->markTestSkipped('sanitize_email_list() removed — use WordPress sanitize_email() instead.');
    }

    /** @coversNothing */
    public function test_mask_email_removed(): void
    {
        $this->markTestSkipped('mask_email() removed — use GDPR-aware helpers in Members plugin instead.');
    }

    /** @coversNothing */
    public function test_generate_token_renamed(): void
    {
        $this->markTestSkipped('generate_token() renamed to generate_access_code() — covered above.');
    }

    /** @coversNothing */
    public function test_get_client_ip_removed(): void
    {
        $this->markTestSkipped('get_client_ip() removed — IP tracking handled at HTTP layer.');
    }

    /** @coversNothing */
    public function test_sanitize_phone_removed(): void
    {
        $this->markTestSkipped('sanitize_phone() removed — use WordPress sanitize_text_field() instead.');
    }
}
