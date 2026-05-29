<?php
/**
 * ActivityFactory — creates demo activities via wp_posts for integration tests.
 *
 * @package Convoca\Core\Tests\Factories
 */

namespace Convoca\Core\Tests\Factories;

/**
 * Factory for creating test activity posts.
 *
 * @phpstan-immutable
 */
class ActivityFactory
{
    /**
     * Default activity meta.
     *
     * @var array<string, mixed>
     */
    private const DEFAULT_META = [
        '_conv_fecha_inicio'      => '',
        '_conv_fecha_fin'         => '',
        '_conv_plazas_totales'    => 30,
        '_conv_plazas_disponibles' => 30,
        '_conv_precio_socio'      => 0,
        '_conv_ubicacion'         => 'Test Location',
        '_conv_requiere_pago'     => 0,
        '_conv_actividad_lugg'    => 0,
    ];

    /**
     * Create a test activity.
     *
     * @param array<string, mixed> $overrides Post fields and meta overrides.
     * @return int|\WP_Error Activity post ID.
     */
    public static function create(array $overrides = []): int|\WP_Error
    {
        $meta_overrides = [];
        foreach ($overrides as $key => $value) {
            if (str_starts_with((string) $key, '_conv_')) {
                $meta_overrides[$key] = $value;
                unset($overrides[$key]);
            }
        }

        $post_data = array_merge([
            'post_type'    => 'actividad',
            'post_title'   => 'Test Activity ' . wp_generate_uuid4(),
            'post_status'  => 'publish',
            'post_content' => '',
            'post_excerpt' => 'Test activity excerpt',
        ], $overrides);

        $post_id = wp_insert_post($post_data, true);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        $meta = array_merge(self::DEFAULT_META, $meta_overrides);

        foreach ($meta as $key => $value) {
            update_post_meta($post_id, $key, $value);
        }

        return $post_id;
    }

    /**
     * Create a full activity with dates and pricing.
     *
     * @param string $start_date   Start date (Y-m-d H:i:s).
     * @param int    $total_slots  Total capacity.
     * @param float  $member_price Price for members.
     * @return int Activity post ID.
     */
    public static function create_full(
        string $start_date = '+7 days',
        int $total_slots = 30,
        float $member_price = 10.0
    ): int {
        $start = date('Y-m-d H:i:s', strtotime($start_date));
        $end   = date('Y-m-d H:i:s', strtotime($start_date . ' + 3 hours'));

        return self::create([
            'post_title'               => 'Full Test Activity',
            '_conv_fecha_inicio'        => $start,
            '_conv_fecha_fin'           => $end,
            '_conv_plazas_totales'      => $total_slots,
            '_conv_plazas_disponibles'  => $total_slots,
            '_conv_precio_socio'        => $member_price,
            '_conv_ubicacion'           => 'Test Venue',
            '_conv_requiere_pago'       => 1,
        ]);
    }

    /**
     * Create a past activity (for testing enrollment rejection).
     *
     * @return int Activity post ID.
     */
    public static function create_past(): int
    {
        $start = date('Y-m-d H:i:s', strtotime('-7 days'));

        return self::create([
            'post_title'        => 'Past Test Activity',
            '_conv_fecha_inicio' => $start,
            '_conv_fecha_fin'    => date('Y-m-d H:i:s', strtotime('-7 days + 3 hours')),
        ]);
    }

    /**
     * Create a full activity (no remaining slots).
     *
     * @return int Activity post ID.
     */
    public static function create_full_activity(): int
    {
        return self::create([
            'post_title'               => 'Full Capacity Activity',
            '_conv_plazas_totales'      => 0,
            '_conv_plazas_disponibles'  => 0,
        ]);
    }

    /**
     * Delete a test activity by ID.
     *
     * @param int $post_id Activity post ID.
     * @return bool True on success.
     */
    public static function delete(int $post_id): bool
    {
        return (bool) wp_delete_post($post_id, true);
    }
}
