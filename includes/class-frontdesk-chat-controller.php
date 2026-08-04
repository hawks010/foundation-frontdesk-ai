<?php
if (!defined('ABSPATH')) { exit; }

class Frontdesk_Chat_Controller {
    public static function init(): void {
        add_action('wp_ajax_fnd_frontdesk_chat', [__CLASS__, 'handle']);
        add_action('wp_ajax_nopriv_fnd_frontdesk_chat', [__CLASS__, 'handle']);
        add_action('wp_ajax_fnd_frontdesk_chat', [__CLASS__, 'handle']);
        add_action('wp_ajax_nopriv_fnd_frontdesk_chat', [__CLASS__, 'handle']);
    }

    public static function handle(): void {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field((string) $_POST['nonce']) : '';
        if (!wp_verify_nonce($nonce, 'fnd_frontdesk_chat') && !wp_verify_nonce($nonce, 'fnd_frontdesk_chat')) {
            wp_send_json(Frontdesk_Response::error(__('Sorry, your session expired. Please refresh and try again.', 'foundation-frontdesk'), [
                'error_code' => 'invalid_nonce',
            ]), 403);
        }

        if (!Frontdesk_Rate_Limit::check('chat', 20, 10 * MINUTE_IN_SECONDS)) {
            wp_send_json(Frontdesk_Response::error(__('You have sent a lot of messages in a short time. Please wait a moment and try again.', 'foundation-frontdesk'), [
                'error_code' => 'rate_limited',
            ]), 429);
        }

        $config = Frontdesk_Config::get_all();
        $message = isset($_POST['message']) ? sanitize_textarea_field((string) $_POST['message']) : '';
        $message = trim($message);
        if ($message === '') {
            wp_send_json(Frontdesk_Response::error(__('Please enter a message first.', 'foundation-frontdesk'), [
                'error_code' => 'empty_message',
                'fallback_used' => false,
            ]), 422);
        }

        if (function_exists('mb_substr')) {
            $message = mb_substr($message, 0, (int) $config['max_input_chars']);
        } else {
            $message = substr($message, 0, (int) $config['max_input_chars']);
        }

        $request = [
            'message' => $message,
            'frontdesktion_id' => sanitize_key((string) ($_POST['frontdesktion_id'] ?? wp_generate_uuid4())),
            'page_url' => esc_url_raw((string) ($_POST['page_url'] ?? '')),
            'page_title' => sanitize_text_field((string) ($_POST['page_title'] ?? '')),
            'instance_id' => sanitize_key((string) ($_POST['instance_id'] ?? '')),
        ];

        $context = Frontdesk_Knowledge::search($message, $config);
        $provider = self::provider($config);
        $response = $provider->answer($request, $context, $config);

        if (!$response['ok'] && !empty($config['fallback_enabled']) && !($provider instanceof Frontdesk_Offline_Provider)) {
            $fallback = new Frontdesk_Offline_Provider();
            $response = $fallback->answer($request, $context, $config);
            $response['fallback_used'] = true;
            $response['error_code'] = $response['error_code'] ?? 'fallback_used';
        }

        wp_send_json($response, !empty($response['ok']) ? 200 : 500);
    }

    private static function provider(array $config): Frontdesk_AI_Provider {
        $provider = Frontdesk_Config::provider();
        $runtime_mode = Frontdesk_Config::runtime_mode();

        if (!empty($config['force_offline']) || $provider === 'offline' || in_array($runtime_mode, ['offline', 'faq_only', 'site_index'], true)) {
            return new Frontdesk_Offline_Provider();
        }

        if ($runtime_mode === 'ai_site_index' && $provider === 'openai' && Frontdesk_Config::has_openai_key()) {
            return new Frontdesk_OpenAI_Provider();
        }

        return new Frontdesk_Offline_Provider();
    }
}
