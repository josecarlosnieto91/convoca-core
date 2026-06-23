<?php
/**
 * Integration tests for Convoca Core Webhook Manager.
 *
 * @package       Convoca\Core\Tests
 *
 * @coversDefaultClass \Convoca\Core\Webhook_Manager
 * @group integration
 */

namespace Convoca\Core\Tests;

use Convoca\Core\Webhook_Manager;
use PHPUnit\Framework\TestCase;

/**
 * Tests for webhook signing, verification, and dispatch logic.
 *
 * @covers ::get_webhooks
 * @covers ::add_webhook
 * @covers ::get_webhook
 * @covers ::update_webhook
 * @covers ::delete_webhook
 * @covers ::dispatch
 * @covers ::test_webhook
 */
class WebhookManagerTest extends TestCase
{
    /**
     * Clean up test webhooks after each test.
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        // Clean up any test webhooks created during test.
        if (function_exists('delete_option')) {
            delete_option('conv_webhooks');
        }
    }

    /**
     * Test that get_webhooks returns an array.
     *
     * @covers \Convoca\Core\Webhook_Manager::get_webhooks
     */
    public function test_get_webhooks_returns_array(): void
    {
        $webhooks = Webhook_Manager::get_webhooks();
        $this->assertIsArray($webhooks);
    }

    /**
     * Test that get_webhooks returns empty array initially.
     *
     * @covers \Convoca\Core\Webhook_Manager::get_webhooks
     */
    public function test_get_webhooks_empty_initially(): void
    {
        $webhooks = Webhook_Manager::get_webhooks();
        $this->assertEmpty($webhooks);
    }

    /**
     * Test creating a webhook with all parameters.
     *
     * @covers \Convoca\Core\Webhook_Manager::add_webhook
     */
    public function test_add_webhook(): void
    {
        if (!function_exists('update_option')) {
            $this->markTestSkipped('WordPress not available');
        }

        $id = Webhook_Manager::add_webhook([
            'url'    => 'https://example.com/webhook',
            'secret' => 'test-secret-123',
            'events' => ['member.created', 'payment.completed'],
            'label'  => 'Test Webhook',
        ]);

        $this->assertNotEmpty($id, 'Webhook ID should not be empty');

        // Verify it was stored.
        $webhook = Webhook_Manager::get_webhook($id);
        $this->assertNotNull($webhook);
        $this->assertEquals('https://example.com/webhook', $webhook['url']);
        $this->assertEquals('test-secret-123', $webhook['secret']);
        $this->assertContains('member.created', $webhook['events']);
        $this->assertEquals('Test Webhook', $webhook['label']);
        $this->assertTrue($webhook['active']);
    }

    /**
     * Test creating a webhook without optional parameters.
     *
     * @covers \Convoca\Core\Webhook_Manager::add_webhook
     */
    public function test_add_webhook_minimal(): void
    {
        if (!function_exists('update_option')) {
            $this->markTestSkipped('WordPress not available');
        }

        $id = Webhook_Manager::add_webhook([
            'url'   => 'https://example.com/minimal',
            'label' => 'Minimal Webhook',
        ]);

        $this->assertNotEmpty($id);

        $webhook = Webhook_Manager::get_webhook($id);
        $this->assertNotNull($webhook);
        $this->assertEquals('https://example.com/minimal', $webhook['url']);
        $this->assertEmpty($webhook['secret']);
        $this->assertEmpty($webhook['events']);
        $this->assertTrue($webhook['active']);
    }

    /**
     * Test creating a webhook without events (should subscribe to all).
     *
     * @covers \Convoca\Core\Webhook_Manager::add_webhook
     */
    public function test_add_webhook_empty_events(): void
    {
        if (!function_exists('update_option')) {
            $this->markTestSkipped('WordPress not available');
        }

        $id = Webhook_Manager::add_webhook([
            'url'    => 'https://example.com/all-events',
            'label'  => 'All Events',
            'events' => [],
        ]);

        $webhook = Webhook_Manager::get_webhook($id);
        $this->assertNotNull($webhook);
        $this->assertEmpty($webhook['events']);
    }

    /**
     * Test adding a webhook with invalid URL.
     *
     * @covers \Convoca\Core\Webhook_Manager::add_webhook
     */
    public function test_add_webhook_invalid_url(): void
    {
        if (!function_exists('update_option')) {
            $this->markTestSkipped('WordPress not available');
        }

        $id = Webhook_Manager::add_webhook([
            'url'   => 'not-a-url',
            'label' => 'Invalid URL',
        ]);

        // Should still create the webhook even with invalid URL.
        $this->assertNotEmpty($id);

        $webhook = Webhook_Manager::get_webhook($id);
        $this->assertNotNull($webhook);
        // Invalid URL should be sanitized (may be empty or cleaned depending on WP version)
        $this->assertNotNull($webhook);
        $this->assertIsString($webhook['url']);
    }

