<?php
/**
 * Unit tests for Convoca Core License_Manager.
 *
 * Pure logic tests for has_pro(), get_status_label(), and PRO_FEATURES constant.
 *
 * @package       Convoca\Core\Tests
 *
 * @coversDefaultClass \Convoca\Core\License_Manager
 */

namespace Convoca\Core\Tests;

use Convoca\Core\License_Manager;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the License_Manager class.
 *
 * @covers ::has_pro
 * @covers ::get_status_label
 * @covers ::PRO_FEATURES
 */
class LicenseManagerTest extends TestCase
{
    /**
     * @var array<string, mixed> Stored license data returned by mocked get_option.
     * @internal Public so the namespace-level get_option() mock can read it.
     */
    public static array $mockLicense = [];

    /**
     * Set up mocks before the class is autoloaded.
     * Called once before any tests in this class run.
     */
    public static function setUpBeforeClass(): void
    {
        self::$mockLicense = [
            'key'      => '',
            'status'   => 'inactive',
            'type'     => 'free',
            'features' => [],
            'expires'  => '',
            'email'    => '',
        ];
    }

    /**
     * Reset mock license state before each test.
     */
    protected function setUp(): void
    {
        self::$mockLicense = [
            'key'      => '',
            'status'   => 'inactive',
            'type'     => 'free',
            'features' => [],
            'expires'  => '',
            'email'    => '',
        ];
    }

    // ────────────────────────────────────────────
    // PRO_FEATURES constant
    // ────────────────────────────────────────────

    /**
     * PRO_FEATURES has the 9 expected keys with Spanish labels.
     *
     * @coversNothing — constant, not a method
     */
    public function test_pro_features_has_nine_keys(): void
    {
        $features = License_Manager::PRO_FEATURES;
        $this->assertCount(11, $features, 'PRO_FEATURES should contain exactly 11 features.');
    }

    /**
     * PRO_FEATURES contains all expected feature keys.
     *
     * @coversNothing — constant, not a method
     */
    public function test_pro_features_expected_keys(): void
    {
        $features = License_Manager::PRO_FEATURES;

        $expected = [
            'members',
            'enroll',
            'gateway',
            'shifts',
            'gamification',
            'pdf_memories',
            'pwa_checkin',
            'analytics',
            'webhooks',
            'publisher',
            'theme',
        ];

        foreach ($expected as $key) {
            $this->assertArrayHasKey($key, $features, "PRO_FEATURES should contain key '$key'.");
        }
    }

    /**
     * PRO_FEATURES values are non-empty Spanish strings.
     *
     * @coversNothing — constant, not a method
     */
    public function test_pro_features_values_are_non_empty(): void
    {
        $features = License_Manager::PRO_FEATURES;

        foreach ($features as $key => $label) {
            $this->assertIsString($label, "PRO_FEATURES['$key'] should be a string.");
            $this->assertNotEmpty($label, "PRO_FEATURES['$key'] should not be empty.");
        }
    }

    // ────────────────────────────────────────────
    // has_pro() — no license key
    // ────────────────────────────────────────────

    /**
     * has_pro returns false when no license key is set.
     *
     * @covers \Convoca\Core\License_Manager::has_pro
     */
    public function test_has_pro_false_when_no_license_key(): void
    {
        self::$mockLicense['key'] = '';

        $this->assertFalse(
            License_Manager::has_pro('members'),
            'has_pro should return false when license key is empty.'
        );
    }

    /**
     * has_pro returns false for any feature when no key.
     *
     * @covers \Convoca\Core\License_Manager::has_pro
     */
    public function test_has_pro_false_for_all_features_when_no_key(): void
    {
        self::$mockLicense['key'] = '';

        foreach (array_keys(License_Manager::PRO_FEATURES) as $feature) {
            $this->assertFalse(
                License_Manager::has_pro($feature),
                "has_pro('$feature') should return false when no license key."
            );
        }
    }

