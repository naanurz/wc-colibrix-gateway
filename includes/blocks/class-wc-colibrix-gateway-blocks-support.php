<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

defined('ABSPATH') || exit;

final class WC_Colibrix_Gateway_Blocks_Support extends AbstractPaymentMethodType
{
    protected $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function initialize(): void
    {
        $this->settings = get_option('woocommerce_' . $this->name . '_settings', []);
    }

    public function is_active(): bool
    {
        $gateways = WC()->payment_gateways()->payment_gateways();
        return isset($gateways[$this->name]) && $gateways[$this->name]->is_available();
    }

    public function get_payment_method_script_handles(): array
    {
        $handle = 'wc-colibrix-gateway-blocks';
        $src    = plugins_url('assets/js/frontend/blocks.js', WC_COLIBRIX_GATEWAY_PLUGIN_FILE);
        $asset  = WC_COLIBRIX_GATEWAY_PLUGIN_DIR . '/assets/js/frontend/blocks.asset.php';

        $asset_data = file_exists($asset)
            ? require $asset
            : [
                'dependencies' => ['wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities'],
                'version'      => '1.1.0',
            ];

        wp_register_script(
            $handle,
            $src,
            $asset_data['dependencies'],
            $asset_data['version'],
            true
        );

        return [$handle];
    }

    public function get_payment_method_data(): array
    {
        $gateways = WC()->payment_gateways()->payment_gateways();
        $gateway  = $gateways[$this->name] ?? null;

        $icons = [];
        if ($gateway && method_exists($gateway, 'get_icon_urls')) {
            $icons = array_map('esc_url', $gateway->get_icon_urls());
        } elseif ($gateway && ! empty($gateway->icon)) {
            $icons = [esc_url($gateway->icon)];
        }

        return [
            'title'       => $gateway ? $gateway->title : __('Colibrix Gateway', 'wc-colibrix-gateway-payment'),
            'description' => $gateway ? $gateway->description : '',
            'icons'       => $icons,
            // Keep legacy key for older cached scripts.
            'icon'        => $icons[0] ?? '',
            'supports'    => $gateway ? array_values($gateway->supports) : ['products'],
        ];
    }
}

