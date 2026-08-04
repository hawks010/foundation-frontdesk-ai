<?php
if (!defined('ABSPATH')) { exit; }

class Frontdesk_Response {
    public static function success(string $answer, array $args = []): array {
        return wp_parse_args($args, [
            'ok' => true,
            'answer' => $answer,
            'provider' => 'offline',
            'mode' => 'offline',
            'sources' => [],
            'actions' => [],
            'request_id' => wp_generate_uuid4(),
            'error_code' => null,
            'fallback_used' => false,
        ]);
    }

    public static function error(string $answer, array $args = []): array {
        return wp_parse_args($args, [
            'ok' => false,
            'answer' => $answer,
            'provider' => 'offline',
            'mode' => 'offline',
            'sources' => [],
            'actions' => [
                ['type' => 'contact', 'label' => __('Contact us', 'foundation-frontdesk')],
            ],
            'request_id' => wp_generate_uuid4(),
            'error_code' => 'unknown',
            'fallback_used' => true,
        ]);
    }
}
