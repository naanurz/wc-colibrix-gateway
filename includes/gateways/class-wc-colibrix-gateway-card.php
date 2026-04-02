<?php

defined('ABSPATH') || exit;

class WC_Colibrix_Gateway_Card extends WC_Colibrix_Gateway_Abstract
{
    protected function get_gateway_id(): string
    {
        return 'colibrix_gateway_card';
    }

    protected function get_gateway_method_title(): string
    {
        return __('Colibrix Gateway - Card', 'wc-colibrix-gateway-payment');
    }

    protected function get_gateway_method_description(): string
    {
        return __('Pay with card via Colibrix Gateway payment page.', 'wc-colibrix-gateway-payment');
    }

    protected function get_default_checkout_title(): string
    {
        return __('Bank card', 'wc-colibrix-gateway-payment');
    }

    protected function get_default_checkout_description(): string
    {
        return __('Visa, Mastercard and other cards supported by Colibrix Gateway.', 'wc-colibrix-gateway-payment');
    }

    protected function get_payment_endpoint(): string
    {
        return '/payment_page';
    }

    protected function get_refund_endpoint(): ?string
    {
        return '/card/refund';
    }

    protected function build_payment_payload(WC_Order $order, string $tracking_id): array
    {
        $amount = (int) round((float) $order->get_total() * (10 ** wc_get_price_decimals()));

        $payload = [
            'project_id'       => $this->project_id,
            'transaction_type' => 'payment',
            'test'             => $this->test_mode === 'yes',
            'notification_url' => WC()->api_request_url($this->get_notify_api_key()),
            'payment_method'   => [
                'main_type' => 'bank_card',
            ],
            'order'            => [
                'amount'      => $amount,
                'currency'    => (string) $order->get_currency(),
                'description' => sprintf('Order #%d', $order->get_id()),
                'tracking_id' => $tracking_id,
            ],
            'settings'         => [
                'success_url' => $this->build_return_url($order),
                'fail_url'    => $this->build_return_url($order),
                'cancel_url'  => $this->build_return_url($order),
                'language'    => substr(get_locale(), 0, 2),
            ],
            'customer'         => [
                'email'      => (string) $order->get_billing_email(),
                'first_name' => (string) $order->get_billing_first_name(),
                'last_name'  => (string) $order->get_billing_last_name(),
                'country'    => (string) $order->get_billing_country(),
                'city'       => (string) $order->get_billing_city(),
                'state'      => (string) $order->get_billing_state(),
                'zip'        => (string) $order->get_billing_postcode(),
                'address'    => trim((string) $order->get_billing_address_1() . ' ' . (string) $order->get_billing_address_2()),
                'phone'      => (string) $order->get_billing_phone(),
            ],
        ];

        return $this->filter_empty($payload);
    }

    protected function get_redirect_url(array $body): string{
        return $body['redirect_url'] ?? '';
    }

    protected function get_response_uid(array $body): string
    {
        return $body['uid'] ?? '';
    }
}