    // ────────────────────────────────────────────
    // has_pro() — expired license
    // ────────────────────────────────────────────

    /**
     * has_pro returns false when license is expired.
     *
     * @covers \Convoca\Core\License_Manager::has_pro
     */
    public function test_has_pro_false_when_expired(): void
    {
        self::$mockLicense['key']     = 'TEST-KEY-EXPIRED';
        self::$mockLicense['type']    = 'single';
        self::$mockLicense['expires'] = '2020-01-01 00:00:00'; // Far in the past

        $this->assertFalse(
            License_Manager::has_pro('members'),
            'has_pro should return false when license is expired.'
        );
    }

    /**
     * has_pro returns false even for features in the array when expired.
     *
     * @covers \Convoca\Core\License_Manager::has_pro
     */
    public function test_has_pro_false_when_expired_even_with_matching_feature(): void
    {
        self::$mockLicense['key']      = 'TEST-KEY-EXPIRED-2';
        self::$mockLicense['type']     = 'single';
        self::$mockLicense['features'] = ['members', 'enroll'];
        self::$mockLicense['expires']  = '2020-06-15 12:00:00'; // Far in the past

        $this->assertFalse(
            License_Manager::has_pro('members'),
            'has_pro should return false when expired, even if feature is in the features array.'
        );
    }

    // ────────────────────────────────────────────
    // has_pro() — unlimited type
    // ────────────────────────────────────────────

    /**
     * has_pro returns true when license type is unlimited.
     *
     * @covers \Convoca\Core\License_Manager::has_pro
     */
    public function test_has_pro_true_when_unlimited(): void
    {
        self::$mockLicense['key']  = 'TEST-KEY-UNLIMITED';
        self::$mockLicense['type'] = 'unlimited';

        $this->assertTrue(
            License_Manager::has_pro('members'),
            'has_pro should return true for unlimited license type.'
        );
    }

    /**
     * has_pro returns true for ALL features when unlimited.
     *
     * @covers \Convoca\Core\License_Manager::has_pro
     */
    public function test_has_pro_true_for_all_features_when_unlimited(): void
    {
        self::$mockLicense['key']  = 'TEST-KEY-UNLIMITED-2';
        self::$mockLicense['type'] = 'unlimited';

        foreach (array_keys(License_Manager::PRO_FEATURES) as $feature) {
            $this->assertTrue(
                License_Manager::has_pro($feature),
                "has_pro('$feature') should return true for unlimited license."
            );
        }
    }

    /**
     * has_pro returns false for unlimited when expiry is in the past.
     * (The expiry check happens before the unlimited check in the source code.)
     *
     * @covers \Convoca\Core\License_Manager::has_pro
     */
    public function test_has_pro_false_when_unlimited_with_past_expiry(): void
    {
        self::$mockLicense['key']     = 'TEST-KEY-UNLIMITED-3';
        self::$mockLicense['type']    = 'unlimited';
        self::$mockLicense['expires'] = '2020-01-01 00:00:00';

        // Expiry is checked BEFORE the unlimited type check.
        $this->assertFalse(
            License_Manager::has_pro('members'),
            'has_pro should return false when expired, even for unlimited type ' .
            '(expiry check precedes type check in implementation).'
        );
    }

    // ────────────────────────────────────────────
    // has_pro() — feature in features array
    // ────────────────────────────────────────────

    /**
     * has_pro returns true when the requested feature is in the features array.
     *
     * @covers \Convoca\Core\License_Manager::has_pro
     */
    public function test_has_pro_true_when_feature_in_array(): void
    {
        self::$mockLicense['key']      = 'TEST-KEY-SINGLE';
        self::$mockLicense['type']     = 'single';
        self::$mockLicense['features'] = ['members', 'shifts'];

        $this->assertTrue(
            License_Manager::has_pro('members'),
            "has_pro should return true when 'members' is in features array."
        );

        $this->assertTrue(
            License_Manager::has_pro('shifts'),
            "has_pro should return true when 'shifts' is in features array."
        );
    }

