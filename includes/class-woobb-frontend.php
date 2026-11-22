<?php
defined('ABSPATH') || exit;

class WOOBB_Frontend
{
    public function __construct()
    {
        add_action('wp_enqueue_scripts', [$this, 'scripts']);
        add_action('woocommerce_after_add_to_cart_button', [$this, 'woobb_display_bogo_rules_frontend']);
        //add_action('woocommerce_add_to_cart', [$this, 'woobb_add_free_products_to_cart'], 99, 6);
        //add_filter('woocommerce_cart_item_price', [$this, 'woobb_display_free_price_badge'], 10, 3);
        //add_filter('woocommerce_cart_item_subtotal', [$this, 'woobb_display_free_subtotal_badge'], 10, 3);
        //add_filter('woocommerce_cart_item_quantity', [$this, 'woobb_disable_free_quantity_edit'], 10, 3);
        //add_action('woocommerce_before_calculate_totals', [$this, 'woobb_set_free_price'], 999);
        add_action('wp_body_open', [$this, 'woobb_claim_gift']);
        add_action('wp_ajax_woobb_load_gifts', [$this, 'woobb_load_gift_options']);
        add_action('wp_ajax_nopriv_woobb_load_gifts', [$this, 'woobb_load_gift_options']);
        add_action('wp_ajax_woobb_claim_free_gift', [$this, 'woobb_claim_free_gift']);
        add_action('wp_ajax_nopriv_woobb_claim_free_gift', [$this, 'woobb_claim_free_gift']);
        add_filter('woocommerce_get_price_html', function ($price, $product) {
            if ($product->get_price() == 0) {
                return '<span class="woobb-free-badge">FREE</span>';
            }
            return $price;
        }, 20, 2);
        add_action('woocommerce_before_calculate_totals', function ($cart) {
            if (is_admin() && !defined('DOING_AJAX')) return;

            foreach ($cart->get_cart() as $key => $item) {
                if (!empty($item['woobb_free_gift'])) {
                    $parent_still_exists = false;

                    foreach ($cart->get_cart() as $k => $p) {
                        if (empty($p['woobb_free_gift']) && $p['product_id']) {
                            $parent_still_exists = true;
                        }
                    }

                    if (!$parent_still_exists) {
                        $cart->remove_cart_item($key);
                    }
                }
            }
        }, 999);
        //add_action('woocommerce_cart_loaded_from_session', [$this, 'woobb_sync_free_items_on_cart_reload'], 99);
        //add_action('woocommerce_after_cart_item_quantity_update', [$this, 'woobb_sync_free_items_on_qty_update'], 99, 3);
        //add_action('woocommerce_cart_item_removed', [$this, 'woobb_remove_free_items_with_parent'], 99, 2);
    }

    function woobb_claim_free_gift()
    {

        $gift_id = intval($_POST['gift_id']);
        $variation_id = intval($_POST['variation_id']);

        $args = [
            'woobb_free_gift' => true
        ];

        if ($variation_id > 0) {
            $key = WC()->cart->add_to_cart($gift_id, 1, $variation_id, [], $args);
        } else {
            $key = WC()->cart->add_to_cart($gift_id, 1, 0, [], $args);
        }

        if ($key) {
            WC()->cart->cart_contents[$key]['data']->set_price(0);
        }

        wp_send_json(['success' => true]);
    }

    function woobb_load_gift_options()
    {

        $gift_ids = isset($_POST['gift_ids']) ? $_POST['gift_ids'] : [];
        $html = '';

        foreach ($gift_ids as $gid) {

            $product = wc_get_product($gid);

            if (!$product) continue;

            if ($product->is_type('variable')) {

                $variations = $product->get_available_variations();

                $html .= "<label class='woobb-gift-item'>
                        <input type='radio' name='woobb_gift' value='{$gid}'>
                        <strong>{$product->get_name()}</strong>
                        <select name='woobb_variation'>";

                foreach ($variations as $v) {
                    $vid = $v['variation_id'];
                    $vname = wc_get_product($vid)->get_name();
                    $html .= "<option value='{$vid}'>{$vname}</option>";
                }

                $html .= "</select></label>";
            } else {

                $html .= "<label class='woobb-gift-item'>
                        <input type='radio' name='woobb_gift' value='{$gid}'>
                        <strong>{$product->get_name()}</strong>
                      </label>";
            }
        }

        wp_send_json(['html' => $html]);
    }

