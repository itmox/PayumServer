<?php
namespace Payum\Server;

use Omnipay\Omnipay;
use Payum\Core\Bridge\Symfony\ReplyToSymfonyResponseConverter;
use Payum\Core\Bridge\Symfony\Security\HttpRequestVerifier;
use Payum\Core\Bridge\Symfony\Security\TokenFactory;
use Payum\Core\PaymentInterface;
use Payum\Core\Registry\SimpleRegistry;
use Payum\Core\Storage\FilesystemStorage;
use Payum\Paypal\ExpressCheckout\Nvp\Api;
use Payum\Paypal\ExpressCheckout\Nvp\PaymentFactory;
use Payum\OmnipayBridge\PaymentFactory as OmnipayPaymentFactory;
use Silex\Application;
use Silex\ServiceProviderInterface;
use Symfony\Component\Yaml\Yaml;

class ServiceProvider implements ServiceProviderInterface
{
    /**
     * {@inheritDoc}
     */
    public function register(Application $app)
    {
        $app['debug'] = true;
        $app['payum.config_file'] = $app['app.root_dir'].'/payum.yml';
        $app['payum.config'] = file_exists($app['payum.config_file']) ?
            Yaml::parse(file_get_contents($app['payum.config_file'])) :
            array('payments' => array())
        ;
        $app['payum.storage_dir'] = $app['app.root_dir'].'/storage';
        $app['payum.model.payment_details_class'] = 'Payum\Server\Model\PaymentDetails';
        $app['payum.model.order_class'] = 'Payum\Server\Model\Order';
        $app['payum.model.security_token_class'] = 'Payum\Server\Model\SecurityToken';

        $app['payum.security.token_storage'] = $app->share(function($app) {
            return new FilesystemStorage(
                $app['payum.storage_dir'],
                $app['payum.model.security_token_class'],
                'hash'
            );
        });

        $app['payum.reply_to_symfony_response_converter'] = $app->share(function($app) {
            return new ReplyToSymfonyResponseConverter();
        });

        $app['payum.security.http_request_verifier'] = $app->share(function($app) {
            return new HttpRequestVerifier($app['payum.security.token_storage']);
        });

        $app['payum.security.token_factory'] = $app->share(function($app) {
            return new TokenFactory(
                $app['url_generator'],
                $app['payum.security.token_storage'],
                $app['payum'],
                'capture',
                'notify',
                'authorize'
            );
        });

        $app['payum'] = $app->share(function($app) {
            $config = $app['payum.config'];

            $orderClass = $app['payum.model.order_class'];

            $storages = array(
                $orderClass => new FilesystemStorage($app['payum.storage_dir'], $orderClass, 'number')
            );

            /** @var PaymentInterface[] $payments */
            $payments = array();
            foreach ($config['payments'] as $name => $paymentConfig) {
                if ('paypal' != isset($paymentConfig['factory'])) {
                    continue;
                }

                /** @var PaymentInterface[] $payments */
                $payments[$name] = PaymentFactory::create(new Api($paymentConfig['options']));
            }

            return new SimpleRegistry($payments, $storages, null, null);
        });
    }

    /**
     * {@inheritDoc}
     */
    public function boot(Application $app)
    {
    }
}