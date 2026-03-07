<?php
/**
 * AJAX handlers for:
 * - Searching Trade Names (Select2 / autocomplete)
 * - Fetching Trade Name meta (pH, price_per_kg, MOQ)
 * - Recalculating the "to 100%" row value
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PC_Ajax_Handler {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'wp_ajax_pc_search_trade_names', array( $this, 'search_trade_names' ) );
        add_action( 'wp_ajax_pc_get_trade_name_meta', array( $this, 'get_trade_name_meta' ) );
        add_action( 'wp_ajax_pc_recalc_to100', array( $this, 'recalc_to100' ) );
    }

    /**
     * Search Trade Names CPT for the autocomplete dropdown.
     */
    public function search_trade_names() {
        check_ajax_referer( 'pc_nonce', 'nonce' );

        $search = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';

        $args = array(
            'post_type'      => 'trade-names',
            'post_status'    => 'publish',
            'posts_per_page' => 30,
            's'              => $search,
            'orderby'        => 'title',
            'order'          => 'ASC',
        );

        $query   = new WP_Query( $args );
        $results = array();

        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $results[] = array(
                    'id'   => get_the_ID(),
                    'text' => get_the_title(),
                );
            }
            wp_reset_postdata();
        }

        wp_send_json_success( $results );
    }

    /**
     * Return meta fields for a given Trade Name post.
     */
    public function get_trade_name_meta() {
        check_ajax_referer( 'pc_nonce', 'nonce' );

        $post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;

        if ( ! $post_id || 'trade-names' !== get_post_type( $post_id ) ) {
            wp_send_json_error( 'Invalid trade name.' );
        }

        // Try common meta key patterns for these fields.
        $ph_range     = $this->get_meta_value( $post_id, array( 'ph_range', 'pH_range', 'ph', '_ph_range' ) );
        $price_per_kg = $this->get_meta_value( $post_id, array( 'price_per_kg', '_price_per_kg', 'price_kg' ) );
        $moq          = $this->get_meta_value( $post_id, array( 'moq', '_moq', 'MOQ' ) );
        $function1    = $this->get_meta_value( $post_id, array( 'function1', '_function1', 'function' ) );

        wp_send_json_success( array(
            'ph_range'     => $ph_range,
            'price_per_kg' => $price_per_kg,
            'moq'          => $moq,
            'function1'    => $function1,
            'title'        => get_the_title( $post_id ),
        ) );
    }

    /**
     * Try multiple meta key variants and return the first non-empty value.
     */
    private function get_meta_value( $post_id, $keys ) {
        foreach ( $keys as $key ) {
            $val = get_post_meta( $post_id, $key, true );
            if ( '' !== $val && false !== $val ) {
                return $val;
            }
        }
        return '';
    }

    /**
     * Recalculate the "to 100%" value.
     * Receives the sum of all other rows' %w/w values, returns 100 - sum.
     */
    public function recalc_to100() {
        check_ajax_referer( 'pc_nonce', 'nonce' );

        $other_sum = isset( $_POST['other_sum'] ) ? floatval( $_POST['other_sum'] ) : 0;
        $to100     = max( 0, 100 - $other_sum );

        wp_send_json_success( array(
            'to100' => round( $to100, 4 ),
        ) );
    }
}
