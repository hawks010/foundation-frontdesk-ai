<?php
if (!defined('ABSPATH')) { exit; }

class Frontdesk_Rate_Limit {
    public static function check(string $action, int $limit, int $window): bool {
        $key = 'frontdesk_rl_' . md5($action . '|' . self::fingerprint());
        $bucket = get_transient($key);
        if (!is_array($bucket)) {
            $bucket = [
                'count' => 0,
                'expires' => time() + $window,
            ];
        }

        if (($bucket['expires'] ?? 0) < time()) {
            $bucket = [
                'count' => 0,
                'expires' => time() + $window,
            ];
        }

        if (($bucket['count'] ?? 0) >= $limit) {
            return false;
        }

        $bucket['count'] = (int) ($bucket['count'] ?? 0) + 1;
        set_transient($key, $bucket, $window);
        return true;
    }

    private static function fingerprint(): string {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
        $session = isset($_COOKIE[LOGGED_IN_COOKIE]) ? (string) $_COOKIE[LOGGED_IN_COOKIE] : '';
        return hash('sha256', $ip . '|' . $ua . '|' . $session . '|' . wp_salt('nonce'));
    }
}
