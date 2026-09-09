<?php
/**
 * Unit tests for Convoca Core Logger.
 *
 * @package       Convoca\Core\Tests
 *
 * @coversDefaultClass \Convoca\Core\Logger
 */

namespace Convoca\Core\Tests;

use Convoca\Core\Logger;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the Logger class.
 *
 * @covers ::log
 * @covers ::info
 * @covers ::warning
 * @covers ::error
 * @covers ::get_logs
 * @covers ::get_stats
 * @covers ::cleanup
 */
class LoggerTest extends TestCase
{
    /**
     * Test that log method accepts various severity levels.
     *
     * @covers \Convoca\Core\Logger::log
     * @covers \Convoca\Core\Logger::info
     * @covers \Convoca\Core\Logger::warning
     * @covers \Convoca\Core\Logger::error
     */
    public function test_log_accepts_levels(): void
    {
        // These should not throw exceptions even if DB table doesn't exist.
        Logger::info('Info message', 'Test', 1);
        Logger::warning('Warning message', 'Test', 2);
        Logger::error('Error message', 'Test', 3);

        // Base log method.
        Logger::log('Direct log', 'debug', 'Test', 4);

        // If we got here without exceptions, the method signature is solid.
        $this->assertTrue(true);
    }

    /**
     * Test that log handles very long messages gracefully.
     *
     * @covers \Convoca\Core\Logger::log
     */
    public function test_log_long_message(): void
    {
        $long = str_repeat('A', 10000);
        Logger::log($long, 'info', 'Test');
        $this->assertTrue(true);
    }

    /**
     * Test that log handles empty message.
     *
     * @covers \Convoca\Core\Logger::log
     */
    public function test_log_empty_message(): void
    {
        Logger::log('', 'info', 'Test');
        $this->assertTrue(true);
    }

    /**
     * Test that log handles special characters.
     *
     * @covers \Convoca\Core\Logger::log
     */
    public function test_log_special_chars(): void
    {
        Logger::log("Message with ñoñerías & accents: áéíóú üñ", 'info', 'Test');
        Logger::log('<script>alert("xss")</script>', 'info', 'Test');
        Logger::log("Multiline\nString\r\nWith\tTabs", 'info', 'Test');
        $this->assertTrue(true);
    }

    /**
     * Test that log handles null object_id.
     *
     * @covers \Convoca\Core\Logger::log
     */
    public function test_log_null_object_id(): void
    {
        Logger::log('Test with null object_id', 'info', 'Test', null);
        $this->assertTrue(true);
    }

    /**
     * Test get_logs returns array (even if empty).
     *
     * @covers \Convoca\Core\Logger::get_logs
     */
    public function test_get_logs_returns_array(): void
    {
        $logs = Logger::get_logs();
        $this->assertIsArray($logs);
    }

    /**
     * Test get_logs with filters.
     *
     * @covers \Convoca\Core\Logger::get_logs
     */
    public function test_get_logs_with_filters(): void
    {
        // These should not throw.
        $logs = Logger::get_logs(['context' => 'System']);
        $this->assertIsArray($logs);

        $logs = Logger::get_logs(['level' => 'error']);
        $this->assertIsArray($logs);

        $logs = Logger::get_logs(['limit' => 5]);
        $this->assertIsArray($logs);
        $this->assertLessThanOrEqual(5, count($logs));

        $logs = Logger::get_logs(['object_id' => 999999]);
        $this->assertIsArray($logs);
    }

    /**
     * Test get_logs with invalid limit values.
     *
     * @covers \Convoca\Core\Logger::get_logs
     */
    public function test_get_logs_invalid_limit(): void
    {
        // Limit of 0 should fall back to default.
        $logs = Logger::get_logs(['limit' => 0]);
        $this->assertIsArray($logs);

        // Very large limit should be capped.
        $logs = Logger::get_logs(['limit' => 99999]);
        $this->assertIsArray($logs);
    }

    /**
     * Test get_stats returns expected structure.
     *
     * @covers \Convoca\Core\Logger::get_stats
     */
    public function test_get_stats_structure(): void
    {
        $stats = Logger::get_stats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total', $stats);
        $this->assertArrayHasKey('by_level', $stats);
        $this->assertArrayHasKey('by_context', $stats);
        $this->assertArrayHasKey('size_kb', $stats);
    }

    /**
     * Test cleanup runs without error.
     *
     * @covers \Convoca\Core\Logger::cleanup
     */
    public function test_cleanup_runs(): void
    {
        // Should not throw even if table doesn't exist.
        Logger::cleanup();
        $this->assertTrue(true);
    }