    public function woobb_claim_gift()
    {
        global $woocommerce;
        $cart = $woocommerce->cart->get_cart();
        $eligible = [];
        foreach ($cart as $key => $item) {
            $rule_indexes = [];
            $cart_data = $item['data'];
            $product_id = $cart_data->get_parent_id() > 0 ? $cart_data->get_parent_id() : $cart_data->get_id();
            $rules = get_post_meta($product_id, '_woobb_bogo_rules', true);
            if (empty($rules)) continue;
            foreach ($rules as $rule) {
                $rule_indexes[] = $rule['product_qty'];
            }

            rsort($rule_indexes);
            // echo '<pre>';
            // print_r($rule_indexes);
            // //print_r($eligible);
            // echo '</pre>';

            if (isset($eligible[$product_id])) {
                $cart_qty = isset($eligible[$product_id]['cart_qty']) ? $eligible[$product_id]['cart_qty'] : 0;
                $eligible[$product_id] = array(
                    'cart_qty' => (int) $cart_qty + (int) $item['quantity'],
                    'rules' => $rules,
                    'sorted_qty' => $rule_indexes
                );
            } else {
                $eligible[$product_id] = array(
                    'cart_qty' => (int) $item['quantity'],
                    'rules' => $rules,
                    'sorted_qty' => $rule_indexes
                );
            }
        }


        echo '<pre>';
        //print_r($rule_indexes);
        print_r($eligible);
        echo '</pre>';

        if (empty($eligible)) return;

        foreach($eligible as $product_id => $product_data){            
            foreach($product_data['sorted_qty'] as $serial){
                echo '<pre>';
                print_r($product_data['cart_qty'] . "\n");
                print_r($serial);
                echo '</pre>';
            }
        }
    }





    public function woobb_sync_free_items_on_cart_reload($cart)
    {
        $this->woobb_sync_free_items();
    }

    public function woobb_sync_free_items_on_qty_update($cart_item_key, $quantity, $old_quantity)
    {
        // Only sync when quantity actually changed
        if ($quantity === $old_quantity) {
            return;
        }
        $this->woobb_sync_free_items();
    }

    function woobb_remove_free_items_with_parent($cart_item_key, $cart)
    {
        foreach ($cart->get_cart() as $key => $item) {
            if (isset($item['woobb_parent']) && $item['woobb_parent'] === $cart_item_key) {
                $cart->remove_cart_item($key);
            }
        }
    }

