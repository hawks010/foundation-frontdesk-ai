<?php
/*
Plugin Name: Foundation: Frontdesk AI
Plugin URI: https://github.com/hawks010/foundation-frontdesk-ai
Description: A minimal, accessible chat & contact widget. Self-hosted, fast, and private—route messages to your inbox or (optionally) your AI assistant. Part of the Foundation plugin series by Inkfire Limited.
Version: 1.0.13
Author: Sonny x Inkfire
Author URI: https://inkfire.co.uk/
Text Domain: foundation-frontdesk
Domain Path: /languages
Requires at least: 5.5
Tested up to: 6.9
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Update URI: https://github.com/hawks010/foundation-frontdesk-ai
*/

if (!defined('ABSPATH')) exit;

// -----------------------------------------------------------------------------
// Core constants
// -----------------------------------------------------------------------------
if (!defined('FND_CONVERSA_VERSION')) define('FND_CONVERSA_VERSION', '1.0.13');
if (!defined('FND_CONVERSA_FILE'))    define('FND_CONVERSA_FILE', __FILE__);
if (!defined('FND_CONVERSA_PATH'))    define('FND_CONVERSA_PATH', plugin_dir_path(__FILE__));
if (!defined('FND_CONVERSA_URL'))     define('FND_CONVERSA_URL',  plugin_dir_url(__FILE__));
if (!defined('FND_CONVERSA_CORE_SLUG')) define('FND_CONVERSA_CORE_SLUG', 'foundation-frontdesk-ai');
if (!defined('FND_CONVERSA_MIN_CORE'))  define('FND_CONVERSA_MIN_CORE', '0.1.0');

// Provide the main plugin file path for hooks inside included classes (e.g., onboarding)
if (!defined('FP_PLUGIN_FILE'))       define('FP_PLUGIN_FILE', __FILE__);

// Enable the floating launcher globally (footer) by default.
// You can comment this out if you want to control it elsewhere.
if (!defined('FOUNDATION_CONVERSA_AUTO')) {
    define('FOUNDATION_CONVERSA_AUTO', true);
}

// -----------------------------------------------------------------------------
// i18n
// -----------------------------------------------------------------------------
add_action('init', function () {
    load_plugin_textdomain(
        'foundation-frontdesk',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );
});

// -----------------------------------------------------------------------------
// Includes
// -----------------------------------------------------------------------------
require_once FND_CONVERSA_PATH . 'includes/class-frontdesk-github-updater.php';
require_once FND_CONVERSA_PATH . 'includes/helpers.php';
require_once FND_CONVERSA_PATH . 'includes/class-conversa.php';        // Front-end (inline CSS, shortcode + auto-float, contact handler)
require_once FND_CONVERSA_PATH . 'includes/class-conversa-ajax.php';   // AJAX endpoints (chat only)
require_once FND_CONVERSA_PATH . 'includes/class-conversa-admin.php';  // Admin UI
require_once FND_CONVERSA_PATH . 'includes/class-conversa-rag.php';    // Retrieval helper used by AJAX (no init hook)
require_once FND_CONVERSA_PATH . 'includes/class-fp-onboarding.php';   // First-run onboarding wizard

// -----------------------------------------------------------------------------
// Foundation Core integration
// -----------------------------------------------------------------------------
function fnd_conversa_core_is_available(): bool {
    return function_exists('foundation_core_register_addon')
        && defined('FOUNDATION_CORE_VERSION')
        && version_compare(FOUNDATION_CORE_VERSION, FND_CONVERSA_MIN_CORE, '>=');
}

function fnd_conversa_log_to_core(string $level, string $event, array $context = []): void {
    if (function_exists('foundation_core_log_event')) {
        foundation_core_log_event($level, $event, $context, FND_CONVERSA_CORE_SLUG);
    }
}

function fnd_conversa_is_isolated_by_core(): bool {
    if (!function_exists('foundation_core_get_safe_mode_manager')) {
        return false;
    }

    $manager = foundation_core_get_safe_mode_manager();
    return is_object($manager)
        && method_exists($manager, 'is_isolated')
        && $manager->is_isolated(FND_CONVERSA_CORE_SLUG);
}

function fnd_conversa_register_with_core(): void {
    if (!function_exists('foundation_core_register_addon')) {
        return;
    }

    $result = foundation_core_register_addon([
        'slug'                  => FND_CONVERSA_CORE_SLUG,
        'name'                  => 'Foundation: Frontdesk AI',
        'version'               => FND_CONVERSA_VERSION,
        'type'                  => 'commercial-addon',
        'channel'               => 'stable',
        'min_core_version'      => FND_CONVERSA_MIN_CORE,
        'requires_license'      => true,
        'admin_page_slug'       => 'foundation-conversa-settings',
        'product_url'           => 'https://inkfire.co.uk/',
        'support_url'           => 'https://inkfire.co.uk/',
        'docs_url'              => 'https://inkfire.co.uk/',
        'module_class'          => 'Foundation_Conversa_Admin',
        'health_check_callback' => 'fnd_conversa_health_check',
        'status'                => fnd_conversa_is_isolated_by_core() ? 'isolated' : 'active',
    ]);

    if (is_wp_error($result)) {
        fnd_conversa_log_to_core(
            'error',
            'addon_registration_failed',
            [
                'code'    => $result->get_error_code(),
                'message' => $result->get_error_message(),
            ]
        );
        return;
    }

    fnd_conversa_log_to_core(
        'info',
        'addon_registered_with_core',
        [
            'version'          => FND_CONVERSA_VERSION,
            'min_core_version' => FND_CONVERSA_MIN_CORE,
        ]
    );
}
add_action('foundation_core_register_addons', 'fnd_conversa_register_with_core');

