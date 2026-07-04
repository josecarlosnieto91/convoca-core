<?php
namespace Convoca\Core\Tests;

use PHPUnit\Framework\TestCase;

class CapabilitiesTest extends TestCase
{
    private function loadClass(): void
    {
        require_once dirname(__DIR__, 2) . '/includes/Capabilities.php';
    }

    protected function setUp(): void
    {
        $this->loadClass();
    }

    public function test_get_all_returns_array(): void
    {
        $caps = \Convoca\Core\Capabilities::get_all();
        $this->assertIsArray($caps);
        $this->assertNotEmpty($caps);
    }

    public function test_get_all_structure(): void
    {
        $caps = \Convoca\Core\Capabilities::get_all();
        foreach ($caps as $cap_name => $config) {
            $this->assertIsString($cap_name);
            $this->assertArrayHasKey('description', $config);
            $this->assertArrayHasKey('roles', $config);
            $this->assertIsArray($config['roles']);
            $this->assertNotEmpty($config['roles']);
        }
    }

    public function test_admin_has_manage_webhooks(): void
    {
        $caps = \Convoca\Core\Capabilities::get_all();
        $found = false;
        foreach ($caps as $cap_name => $config) {
            if ($cap_name === 'convoca_manage_webhooks') {
                $this->assertContains('administrator', $config['roles']);
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'convoca_manage_webhooks should exist and be assigned to admin');
    }

    public function test_convoca_caps_have_admin_role(): void
    {
        $caps = \Convoca\Core\Capabilities::get_all();
        foreach ($caps as $cap_name => $config) {
            if (str_starts_with($cap_name, 'convoca_')) {
                $this->assertContains(
                    'administrator',
                    $config['roles'],
                    "Admin should have $cap_name"
                );
            }
        }
    }

    public function test_subscriber_has_no_convoca_caps(): void
    {
        $caps = \Convoca\Core\Capabilities::get_all();
        foreach ($caps as $cap_name => $config) {
            $this->assertNotContains('subscriber', $config['roles'], "$cap_name should not be assigned to subscriber");
        }
    }

    public function test_export_members_is_admin_only(): void
    {
        $caps = \Convoca\Core\Capabilities::get_all();
        $this->assertArrayHasKey('convoca_export_members', $caps);
        $this->assertEquals(['administrator'], $caps['convoca_export_members']['roles']);
    }

    public function test_get_all_returns_expected_capabilities(): void
    {
        $caps = \Convoca\Core\Capabilities::get_all();
        $expected = [
            'gestionar_mis_turnos',
            'convoca_shifts_manage_turnos',
            'convoca_manage_checkin',
            'convoca_manage_webhooks',
            'common_view_logs',
        ];
        foreach ($expected as $cap) {
            $this->assertArrayHasKey($cap, $caps, "Missing capability: $cap");
        }
    }
}
