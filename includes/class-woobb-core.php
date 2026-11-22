<?php

defined('ABSPATH') || exit;

class WOOBB_Core
{
    public function load()
    {
        $this->load_dependencies();
        $this->init_classes();
        $this->init_hooks();
    }

    private function load_dependencies()
    {
        require_once WOOBB_PATH . 'includes/class-woobb-admin.php';
        require_once WOOBB_PATH . 'includes/class-woobb-frontend.php';
    }

    private function init_hooks() {}

    private function init_classes() {
        $admin = new WOOBB_Admin();
        $frontend = new WOOBB_Frontend();        
    }
}
