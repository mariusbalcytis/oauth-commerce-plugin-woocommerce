<?php

/**
 * Namespace is not used for the gateway itself due to an error in woocommerce/wordpress
 * Class Maba_AccountsPlugin_FormGateway
 */
class Maba_AccountsPlugin_FormGateway extends \Maba\AccountsPlugin\Gateway
{

    public function process_payment($orderId)
    {
        $redirectUri = add_query_arg(
            'order',
            $orderId,
            $this->plugin->getWooCommerce()->api_request_url('maba_oauth_commerce_accounts_form', true)
        );
        return array(
            'result' => 'success',
            'redirect' => $redirectUri,
        );
    }

    public function show_form()
    {
        $error = null;
        if (!empty($_POST['username']) && !empty($_POST['password']) && !empty($_POST['csrf'])) {
            if ($_POST['csrf'] === $_SESSION['maba_csrf']) {
                $orderId = isset($_GET['order']) ? $_GET['order'] : null;
                $order = new \WC_Order($orderId);

                $transaction = $this->createTransaction($order);
                try {
                    $token = $this->api()->auth()->createSecretCredentialsToken(
                        \Maba\OAuthCommerceClient\Entity\UserCredentials\Password::create()
                            ->setSite('accounts.maba.lt')
                            ->setUsername($_POST['username'])
                            ->setPassword($_POST['password'])
                        ,
                        array('transaction:' . $transaction->getKey())
                    )->getResult();

                    $transaction = $this->api()->accounts()->reserveTransaction($transaction->getKey(), $token)->getResult();
                    $transaction = $this->api()->accounts()->confirmTransaction($transaction->getKey(), $token)->getResult();
                    if ($transaction->getStatus() === 'done') {
                        if ($order->status !== 'completed') {
                            $this->log('Order #' . $order->id . ' Callback payment completed.');

                            $order->add_order_note(__('Payment successfully confirmed', 'woocomerce'));
                            $order->payment_complete();

                            header('Location: ' . $this->get_return_url($orderId));
                            exit;
                        }
                    }

                } catch (\Maba\OAuthCommerceClient\Exception\ClientErrorException $exception) {
                    if ($exception->getErrorCode() === 'invalid_credentials') {
                        $error = __('Invalid credentials', 'woocommerce');
                    } elseif ($exception->getErrorCode() === 'insufficient_funds') {
                        $error = __('Insufficient funds in account', 'woocommerce');
                    } else {
                        $error = __('Unknown error', 'woocommerce');
                    }
                }
            }
        }
        $_SESSION['maba_csrf'] = hash('sha256', mt_rand() . mt_rand() . mt_rand() . mt_rand());
        $this->plugin->render('form', array('csrf' => $_SESSION['maba_csrf'], 'error' => $error));
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
        );
    }

    public function admin_options() {
        parent::admin_options();
        echo '<p>' . _e('Other options are available in main gateway', 'woocommerce') . '</p>';
    }


    protected function init()
    {
        $this->id = 'maba-oauth-commerce-accounts-form';
        $this->title = __('OAuth Commerce Accounts by Form', 'woocommerce');
        add_action('woocommerce_api_maba_oauth_commerce_accounts_form', array($this, 'show_form'));
    }
}