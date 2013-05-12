<?php

namespace Maba\AccountsPlugin;

use Maba\OAuthCommerceAccountsClient\Entity\Transaction;

abstract class Gateway extends \WC_Payment_Gateway
{
    /**
     * @var \WC_Logger
     */
    protected $logger;

    /**
     * @var \Maba\OAuthCommerceAccountsClient\AccountsApi
     */
    protected $api;

    protected $plugin;

    protected $credentials;

    protected $beneficiaryAccount;

    public function __construct()
    {
        $this->init();

        $this->plugin = Plugin::getInstance(); // no way to inject into gateway object

        $this->has_fields = false;
        $this->init_form_fields();
        $this->init_settings();
        $settings = get_option($this->plugin_id . 'maba-oauth-commerce-accounts_settings', null) + array(
            'access_token' => '',
            'mac_key' => '',
            'mac_algorithm' => '',
            'beneficiary' => '',
        );

        $this->debug = $this->settings['debug'];
        if ($this->debug) {
            $this->logger = $this->plugin->getWooCommerce()->logger();
        }

        $this->credentials = array(
            'access_token' => $settings['access_token'],
            'mac_key' => $settings['mac_key'],
            'mac_algorithm' => $settings['mac_algorithm'],
        );
        $this->beneficiaryAccount = $settings['beneficiary'];

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    }

    abstract protected function init();

    protected function createTransaction(\WC_Order $order)
    {
        $description = strtr(__('Order no. [id] in site [site]', 'woocommerce'), array(
            '[id]' => $order->id,
            '[site]' => $this->plugin->getBaseHost(),
        ));
        return $this->api()->accounts()->createTransaction(
            Transaction::create()
                ->setDescription($description)
                ->setBeneficiary($this->beneficiaryAccount)
                ->setAmount($order->get_total() * 100)
        )->getResult();
    }

    /**
     * Initialise Gateway Settings Form Fields
     * @access public
     * @return void
     */
    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'woocommerce'),
                'type' => 'checkbox',
                'label' => __('Enable Accounts gateway', 'woocommerce'),
                'default' => 'no'
            ),
            'access_token' => array(
                'title' => __('Access token', 'woocommerce'),
                'type' => 'text',
                'description' => __('MAC ID of client, got when registering client', 'woocommerce'),
                'default' => '',
                'desc_tip' => true,
            ),
            'mac_algorithm' => array(
                'title' => __('MAC algorithm', 'woocommerce'),
                'type' => 'select',
                'options' => array(
                    'hmac-sha-256' => 'hmac-sha-256',
                    'hmac-sha-512' => 'hmac-sha-512',
                    'rsa-pkcs1-sha-256' => 'rsa-pkcs1-sha-256',
                    'rsa-pkcs1-sha-512' => 'rsa-pkcs1-sha-512',
                ),
                'default' => 'hmac-sha-256',
            ),
            'mac_key' => array(
                'title' => __('Shared of private key', 'woocommerce'),
                'type' => 'text',
                'description' => __(
                    'Your generated private key in PEM format or provided shared key when registering client',
                    'woocommerce'
                ),
                'default' => '',
                'desc_tip' => true,
            ),
            'beneficiary' => array(
                'title' => __('Beneficiary account number', 'woocommerce'),
                'type' => 'text',
                'description' => __(
                    'Can be found in API Clients page when logged in to accounts system',
                    'woocommerce'
                ),
                'default' => '',
                'desc_tip' => true,
            ),
        );
    }

    protected function log($message)
    {
        if ($this->logger) {
            $this->logger->add('maba-oauth-commerce-accounts', $message);
        }
    }

    protected function api()
    {
        if ($this->api === null) {
            $this->api = $this->plugin->createApi($this->credentials);
        }
        return $this->api;
    }
}