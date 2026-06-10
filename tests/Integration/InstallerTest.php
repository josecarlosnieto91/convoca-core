<?php
/**
 * Integration tests for Convoca Core Installer.
 *
 * @package       Convoca\Core\Tests
 *
 * @coversDefaultClass \Convoca\Core\Installer
 * @group integration
 */

namespace Convoca\Core\Tests;

use Convoca\Core\Installer;
use PHPUnit\Framework\TestCase;

/**
 * Tests for DB schema installation and table management.
 *
 * @covers ::db_init
 * @covers ::ensure_member_access_codes
 * @covers ::run_cleanup
 * @covers ::run_purge
 * @requires extension pdo_mysql
 * @requires function dbDelta
 */
class InstallerTest extends TestCase
{
    /**
     * Test that db_init creates required tables.
     *
     * @group integration
     */
    public function test_db_init_creates_tables(): void
    {
        if (!function_exists('dbDelta')) {
            $this->markTestSkipped('dbDelta not available (WordPress not loaded)');
        }

        Installer::db_init();

        global $wpdb;
        $expected_tables = [
            $wpdb->prefix . 'convoca_logs',
            $wpdb->prefix . 'convoca_webhook_retries',
            $wpdb->prefix . 'convoca_locks',
            $wpdb->prefix . 'conv_member_sequence',
        ];

        foreach ($expected_tables as $table) {
            $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
            $this->assertEquals($table, $exists, "Table $table should exist after db_init()");
        }
    }

    /**
     * Test that db_init creates the logs table with correct schema.
     *
     * @group integration
     */
    public function test_db_init_logs_schema(): void
    {
        if (!function_exists('dbDelta')) {
            $this->markTestSkipped('dbDelta not available (WordPress not loaded)');
        }

        Installer::db_init();

        global $wpdb;
        $table = $wpdb->prefix . 'convoca_logs';
        $columns = $wpdb->get_results("SHOW COLUMNS FROM $table");

        $column_names = array_map(function ($col) {
            return $col->Field;
        }, $columns);

        $this->assertContains('id', $column_names);
        $this->assertContains('created_at', $column_names);
        $this->assertContains('level', $column_names);
        $this->assertContains('context', $column_names);
        $this->assertContains('message', $column_names);
        $this->assertContains('user_id', $column_names);
        $this->assertContains('object_id', $column_names);
    }

    /**
     * Test that db_init creates indexes on logs table.
     *
     * @group integration
     */
    public function test_db_init_logs_indexes(): void
    {
        if (!function_exists('dbDelta')) {
            $this->markTestSkipped('dbDelta not available (WordPress not loaded)');
        }

        Installer::db_init();

        global $wpdb;
        $table = $wpdb->prefix . 'convoca_logs';
        $indexes = $wpdb->get_results("SHOW INDEX FROM $table");

        $index_names = array_map(function ($idx) {
            return $idx->Key_name;
        }, $indexes);

        $this->assertContains('level', $index_names);
        $this->assertContains('context', $index_names);
        $this->assertContains('context_level_created', $index_names);
    }

    /**
     * Test that db_init creates webhook retries table with correct schema.
     *
     * @group integration
     */
    public function test_db_init_retries_schema(): void
    {
        if (!function_exists('dbDelta')) {
            $this->markTestSkipped('dbDelta not available (WordPress not loaded)');
        }

        Installer::db_init();

        global $wpdb;
        $table = $wpdb->prefix . 'convoca_webhook_retries';
        $columns = $wpdb->get_results("SHOW COLUMNS FROM $table");

        $column_names = array_map(function ($col) {
            return $col->Field;
        }, $columns);

        $this->assertContains('id', $column_names);
        $this->assertContains('webhook_id', $column_names);
        $this->assertContains('webhook_url', $column_names);
        $this->assertContains('payload', $column_names);
        $this->assertContains('attempt', $column_names);
        $this->assertContains('scheduled_at', $column_names);
    }

    /**
     * Test that db_init creates locks table with correct schema.
     *
     * @group integration
     */
    public function test_db_init_locks_schema(): void
    {
        if (!function_exists('dbDelta')) {
            $this->markTestSkipped('dbDelta not available (WordPress not loaded)');
        }

        Installer::db_init();

        global $wpdb;
        $table = $wpdb->prefix . 'convoca_locks';
        $columns = $wpdb->get_results("SHOW COLUMNS FROM $table");

        $column_names = array_map(function ($col) {
            return $col->Field;
        }, $columns);

        $this->assertContains('lock_key', $column_names);
        $this->assertContains('expires', $column_names);
        $this->assertContains('created_at', $column_names);
    }

    /**
     * Test that run_cleanup does not throw.
     *
     * @group integration
     */
    public function test_run_cleanup(): void
    {
        if (!function_exists('wp_next_scheduled')) {
            $this->markTestSkipped('WordPress functions not available');
        }

        Installer::run_cleanup();
        $this->assertTrue(true);
    }

    /**
     * Test that run_purge does not throw.
     *
     * @group integration
     */
    public function test_run_purge(): void
    {
        if (!function_exists('wp_next_scheduled')) {
            $this->markTestSkipped('WordPress functions not available');
        }

        Installer::run_purge();
        $this->assertTrue(true);
    }

    /**
     * Test that ensure_member_access_codes processes without errors.
     *
     * @group integration
     */
    public function test_ensure_member_access_codes(): void
    {
        if (!class_exists('\\Convoca\\Core\\Utils')) {
            $this->markTestSkipped('Convoca\Core\Utils not available');
        }

        if (!function_exists('wp_next_scheduled')) {
            $this->markTestSkipped('WordPress not available');
        }

        Installer::ensure_member_access_codes();
        $this->assertTrue(true);
    }
}