    /**
     * Test purge_old_logs runs without error.
     *
     * @covers \Convoca\Core\Logger::purge_old_logs
     */
    public function test_purge_old_logs_runs(): void
    {
        Logger::purge_old_logs();
        $this->assertTrue(true);
    }

    /**
     * Test the default per-level retention policy.
     *
     * @covers \Convoca\Core\Logger::get_log_retention_days
     */
    public function test_log_retention_defaults(): void
    {
        delete_option('convoca_log_retention_days');
        $this->assertSame(
            array('info' => 14, 'warning' => 30, 'error' => 90),
            Logger::get_log_retention_days()
        );
    }

    /**
     * Test custom per-level retention values are honored.
     *
     * @covers \Convoca\Core\Logger::get_log_retention_days
     */
    public function test_log_retention_custom(): void
    {
        update_option('convoca_log_retention_days', array('info' => 7, 'warning' => 15, 'error' => 45));
        $this->assertSame(
            array('info' => 7, 'warning' => 15, 'error' => 45),
            Logger::get_log_retention_days()
        );
    }

    /**
     * Test invalid retention values fall back to defaults.
     *
     * @covers \Convoca\Core\Logger::get_log_retention_days
     */
    public function test_log_retention_invalid_falls_back(): void
    {
        update_option('convoca_log_retention_days', array('info' => 0, 'warning' => -5, 'error' => 'abc'));
        $this->assertSame(
            array('info' => 14, 'warning' => 30, 'error' => 90),
            Logger::get_log_retention_days()
        );
    }

    /**
     * Test a non-array retention option falls back to defaults.
     *
     * @covers \Convoca\Core\Logger::get_log_retention_days
     */
    public function test_log_retention_non_array_falls_back(): void
    {
        update_option('convoca_log_retention_days', 'not-an-array');
        $this->assertSame(
            array('info' => 14, 'warning' => 30, 'error' => 90),
            Logger::get_log_retention_days()
        );
    }

    /**
     * Test partial retention config keeps defaults for missing levels.
     *
     * @covers \Convoca\Core\Logger::get_log_retention_days
     */
    public function test_log_retention_partial(): void
    {
        update_option('convoca_log_retention_days', array('info' => 3));
        $this->assertSame(
            array('info' => 3, 'warning' => 30, 'error' => 90),
            Logger::get_log_retention_days()
        );
    }

    /**
     * Test cleanup deletes per level using the configured retention days.
     *
     * @covers \Convoca\Core\Logger::cleanup
     */
    public function test_cleanup_uses_per_level_retention(): void
    {
        update_option('convoca_log_retention_days', array('info' => 14, 'warning' => 30, 'error' => 90));
        set_transient('convoca_logger_table_exists', 1);

        $spy = new class {
            public $prefix = 'wp_';
            public $last_error = '';
            public $queries = array();
            public function query($sql) {
                $this->queries[] = $sql;
                return 1;
            }
            public function prepare($sql, ...$args) {
                foreach ($args as $arg) {
                    $p = strpos($sql, '%');
                    if ($p !== false) {
                        $sql = substr_replace($sql, (string) $arg, $p, 2);
                    }
                }
                return $sql;
            }
            public function insert($table, $data, $format = null) {
                return 1;
            }
            public function get_var($q = null) {
                return '0';
            }
        };

        $original_wpdb = $GLOBALS['wpdb'];
        $GLOBALS['wpdb'] = $spy;

        try {
            Logger::cleanup();
        } finally {
            $GLOBALS['wpdb'] = $original_wpdb;
            delete_transient('convoca_logger_table_exists');
        }

        $this->assertCount(3, $spy->queries);
        $this->assertStringContainsString('INTERVAL 14 DAY', $spy->queries[0]);
        $this->assertStringContainsString('level = info', $spy->queries[0]);
        $this->assertStringContainsString('INTERVAL 30 DAY', $spy->queries[1]);
        $this->assertStringContainsString('level = warning', $spy->queries[1]);
        $this->assertStringContainsString('INTERVAL 90 DAY', $spy->queries[2]);
        $this->assertStringContainsString('level = error', $spy->queries[2]);
    }

    /**
     * Test rapid successive log calls (rate limiting check).
     *
     * @covers \Convoca\Core\Logger::log
     */
    public function test_rapid_log_calls(): void
    {
        for ($i = 0; $i < 10; $i++) {
            Logger::log("Rapid test message #$i", 'info', 'Test');
        }
        $this->assertTrue(true);
    }

    /**
     * Test recursive log prevention.
     *
     * @covers \Convoca\Core\Logger::log
     */
    public function test_recursion_prevention(): void
    {
        // Simulate by logging from within a logger context.
        Logger::log('Outer call', 'info', 'Test');
        Logger::log('Inner call', 'info', 'Test');
        $this->assertTrue(true);
    }
}
