<?php
/*
Plugin Name: Maba OAuth Commerce Accounts
Plugin URI: https://accounts.maba.lt/
Description: WooCommerce Gateway for OAuth Commerce example accounts system
Version: 0.1
Author: Marius Balčytis
License: MIT
*/

if (file_exists(ABSPATH . 'vendor/autoload.php')) {
    require ABSPATH . 'vendor/autoload.php';
    new \Maba\AccountsPlugin\Plugin(plugin_dir_path(__FILE__), $_SERVER['HTTP_HOST']);
} else {
    add_action('admin_notices', function() {
        echo '<div class="error"><p><strong>
            Maba OAuth Commerce Accounts plugin not loaded, because you need to install vendors with composer
            in your root directory of wordpress.
        </strong></p></div>';
    });
}

// for simplicity in this example shop system, we remove all billing fields
add_filter('woocommerce_checkout_fields' , 'disableBillingFields');
function disableBillingFields($fields) {
    $fields['billing'] = array();
    return $fields;
}

/**
 * Class maba_oauth_commerce_accounts
 *
 * FIX for wc-api, it requires lower-cased class name and creates it. We need the real gateway to be created
 */
class maba_oauth_commerce_accounts {
    public function __construct() {
        new Maba_AccountsPlugin_RedirectGateway();
    }
}
class maba_oauth_commerce_accounts_form {
    public function __construct() {
        new Maba_AccountsPlugin_FormGateway();
    }
}

