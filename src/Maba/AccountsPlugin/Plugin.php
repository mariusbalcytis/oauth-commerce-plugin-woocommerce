<?php

namespace Maba\AccountsPlugin;

use Maba\OAuthCommerceAccountsClient\AccountsApi;
use Maba\OAuthCommerceAccountsClient\AccountsApiFactory;
use Maba\OAuthCommerceAccountsClient\DependencyInjection\AccountsClientExtension;
use Maba\OAuthCommerceClient\DependencyInjection\BaseClientExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

class Plugin
{
    protected static $instance;
    protected $basePath;

    /**
     * @var ContainerInterface
     */
    protected $container;

    public function __construct($basePath, $baseHost)
    {
        self::$instance = $this;
        $this->basePath = $basePath;
        $this->baseHost = $baseHost;
        add_action('plugins_loaded', array($this, 'init'));
        add_action('init', array($this, 'startSession'), 1);
    }

    public static function getInstance()
    {
        return self::$instance;
    }

    public function render($page, $vars = array())
    {
        extract($vars, EXTR_SKIP);
        require $this->basePath . '/templates/' . $page . '.php';
        die;
    }

    public function init()
    {
        if (class_exists('WC_Payment_Gateway')) {
            add_filter('woocommerce_payment_gateways', array($this, 'addGateway'));
            $this->loadContainer();
        }
    }

    public function startSession() {
        if (!session_id()) {
            session_start();
        }
    }

    protected function loadContainer()
    {
        $container = new ContainerBuilder(new ParameterBag(array(
            'maba_oauth_commerce.internal_client.default_base_url' => 'https://accounts.maba.lt/api/internal/v1',
            'maba_oauth_commerce.auth_client.default_base_url' => 'https://accounts.maba.lt/api/auth/v1',
        )));
        $container->setResourceTracking(false);

        $extension = new BaseClientExtension();
        $container->registerExtension($extension);
        $container->loadFromExtension($extension->getAlias());
        $extension->addCompilerPasses($container);
        $extension = new AccountsClientExtension();
        $container->registerExtension($extension);
        $container->loadFromExtension($extension->getAlias());

        $container->compile();

        $this->container = $container;
    }

    public function getBaseHost()
    {
        return $this->baseHost;
    }

    /**
     * @return \Woocommerce
     */
    public function getWooCommerce()
    {
        return $GLOBALS['woocommerce'];
    }

    /**
     * Add the gateway to WooCommerce
     *
     * @param array $methods
     *
     * @return array
     */
    public function addGateway($methods)
    {
        $methods[] = 'Maba_AccountsPlugin_RedirectGateway';
        $methods[] = 'Maba_AccountsPlugin_FormGateway';

        return $methods;
    }

    /**
     * @param array $credentials
     *
     * @return AccountsApi
     */
    public function createApi($credentials)
    {
        return $this->container->get('maba_oauth_commerce.factory.accounts_api')
            ->createApi($credentials, AccountsApiFactory::DEFAULT_ENDPOINT);
    }
}