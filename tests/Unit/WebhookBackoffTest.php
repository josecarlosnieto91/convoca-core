<?php
/**
 * Unit tests for Convoca Core webhook retry backoff.
 *
 * @package       Convoca\Core\Tests
 *
 * @coversDefaultClass \Convoca\Core\Webhook_Manager
 */

namespace Convoca\Core\Tests;

use Convoca\Core\Webhook_Manager;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the webhook retry backoff calculation (standalone, no DB).
 *
 * @covers ::get_backoff
 * @covers ::get_retry_delay
 */
class WebhookBackoffTest extends TestCase {

	/**
	 * Default backoff schedule is [60, 120, 240].
	 *
	 * @covers \Convoca\Core\Webhook_Manager::get_backoff
	 */
	public function test_default_backoff_is_60_120_240(): void {
		$this->assertSame( array( 60, 120, 240 ), Webhook_Manager::get_backoff() );
	}

	/**
	 * First failed attempt schedules the next retry at 60s.
	 *
	 * @covers \Convoca\Core\Webhook_Manager::get_retry_delay
	 */
	public function test_retry_delay_after_first_attempt(): void {
		$this->assertSame( 60, Webhook_Manager::get_retry_delay( 1 ) );
	}

	/**
	 * Second failed attempt schedules the next retry at 120s.
	 *
	 * @covers \Convoca\Core\Webhook_Manager::get_retry_delay
	 */
	public function test_retry_delay_after_second_attempt(): void {
		$this->assertSame( 120, Webhook_Manager::get_retry_delay( 2 ) );
	}

	/**
	 * Third attempt is the last one: no further retry is scheduled.
	 *
	 * @covers \Convoca\Core\Webhook_Manager::get_retry_delay
	 */
	public function test_retry_delay_exhausted_after_max_retries(): void {
		$this->assertNull( Webhook_Manager::get_retry_delay( 3 ) );
		$this->assertNull( Webhook_Manager::get_retry_delay( 4 ) );
	}

	/**
	 * Custom backoff schedules map 1:1 by failed attempt index.
	 *
	 * @covers \Convoca\Core\Webhook_Manager::get_retry_delay
	 */
	public function test_retry_delay_with_custom_backoff(): void {
		$backoff = array( 10, 20, 30 );

		$this->assertSame( 10, Webhook_Manager::get_retry_delay( 1, $backoff ) );
		$this->assertSame( 20, Webhook_Manager::get_retry_delay( 2, $backoff ) );
		$this->assertNull( Webhook_Manager::get_retry_delay( 3, $backoff ) );
	}

	/**
	 * A shorter-than-expected schedule repeats its last delay.
	 *
	 * @covers \Convoca\Core\Webhook_Manager::get_retry_delay
	 */
	public function test_retry_delay_short_backoff_repeats_last(): void {
		$this->assertSame( 30, Webhook_Manager::get_retry_delay( 2, array( 30 ) ) );
	}

	/**
	 * An empty explicit schedule yields no available delay.
	 *
	 * @covers \Convoca\Core\Webhook_Manager::get_retry_delay
	 */
	public function test_retry_delay_empty_backoff_is_null(): void {
		$this->assertNull( Webhook_Manager::get_retry_delay( 1, array() ) );
	}
}
