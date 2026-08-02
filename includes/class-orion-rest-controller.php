<?php
if (!defined('ABSPATH')) { exit; }

final class Orion_REST_Controller {
    private Orion_Chat_Orchestrator $chat;
    private Orion_Rate_Limiter $rate_limiter;
    private Orion_Conversation_Service $conversations;

    public function __construct(Orion_Chat_Orchestrator $chat, Orion_Rate_Limiter $rate_limiter, Orion_Conversation_Service $conversations) {
        $this->chat = $chat;
        $this->rate_limiter = $rate_limiter;
        $this->conversations = $conversations;
    }

    public function register_routes(): void {
        register_rest_route('orion-ai/v1', '/chat', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'chat'),
            'permission_callback' => '__return_true',
            'args' => array(
                'message' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_textarea_field',
                    'validate_callback' => array($this, 'validate_message'),
                ),
                'session' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => array($this, 'validate_session'),
                ),
            ),
        ));
        register_rest_route('orion-ai/v1', '/cart', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'cart'),
            'permission_callback' => '__return_true',
            'args' => array(
                'product_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                    'validate_callback' => static fn($value) => absint($value) > 0,
                ),
                'quantity' => array(
                    'required' => false,
                    'default' => 1,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                    'validate_callback' => static fn($value) => absint($value) >= 1 && absint($value) <= 99,
                ),
            ),
        ));
    }

    public function chat(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $session = (string) $request->get_param('session');
        if ($session === '') $session = (string) ($_COOKIE['orion_ai_session'] ?? '');
        $result = $this->chat->respond((string) $request->get_param('message'), $session);
        if (is_wp_error($result)) return $result;

        $response = new WP_REST_Response($result, 200);
        $this->set_session_cookie((string) $result['session']);
        return $response;
    }

    public function cart(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $settings = Orion_AI_Settings::get();
        $rate = $this->rate_limiter->check($settings, 'cart');
        if (is_wp_error($rate)) return $rate;
        if (!class_exists('WooCommerce')) {
            return new WP_Error('woocommerce_missing', 'WooCommerce is unavailable.', array('status' => 503));
        }

        $product_id = absint($request->get_param('product_id'));
        $quantity = max(1, min(99, absint($request->get_param('quantity') ?: 1)));
        $product = wc_get_product($product_id);
        if (!$product || !$product->is_type('simple') || !$product->is_purchasable() || !$product->is_in_stock()) {
            return new WP_Error('not_purchasable', 'Please choose options on the product page.', array('status' => 400));
        }
        if (function_exists('wc_load_cart') && !WC()->cart) wc_load_cart();
        $key = WC()->cart->add_to_cart($product_id, $quantity);
        if (!$key) {
            return new WP_Error('cart_error', 'The product could not be added to the basket.', array('status' => 400));
        }

        $this->conversations->record_event('cart_added', array('product_id' => $product_id, 'quantity' => $quantity));
        return new WP_REST_Response(array(
            'ok' => true,
            'message' => 'Added to basket.',
            'cartUrl' => wc_get_cart_url(),
            'count' => WC()->cart->get_cart_contents_count(),
        ), 200);
    }

    public function validate_message($value) {
        $length = is_scalar($value) ? mb_strlen(trim((string) $value)) : 0;
        return $length > 0 && $length <= 1200;
    }

    public function validate_session($value) {
        if ($value === null || $value === '') return true;
        return is_scalar($value) && (bool) preg_match('/^[a-f0-9-]{0,36}$/i', (string) $value);
    }

    private function set_session_cookie(string $session): void {
        if ($session === '' || headers_sent()) return;
        setcookie('orion_ai_session', $session, array(
            'expires' => time() + DAY_IN_SECONDS,
            'path' => COOKIEPATH ?: '/',
            'domain' => COOKIE_DOMAIN,
            'secure' => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ));
    }
}
