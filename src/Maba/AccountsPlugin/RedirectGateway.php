<?php

/**
 * Namespace is not used for the gateway itself due to an error in woocommerce/wordpress
 * Class Maba_AccountsPlugin_RedirectGateway
 */
class Maba_AccountsPlugin_RedirectGateway extends \Maba\AccountsPlugin\Gateway
{

    public function process_payment($orderId)
    {
        $order = new \WC_Order($orderId);
        $transaction = $this->createTransaction($order);
        $scopes = array('transaction:' . $transaction->getKey());

        $redirectUri = add_query_arg(
            'order',
            $order->id,
            $this->plugin->getWooCommerce()->api_request_url('maba_oauth_commerce_accounts', true)
        );

        $state = $this->api()->codeGrantHandler()->generateState();
        $uri = $this->api()->codeGrantHandler()->getAuthUri($state, $redirectUri, $scopes);

        $_SESSION['maba_oauth_commerce_accounts'][$order->id] = array(
            'transaction' => $transaction->getKey(),
            'state' => $state,
            'redirectUri' => $redirectUri,
        );

        return array(
            'result' => 'success',
            'redirect' => $uri,
        );
    }

    public function process_return()
    {
        parse_str($_SERVER['QUERY_STRING'], $get);  // this is needed as "error" parameter is unset somewhere in system
        $orderId = isset($get['order']) ? $get['order'] : null;
        $order = new \WC_Order($orderId);
        $params = isset($_SESSION['maba_oauth_commerce_accounts'][$orderId])
            ? $_SESSION['maba_oauth_commerce_accounts'][$orderId]
            : null;

        if ($params && $order->id && $order->status !== 'completed') {
            try {
                $code = $this->api()->codeGrantHandler()->getCodeFromParameters($get, $params['state']);
            } catch (\Maba\OAuthCommerceClient\Exception\AuthorizationException $exception) {
                if ($exception->getErrorCode() !== 'access_denied') {
                    $this->log('Got OAuth error: ' . $exception->getErrorCode() . ', ' . $exception->getErrorDescription());
                }
                header('Location: ' . $order->get_cancel_order_url());
                exit;
            }
            /** @var \Maba\OAuthCommerceClient\Entity\AccessToken $token */
            $token = $this->api()->auth()->exchangeCodeForToken($code, $params['redirectUri'])->getResult();
            if (in_array('transaction:' . $params['transaction'], $token->getScopes())) {
                $this->log('Order #' . $order->id . ' Got access token.');
                $this->confirmTransaction($order, $params['transaction'], $token);
            }
        }

        header('Location: ' . $this->get_return_url($orderId));
        exit;
    }


    protected function init()
    {
        $this->id = 'maba-oauth-commerce-accounts';
        $this->title = __('OAuth Commerce Accounts', 'woocommerce');
        add_action('woocommerce_api_maba_oauth_commerce_accounts', array($this, 'process_return'));
    }
}