function fnd_conversa_health_check(array $addon = [], array $context = [], $registry = null): array {
    global $wpdb;

    $options = get_option('fnd_conversa_options', []);
    $rag_status = get_option('fnd_conversa_rag_status', []);
    $rag_table = $wpdb->prefix . (class_exists('Foundation_Conversa_RAG') ? Foundation_Conversa_RAG::TABLE : 'fnd_conversa_chunks');
    $rag_table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($rag_table))) === $rag_table;
    $asset_checks = [
        'shell_js' => file_exists(FND_CONVERSA_PATH . 'assets/admin/foundation-admin-shell.js'),
        'shell_css' => file_exists(FND_CONVERSA_PATH . 'assets/admin/foundation-admin-shell.css'),
        'admin_js' => file_exists(FND_CONVERSA_PATH . 'assets/admin/frontdesk-admin.js'),
        'chat_template' => file_exists(FND_CONVERSA_PATH . 'templates/chatbox.php'),
    ];
    $missing_assets = array_keys(array_filter($asset_checks, static function ($present) {
        return !$present;
    }));

    $critical_classes = [
        'Foundation_Conversa_Admin' => class_exists('Foundation_Conversa_Admin'),
        'Foundation_Conversa_Ajax' => class_exists('Foundation_Conversa_Ajax'),
        'Foundation_Conversa_RAG' => class_exists('Foundation_Conversa_RAG'),
        'Foundation_Conversa_Onboarding' => class_exists('Foundation_Conversa_Onboarding'),
    ];
    $missing_classes = array_keys(array_filter($critical_classes, static function ($present) {
        return !$present;
    }));

    $provider = is_array($options) ? sanitize_key($options['fd_provider'] ?? 'offline') : 'offline';
    $needs_key = in_array($provider, ['openai', 'gemini'], true) && empty($options[$provider . '_api_key']);
    $state = (is_array($options) && empty($missing_assets) && empty($missing_classes) && !$needs_key) ? 'ok' : 'degraded';

    return [
        'state' => $state,
        'message' => 'ok' === $state
            ? __('Frontdesk AI runtime checks passed.', 'foundation-frontdesk')
            : __('Frontdesk AI is available, but one or more runtime checks are degraded.', 'foundation-frontdesk'),
        'data' => [
            'options_ok' => is_array($options),
            'provider' => $provider,
            'provider_key_missing' => $needs_key,
            'rag_table_exists' => $rag_table_exists,
            'rag_status' => is_array($rag_status) && isset($rag_status['status']) ? sanitize_key($rag_status['status']) : 'idle',
            'missing_assets' => $missing_assets,
            'missing_classes' => $missing_classes,
        ],
        'context' => $context,
    ];
}

function fnd_conversa_core_notice(): void {
    if (!current_user_can('manage_options') || fnd_conversa_core_is_available()) {
        return;
    }

    $message = function_exists('foundation_core_register_addon')
        ? sprintf(
            /* translators: %s: minimum Foundation Core version. */
            __('Foundation: Frontdesk AI is running in legacy mode because Foundation Core is older than %s.', 'foundation-frontdesk'),
            FND_CONVERSA_MIN_CORE
        )
        : __('Foundation: Frontdesk AI is running in legacy mode until Foundation Core is installed and active.', 'foundation-frontdesk');

    echo '<div class="notice notice-warning"><p><strong>Foundation: Frontdesk AI</strong> — ' . esc_html($message) . '</p></div>';
}
add_action('admin_notices', 'fnd_conversa_core_notice');

function fnd_conversa_safe_mode_notice(): void {
    if (!current_user_can('manage_options') || !fnd_conversa_is_isolated_by_core()) {
        return;
    }

    echo '<div class="notice notice-error"><p><strong>Foundation: Frontdesk AI</strong> — '
        . esc_html__('This addon is currently isolated by Foundation Core safe mode. Restore it from Foundation > Addons when you are ready to re-enable Frontdesk runtime hooks.', 'foundation-frontdesk')
        . '</p></div>';
}
add_action('admin_notices', 'fnd_conversa_safe_mode_notice');

// -----------------------------------------------------------------------------
// Init
// -----------------------------------------------------------------------------
// These classes do not self-init.
if (class_exists('Foundation_Frontdesk_Github_Updater')) Foundation_Frontdesk_Github_Updater::instance();
if (fnd_conversa_is_isolated_by_core()) {
    fnd_conversa_log_to_core(
        'warning',
        'addon_bootstrap_skipped_safe_mode',
        [
            'reason' => 'core_safe_mode_isolated',
        ]
    );
} else {
    fnd_conversa_log_to_core(
        'info',
        'addon_bootstrap_started',
        [
            'version' => FND_CONVERSA_VERSION,
            'core_available' => fnd_conversa_core_is_available(),
        ]
    );
    if (class_exists('Foundation_Conversa_Admin'))      Foundation_Conversa_Admin::init();
    if (class_exists('Foundation_Conversa_Ajax'))       Foundation_Conversa_Ajax::init();
    if (class_exists('Foundation_Conversa_Onboarding')) Foundation_Conversa_Onboarding::init();
    fnd_conversa_log_to_core(
        'info',
        'addon_bootstrap_completed',
        [
            'admin_ui' => is_admin(),
            'chat_ajax' => class_exists('Foundation_Conversa_Ajax'),
            'onboarding' => class_exists('Foundation_Conversa_Onboarding'),
        ]
    );
}
