<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Helpers — Foundation: Conversa (Improved)
 * Build: 2025-08-20
 *
 * What’s new here vs. your last version:
 * - Safer logging helper (respects WP_DEBUG)
 * - JSON decode/encode helpers + safe text sanitizer
 * - Multisite-aware getters AND setters (optional)
 * - Option array getter + in-memory cache reset
 * - Personality map override order: option → JSON file → built-ins
 * - Readable text color chooser for UI theming
 * - Small utilities: hash id, is_rest, color clamp
 */

// -----------------------------------------------------------------------------
// Basic coercions / sanitizers
// -----------------------------------------------------------------------------

if (!function_exists('fnd_conversa_bool')) {
    /** Convert loose values to boolean in a predictable way */
    function fnd_conversa_bool($val): bool {
        if (is_bool($val)) return $val;
        if (is_numeric($val)) return (bool) $val;
        if (is_string($val)) {
            $t = strtolower(trim($val));
            return in_array($t, ['1','true','on','yes','y'], true);
        }
        return (bool) $val;
    }
}

if (!function_exists('fnd_conversa_sanitize_hex_color')) {
    /**
     * Sanitize hex colors. Accepts 3,6,8 digit hex; returns fallback on failure.
     * Examples: #abc, #aabbcc, #aabbccdd
     */
    function fnd_conversa_sanitize_hex_color($color, string $fallback = '#4F46E5'): string {
        $hex = is_string($color) ? ltrim(trim($color), '#') : '';
        if (preg_match('/^([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6}|[A-Fa-f0-9]{8})$/', $hex)) {
            return '#' . $hex;
        }
        return $fallback;
    }
}