    public function woobb_sync_free_items()
    {
        static $running = false;
        if ($running) return;
        $running = true;

        if (is_admin() && !defined('DOING_AJAX')) return;

        $cart = WC()->cart;
        if (empty($cart)) return;

        $cart_items = $cart->get_cart();
        $free_links = [];
        $to_add = [];
        $to_remove = [];

        foreach ($cart_items as $cart_item_key => $cart_item) {

            if (!empty($cart_item['woobb_free'])) continue;

            $product_id = $cart_item['product_id'];
            $quantity   = intval($cart_item['quantity']);

            $rules = get_post_meta($product_id, '_woobb_bogo_rules', true);
            if (empty($rules) || !is_array($rules)) continue;

            $matched_rule = null;
            $fallback_rule = null;

            foreach ($rules as $rule) {
                $rule_qty = intval($rule['product_qty']);
                if ($rule_qty === $quantity) {
                    $matched_rule = $rule;
                    break;
                } elseif ($rule_qty < $quantity) {
                    if (!$fallback_rule || $rule_qty > intval($fallback_rule['product_qty'])) {
                        $fallback_rule = $rule;
                    }
                }
            }

            $applicable_rule = $matched_rule ?: $fallback_rule;
            if (!$applicable_rule) continue;

            foreach ($applicable_rule['free_items'] as $free_item) {
                $free_id  = intval($free_item['id']);
                $free_qty = intval($free_item['qty']);

                if (!$matched_rule && $fallback_rule) {
                    $ratio = $quantity / intval($fallback_rule['product_qty']);
                    $free_qty = ceil($free_qty * $ratio);
                }

                $exists = false;
                foreach ($cart->get_cart() as $key => $item) {
                    if (!empty($item['woobb_free']) && intval($item['product_id']) === $free_id && $item['woobb_parent'] == $product_id) {
                        if ($item['quantity'] != $free_qty) {
                            $cart->set_quantity($key, $free_qty, false);
                        }
                        $exists = true;
                        break;
                    }
                }

                if (!$exists) {
                    $to_add[] = ['id' => $free_id, 'qty' => $free_qty, 'parent' => $product_id];
                }

                $free_links[$product_id][$free_id] = $free_qty;
            }
        }

        // Cleanup and add items after main loop
        foreach ($cart->get_cart() as $key => $item) {
            if (!empty($item['woobb_free'])) {
                $parent = intval($item['woobb_parent']);
                if (!isset($free_links[$parent][$item['product_id']])) {
                    $to_remove[] = $key;
                }
            }
        }

        foreach ($to_remove as $key) {
            $cart->remove_cart_item($key);
        }

        foreach ($to_add as $add_item) {
            $cart->add_to_cart($add_item['id'], $add_item['qty'], 0, [], [
                'woobb_free' => true,
                'woobb_parent' => $add_item['parent'],
            ]);
        }

        $cart->calculate_totals();
        $running = false;
    }

