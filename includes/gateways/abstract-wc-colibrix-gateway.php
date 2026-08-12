<?php

defined('ABSPATH') || exit;

abstract class WC_Colibrix_Gateway_Abstract extends WC_Payment_Gateway
{
    protected const META_UID = '_colibrix_gateway_uid';
    protected const META_TRACKING_ID = '_colibrix_gateway_tracking_id';
    private const DEFAULT_API_BASE = 'https://mapi.new.acq.mellifera.dev';
    private const RETURN_TOKEN_TTL = 86400;

    protected string $api_base_url = self::DEFAULT_API_BASE;
    protected int $project_id = 0;
    protected string $api_key = '';
    protected string $signature_key = '';
    protected string $transaction_type = 'payment';
    protected string $test_mode = 'no';
    protected string $debug_mode = 'no';

    abstract protected function get_gateway_id(): string;
    abstract protected function get_gateway_method_title(): string;
    abstract protected function get_gateway_method_description(): string;
    abstract protected function get_default_checkout_title(): string;
    abstract protected function get_default_checkout_description(): string;
    abstract protected function get_refund_endpoint(): ?string;
    abstract protected function get_payment_endpoint(): ?string;
    abstract protected function build_payment_payload(WC_Order $order, string $tracking_id): array;

    abstract protected function get_redirect_url(array $body): string;
    abstract protected function get_response_uid(array $body): string;

