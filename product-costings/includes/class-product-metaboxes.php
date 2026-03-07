<?php
/**
 * Registers and renders metaboxes on the Products CPT edit screen.
 * - Formula Ingredients repeater
 * - Product costing fields
 * - Method (WYSIWYG)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PC_Product_Metaboxes {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'add_meta_boxes', array( $this, 'register_metaboxes' ) );
        add_action( 'save_post_products', array( $this, 'save_meta' ), 10, 2 );
    }

    public function register_metaboxes() {
        add_meta_box(
            'pc_formula_ingredients',
            __( 'Formula Ingredients', 'product-costings' ),
            array( $this, 'render_formula_metabox' ),
            'products',
            'normal',
            'high'
        );

        add_meta_box(
            'pc_product_costs',
            __( 'Product Costing', 'product-costings' ),
            array( $this, 'render_costing_metabox' ),
            'products',
            'normal',
            'default'
        );

        add_meta_box(
            'pc_product_method',
            __( 'Method', 'product-costings' ),
            array( $this, 'render_method_metabox' ),
            'products',
            'normal',
            'default'
        );
    }

    /* ───────────────────────────────────────────────
     * Formula Ingredients Repeater
     * ─────────────────────────────────────────────── */

    public function render_formula_metabox( $post ) {
        wp_nonce_field( 'pc_save_formula', 'pc_formula_nonce' );

        $rows = get_post_meta( $post->ID, '_pc_formula_rows', true );
        if ( ! is_array( $rows ) ) {
            $rows = array();
        }

        $functions = PC_Formula_Functions::get_functions();
        ?>
        <div id="pc-formula-wrap">
            <table id="pc-formula-table" class="widefat pc-formula-table">
                <thead>
                    <tr>
                        <th class="pc-col-sort">&nbsp;</th>
                        <th class="pc-col-phase"><?php esc_html_e( 'Phase', 'product-costings' ); ?></th>
                        <th class="pc-col-ww"><?php esc_html_e( '% w/w', 'product-costings' ); ?></th>
                        <th class="pc-col-trade"><?php esc_html_e( 'Trade Name', 'product-costings' ); ?></th>
                        <th class="pc-col-function"><?php esc_html_e( 'Function', 'product-costings' ); ?></th>
                        <th class="pc-col-ph"><?php esc_html_e( 'pH Range', 'product-costings' ); ?></th>
                        <th class="pc-col-price"><?php esc_html_e( 'Price/KG', 'product-costings' ); ?></th>
                        <th class="pc-col-moq"><?php esc_html_e( 'MOQ', 'product-costings' ); ?></th>
                        <th class="pc-col-actions">&nbsp;</th>
                    </tr>
                </thead>
                <tbody id="pc-formula-body">
                    <?php
                    if ( ! empty( $rows ) ) {
                        foreach ( $rows as $i => $row ) {
                            $this->render_row( $i, $row, $functions );
                        }
                    }
                    ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2" class="pc-total-label"><strong><?php esc_html_e( 'Total % w/w:', 'product-costings' ); ?></strong></td>
                        <td id="pc-total-ww"><strong>0.00</strong></td>
                        <td colspan="6"></td>
                    </tr>
                </tfoot>
            </table>

            <p style="margin-top:12px;">
                <button type="button" id="pc-add-row" class="button button-primary">
                    <?php esc_html_e( '+ Add Ingredient', 'product-costings' ); ?>
                </button>
            </p>
        </div>

        <!-- Row template (hidden) -->
        <script type="text/html" id="tmpl-pc-row">
            <tr class="pc-row" data-index="{{data.i}}">
                <td class="pc-col-sort pc-drag-handle">&#9776;</td>
                <td class="pc-col-phase">
                    <input type="text" name="pc_rows[{{data.i}}][phase]" value="" placeholder="A" class="pc-field-phase" maxlength="5">
                </td>
                <td class="pc-col-ww">
                    <input type="number" name="pc_rows[{{data.i}}][percent_w_w]" value="" step="any" min="0" max="100" class="pc-field-ww" placeholder="0.00">
                </td>
                <td class="pc-col-trade">
                    <select name="pc_rows[{{data.i}}][trade_name_id]" class="pc-field-trade-name">
                        <option value=""><?php esc_html_e( '— Select —', 'product-costings' ); ?></option>
                    </select>
                </td>
                <td class="pc-col-function">
                    <select name="pc_rows[{{data.i}}][function]" class="pc-field-function">
                        <option value=""><?php esc_html_e( '— Select —', 'product-costings' ); ?></option>
                        <?php foreach ( $functions as $fn ) : ?>
                            <option value="<?php echo esc_attr( $fn ); ?>"><?php echo esc_html( $fn ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td class="pc-col-ph">
                    <input type="text" name="pc_rows[{{data.i}}][ph_range]" value="" class="pc-field-ph" readonly>
                </td>
                <td class="pc-col-price">
                    <input type="text" name="pc_rows[{{data.i}}][price_per_kg]" value="" class="pc-field-price" readonly>
                </td>
                <td class="pc-col-moq">
                    <input type="text" name="pc_rows[{{data.i}}][moq]" value="" class="pc-field-moq" readonly>
                </td>
                <td class="pc-col-actions">
                    <button type="button" class="button pc-duplicate-row" title="<?php esc_attr_e( 'Duplicate', 'product-costings' ); ?>">&#x2398;</button>
                    <button type="button" class="button pc-remove-row" title="<?php esc_attr_e( 'Remove', 'product-costings' ); ?>">&#x1F5D1;</button>
                </td>
            </tr>
        </script>
        <?php
    }

    /**
     * Render a single repeater row.
     */
    private function render_row( $i, $row, $functions ) {
        $phase        = isset( $row['phase'] ) ? $row['phase'] : '';
        $ww           = isset( $row['percent_w_w'] ) ? $row['percent_w_w'] : '';
        $trade_id     = isset( $row['trade_name_id'] ) ? (int) $row['trade_name_id'] : 0;
        $fn_val       = isset( $row['function'] ) ? $row['function'] : '';
        $ph           = isset( $row['ph_range'] ) ? $row['ph_range'] : '';
        $price        = isset( $row['price_per_kg'] ) ? $row['price_per_kg'] : '';
        $moq          = isset( $row['moq'] ) ? $row['moq'] : '';
        $is_to_100    = isset( $row['is_to_100'] ) ? (bool) $row['is_to_100'] : false;
        ?>
        <tr class="pc-row <?php echo $is_to_100 ? 'pc-row-to100' : ''; ?>" data-index="<?php echo (int) $i; ?>">
            <td class="pc-col-sort pc-drag-handle">&#9776;</td>
            <td class="pc-col-phase">
                <input type="text" name="pc_rows[<?php echo (int) $i; ?>][phase]" value="<?php echo esc_attr( $phase ); ?>" placeholder="A" class="pc-field-phase" maxlength="5">
            </td>
            <td class="pc-col-ww">
                <input type="number" name="pc_rows[<?php echo (int) $i; ?>][percent_w_w]" value="<?php echo esc_attr( $ww ); ?>" step="any" min="0" max="100" class="pc-field-ww" placeholder="0.00" <?php echo $is_to_100 ? 'readonly' : ''; ?>>
                <?php if ( $is_to_100 ) : ?>
                    <input type="hidden" name="pc_rows[<?php echo (int) $i; ?>][is_to_100]" value="1">
                    <span class="pc-to100-badge"><?php esc_html_e( 'to 100%', 'product-costings' ); ?></span>
                <?php endif; ?>
            </td>
            <td class="pc-col-trade">
                <select name="pc_rows[<?php echo (int) $i; ?>][trade_name_id]" class="pc-field-trade-name">
                    <option value=""><?php esc_html_e( '— Select —', 'product-costings' ); ?></option>
                    <?php if ( $trade_id ) : ?>
                        <option value="<?php echo $trade_id; ?>" selected><?php echo esc_html( get_the_title( $trade_id ) ); ?></option>
                    <?php endif; ?>
                </select>
            </td>
            <td class="pc-col-function">
                <select name="pc_rows[<?php echo (int) $i; ?>][function]" class="pc-field-function">
                    <option value=""><?php esc_html_e( '— Select —', 'product-costings' ); ?></option>
                    <?php foreach ( $functions as $fn ) : ?>
                        <option value="<?php echo esc_attr( $fn ); ?>" <?php selected( $fn_val, $fn ); ?>><?php echo esc_html( $fn ); ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td class="pc-col-ph">
                <input type="text" name="pc_rows[<?php echo (int) $i; ?>][ph_range]" value="<?php echo esc_attr( $ph ); ?>" class="pc-field-ph" readonly>
            </td>
            <td class="pc-col-price">
                <input type="text" name="pc_rows[<?php echo (int) $i; ?>][price_per_kg]" value="<?php echo esc_attr( $price ); ?>" class="pc-field-price" readonly>
            </td>
            <td class="pc-col-moq">
                <input type="text" name="pc_rows[<?php echo (int) $i; ?>][moq]" value="<?php echo esc_attr( $moq ); ?>" class="pc-field-moq" readonly>
            </td>
            <td class="pc-col-actions">
                <?php if ( ! $is_to_100 ) : ?>
                    <button type="button" class="button pc-mark-to100" title="<?php esc_attr_e( 'Set as &quot;to 100%&quot; row', 'product-costings' ); ?>">&#x1F4A7;</button>
                <?php else : ?>
                    <button type="button" class="button pc-unmark-to100" title="<?php esc_attr_e( 'Remove &quot;to 100%&quot;', 'product-costings' ); ?>">&#x2716;</button>
                <?php endif; ?>
                <button type="button" class="button pc-duplicate-row" title="<?php esc_attr_e( 'Duplicate', 'product-costings' ); ?>">&#x2398;</button>
                <button type="button" class="button pc-remove-row" title="<?php esc_attr_e( 'Remove', 'product-costings' ); ?>">&#x1F5D1;</button>
            </td>
        </tr>
        <?php
    }

    /* ───────────────────────────────────────────────
     * Product Costing Meta Fields
     * ─────────────────────────────────────────────── */

    public function render_costing_metabox( $post ) {
        wp_nonce_field( 'pc_save_costing', 'pc_costing_nonce' );

        $fields = array(
            'facility_running_costs' => __( 'Facility Running Costs (£)', 'product-costings' ),
            'labour_manufacturing'   => __( 'Labour Costs – Manufacturing (£)', 'product-costings' ),
            'labour_filling'         => __( 'Labour Costs – Filling (£)', 'product-costings' ),
            'batch_size'             => __( 'Batch Size (KG)', 'product-costings' ),
            'packaging_unit_cost'    => __( 'Packaging Unit Cost (£)', 'product-costings' ),
            'packaging_size'         => __( 'Packaging Size (g)', 'product-costings' ),
        );
        ?>
        <table class="form-table pc-costing-table">
            <?php foreach ( $fields as $key => $label ) : ?>
                <tr>
                    <th><label for="pc_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
                    <td>
                        <input
                            type="number"
                            id="pc_<?php echo esc_attr( $key ); ?>"
                            name="pc_costing[<?php echo esc_attr( $key ); ?>]"
                            value="<?php echo esc_attr( get_post_meta( $post->ID, '_pc_' . $key, true ) ); ?>"
                            step="any"
                            min="0"
                            class="regular-text pc-costing-field"
                        >
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>

        <div id="pc-cost-summary" class="pc-cost-summary">
            <h3><?php esc_html_e( 'Cost Summary (calculated)', 'product-costings' ); ?></h3>
            <table class="widefat striped">
                <tr>
                    <th><?php esc_html_e( 'Raw Material Cost per KG', 'product-costings' ); ?></th>
                    <td id="pc-raw-cost-kg">—</td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Raw Material Cost per Batch', 'product-costings' ); ?></th>
                    <td id="pc-raw-cost-batch">—</td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Cost per Unit', 'product-costings' ); ?></th>
                    <td id="pc-cost-unit">—</td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Units per Batch', 'product-costings' ); ?></th>
                    <td id="pc-units-batch">—</td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Total Batch Cost', 'product-costings' ); ?></th>
                    <td id="pc-batch-cost">—</td>
                </tr>
            </table>
        </div>
        <?php
    }

    /* ───────────────────────────────────────────────
     * Method WYSIWYG
     * ─────────────────────────────────────────────── */

    public function render_method_metabox( $post ) {
        $content = get_post_meta( $post->ID, '_pc_method', true );
        wp_editor( $content, 'pc_method_editor', array(
            'textarea_name' => 'pc_method',
            'textarea_rows' => 10,
            'media_buttons' => true,
        ) );
    }

    /* ───────────────────────────────────────────────
     * Save
     * ─────────────────────────────────────────────── */

    public function save_meta( $post_id, $post ) {
        // Skip autosaves and revisions.
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( wp_is_post_revision( $post_id ) ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // --- Formula rows ---
        if ( isset( $_POST['pc_formula_nonce'] ) && wp_verify_nonce( $_POST['pc_formula_nonce'], 'pc_save_formula' ) ) {
            $raw_rows = isset( $_POST['pc_rows'] ) ? $_POST['pc_rows'] : array();
            $clean    = array();

            if ( is_array( $raw_rows ) ) {
                foreach ( $raw_rows as $row ) {
                    $clean[] = array(
                        'phase'        => sanitize_text_field( $row['phase'] ?? '' ),
                        'percent_w_w'  => floatval( $row['percent_w_w'] ?? 0 ),
                        'trade_name_id'=> absint( $row['trade_name_id'] ?? 0 ),
                        'function'     => sanitize_text_field( $row['function'] ?? '' ),
                        'ph_range'     => sanitize_text_field( $row['ph_range'] ?? '' ),
                        'price_per_kg' => sanitize_text_field( $row['price_per_kg'] ?? '' ),
                        'moq'          => sanitize_text_field( $row['moq'] ?? '' ),
                        'is_to_100'    => ! empty( $row['is_to_100'] ) ? true : false,
                    );
                }
            }

            update_post_meta( $post_id, '_pc_formula_rows', $clean );
        }

        // --- Costing fields ---
        if ( isset( $_POST['pc_costing_nonce'] ) && wp_verify_nonce( $_POST['pc_costing_nonce'], 'pc_save_costing' ) ) {
            $costing = isset( $_POST['pc_costing'] ) ? (array) $_POST['pc_costing'] : array();
            $allowed = array(
                'facility_running_costs',
                'labour_manufacturing',
                'labour_filling',
                'batch_size',
                'packaging_unit_cost',
                'packaging_size',
            );
            foreach ( $allowed as $key ) {
                $value = isset( $costing[ $key ] ) ? floatval( $costing[ $key ] ) : '';
                update_post_meta( $post_id, '_pc_' . $key, $value );
            }
        }

        // --- Method ---
        if ( isset( $_POST['pc_method'] ) ) {
            update_post_meta( $post_id, '_pc_method', wp_kses_post( $_POST['pc_method'] ) );
        }
    }
}