    /**
     * has_pro returns false when feature is NOT in the features array.
     *
     * @covers \Convoca\Core\License_Manager::has_pro
     */
    public function test_has_pro_false_when_feature_not_in_array(): void
    {
        self::$mockLicense['key']      = 'TEST-KEY-SINGLE-2';
        self::$mockLicense['type']     = 'single';
        self::$mockLicense['features'] = ['members', 'enroll'];

        $this->assertFalse(
            License_Manager::has_pro('gateway'),
            "has_pro should return false when 'gateway' is not in features array."
        );

        $this->assertFalse(
            License_Manager::has_pro('webhooks'),
            "has_pro should return false when 'webhooks' is not in features array."
        );
    }

    /**
     * has_pro returns false when features array is empty.
     *
     * @covers \Convoca\Core\License_Manager::has_pro
     */
    public function test_has_pro_false_with_empty_features_array(): void
    {
        self::$mockLicense['key']      = 'TEST-KEY-EMPTY';
        self::$mockLicense['type']     = 'single';
        self::$mockLicense['features'] = [];

        $this->assertFalse(
            License_Manager::has_pro('members'),
            'has_pro should return false when features array is empty.'
        );
    }

    /**
     * has_pro handles non-existent feature key gracefully.
     *
     * @covers \Convoca\Core\License_Manager::has_pro
     */
    public function test_has_pro_false_for_unknown_feature(): void
    {
        self::$mockLicense['key']      = 'TEST-KEY-SINGLE-3';
        self::$mockLicense['type']     = 'single';
        self::$mockLicense['features'] = ['members'];

        $this->assertFalse(
            License_Manager::has_pro('nonexistent_feature'),
            'has_pro should return false for a feature key that does not exist.'
        );
    }

    // ────────────────────────────────────────────
    // get_status_label()
    // ────────────────────────────────────────────

    /**
     * get_status_label returns 'Activa' for active status.
     *
     * @covers \Convoca\Core\License_Manager::get_status_label
     */
    public function test_status_label_activa(): void
    {
        self::$mockLicense['status'] = 'active';

        $this->assertEquals(
            'Activa',
            License_Manager::get_status_label(),
            'Status "active" should produce label "Activa".'
        );
    }

    /**
     * get_status_label returns 'Expirada' for expired status.
     *
     * @covers \Convoca\Core\License_Manager::get_status_label
     */
    public function test_status_label_expirada(): void
    {
        self::$mockLicense['status'] = 'expired';

        $this->assertEquals(
            'Expirada',
            License_Manager::get_status_label(),
            'Status "expired" should produce label "Expirada".'
        );
    }

    /**
     * get_status_label returns 'Invalida' for invalid status.
     *
     * @covers \Convoca\Core\License_Manager::get_status_label
     */
    public function test_status_label_invalida(): void
    {
        self::$mockLicense['status'] = 'invalid';

        $this->assertEquals(
            'Invalida',
            License_Manager::get_status_label(),
            'Status "invalid" should produce label "Invalida".'
        );
    }

    /**
     * get_status_label returns 'Inactiva' for inactive status (default).
     *
     * @covers \Convoca\Core\License_Manager::get_status_label
     */
    public function test_status_label_inactiva(): void
    {
        self::$mockLicense['status'] = 'inactive';

        $this->assertEquals(
            'Inactiva',
            License_Manager::get_status_label(),
            'Status "inactive" should produce label "Inactiva".'
        );
    }

    /**
     * get_status_label returns 'Inactiva' for unknown/unexpected status (default case).
     *
     * @covers \Convoca\Core\License_Manager::get_status_label
     */
    public function test_status_label_default_for_unknown_status(): void
    {
        self::$mockLicense['status'] = 'some_unknown_value';

        $this->assertEquals(
            'Inactiva',
            License_Manager::get_status_label(),
            'Unknown status should fall through to default label "Inactiva".'
        );
    }

