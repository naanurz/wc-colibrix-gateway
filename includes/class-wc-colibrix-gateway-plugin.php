<?php

defined('ABSPATH') || exit;

class WC_Colibrix_Gateway_Plugin
{
    public static function init(): void
    {
        add_action('before_woocommerce_init', [__CLASS__, 'declare_compatibility']);
        add_action('plugins_loaded', [__CLASS__, 'register_gateways'], 20);
        add_action('woocommerce_blocks_loaded', [__CLASS__, 'register_blocks_support']);
    }

    public static function declare_compatibility(): void
    {
        if (! class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
            return;
        }

        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', WC_COLIBRIX_GATEWAY_PLUGIN_FILE, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', WC_COLIBRIX_GATEWAY_PLUGIN_FILE, true);
    }

    public static function register_gateways(): void
    {
        if (! class_exists('WC_Payment_Gateway')) {
            return;
        }

        require_once WC_COLIBRIX_GATEWAY_PLUGIN_DIR . '/includes/gateways/abstract-wc-colibrix-gateway.php';
        require_once WC_COLIBRIX_GATEWAY_PLUGIN_DIR . '/includes/gateways/class-wc-colibrix-gateway-card.php';
        require_once WC_COLIBRIX_GATEWAY_PLUGIN_DIR . '/includes/gateways/class-wc-colibrix-gateway-apm.php';

        add_filter('woocommerce_payment_gateways', static function (array $methods): array {
            $methods[] = 'WC_Colibrix_Gateway_Card';
            $methods[] = 'WC_Colibrix_Gateway_Apm';

            return $methods;
        });
    }

    public static function register_blocks_support(): void
    {
        if (! class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
            return;
        }

        $new_blocks_class = WC_COLIBRIX_GATEWAY_PLUGIN_DIR . '/includes/blocks/class-wc-colibrix-gateway-blocks-support.php';
        $old_blocks_class = WC_COLIBRIX_GATEWAY_PLUGIN_DIR . '/includes/blocks/class-wc-gateway-begateway-payment-blocks-support.php';

        if (file_exists($new_blocks_class)) {
            require_once $new_blocks_class;
        } elseif (file_exists($old_blocks_class)) {
            require_once $old_blocks_class;
        } else {
            return;
        }

        add_action(
            'woocommerce_blocks_payment_method_type_registration',
            static function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry): void {
                if (class_exists('WC_Colibrix_Gateway_Blocks_Support')) {
                    $payment_method_registry->register(new WC_Colibrix_Gateway_Blocks_Support('colibrix_gateway_card'));
                    $payment_method_registry->register(new WC_Colibrix_Gateway_Blocks_Support('colibrix_gateway_apm'));
                } elseif (class_exists('WC_Gateway_BeGateway_Payment_Blocks_Support')) {
                    $payment_method_registry->register(new WC_Gateway_BeGateway_Payment_Blocks_Support());
                }
            }
        );
    }
}