    public function __construct()
    {
        $this->id                 = $this->get_gateway_id();
        $this->method_title       = $this->get_gateway_method_title();
        $this->method_description = $this->get_gateway_method_description();
        $this->has_fields         = false;
        $this->supports           = ['products', 'refunds'];

        $this->init_form_fields();
        $this->init_settings();

        $this->title             = (string) $this->get_option('title', $this->get_default_checkout_title());
        $this->description       = (string) $this->get_option('description', $this->get_default_checkout_description());
        $icon_urls               = $this->get_icon_urls();
        $this->icon              = $icon_urls[0] ?? '';
        $this->enabled           = (string) $this->get_option('enabled', 'no');
        $api_base_url            = rtrim(trim((string) $this->get_option('api_base_url', self::DEFAULT_API_BASE)), '/');
        $this->api_base_url      = $api_base_url !== '' ? $api_base_url : self::DEFAULT_API_BASE;
        $this->project_id        = (int) $this->get_option('project_id', 0);
        $this->api_key           = trim((string) $this->get_option('api_key', ''));
        $this->signature_key     = trim((string) $this->get_option('signature_key', ''));
        $this->test_mode         = (string) $this->get_option('test_mode', 'no');
        $this->debug_mode        = (string) $this->get_option('debug_mode', 'yes');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('woocommerce_api_' . $this->get_return_api_key(), [$this, 'handle_return']);
        add_action('woocommerce_api_' . $this->get_notify_api_key(), [$this, 'handle_notification']);
        add_action('woocommerce_admin_order_data_after_payment_info', [$this, 'render_admin_payment_info']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    public function init_form_fields(): void
    {
        $this->form_fields = [
            'enabled' => [
                'title'   => __('Enable/Disable', 'wc-colibrix-gateway-payment'),
                'type'    => 'checkbox',
                'label'   => __('Enable this payment method', 'wc-colibrix-gateway-payment'),
                'default' => 'no',
            ],
            'title' => [
                'title'       => __('Title', 'wc-colibrix-gateway-payment'),
                'type'        => 'text',
                'description' => __('Shown to customers at checkout.', 'wc-colibrix-gateway-payment'),
                'default'     => $this->get_default_checkout_title(),
                'desc_tip'    => true,
            ],
            'description' => [
                'title'       => __('Description', 'wc-colibrix-gateway-payment'),
                'type'        => 'textarea',
                'description' => __('Shown to customers at checkout.', 'wc-colibrix-gateway-payment'),
                'default'     => $this->get_default_checkout_description(),
            ],
            'icons' => [
                'title'       => __('Icons', 'wc-colibrix-gateway-payment'),
                'type'        => 'icons',
                'description' => sprintf(
                    /* translators: %d: maximum number of icons */
                    __('Up to %d logos shown next to the title at checkout (for example Visa, Mastercard, Amex).', 'wc-colibrix-gateway-payment'),
                    $this->get_max_icons()
                ),
                'default'     => '',
            ],
            'api_base_url' => [
                'title'       => __('API Base URL', 'wc-colibrix-gateway-payment'),
                'type'        => 'text',
                'description' => __('Example: https://mapi.example.com', 'wc-colibrix-gateway-payment'),
                'default'     => self::DEFAULT_API_BASE,
            ],
            'project_id' => [
                'title'       => __('Project ID', 'wc-colibrix-gateway-payment'),
                'type'        => 'number',
                'description' => __('Project ID from Colibrix Gateway.', 'wc-colibrix-gateway-payment'),
                'default'     => '',
            ],
            'api_key' => [
                'title'       => __('API Key', 'wc-colibrix-gateway-payment'),
                'type'        => 'password',
                'description' => __('X-API-KEY header value.', 'wc-colibrix-gateway-payment'),
                'default'     => '',
            ],
            'signature_key' => [
                'title'       => __('Signature Key', 'wc-colibrix-gateway-payment'),
                'type'        => 'password',
                'description' => __('Used for X-Signature = sha256(json + signature_key).', 'wc-colibrix-gateway-payment'),
                'default'     => '',
            ],
            'test_mode' => [
                'title'   => __('Test mode', 'wc-colibrix-gateway-payment'),
                'type'    => 'checkbox',
                'label'   => __('Send "test: true" in request', 'wc-colibrix-gateway-payment'),
                'default' => 'no',
            ],
            'debug_mode' => [
                'title'       => __('Debug log', 'wc-colibrix-gateway-payment'),
                'type'        => 'checkbox',
                'label'       => __('Enable detailed logs to WooCommerce > Status > Logs', 'wc-colibrix-gateway-payment'),
                'default'     => 'no',
                'description' => __('Log API requests/responses and callbacks.', 'wc-colibrix-gateway-payment'),
            ],
        ];
    }

    /**
     * Maximum number of checkout icons a merchant can configure.
     */
    public function get_max_icons(): int
    {
        $max = (int) apply_filters('wc_colibrix_gateway_max_icons', 10, $this->id);

        return max(1, $max);
    }

    /**
     * @return list<string>
     */
    public function get_icon_urls(): array
    {
        $raw = (string) $this->get_option('icons', '');
        if ($raw === '') {
            // Backward compatibility with the single-icon setting.
            $raw = (string) $this->get_option('icon', '');
        }

        $urls = preg_split('/\r\n|\r|\n/', $raw) ?: [];
        $urls = array_values(array_filter(array_map(static function ($url): string {
            return esc_url_raw(trim((string) $url));
        }, $urls)));

        return array_slice($urls, 0, $this->get_max_icons());
    }

    public function get_icon(): string
    {
        $urls  = $this->get_icon_urls();
        $title = esc_attr($this->get_title());
        $html  = '';

        foreach ($urls as $url) {
            $html .= '<img src="' . esc_url($url) . '" alt="' . $title . '" style="max-height:24px;margin-left:4px;vertical-align:middle;" />';
        }

        return apply_filters('woocommerce_gateway_icon', $html, $this->id);
    }

    /**
     * Render icons setting with multi-select Media Library picker.
     *
     * @param string $key Field key.
     * @param array  $data Field config.
     */
    public function generate_icons_html(string $key, array $data): string
    {
        $field_key = $this->get_field_key($key);
        $data      = wp_parse_args(
            $data,
            [
                'title'             => '',
                'disabled'          => false,
                'class'             => '',
                'css'               => '',
                'placeholder'       => '',
                'desc_tip'          => false,
                'description'       => '',
                'custom_attributes' => [],
            ]
        );
        $urls = $this->get_icon_urls();
        $value = implode("\n", $urls);
        $max_icons = $this->get_max_icons();

        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr($field_key); ?>"><?php echo wp_kses_post($data['title']); ?></label>
            </th>
            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text"><span><?php echo wp_kses_post($data['title']); ?></span></legend>
                    <textarea
                        class="input-text wide-input wc-colibrix-gateway-icons-input <?php echo esc_attr($data['class']); ?>"
                        name="<?php echo esc_attr($field_key); ?>"
                        id="<?php echo esc_attr($field_key); ?>"
                        rows="3"
                        style="display:none;"
                    ><?php echo esc_textarea($value); ?></textarea>
                    <div
                        class="wc-colibrix-gateway-icons-preview"
                        data-target="<?php echo esc_attr($field_key); ?>"
                        style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:8px;"
                    >
                        <?php foreach ($urls as $url) : ?>
                            <span class="wc-colibrix-gateway-icon-chip" data-url="<?php echo esc_attr($url); ?>" style="display:inline-flex;align-items:center;gap:4px;border:1px solid #c3c4c7;border-radius:4px;padding:4px 6px;background:#fff;">
                                <img src="<?php echo esc_url($url); ?>" alt="" style="max-height:24px;" />
                                <button type="button" class="button-link-delete wc-colibrix-gateway-remove-icon" aria-label="<?php esc_attr_e('Remove icon', 'wc-colibrix-gateway-payment'); ?>">×</button>
                            </span>
                        <?php endforeach; ?>
                    </div>
                    <button
                        type="button"
                        class="button wc-colibrix-gateway-upload-icons"
                        data-target="<?php echo esc_attr($field_key); ?>"
                        <?php disabled(count($urls) >= $max_icons); ?>
                    >
                        <?php esc_html_e('Add icons', 'wc-colibrix-gateway-payment'); ?>
                    </button>
                    <button
                        type="button"
                        class="button wc-colibrix-gateway-clear-icons"
                        data-target="<?php echo esc_attr($field_key); ?>"
                        <?php disabled($urls === []); ?>
                    >
                        <?php esc_html_e('Clear', 'wc-colibrix-gateway-payment'); ?>
                    </button>
                    <?php echo $this->get_description_html($data); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </fieldset>
            </td>
        </tr>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * Sanitize icons list on save.
     *
     * @param string $key Field key.
     * @param string|null $value Submitted value.
     */
    public function validate_icons_field(string $key, $value): string
    {
        unset($key);

        $urls = preg_split('/\r\n|\r|\n/', (string) $value) ?: [];
        $urls = array_values(array_filter(array_map(static function ($url): string {
            return esc_url_raw(trim((string) $url));
        }, $urls)));

        return implode("\n", array_slice($urls, 0, $this->get_max_icons()));
    }

    public function enqueue_admin_assets(string $hook): void
    {
        if ($hook !== 'woocommerce_page_wc-settings') {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $section = isset($_GET['section']) ? sanitize_text_field(wp_unslash((string) $_GET['section'])) : '';
        if ($section !== $this->id) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_script(
            'wc-colibrix-gateway-icon-settings',
            WC_COLIBRIX_GATEWAY_PLUGIN_URL . 'assets/js/admin/icon-settings.js',
            ['jquery'],
            '1.2.0',
            true
        );
        wp_localize_script(
            'wc-colibrix-gateway-icon-settings',
            'wcColibrixGatewayIconSettings',
            [
                'title'    => __('Select payment method icons', 'wc-colibrix-gateway-payment'),
                'button'   => __('Use selected images', 'wc-colibrix-gateway-payment'),
                'maxIcons' => $this->get_max_icons(),
                'remove'   => __('Remove icon', 'wc-colibrix-gateway-payment'),
            ]
        );
    }

    public function process_payment($order_id): array
    {
        $order = wc_get_order($order_id);
        if (! $order instanceof WC_Order) {
            wc_add_notice(__('Order not found.', 'wc-colibrix-gateway-payment'), 'error');
            return ['result' => 'failure'];
        }

        if ($this->project_id <= 0 || $this->api_key === '' || $this->signature_key === '') {
            wc_add_notice(__('Payment gateway is not configured.', 'wc-colibrix-gateway-payment'), 'error');
            return ['result' => 'failure'];
        }

        $tracking_id = (string) 'wc-order-'.$order->get_id();
        $payload     = $this->build_payment_payload($order, $tracking_id);
        $response    = $this->api_post($this->get_payment_endpoint(), $payload);

        if (is_wp_error($response)) {
            wc_add_notice($response->get_error_message(), 'error');
            return ['result' => 'failure'];
        }

        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        $redirect_url = $this->get_redirect_url($body);
        $uid = $this->get_response_uid($body);

        if (! is_array($body) || empty($redirect_url)) {
            $message = is_array($body) && ! empty($body['message'])
                ? (string) $body['message']
                : __('Unable to initialize payment.', 'wc-colibrix-gateway-payment');
            wc_add_notice($message, 'error');
            return ['result' => 'failure'];
        }

        if (! empty($uid)) {
            $this->save_uid($order, sanitize_text_field($uid));
        }
        $this->save_tracking_id($order, $tracking_id);

        if (! $order->is_paid() && $order->get_status() !== 'on-hold') {
            $order->update_status('on-hold', __('Colibrix Gateway: awaiting webhook confirmation.', 'wc-colibrix-gateway-payment'));
        }

        return [
            'result'   => 'success',
            'redirect' => $redirect_url,
        ];
    }

    public function handle_return(): void
    {
        $uid      = isset($_GET['uid']) ? sanitize_text_field(wp_unslash((string) $_GET['uid'])) : '';
        $order_id = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;
        $token    = isset($_GET['token']) ? sanitize_text_field(wp_unslash((string) $_GET['token'])) : '';
        $expires  = isset($_GET['expires']) ? absint($_GET['expires']) : 0;

        if ($order_id <= 0 && $uid !== '') {
            $order = $this->find_order_by_uid($uid);
            if ($order instanceof WC_Order) {
                $order_id = $order->get_id();
            }
        }

        if ($order_id <= 0) {
            wp_safe_redirect(wc_get_checkout_url());
            exit;
        }

        $order = wc_get_order($order_id);
        if (! $order instanceof WC_Order) {
            wp_safe_redirect(wc_get_checkout_url());
            exit;
        }

        if (! $this->is_valid_return_request($order, $token, $expires)) {
            wp_safe_redirect(wc_get_checkout_url());
            exit;
        }

        wp_safe_redirect($this->get_return_url($order));
        exit;
    }

    public function handle_notification(): void
    {
        $raw = file_get_contents('php://input');
        $sig = $this->get_request_signature();

        if (! is_string($raw) || $raw === '') {
            status_header(400);
            exit;
        }

        if ($sig === '' || ! hash_equals(hash('sha256', $raw . $this->signature_key), $sig)) {
            status_header(403);
            exit;
        }

        $data = json_decode($raw, true);
        if (! is_array($data)) {
            status_header(400);
            exit;
        }

        $normalized = $this->normalize_webhook_payload($data);
        $order      = null;

        if ($normalized['tracking_id'] !== '') {
            $order_id = $this->extract_order_id_from_tracking_id($normalized['tracking_id']);
            if ($order_id > 0) {
                $order = wc_get_order($order_id);
            }
        }

        if (! $order instanceof WC_Order && $normalized['uid'] !== '') {
            $order = $this->find_order_by_uid($normalized['uid']);
        }

        if (! $order instanceof WC_Order) {
            status_header(200);
            echo 'ok';
            exit;
        }

        if ($normalized['tracking_id'] !== '') {
            $this->save_tracking_id($order, $normalized['tracking_id']);
        }
        if ($normalized['uid'] !== '') {
            $this->save_uid($order, $normalized['uid']);
        }

        $this->apply_payment_status($order, [
            'status' => $normalized['status'],
            'uid'    => $normalized['uid'],
        ]);

        status_header(200);
        echo 'ok';
        exit;
    }

    public function process_refund($order_id, $amount = null, $reason = '')
    {
        $endpoint = $this->get_refund_endpoint();
        if ($endpoint === null) {
            return new WP_Error('colibrix_refund_not_supported', __('Refund is not supported for this payment method.', 'wc-colibrix-gateway-payment'));
        }

        $order = wc_get_order($order_id);
        if (! $order instanceof WC_Order) {
            return new WP_Error('colibrix_refund_order_not_found', __('Order not found.', 'wc-colibrix-gateway-payment'));
        }

        if ($order->get_payment_method() !== $this->id) {
            return new WP_Error('colibrix_refund_wrong_gateway', __('Order was not paid via this gateway.', 'wc-colibrix-gateway-payment'));
        }

        $parent_uid = $this->get_uid($order);
        if ($parent_uid === '') {
            return new WP_Error('colibrix_refund_no_uid', __('Cannot refund: payment UID is missing.', 'wc-colibrix-gateway-payment'));
        }

        if ($this->project_id <= 0 || $this->api_key === '' || $this->signature_key === '') {
            return new WP_Error('colibrix_refund_not_configured', __('Gateway API credentials are not configured.', 'wc-colibrix-gateway-payment'));
        }

        $amount = is_null($amount) ? (float) $order->get_total() : (float) $amount;
        if ($amount <= 0) {
            return new WP_Error('colibrix_refund_bad_amount', __('Refund amount must be greater than zero.', 'wc-colibrix-gateway-payment'));
        }

        $amount_minor = (int) round($amount * (10 ** wc_get_price_decimals()));
        if ($amount_minor <= 0) {
            return new WP_Error('colibrix_refund_bad_amount_minor', __('Refund amount in minor units must be greater than zero.', 'wc-colibrix-gateway-payment'));
        }

        $payload = [
            'project_id' => $this->project_id,
            'parent_uid' => $parent_uid,
            'amount'     => $amount_minor,
        ];
        if (is_string($reason) && trim($reason) !== '') {
            $payload['reason'] = substr(trim($reason), 0, 255);
        }

        $response = $this->api_post($endpoint, $payload);
        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        if (! is_array($body)) {
            return new WP_Error('colibrix_refund_invalid_response', __('Invalid refund response from API.', 'wc-colibrix-gateway-payment'));
        }

        $status = isset($body['status']) ? strtolower((string) $body['status']) : '';
        if ($status === 'failed') {
            $message = ! empty($body['message'])
                ? (string) $body['message']
                : __('Refund was rejected by payment API.', 'wc-colibrix-gateway-payment');
            return new WP_Error('colibrix_refund_failed', $message);
        }

        $refund_uid = isset($body['uid']) ? sanitize_text_field((string) $body['uid']) : '';
        $order->add_order_note(sprintf(
            __('Colibrix Gateway refund requested. Amount: %1$s. Parent UID: %2$s. Refund UID: %3$s. Status: %4$s', 'wc-colibrix-gateway-payment'),
            wc_format_decimal($amount, wc_get_price_decimals()),
            $parent_uid,
            $refund_uid !== '' ? $refund_uid : '-',
            $status !== '' ? $status : 'unknown'
        ));

        return true;
    }

    public function render_admin_payment_info($order): void
    {
        if (! $order instanceof WC_Order) {
            return;
        }

        if ($order->get_payment_method() !== $this->id) {
            return;
        }

        $uid        = $this->get_uid($order);
        $trackingId = $this->get_tracking_id($order);

        echo '<p><strong>' . esc_html__('Tracing ID', 'wc-colibrix-gateway-payment') . ':</strong> ' . esc_html($trackingId !== '' ? $trackingId : '-') . '</p>';
        echo '<p><strong>' . esc_html__('Payment UID', 'wc-colibrix-gateway-payment') . ':</strong> ' . esc_html($uid !== '' ? $uid : '-') . '</p>';
    }

    protected function api_post(string $path, array $payload)
    {
        $json = wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (! is_string($json)) {
            return new WP_Error('colibrix_json_encode', __('Cannot encode request payload.', 'wc-colibrix-gateway-payment'));
        }

        $headers = [
            'Content-Type' => 'application/json',
            'X-API-KEY'    => $this->api_key,
            'X-Signature'  => hash('sha256', $json . $this->signature_key),
        ];
        $this->log('debug', 'API POST request', [
            'url'     => $this->api_base_url . $path,
            'payload' => $payload,
            'json'    => $json,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-API-KEY'    => $this->mask_secret($this->api_key),
                'X-Signature'  => $this->mask_secret($headers['X-Signature']),
            ],
        ]);

        $response = wp_remote_post($this->api_base_url . $path, [
            'timeout' => 30,
            'headers' => $headers,
            'body'    => $json,
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('colibrix_request_error', $response->get_error_message());
        }

        $code     = (int) wp_remote_retrieve_response_code($response);
        $raw_body = (string) wp_remote_retrieve_body($response);
        $this->log('debug', 'API POST response', [
            'url'      => $this->api_base_url . $path,
            'code'     => $code,
            'body'     => json_decode($raw_body, true),
            'body_raw' => $raw_body,
        ]);

        if ($code < 200 || $code > 299) {
            $body    = json_decode($raw_body, true);
            $message = is_array($body) && ! empty($body['message'])
                ? (string) $body['message']
                : __('Payment API request failed.', 'wc-colibrix-gateway-payment');
            return new WP_Error('colibrix_bad_status', $message);
        }

        return $response;
    }

    protected function apply_payment_status(WC_Order $order, array $data): void
    {
        $status = isset($data['status']) ? strtolower((string) $data['status']) : '';
        $uid    = isset($data['uid']) ? sanitize_text_field((string) $data['uid']) : '';
        $is_paid = $order->is_paid();

        if ($uid !== '') {
            $this->save_uid($order, $uid);
        }

        if (in_array($status, ['success', 'settlement in progress'], true)) {
            if (! $is_paid) {
                $order->payment_complete($uid);
                $order->add_order_note(__('Colibrix Gateway: payment successful.', 'wc-colibrix-gateway-payment'));
            }
            return;
        }

        if ($status === 'failed') {
            if ($is_paid) {
                $order->add_order_note(__('Colibrix Gateway: ignored failed webhook because the order is already paid.', 'wc-colibrix-gateway-payment'));
                return;
            }

            $order->update_status('failed', __('Colibrix Gateway: payment failed.', 'wc-colibrix-gateway-payment'));
            return;
        }

        if (in_array($status, ['pending', 'new'], true) && $order->get_status() !== 'on-hold') {
            $order->update_status('on-hold', __('Colibrix Gateway: waiting for payment confirmation.', 'wc-colibrix-gateway-payment'));
        }
    }

    protected function normalize_webhook_payload(array $data): array
    {
        $tracking_id = '';
        $uid         = '';
        $status      = '';

        if (! empty($data['tracking_id'])) {
            $tracking_id = (string) $data['tracking_id'];
        } elseif (! empty($data['order']['tracking_id'])) {
            $tracking_id = (string) $data['order']['tracking_id'];
        } elseif (! empty($data['transaction']['tracking_id'])) {
            $tracking_id = (string) $data['transaction']['tracking_id'];
        }

        if (! empty($data['uid'])) {
            $uid = (string) $data['uid'];
        } elseif (! empty($data['transaction']['uid'])) {
            $uid = (string) $data['transaction']['uid'];
        }

        if (! empty($data['status'])) {
            $status = (string) $data['status'];
        } elseif (! empty($data['transaction']['status'])) {
            $status = (string) $data['transaction']['status'];
        } elseif (! empty($data['payment']['status'])) {
            $status = (string) $data['payment']['status'];
        }

        return [
            'tracking_id' => sanitize_text_field($tracking_id),
            'uid'         => sanitize_text_field($uid),
            'status'      => sanitize_text_field($status),
        ];
    }

    protected function find_order_by_uid(string $uid): ?WC_Order
    {
        $uid = sanitize_text_field($uid);
        return $this->find_order_by_meta(self::META_UID, $uid);
    }

    protected function find_order_by_meta(string $meta_key, string $meta_value): ?WC_Order
    {
        $orders = wc_get_orders([
            'limit'      => 1,
            'meta_key'   => $meta_key,
            'meta_value' => $meta_value,
            'return'     => 'objects',
        ]);

        if (! empty($orders) && $orders[0] instanceof WC_Order) {
            return $orders[0];
        }

        return null;
    }

    protected function save_uid(WC_Order $order, string $uid): void
    {
        $uid = sanitize_text_field($uid);
        if ($uid === '') {
            return;
        }

        $order->update_meta_data(self::META_UID, $uid);
        $order->save();
    }

    protected function save_tracking_id(WC_Order $order, string $tracking_id): void
    {
        $tracking_id = sanitize_text_field($tracking_id);
        if ($tracking_id === '') {
            return;
        }

        $order->update_meta_data(self::META_TRACKING_ID, $tracking_id);
        $order->save();
    }

    protected function get_uid(WC_Order $order): string
    {
        return (string) $order->get_meta(self::META_UID);
    }

    protected function get_tracking_id(WC_Order $order): string
    {
        return (string) $order->get_meta(self::META_TRACKING_ID);
    }

    protected function extract_order_id_from_tracking_id(string $tracking_id): int
    {
        if (preg_match('/^\d+$/', $tracking_id)) {
            return absint($tracking_id);
        }

        if (preg_match('/^wc-order-(\d+)$/', $tracking_id, $matches)) {
            return absint($matches[1]);
        }

        return 0;
    }

    protected function filter_empty(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $item = $this->filter_empty($item);
                if ($item === []) {
                    unset($value[$key]);
                    continue;
                }
                $value[$key] = $item;
                continue;
            }

            if ($item === null || $item === '') {
                unset($value[$key]);
            }
        }

        return $value;
    }

    protected function log(string $level, string $message, array $context = []): void
    {
        if ($level === 'debug' && $this->debug_mode !== 'yes') {
            return;
        }

        if (! function_exists('wc_get_logger')) {
            return;
        }

        wc_get_logger()->log($level, $message . ' ' . wp_json_encode($context), ['source' => $this->id]);
    }

    protected function mask_secret(string $value): string
    {
        $length = strlen($value);
        if ($length <= 8) {
            return str_repeat('*', $length);
        }

        return substr($value, 0, 4) . str_repeat('*', $length - 8) . substr($value, -4);
    }

    protected function build_return_url(WC_Order $order): string
    {
        $expires = time() + self::RETURN_TOKEN_TTL;
        $token   = $this->generate_return_token($order, $expires);

        return add_query_arg(
            [
                'wc-api'   => $this->get_return_api_key(),
                'order_id' => $order->get_id(),
                'expires'  => $expires,
                'token'    => $token,
            ],
            home_url('/')
        );
    }

    protected function is_valid_return_request(WC_Order $order, string $token, int $expires): bool
    {
        if ($token === '' || $expires <= 0 || $expires < time()) {
            return false;
        }

        return hash_equals($this->generate_return_token($order, $expires), $token);
    }

    protected function generate_return_token(WC_Order $order, int $expires): string
    {
        $payload = implode('|', [
            (string) $order->get_id(),
            (string) $order->get_order_key(),
            $this->id,
            (string) $expires,
        ]);

        return hash_hmac('sha256', $payload, $this->signature_key);
    }

    protected function get_request_signature(): string
    {
        $candidates = [
            $_SERVER['HTTP_X_SIGNATURE'] ?? null,
            $_SERVER['X-Signature'] ?? null,
            $_SERVER['X_SIGNATURE'] ?? null,
        ];

        foreach ($candidates as $value) {
            if (is_string($value) && $value !== '') {
                return sanitize_text_field($value);
            }
        }

        return '';
    }

    protected function get_return_api_key(): string
    {
        return 'wc_colibrix_gateway_' . $this->id . '_return';
    }

    protected function get_notify_api_key(): string
    {
        return 'wc_colibrix_gateway_' . $this->id . '_notify';
    }
}