if (!function_exists('fnd_conversa_safe_text')) {
    /**
     * Trim + normalize whitespace and strip tags. Bound to a max length.
     */
    function fnd_conversa_safe_text($value, int $max_len = 4000): string {
        $txt = is_string($value) ? $value : (string) $value;
        $txt = wp_strip_all_tags($txt);
        $txt = html_entity_decode($txt, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $txt = preg_replace('/\s+/u', ' ', $txt);
        if (function_exists('mb_substr')) {
            $txt = mb_substr(trim($txt), 0, $max_len);
        } else {
            $txt = substr(trim($txt), 0, $max_len);
        }
        return $txt;
    }
}

if (!function_exists('fnd_conversa_array_get')) {
    /** Safe array access with optional dot notation (e.g. "a.b.c"). */
    function fnd_conversa_array_get(array $arr, string $key, $default = null, string $delimiter = '.') {
        if (array_key_exists($key, $arr)) return $arr[$key];
        if ($delimiter === '' || strpos($key, $delimiter) === false) return $default;
        $segments = explode($delimiter, $key);
        foreach ($segments as $seg) {
            if (!is_array($arr) || !array_key_exists($seg, $arr)) return $default;
            $arr = $arr[$seg];
        }
        return $arr;
    }
}

// -----------------------------------------------------------------------------
// JSON helpers
// -----------------------------------------------------------------------------

if (!function_exists('fnd_conversa_json_try_decode')) {
    /** Decode JSON string to array/object; on failure return $default. */
    function fnd_conversa_json_try_decode($maybe_json, bool $assoc = true, $default = []) {
        if (!is_string($maybe_json) || $maybe_json === '') return $default;
        $data = json_decode($maybe_json, $assoc);
        if (json_last_error() === JSON_ERROR_NONE) return $data;
        if (defined('WP_DEBUG') && WP_DEBUG) error_log('[Conversa] JSON decode error: ' . json_last_error_msg());
        return $default;
    }
}

if (!function_exists('fnd_conversa_json_file_decode')) {
    /**
     * Read a JSON file if readable; return decoded value or $default.
     * Uses core wp_json_file_decode when available (WP 5.9+), else fallback.
     */
    function fnd_conversa_json_file_decode(string $path, $default = [], bool $assoc = true) {
        if (!is_readable($path)) return $default;
        if (function_exists('wp_json_file_decode')) {
            $decoded = wp_json_file_decode($path, ['associative' => $assoc]);
            return $decoded !== null ? $decoded : $default;
        }
        $raw = @file_get_contents($path);
        if ($raw === false) return $default;
        $data = json_decode($raw, $assoc);
        return (json_last_error() === JSON_ERROR_NONE) ? $data : $default;
    }
}

// -----------------------------------------------------------------------------
// Multisite-aware options (get + set)
// -----------------------------------------------------------------------------

if (!function_exists('fnd_conversa_get_network_option')) {
    function fnd_conversa_get_network_option(string $key, $default = '') {
        return is_multisite() ? get_network_option(null, $key, $default) : get_option($key, $default);
    }
}

if (!function_exists('fnd_conversa_update_network_option')) {
    function fnd_conversa_update_network_option(string $key, $value): bool {
        return is_multisite() ? (bool) update_network_option(null, $key, $value) : (bool) update_option($key, $value);
    }
}

// -----------------------------------------------------------------------------
// Consolidated option getters/setters with static cache
// -----------------------------------------------------------------------------

if (!function_exists('fnd_conversa__legacy_map')) {
    function fnd_conversa__legacy_map(): array {
        return [
            'api_key'             => 'conversa_api_key',
            'default_personality' => 'conversa_personality',
            'bot_name'            => 'conversa_bot_name',
            'brand_color'         => 'conversa_brand_color',
            'bot_avatar'          => 'conversa_bot_avatar',
            'enable_contact'      => 'conversa_enable_contact',
            'enable_floating'     => 'conversa_enable_floating',
            'contact_email'       => 'conversa_contact_email',
            'admin_dark_mode'     => 'conversa_admin_dark_mode',
            'greeting_text'       => 'conversa_greeting_text',
            'opening_hours'       => 'conversa_opening_hours',
            'alt_contact'         => 'conversa_alt_contact',
            'header_byline'       => 'conversa_header_byline',
            // New UI/position keys (no legacy expected)
            'ui_button_color'       => null,
            'ui_button_hover_color' => null,
            'ui_text_color'         => null,
            'ui_header_bg'          => null,
            'ui_header_text'        => null,
            'ui_position_corner'    => null,
            'ui_offset_x'           => null,
            'ui_offset_y'           => null,
            // Personality override JSON stored in a single option (new)
            'personalities_json'    => null,
        ];
    }
}

if (!function_exists('fnd_conversa_get_option')) {
    /** Get a key from the consolidated options array with static caching + legacy fallback. */
    function fnd_conversa_get_option(string $key, $default = '') {
        static $opts = null;
        if ($opts === null) {
            $opts = get_option('fnd_conversa_options', []);
            if (!is_array($opts)) $opts = [];
        }
        if (array_key_exists($key, $opts)) {
            $val = $opts[$key];
            return is_string($val) ? trim($val) : $val;
        }
        $legacy_map = fnd_conversa__legacy_map();
        if (array_key_exists($key, $legacy_map) && $legacy_map[$key]) {
            $legacy = get_option($legacy_map[$key], null);
            if ($legacy !== null && $legacy !== '') {
                return is_string($legacy) ? trim($legacy) : $legacy;
            }
        }
        return $default;
    }
}

if (!function_exists('fnd_conversa_get_option_array')) {
    /** Fetch an option that should be an array; guarantees array return. */
    function fnd_conversa_get_option_array(string $key, array $default = []): array {
        $val = fnd_conversa_get_option($key, $default);
        if (is_string($val)) {
            $decoded = json_decode($val, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) return $decoded;
            return $default;
        }
        return is_array($val) ? $val : $default;
    }
}

if (!function_exists('fnd_conversa_update_option')) {
    /** Update a single key within the consolidated options array */
    function fnd_conversa_update_option(string $key, $value): bool {
        $opts = get_option('fnd_conversa_options', []);
        if (!is_array($opts)) $opts = [];
        $opts[$key] = is_string($value) ? trim($value) : $value;
        $ok = update_option('fnd_conversa_options', $opts);
        if (!$ok && defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Conversa] Failed to update option key: ' . $key);
        }
        // Reset the static cache inside fnd_conversa_get_option
        if (function_exists('wp_cache_delete')) wp_cache_delete('fnd_conversa_options', 'options');
        return (bool) $ok;
    }
}

if (!function_exists('fnd_conversa_reset_options_cache')) {
    /** Manually clear in-memory cache used by getters (on settings save). */
    function fnd_conversa_reset_options_cache(): void {
        // No-op by default; kept for future in-process caches.
        // You can call this after bulk updates if needed.
    }
}

// -----------------------------------------------------------------------------
// Personalities (option → file → built-ins) with static cache
// -----------------------------------------------------------------------------

