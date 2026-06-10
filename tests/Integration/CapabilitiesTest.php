<?php
/**
 * Integration tests for Convoca Core Capabilities.
 *
 * @package       Convoca\Core\Tests
 *
 * @coversDefaultClass \Convoca\Core\Capabilities
 * @group integration
 */

namespace Convoca\Core\Tests;

use Convoca\Core\Capabilities;
use PHPUnit\Framework\TestCase;

/**
 * Tests for role and capability management.
 *
 * @covers ::get_all
 * @covers ::register
 * @covers ::ensure
 */
class CapabilitiesTest extends TestCase
{
    /**
     * Test that get_all returns the expected structure.
     *
     * @covers \Convoca\Core\Capabilities::get_all
     */
    public function test_get_all_returns_expected_structure(): void
    {
        $caps = Capabilities::get_all();

        $this->assertIsArray($caps);
        $this->assertNotEmpty($caps);

        // Verify a few known capabilities exist.
        $this->assertArrayHasKey('cst_manage_turnos', $caps);
        $this->assertArrayHasKey('conv_manage_checkin', $caps);
        $this->assertArrayHasKey('conv_manage_hours', $caps);
        $this->assertArrayHasKey('conv_view_payments', $caps);
        $this->assertArrayHasKey('common_view_logs', $caps);
        $this->assertArrayHasKey('common_manage_backup', $caps);

        // Each capability should have description and roles.
        foreach ($caps as $cap_key => $config) {
            $this->assertArrayHasKey('description', $config, "Capability $cap_key missing description");
            $this->assertArrayHasKey('roles', $config, "Capability $cap_key missing roles");
            $this->assertIsArray($config['roles'], "Capability $cap_key roles should be an array");
            $this->assertNotEmpty($config['roles'], "Capability $cap_key should have at least one role");
        }
    }

    /**
     * Test that all capabilities list administrator as a role.
     *
     * @covers \Convoca\Core\Capabilities::get_all
     */
    public function test_all_caps_have_admin_role(): void
    {
        $caps = Capabilities::get_all();

        foreach ($caps as $cap_key => $config) {
            $this->assertContains(
                'administrator',
                $config['roles'],
                "Capability $cap_key should include administrator role"
            );
        }
    }

    /**
     * Test that register does not throw when WP roles exist.
     *
     * @group integration
     */
    public function test_register_runs(): void
    {
        if (!function_exists('get_role')) {
            $this->markTestSkipped('WordPress not loaded');
        }

        // Should run without errors.
        Capabilities::register();

        // Verify admin has at least one custom cap.
        $admin = get_role('administrator');
        if ($admin) {
            $this->assertTrue($admin->has_cap('cst_manage_turnos'));
        }

        $this->assertTrue(true);
    }

    /**
     * Test that ensure() runs without errors.
     *
     * @group integration
     */
    public function test_ensure_runs(): void
    {
        if (!function_exists('get_role')) {
            $this->markTestSkipped('WordPress not loaded');
        }

        Capabilities::ensure();
        $this->assertTrue(true);
    }

    /**
     * Test that register is idempotent (safe to call multiple times).
     *
     * @group integration
     */
    public function test_register_is_idempotent(): void
    {
        if (!function_exists('get_role')) {
            $this->markTestSkipped('WordPress not loaded');
        }

        // Call multiple times.
        Capabilities::register();
        Capabilities::register();
        Capabilities::register();

        $admin = get_role('administrator');
        if ($admin) {
            $this->assertTrue($admin->has_cap('cst_manage_turnos'));
        }

        $this->assertTrue(true);
    }

    /**
     * Test that monitor_actividad role gets expected capabilities.
     *
     * @group integration
     */
    public function test_monitor_role_caps(): void
    {
        if (!function_exists('get_role')) {
            $this->markTestSkipped('WordPress not loaded');
        }

        Capabilities::register();
        $monitor = get_role('monitor_actividad');

        if (!$monitor) {
            $this->markTestSkipped('monitor_actividad role not found');
        }

        $this->assertTrue($monitor->has_cap('manage_inscripciones'));
        $this->assertTrue($monitor->has_cap('cst_manage_turnos'));
        $this->assertTrue($monitor->has_cap('conv_manage_checkin'));
        $this->assertTrue($monitor->has_cap('conv_manage_hours'));

        // Admin-only capabilities should NOT be on monitor.
        $this->assertFalse($monitor->has_cap('conv_export_members'));
        $this->assertFalse($monitor->has_cap('conv_manage_webhooks'));
        $this->assertFalse($monitor->has_cap('common_manage_backup'));
    }

    /**
     * Test capability descriptions are translatable strings.
     *
     * @covers \Convoca\Core\Capabilities::get_all
     */
    public function test_cap_descriptions_are_strings(): void
    {
        $caps = Capabilities::get_all();

        foreach ($caps as $cap_key => $config) {
            $this->assertIsString(
                $config['description'],
                "Capability $cap_key description should be a string"
            );
            $this->assertNotEmpty(
                $config['description'],
                "Capability $cap_key description should not be empty"
            );
        }
    }

    /**
     * Test that no capability has an empty roles array.
     *
     * @covers \Convoca\Core\Capabilities::get_all
     */
    public function test_no_empty_role_assignments(): void
    {
        $caps = Capabilities::get_all();

        foreach ($caps as $cap_key => $config) {
            $this->assertNotEmpty(
                $config['roles'],
                "Capability $cap_key has no roles assigned"
            );
        }
    }
}
