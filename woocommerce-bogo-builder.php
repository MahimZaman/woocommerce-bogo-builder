
<?php
/**
 * Plugin Name: WooCommerce BOGO Builder
 * Description: Create buy X get Y products rule via product edit page
 * Author: Mahim Zaman
 * Author URI: https://www.mahimzaman.com
 * Version: 1.0.0
 * Text Domain: woobb
 * Domain Path: /languages
 */

define('WOOBB_PATH', trailingslashit(plugin_dir_path(__FILE__)));
define('WOOBB_URL', trailingslashit(plugin_dir_url(__FILE__)));
define('WOOBB_VER', time());

require_once WOOBB_PATH . 'includes/class-woobb-core.php';

add_action('plugins_loaded', function () {
    $core = new WOOBB_Core();
    $core->load();
});
