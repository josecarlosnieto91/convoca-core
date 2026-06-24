<?php
namespace Convoca\Core\Tests;

use PHPUnit\Framework\TestCase;

class FeaturesTest extends TestCase
{
    private function loadClass(): void
    {
        require_once dirname(__DIR__, 2) . '/includes/Features.php';
    }

    protected function setUp(): void
    {
        $this->loadClass();
    }

    // ── is_enroll_active ──────────────────────────

    public function test_is_enroll_active_when_constant_defined(): void
    {
        if (!defined('CONVOCA_ENROLL_VERSION')) {
            define('CONVOCA_ENROLL_VERSION', '2.6.1');
        }
        $this->assertTrue(\Convoca\Core\Features::is_enroll_active());
    }

    /**
     * @runInSeparateProcess
     */
    public function test_is_enroll_active_when_not_defined(): void
    {
        // In a separate process, CONVOCA_ENROLL_VERSION is not defined from previous tests
        $this->loadClass();
        $this->assertFalse(\Convoca\Core\Features::is_enroll_active());
    }

    // ── is_gateway_active ─────────────────────────

    public function test_is_gateway_active_by_constant(): void
    {
        if (!defined('CONVOCA_GATEWAY_VERSION')) {
            define('CONVOCA_GATEWAY_VERSION', '2.6.2');
        }
        $this->assertTrue(\Convoca\Core\Features::is_gateway_active());
    }

    // ── is_members_active ─────────────────────────

    public function test_is_members_active_by_constant(): void
    {
        if (!defined('CONVOCA_MEMBERS_VERSION')) {
            define('CONVOCA_MEMBERS_VERSION', '2.6.2');
        }
        $this->assertTrue(\Convoca\Core\Features::is_members_active());
    }

    // ── get_missing_dependencies ──────────────────

    public function test_get_missing_dependencies_returns_empty_when_all_present(): void
    {
        if (!defined('CONVOCA_ENROLL_VERSION')) {
            define('CONVOCA_ENROLL_VERSION', '2.6.1');
        }
        if (!defined('CONVOCA_MEMBERS_VERSION')) {
            define('CONVOCA_MEMBERS_VERSION', '2.6.2');
        }
        $missing = \Convoca\Core\Features::get_missing_dependencies([
            'enroll'  => 'Convoca Enroll',
            'members' => 'Convoca Members',
        ]);
        $this->assertEmpty($missing);
    }

    /**
     * @runInSeparateProcess
     */
    public function test_get_missing_dependencies_returns_missing_plugin(): void
    {
        $this->loadClass();
        // In separate process, only enroll is defined, gateway is truly missing
        if (!defined('CONVOCA_ENROLL_VERSION')) {
            define('CONVOCA_ENROLL_VERSION', '2.6.1');
        }
        $missing = \Convoca\Core\Features::get_missing_dependencies([
            'enroll'  => 'Convoca Enroll',
            'gateway' => 'Convoca Gateway',
        ]);
        $this->assertNotEmpty($missing);
        $this->assertContains('Convoca Gateway', $missing);
    }

    public function test_get_missing_dependencies_ignores_unknown_features(): void
    {
        $missing = \Convoca\Core\Features::get_missing_dependencies([
            'nonexistent' => 'Ghost Plugin',
        ]);
        $this->assertEmpty($missing);
    }

    // ── is_convoca_shifts_active ──────────────────

    public function test_is_shifts_active_by_constant(): void
    {
        if (!defined('CONVOCA_SHIFTS_VERSION')) {
            define('CONVOCA_SHIFTS_VERSION', '2.5.1');
        }
        $this->assertTrue(\Convoca\Core\Features::is_convoca_shifts_active());
    }
}
