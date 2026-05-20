<?php
/**
 * Unit tests for Convoca Core Utils.
 *
 * @package Convoca\Core\Tests
 */

namespace Convoca\Core\Tests;

use Convoca\Core\Utils;
use PHPUnit\Framework\TestCase;

/**
 * Test Utils helper methods.
 */
class TestUtils extends TestCase {

    /**
     * Test sanitize_email_list removes invalid emails.
     */
    public function test_sanitize_email_list_filters_invalid(): void {
        $input   = 'valid@example.com, not-an-email, another@test.org';
        $result  = Utils::sanitize_email_list($input);
        $expected = ['valid@example.com', 'another@test.org'];
        $this->assertEquals($expected, $result);
    }

    /**
     * Test sanitize_email_list handles empty input.
     */
    public function test_sanitize_email_list_empty(): void {
        $this->assertEquals([], Utils::sanitize_email_list(''));
        $this->assertEquals([], Utils::sanitize_email_list(',,'));
    }

    /**
     * Test mask_email hides part of the local part.
     */
    public function test_mask_email(): void {
        $result = Utils::mask_email('john.doe@example.com');
        $this->assertStringContainsString('***', $result);
        $this->assertStringContainsString('@example.com', $result);
        $this->assertStringNotContainsString('john.doe', $result);
    }

    /**
     * Test generate_token produces a string of expected length.
     */
    public function test_generate_token_length(): void {
        $token = Utils::generate_token();
        $this->assertEquals(64, strlen($token));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
    }

    /**
     * Test generate_token produces unique values.
     */
    public function test_generate_token_unique(): void {
        $t1 = Utils::generate_token();
        $t2 = Utils::generate_token();
        $this->assertNotEquals($t1, $t2);
    }

    /**
     * Test get_client_ip returns a valid IP.
     */
    public function test_get_client_ip(): void {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        $ip = Utils::get_client_ip();
        $this->assertEquals('192.168.1.1', $ip);
    }
}