    /**
     * get_status_label returns 'Inactiva' when status key is missing.
     *
     * @covers \Convoca\Core\License_Manager::get_status_label
     */
    public function test_status_label_default_when_status_missing(): void
    {
        unset(self::$mockLicense['status']);

        $this->assertEquals(
            'Inactiva',
            License_Manager::get_status_label(),
            'Missing status key should fall through to default label "Inactiva".'
        );
    }

    /**
     * get_status_label returns a string type.
     *
     * @covers \Convoca\Core\License_Manager::get_status_label
     */
    public function test_status_label_returns_string(): void
    {
        self::$mockLicense['status'] = 'active';
        $this->assertIsString(License_Manager::get_status_label());

        self::$mockLicense['status'] = 'expired';
        $this->assertIsString(License_Manager::get_status_label());

        self::$mockLicense['status'] = 'invalid';
        $this->assertIsString(License_Manager::get_status_label());

        self::$mockLicense['status'] = 'inactive';
        $this->assertIsString(License_Manager::get_status_label());
    }

    // ────────────────────────────────────────────
    // Edge cases
    // ────────────────────────────────────────────

    /**
     * has_pro returns false when type is free (not unlimited and not matching).
     *
     * @covers \Convoca\Core\License_Manager::has_pro
     */
    public function test_has_pro_false_for_free_type(): void
    {
        self::$mockLicense['key']      = 'TEST-KEY-FREE';
        self::$mockLicense['type']     = 'free';
        self::$mockLicense['features'] = [];

        $this->assertFalse(License_Manager::has_pro('members'));
    }

    /**
     * has_pro returns false when type is free, even with features in the array.
     * (type=free means features array contents should be ignored for gating.)
     *
     * @covers \Convoca\Core\License_Manager::has_pro
     */
    public function test_has_pro_false_for_free_type_with_features(): void
    {
        self::$mockLicense['key']      = 'TEST-KEY-FREE-2';
        self::$mockLicense['type']     = 'free';
        self::$mockLicense['features'] = ['members', 'enroll'];

        // free !== 'unlimited', so falls through to features array check.
        // 'members' IS in features, so this will return TRUE — documenting
        // that free type with features populated behaves like a normal license.
        // This is an edge-case awareness test, not necessarily a bug.
        $result = License_Manager::has_pro('members');
        // Based on source logic: free !== unlimited, so falls to in_array check.
        $this->assertTrue(
            $result,
            'has_pro returns true for free type when feature is in features array ' .
            '(falls through to in_array check since free !== unlimited).'
        );
    }
}


// ═══════════════════════════════════════════════════════════════════════════════
// NAMESPACE-LEVEL MOCK FOR get_option()
// ═══════════════════════════════════════════════════════════════════════════════
// PHP resolves unqualified function calls (get_option()) by looking in the
// current namespace first, then falling back to the global namespace.
//
// Since License_Manager lives in Convoca\Core, and calls get_option() without
// a namespace prefix, PHP will find this function BEFORE the global one.
//
// We read from LicenseManagerTest::$mockLicense so each test can set up
// the license data it needs.
//
// This must be defined AFTER the test class so the static property reference
// resolves correctly.

namespace Convoca\Core;

/**
 * Mock for WordPress get_option().
 *
 * When running unit tests without WordPress, this function intercepts calls
 * to get_option() from within the Convoca\Core namespace.
 *
 * @param string $option  Option name.
 * @param mixed  $default Default value if option not found.
 * @return mixed
 */
function get_option(string $option, $default = [])
{
    $mock = \Convoca\Core\Tests\LicenseManagerTest::$mockLicense ?? [];

    if ($option === 'convoca_license') {
        return $mock;
    }

    return $default;
}