    public function woobb_set_free_price($cart)
    {

        if (is_admin() && ! defined('DOING_AJAX')) return;

        //if (did_action('woocommerce_before_calculate_totals') >= 2) return;

        // LOOP THROUGH CART ITEMS & APPLY 20% DISCOUNT
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            // if (isset($cart_item['woobb_free']) && $cart_item['woobb_free']) {
            //     $product = $cart_item['data'];
            //     $cart_item['data']->set_price(0);
            // }
            $product_id = $cart_item['data']->get_id();
            $product = wc_get_product($product_id);
            $parent_product_id = $product->get_parent_id() > 0 ? $product->get_parent_id() : $product_id;
            $rules = get_post_meta($parent_product_id, '_woobb_bogo_rules', true);
            echo '<pre>';
            print_r($rules);
            echo '</pre>';
        }
    }

    public function woobb_display_free_subtotal_badge($subtotal, $cart_item, $cart_item_key)
    {
        if (isset($cart_item['woobb_free']) && $cart_item['woobb_free']) {
            $badge = '<span style="color:white;background:green;padding: 3px 6px;line-height: 1.3; border-radius: 5px;font-size: 13px;font-weight:600;">Free</span>';
            return $badge;
        }
        return $subtotal;
    }

    public function woobb_display_free_price_badge($price, $cart_item, $cart_item_key)
    {
        if (isset($cart_item['woobb_free']) && $cart_item['woobb_free']) {
            $badge = '<span style="color:white;background:green;padding: 3px 6px;line-height: 1.3; border-radius: 5px;font-size: 13px;font-weight:600;">Free</span>';
            return $badge;
        }
        return $price;
    }

    public function woobb_disable_free_quantity_edit($product_quantity, $cart_item_key, $cart_item)
    {
        if (isset($cart_item['woobb_free']) && $cart_item['woobb_free']) {
            return '<span>' . $cart_item['quantity'] . '</span>';
        }
        return $product_quantity;
    }

    public function woobb_add_free_products_to_cart($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data)
    {
        $rules = get_post_meta($product_id, '_woobb_bogo_rules', true);
        if (empty($rules)) {
            return;
        }

        foreach ($rules as $rule_index => $rule) {
            $buy_qty   = intval($rule['product_qty'] ?? 1);
            $free_list = $rule['free_items'] ?? array();

            if (empty($free_list) || $quantity < $buy_qty) {
                continue;
            }

            // Calculate how many sets we get
            $multiplier = floor($quantity / $buy_qty);

            foreach ($free_list as $item) {
                $free_id  = intval($item['id'] ?? 0);
                $free_qty = intval($item['qty'] ?? 1) * $multiplier;

                if (! $free_id || $free_qty <= 0) {
                    continue;
                }

                // Prevent duplicates
                $already_in_cart = false;
                foreach (WC()->cart->get_cart() as $key => $val) {
                    if (isset($val['woobb_parent']) && $val['woobb_parent'] == $cart_item_key && $val['product_id'] == $free_id) {
                        WC()->cart->set_quantity($key, $free_qty);
                        $already_in_cart = true;
                    }
                }

                if (! $already_in_cart) {
                    WC()->cart->add_to_cart($free_id, $free_qty, 0, array(), array(
                        'woobb_parent' => $cart_item_key,
                        'woobb_free'   => true,
                        'woobb_rule'   => $rule_index,
                    ));
                }
            }
        }
    }


    public function woobb_display_bogo_rules_frontend()
    {
        global $product;

        if (! $product instanceof WC_Product) {
            return;
        }

        $product_id  = $product->get_id();
        $rules       = get_post_meta($product_id, '_woobb_bogo_rules', true);
        $rules       = is_array($rules) ? $rules : array();

        if (empty($rules)) {
            return;
        }

        echo '<div class="woobb-bogo-rules" style="margin-top:20px;">';
        echo '<h4 style="margin-bottom:10px;">🎁 Buy More, Get Free!</h4>';

        foreach ($rules as $rule) {
            $product_qty = intval($rule['product_qty'] ?? 1);
            $free_items  = $rule['free_items'] ?? array();

            if (empty($free_items)) {
                continue;
            }

            echo '<div class="woobb-bogo-offer" style="border:1px solid #ddd;padding:10px;margin-bottom:10px;">';
            echo '<p><strong>Buy ' . esc_html($product_qty) . '</strong> of this product and get the following items for <span style="color:green;font-weight:600;">FREE</span>:</p>';
            echo '<div class="woobb-bogo-free-items" style="display:flex;flex-wrap:wrap;gap:15px;">';

            foreach ($free_items as $free_item) {
                $fid   = intval($free_item['id'] ?? 0);
                $fqty  = intval($free_item['qty'] ?? 1);
                $fprod = wc_get_product($fid);

                if (! $fprod) {
                    continue;
                }

                $image = $fprod->get_image('woocommerce_thumbnail', array('style' => 'border-radius:6px;box-shadow: none; border: 1px solid #ddd;'));
                $name  = $fprod->get_name();
                $price = wc_price($fprod->get_price());

                echo '<div class="woobb-bogo-item" style="text-align:center;width:120px;">';
                echo '<a href="' . esc_url(get_permalink($fid)) . '">' . $image . '</a>';
                echo '<div style="font-size:13px;margin-top:5px;">' . esc_html($name) . '</div>';
                echo '<div style="font-size:12px;text-decoration:line-through;color:#999;">' . $price . '</div>';
                echo '<div style="color:green;font-weight:600;">Free</div>';
                echo '<small>x' . esc_html($fqty) . '</small>';
                echo '</div>';
            }

            echo '</div></div>';
        }

        echo '</div>';
    }

    public function scripts()
    {
        wp_enqueue_style('frontend', WOOBB_URL . 'assets/css/woobb-frontend.css', [], WOOBB_VER);
        wp_enqueue_script('frontend', WOOBB_URL . 'assets/js/woobb-frontend.js', ['jquery'], WOOBB_VER, true);
        wp_localize_script('frontend', 'woobbLocal', array(
            'ajax' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('woobb_frontend_nonce')
        ));
    }
}
