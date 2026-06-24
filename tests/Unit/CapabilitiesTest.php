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

    public function test_get_all_contains_role_caps(): void
    {
        $caps = \Convoca\Core\Capabilities::get_all();
        $this->assertArrayHasKey('administrator', $caps);
        $this->assertArrayHasKey('editor', $caps);
        $this->assertArrayHasKey('author', $caps);
    }

    public function test_admin_has_all_plugin_capabilities(): void
    {
        $caps = \Convoca\Core\Capabilities::get_all();
        $admin = $caps['administrator'];
        $expected = ['convoca_manage_settings', 'convoca_manage_webhooks', 'convoca_view_reports'];
        foreach ($expected as $cap) {
            $this->assertContains($cap, $admin, "Admin missing: $cap");
        }
    }

    public function test_subscriber_has_no_plugin_caps(): void
    {
        $caps = \Convoca\Core\Capabilities::get_all();
        $sub = $caps['subscriber'] ?? [];
        foreach ($sub as $cap) {
            $this->assertStringNotContainsString('convoca_', $cap);
        }
    }

    public function test_editor_has_view_permissions(): void
    {
        $caps = \Convoca\Core\Capabilities::get_all();
        $editor = $caps['editor'] ?? [];
        $viewCaps = array_filter($editor, fn($c) => str_contains($c, 'convoca_'));
        $this->assertNotEmpty($viewCaps);
        // Editor should not have manage_settings
        $this->assertNotContains('convoca_manage_settings', $editor);
    }
}
