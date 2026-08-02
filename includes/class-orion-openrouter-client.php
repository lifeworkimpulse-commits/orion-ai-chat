<?php
if (!defined('ABSPATH')) { exit; }

final class Orion_OpenRouter_Client {
    private const ENDPOINT = 'https://openrouter.ai/api/v1/chat/completions';
    private string $api_key;
    private string $model;

    public function __construct(string $api_key, string $model) {
        $this->api_key = trim($api_key);
        $this->model = trim($model) ?: 'openrouter/auto';
    }

    public function chat(array $messages, array $tools = array()): array {
        if ($this->api_key === '') {
            return array('ok' => false, 'error' => 'OpenRouter API key is not configured.');
        }

        $body = array(
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => 0.2,
            'max_tokens' => 900,
        );
        if ($tools) {
            $body['tools'] = $tools;
            $body['tool_choice'] = 'auto';
            $body['parallel_tool_calls'] = false;
        }

        $response = wp_remote_post(self::ENDPOINT, array(
            'timeout' => 60,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
                'HTTP-Referer' => home_url('/'),
                'X-OpenRouter-Title' => get_bloginfo('name') . ' AI Assistant',
            ),
            'body' => wp_json_encode($body),
        ));

        if (is_wp_error($response)) {
            return array('ok' => false, 'error' => $response->get_error_message());
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        $json = json_decode(wp_remote_retrieve_body($response), true);
        if ($code < 200 || $code >= 300) {
            $message = is_array($json) ? ($json['error']['message'] ?? '') : '';
            $message = trim(wp_strip_all_tags((string) $message));
            if ($message === '') $message = 'OpenRouter returned HTTP ' . $code . '.';
            return array('ok' => false, 'error' => sanitize_text_field($message));
        }
        $message = $json['choices'][0]['message'] ?? null;
        if (!is_array($message)) {
            return array('ok' => false, 'error' => 'OpenRouter returned an invalid response.');
        }
        return array('ok' => true, 'message' => $message, 'usage' => $json['usage'] ?? array());
    }
}
