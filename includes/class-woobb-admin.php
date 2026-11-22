<?php

defined('ABSPATH') || exit;

class WOOBB_Admin
{
    public function __construct()
    {
        add_action('admin_enqueue_scripts', [$this, 'scripts']);
        add_filter('woocommerce_product_data_tabs', array($this, 'add_admin_tab'), 20);
        add_action('woocommerce_product_data_panels', array($this, 'render_admin_panel'));
        add_action('woocommerce_process_product_meta', array($this, 'save_product_meta'), 10, 2);
        add_action('wp_ajax_woobb_save_rules', array($this, 'woobb_save_rules_callback'));
    }

    public function woobb_save_rules_callback()
    {
        if (empty($_POST['nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'woobb_admin_nonce')) {
            wp_send_json_error(array('message' => 'Invalid nonce'));
        }

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $raw     = isset($_POST['rules']) ? wp_unslash($_POST['rules']) : '';
        $rules   = json_decode($raw, true);

        if (! $post_id || ! is_array($rules)) {
            wp_send_json_error(array('message' => 'Invalid data'));
        }

        $clean = array();
        foreach ($rules as $rule) {
            $product_id  = intval($rule['product_id'] ?? 0);
            $product_qty = intval($rule['product_qty'] ?? 1);
            $free_items  = array();

            if (! empty($rule['free_items']) && is_array($rule['free_items'])) {
                foreach ($rule['free_items'] as $fi) {
                    $fid = intval($fi['id'] ?? 0);
                    $fq  = intval($fi['qty'] ?? 1);
                    if ($fid) {
                        $free_items[] = array(
                            'id'  => $fid,
                            'qty' => $fq,
                        );
                    }
                }
            }

            if ($product_id && $free_items) {
                $clean[] = array(
                    'product_id'  => $product_id,
                    'product_qty' => $product_qty,
                    'free_items'  => $free_items,
                );
            }
        }

        update_post_meta($post_id, '_woobb_bogo_rules', $clean);

        wp_send_json_success(array(
            'message' => 'Rules saved',
            'count'   => count($clean),
        ));
    }

    public function add_admin_tab($tabs)
    {
        $tabs['woobb_bogo'] = array(
            'label' => __('BOGO Rules', 'woobb'),
            'target' => 'woobb_bogo_panel',
            'class' => array('show_if_simple', 'show_if_variable'),
            'priority' => 50,
        );
        return $tabs;
    }

    public function render_admin_panel()
    {
        global $post;
        $post_id = $post->ID;
?>
        <div id="woobb_bogo_panel" class="panel woocommerce_options_panel">
            <div class="woobb_create_rule_header ">
                <span><?php _e('Create rules for current product', 'woobb'); ?></span>
                <button type="button" class="button button-primary" id="woobb_add_rule"><?php _e('Add Rule', 'woobb'); ?></button>
            </div>
            <div class="woobb_rule_container">
                <table id="woobb_bogo_rules">
                    <tbody>
                        <?php
                        $saved_rules = get_post_meta(get_the_ID(), '_woobb_bogo_rules', true);
                        $saved_rules = is_array($saved_rules) ? $saved_rules : array();

                        if (! empty($saved_rules)) :
                            foreach ($saved_rules as $rule_index => $rule) :
                                $unique_id   = 'rule_row_' . $rule_index . '_' . wp_generate_password(4, false);
                                $product_qty = intval($rule['product_qty'] ?? 1);
                                $free_items  = $rule['free_items'] ?? array();
                        ?>
                                <tr id="<?php echo esc_attr($unique_id); ?>" class="woobb-rule-wrapper">
                                    <td colspan="3">
                                        <div class="woobb-rule-header">
                                            <span>Buy</span>
                                            <input type="number"
                                                class="woobb_parent_product_qty"
                                                data-product_id="<?php echo esc_attr(get_the_ID()); ?>"
                                                value="<?php echo esc_attr($product_qty); ?>"
                                                min="1">
                                            <span>number of current product and get</span>
                                            <button type="button" class="button button-primary add_free_product" data-rule_id="<?php echo esc_attr($unique_id); ?>">Add free products +</button>
                                            <button type="button" class="button remove_parent_row" onclick="jQuery('#<?php echo $unique_id ?>').remove()" data-rule_id="<?php echo esc_attr($unique_id); ?>">Remove</button>
                                        </div>

                                        <table class="woobb_rule_table widefat striped">
                                            <thead>
                                                <tr>
                                                    <th>Free Product</th>
                                                    <th>Quantity</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody class="free-products-list">
                                                <?php
                                                if (! empty($free_items)) :
                                                    foreach ($free_items as $item) :
                                                        $free_id  = $item['id'] ?? '';
                                                        $free_qty = intval($item['qty'] ?? 1);

                                                        $simple_products = wc_get_products(array(
                                                            'status'  => 'publish',
                                                            'type'    => 'simple',
                                                            'limit'   => -1,
                                                            'orderby' => 'title',
                                                            'order'   => 'ASC',
                                                        ));

                                                        // Get all variable products
                                                        $variable_products = wc_get_products(array(
                                                            'status'  => 'publish',
                                                            'type'    => 'variable',
                                                            'limit'   => -1,
                                                            'orderby' => 'title',
                                                            'order'   => 'ASC',
                                                        ));
                                                ?>
                                                        <tr>
                                                            <td>
                                                                <select name="free_product[]" class="free_product">
                                                                    <option value=""><?php _e('Select a product...', 'woobb'); ?></option>
                                                                    <?php if (! empty($simple_products)) : ?>
                                                                        <optgroup label="Simple Products">
                                                                            <?php foreach ($simple_products as $product) : ?>
                                                                                <option value="<?php echo esc_attr($product->get_id()); ?>" <?php echo selected($free_id, $product->get_id(), false) ?>>
                                                                                    <?php echo esc_html($product->get_name()); ?>
                                                                                </option>
                                                                            <?php endforeach; ?>
                                                                        </optgroup>
                                                                    <?php endif; ?>

                                                                    <?php if (! empty($variable_products)) : ?>
                                                                        <optgroup label="Variable Products">
                                                                            <?php foreach ($variable_products as $variable) :
                                                                                $variation_id = $variable->get_id();
                                                                                $variation_title = $variable->get_name();
                                                                            ?>
                                                                                <option value="<?php echo esc_attr($variation_id); ?>" <?php echo selected($free_id, $variation_id, false) ?>>
                                                                                    <?php echo esc_html($variation_title); ?>
                                                                                </option>
                                                                            <?php endforeach; ?>
                                                                        </optgroup>
                                                                    <?php endif; ?>
                                                                </select>
                                                            </td>
                                                            <td>
                                                                <input type="number" class="free-product-qty woobb-input" min="1" value="<?php echo esc_attr($free_qty); ?>">
                                                            </td>
                                                            <td>
                                                                <button type="button" class="button woobb-danger-btn remove_free_row">Remove</button>
                                                            </td>
                                                        </tr>
                                                <?php
                                                    endforeach;
                                                endif;
                                                ?>
                                            </tbody>
                                        </table>
                                    </td>
                                </tr>
                        <?php
                            endforeach;
                        endif;
                        ?>
                    </tbody>
                </table>

                <div style="margin-top:20px;padding-top:12px;border-top:1px solid #eee;">
                    <button type="button" class="button button-primary" id="woobb_save_rules" style="display:flex;align-items:center;gap:6px;margin-left:auto;">
                        <?php _e('Save Rules', 'woobb'); ?>
                    </button>
                </div>
            </div>
        </div>
    <?php
    }

    public function save_product_meta($post_id) {}

    public function scripts()
    {
        global $post;
        $simple_products = wc_get_products(array(
            'status'  => 'publish',
            'type'    => 'simple',
            'limit'   => -1,
            'orderby' => 'title',
            'order'   => 'ASC',
        ));

        // Get all variable products
        $variable_products = wc_get_products(array(
            'status'  => 'publish',
            'type'    => 'variable',
            'limit'   => -1,
            'orderby' => 'title',
            'order'   => 'ASC',
        ));
        ob_start();
    ?>
        <select class="free_product" id="">
            <option value=""><?php _e('Select a product...', 'woobb'); ?></option>
            <?php if (! empty($simple_products)) : ?>
                <optgroup label="Simple Products">
                    <?php foreach ($simple_products as $product) : ?>
                        <option value="<?php echo esc_attr($product->get_id()); ?>">
                            <?php echo esc_html($product->get_name()); ?>
                        </option>
                    <?php endforeach; ?>
                </optgroup>
            <?php endif; ?>

            <?php if (! empty($variable_products)) : ?>
                <optgroup label="Variable Product Variations">
                    <?php foreach ($variable_products as $variable) :
                        $variation_id = $variable->get_id();
                        $variation_title = $variable->get_title();
                    ?>
                        <option value="<?php echo esc_attr($variation_id); ?>">
                            <?php echo esc_html($variation_title); ?>
                        </option>
                    <?php endforeach; ?>
                </optgroup>
            <?php endif; ?>
        </select>
<?php
        $products_dropdown = ob_get_clean();
        wp_enqueue_style('admin', WOOBB_URL . 'assets/css/woobb-admin.css', [], WOOBB_VER);
        wp_enqueue_script('admin', WOOBB_URL . 'assets/js/woobb-admin.js', ['jquery'], WOOBB_VER, true);
        wp_localize_script('admin', 'woobbLocal', array(
            'ajax' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('woobb_admin_nonce'),
            'post_id' => is_object($post) ? $post->ID : '',
            'products_list' => $products_dropdown
        ));
    }
}