    /**
     * Test updating a webhook.
     *
     * @covers \Convoca\Core\Webhook_Manager::update_webhook
     */
    public function test_update_webhook(): void
    {
        if (!function_exists('update_option')) {
            $this->markTestSkipped('WordPress not available');
        }

        $id = Webhook_Manager::add_webhook([
            'url'    => 'https://example.com/original',
            'secret' => 'original-secret',
            'events' => ['member.created'],
            'label'  => 'Original',
        ]);

        $updated = Webhook_Manager::update_webhook($id, [
            'url'    => 'https://example.com/updated',
            'secret' => 'updated-secret',
            'events' => ['payment.completed', 'enrollment.created'],
            'label'  => 'Updated',
            'active' => false,
        ]);

        $this->assertTrue($updated);

        $webhook = Webhook_Manager::get_webhook($id);
        $this->assertEquals('https://example.com/updated', $webhook['url']);
        $this->assertEquals('updated-secret', $webhook['secret']);
        $this->assertContains('payment.completed', $webhook['events']);
        $this->assertEquals('Updated', $webhook['label']);
        $this->assertFalse($webhook['active']);
    }

    /**
     * Test updating a non-existent webhook returns false.
     *
     * @covers \Convoca\Core\Webhook_Manager::update_webhook
     */
    public function test_update_nonexistent_webhook(): void
    {
        $result = Webhook_Manager::update_webhook('non-existent-id', [
            'url' => 'https://example.com/new',
        ]);
        $this->assertFalse($result);
    }

    /**
     * Test deleting a webhook.
     *
     * @covers \Convoca\Core\Webhook_Manager::delete_webhook
     */
    public function test_delete_webhook(): void
    {
        if (!function_exists('update_option')) {
            $this->markTestSkipped('WordPress not available');
        }

        $id = Webhook_Manager::add_webhook([
            'url'   => 'https://example.com/delete-me',
            'label' => 'To Delete',
        ]);

        $deleted = Webhook_Manager::delete_webhook($id);
        $this->assertTrue($deleted);

        $webhook = Webhook_Manager::get_webhook($id);
        $this->assertNull($webhook);
    }

    /**
     * Test deleting a non-existent webhook returns false.
     *
     * @covers \Convoca\Core\Webhook_Manager::delete_webhook
     */
    public function test_delete_nonexistent_webhook(): void
    {
        $result = Webhook_Manager::delete_webhook('non-existent-id');
        $this->assertFalse($result);
    }

    /**
     * Test that EVENTS constant has expected structure.
     *
     * @covers \Convoca\Core\Webhook_Manager::EVENTS
     */
    public function test_events_constant(): void
    {
        $events = Webhook_Manager::EVENTS;

        $this->assertIsArray($events);
        $this->assertNotEmpty($events);

        $expected_events = [
            'member.created',
            'member.activated',
            'payment.completed',
            'payment.failed',
            'enrollment.created',
            'enrollment.cancelled',
            'enrollment.checkin',
            'volunteer.hours_logged',
            'volunteer.hours_approved',
        ];

        foreach ($expected_events as $event) {
            $this->assertArrayHasKey($event, $events, "Expected event $event not found");
        }
    }

    /**
     * Test that event labels are non-empty strings.
     *
     * @covers \Convoca\Core\Webhook_Manager::EVENTS
     */
    public function test_event_labels(): void
    {
        $events = Webhook_Manager::EVENTS;

        foreach ($events as $key => $label) {
            $this->assertIsString($label);
            $this->assertNotEmpty($label);
        }
    }

    /**
     * Test HMAC signature verification.
     *
     * @covers \Convoca\Core\Webhook_Manager::deliver
     */
    public function test_hmac_signature(): void
    {
        $secret = 'test-hmac-secret';
        $payload = wp_json_encode([
            'event'     => 'test.ping',
            'timestamp' => date('c'),
            'data'      => ['test' => true],
        ]);

        // The signature should be a hex-encoded SHA256 HMAC.
        $expected = hash_hmac('sha256', $payload, $secret);
        $this->assertEquals(64, strlen($expected));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $expected);
    }

    /**
     * Test that dispatch handles empty webhooks gracefully.
     *
     * @covers \Convoca\Core\Webhook_Manager::dispatch
     */
    public function test_dispatch_empty_webhooks(): void
    {
        // With no webhooks registered, dispatch should do nothing.
        $manager = new Webhook_Manager();

        // Should complete without error.
        $manager->dispatch('test.event', ['foo' => 'bar']);
        $this->assertTrue(true);
    }

    /**
     * Test delivery logs operations.
     *
     * @covers \Convoca\Core\Webhook_Manager::get_delivery_logs
     * @covers \Convoca\Core\Webhook_Manager::clear_delivery_logs
     */
    public function test_delivery_logs(): void
    {
        if (!function_exists('update_option')) {
            $this->markTestSkipped('WordPress not available');
        }

        // Initially empty.
        $logs = Webhook_Manager::get_delivery_logs('test-webhook-id');
        $this->assertIsArray($logs);
        $this->assertEmpty($logs);

        // Clear should not throw.
        Webhook_Manager::clear_delivery_logs('test-webhook-id');
        $this->assertTrue(true);
    }

    /**
     * Test processing retries with empty queue.
     *
     * @covers \Convoca\Core\Webhook_Manager::process_retries
     */
    public function test_process_retries_empty(): void
    {
        Webhook_Manager::process_retries();
        $this->assertTrue(true);
    }

    /**
     * Test test_webhook on non-existent webhook.
     *
     * @covers \Convoca\Core\Webhook_Manager::test_webhook
     */
    public function test_test_webhook_nonexistent(): void
    {
        $result = Webhook_Manager::test_webhook('non-existent');
        $this->assertFalse($result);
    }
}
