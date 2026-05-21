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
 * @covers ::sanitize_email_list
 * @covers ::mask_email
 * @covers ::generate_token
 * @covers ::get_client_ip
 * @covers ::validar_dni
 * @covers ::generate_access_code
 * @covers ::format_date
 * @covers ::sanitize_phone
 */
class TestUtils extends TestCase
{
    /**
     * Test sanitize_email_list removes invalid emails.
     *
     * @covers \Convoca\Core\Utils::sanitize_email_list
     */
    public function test_sanitize_email_list_filters_invalid(): void
    {
        $input   = 'valid@example.com, not-an-email, another@test.org';
        $result  = Utils::sanitize_email_list($input);
        $expected = ['valid@example.com', 'another@test.org'];
        $this->assertEquals($expected, $result);
    }

    /**
     * Test sanitize_email_list handles empty input.
     *
     * @covers \Convoca\Core\Utils::sanitize_email_list
     */
    public function test_sanitize_email_list_empty(): void
    {
        $this->assertEquals([], Utils::sanitize_email_list(''));
        $this->assertEquals([], Utils::sanitize_email_list(',,'));
    }

    /**
     * Test sanitize_email_list handles null input gracefully.
     *
     * @covers \Convoca\Core\Utils::sanitize_email_list
     */
    public function test_sanitize_email_list_null(): void
    {
        $this->assertEquals([], Utils::sanitize_email_list(null));
    }

    /**
     * Test sanitize_email_list handles whitespace.
     *
     * @covers \Convoca\Core\Utils::sanitize_email_list
     */
    public function test_sanitize_email_list_trim_whitespace(): void
    {
        $input  = '  valid@example.com ,  spaces@test.org  ';
        $result = Utils::sanitize_email_list($input);
        $this->assertEquals(['valid@example.com', 'spaces@test.org'], $result);
    }

    /**
     * Test mask_email hides part of the local part.
     *
     * @covers \Convoca\Core\Utils::mask_email
     */
    public function test_mask_email(): void
    {
        $result = Utils::mask_email('john.doe@example.com');
        $this->assertStringContainsString('***', $result);
        $this->assertStringContainsString('@example.com', $result);
        $this->assertStringNotContainsString('john.doe', $result);
    }

    /**
     * Test mask_email with short local part.
     *
     * @covers \Convoca\Core\Utils::mask_email
     */
    public function test_mask_email_short(): void
    {
        $result = Utils::mask_email('ab@test.org');
        $this->assertStringContainsString('@test.org', $result);
    }

    /**
     * Test mask_email with empty input.
     *
     * @covers \Convoca\Core\Utils::mask_email
     */
    public function test_mask_email_empty(): void
    {
        $result = Utils::mask_email('');
        $this->assertEquals('', $result);
    }

    /**
     * Test generate_token produces a string of expected length.
     *
     * @covers \Convoca\Core\Utils::generate_token
     */
    public function test_generate_token_length(): void
    {
        $token = Utils::generate_token();
        $this->assertEquals(64, strlen($token));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
    }

    /**
     * Test generate_token produces unique values.
     *
     * @covers \Convoca\Core\Utils::generate_token
     */
    public function test_generate_token_unique(): void
    {
        $tokens = [];
        for ($i = 0; $i < 10; $i++) {
            $tokens[] = Utils::generate_token();
        }
        $this->assertCount(10, array_unique($tokens));
    }

    /**
     * Test get_client_ip returns a valid IP.
     *
     * @requires function filter_var
     *
     * @covers \Convoca\Core\Utils::get_client_ip
     */
    public function test_get_client_ip(): void
    {
        $ip = Utils::get_client_ip();
        $this->assertIsString($ip);
        $this->assertNotEmpty($ip);
    }

    /**
     * Test validar_dni with valid DNI.
     *
     * @covers \Convoca\Core\Utils::validar_dni
     */
    public function test_validar_dni_valid(): void
    {
        // Valid Spanish DNI examples (generated with known algorithms).
        // 12345678Z is a commonly used test DNI.
        $this->assertTrue(Utils::validar_dni('12345678Z'));
    }

    /**
     * Test validar_dni with invalid format.
     *
     * @covers \Convoca\Core\Utils::validar_dni
     */
    public function test_validar_dni_invalid(): void
    {
        $this->assertFalse(Utils::validar_dni('1234567')); // Too short.
        $this->assertFalse(Utils::validar_dni('ABCDEFGH')); // No digit part.
        $this->assertFalse(Utils::validar_dni(''));         // Empty.
    }

    /**
     * Test validar_dni with valid NIE.
     *
     * @covers \Convoca\Core\Utils::validar_dni
     */
    public function test_validar_dni_valid_nie(): void
    {
        // Valid NIE: X followed by 7 digits + letter.
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
     * Test sanitize_phone with Spanish numbers.
     *
     * @covers \Convoca\Core\Utils::sanitize_phone
     */
    public function test_sanitize_phone(): void
    {
        $result = Utils::sanitize_phone('+34 612 345 678');
        $this->assertIsString($result);
    }

    /**
     * Test sanitize_phone with empty input.
     *
     * @covers \Convoca\Core\Utils::sanitize_phone
     */
    public function test_sanitize_phone_empty(): void
    {
        $result = Utils::sanitize_phone('');
        $this->assertEquals('', $result);
    }
}