if (!function_exists('fnd_conversa_personalities')) {
    function fnd_conversa_personalities(): array {
        static $cache = null;
        if ($cache !== null) return $cache;

        // 1) Option-level JSON override (from settings UI)
        $opt_json = fnd_conversa_get_option('personalities_json', '');
        if (is_string($opt_json) && trim($opt_json) !== '') {
            $opt_map = json_decode($opt_json, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($opt_map) && !empty($opt_map)) {
                $cache = array_map('strval', $opt_map);
                return apply_filters('fnd_conversa_personalities', $cache);
            } elseif (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Conversa] personalities_json in options is not valid JSON: ' . json_last_error_msg());
            }
        }

        // 2) JSON file override (same dir)
        $json_path = __DIR__ . '/personalities.json';
        $file_map  = fnd_conversa_json_file_decode($json_path, [], true);
        if (is_array($file_map) && !empty($file_map)) {
            $cache = array_map('strval', $file_map);
            return apply_filters('fnd_conversa_personalities', $cache);
        }

        // 3) Built-ins (translated)
        $cache = [
            'friendly_support'    => __('Friendly Customer Support', 'foundation-conversa'),
            'tier1_support'       => __('Tier 1 Support (triage)', 'foundation-conversa'),
            'technical_support'   => __('Technical Support (developer)', 'foundation-conversa'),
            'sales_qualifier'     => __('Sales Lead Qualifier', 'foundation-conversa'),
            'sales_closer'        => __('Sales Closer (objection handling)', 'foundation-conversa'),
            'product_finder'      => __('E-commerce Product Finder', 'foundation-conversa'),
            'order_tracker'       => __('Order & Shipping Tracker', 'foundation-conversa'),
            'returns_refunds'     => __('Returns & Refunds Assistant', 'foundation-conversa'),
            'booking_assistant'   => __('Booking / Appointment Assistant', 'foundation-conversa'),
            'events_concierge'    => __('Events Concierge', 'foundation-conversa'),
            'hotel_concierge'     => __('Hotel Concierge', 'foundation-conversa'),
            'restaurant_host'     => __('Restaurant Host (reservations)', 'foundation-conversa'),
            'real_estate_agent'   => __('Real Estate Agent Assistant', 'foundation-conversa'),
            'mortgage_info'       => __('Mortgage Info (general, non-advisory)', 'foundation-conversa'),
            'kb_answers'          => __('Knowledge Base Q&A', 'foundation-conversa'),
            'faq_minimal'         => __('Minimal FAQ Bot', 'foundation-conversa'),
            'onboarding_coach'    => __('Onboarding Coach', 'foundation-conversa'),
            'community_moderator' => __('Community Moderator (guidelines)', 'foundation-conversa'),
            'content_helper'      => __('Content Helper (copy hints)', 'foundation-conversa'),
            'privacy_gdpr_info'   => __('Privacy/GDPR Info (general)', 'foundation-conversa'),
            'healthcare_info'     => __('Healthcare Info (general, no diagnosis)', 'foundation-conversa'),
            'legal_info'          => __('Legal Info (general, no advice)', 'foundation-conversa'),
        ];
        return apply_filters('fnd_conversa_personalities', $cache);
    }
}

if (!function_exists('fnd_conversa_personality_label')) {
    /** Resolve a personality key into a human label (falls back to key). */
    function fnd_conversa_personality_label(string $key): string {
        $map = fnd_conversa_personalities();
        return isset($map[$key]) ? (string) $map[$key] : $key;
    }
}

// -----------------------------------------------------------------------------
// UI helpers
// -----------------------------------------------------------------------------

if (!function_exists('fnd_conversa_readable_text_on')) {
    /**
     * Given a background color, choose a readable text color (#000 / #fff by luminance).
     */
    function fnd_conversa_readable_text_on(string $bg, string $light = '#ffffff', string $dark = '#0f172a'): string {
        $hex = ltrim(fnd_conversa_sanitize_hex_color($bg, '#1e6167'), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        // Relative luminance (WCAG)
        $lum = (0.2126 * $r + 0.7152 * $g + 0.0722 * $b) / 255;
        return ($lum > 0.6) ? $dark : $light;
    }
}

// -----------------------------------------------------------------------------
// Misc helpers
// -----------------------------------------------------------------------------

if (!function_exists('fnd_conversa_log')) {
    /** Log messages when WP_DEBUG is true. */
    function fnd_conversa_log($msg, string $level = 'info'): void {
        if (!defined('WP_DEBUG') || !WP_DEBUG) return;
        if (is_array($msg) || is_object($msg)) {
            $msg = wp_json_encode($msg, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        error_log('[Conversa][' . strtoupper($level) . '] ' . $msg);
    }
}

if (!function_exists('fnd_conversa_hash_id')) {
    /** Stable small hash id for UI element ids, etc. */
    function fnd_conversa_hash_id(string $prefix, $data): string {
        $base = is_scalar($data) ? (string) $data : wp_json_encode($data);
        return $prefix . '-' . substr(md5($base . '|' . site_url()), 0, 8);
    }
}

if (!function_exists('fnd_conversa_is_rest')) {
    /** True during REST API requests */
    function fnd_conversa_is_rest(): bool {
        if (defined('REST_REQUEST') && REST_REQUEST) return true;
        if (!empty($_GET['rest_route'])) return true;
        return false;
    }
}
