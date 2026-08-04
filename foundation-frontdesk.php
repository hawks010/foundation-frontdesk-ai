<?php
/*
Plugin Name: Foundation: Frontdesk AI
Plugin URI: https://inkfire.co.uk/foundation/frontdesk
Description: Frontdesk AI is a visitor-facing WordPress chatbot with floating and embedded widgets, OpenAI responses, offline fallback, site knowledge, and contact capture.
Version: 4.0.0-beta.4
Author: Inkfire Ltd
Author URI: https://inkfire.co.uk/
Text Domain: foundation-frontdesk
Domain Path: /languages
Requires at least: 5.5
Tested up to: 6.8.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

if (!defined('ABSPATH')) exit;

// -----------------------------------------------------------------------------
// Core constants
// -----------------------------------------------------------------------------
if (!defined('FND_FRONTDESK_VERSION')) define('FND_FRONTDESK_VERSION', '4.0.0-beta.4');
if (!defined('FND_FRONTDESK_PATH'))    define('FND_FRONTDESK_PATH', plugin_dir_path(__FILE__));
if (!defined('FND_FRONTDESK_URL'))     define('FND_FRONTDESK_URL',  plugin_dir_url(__FILE__));

// Provide the main plugin file path for hooks inside included classes (e.g., onboarding)
if (!defined('FP_PLUGIN_FILE'))       define('FP_PLUGIN_FILE', __FILE__);

// Enable the floating launcher globally (footer) by default.
// You can comment this out if you want to control it elsewhere.
if (!defined('FOUNDATION_FRONTDESK_AUTO')) {
    define('FOUNDATION_FRONTDESK_AUTO', true);
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
// One-time migration: rename fnd_conversa_* option keys → fnd_frontdesk_*
// -----------------------------------------------------------------------------
add_action('plugins_loaded', function () {
    if (get_option('fnd_conversa_options') !== false && get_option('fnd_frontdesk_options') === false) {
        update_option('fnd_frontdesk_options', get_option('fnd_conversa_options', []), false);
        delete_option('fnd_conversa_options');
    }
    foreach (['wizard_open', 'rag_status', 'do_onboarding_redirect', 'onboarding_dismissed'] as $suffix) {
        $old_val = get_option('fnd_conversa_' . $suffix);
        if ($old_val !== false && get_option('fnd_frontdesk_' . $suffix) === false) {
            update_option('fnd_frontdesk_' . $suffix, $old_val, false);
            delete_option('fnd_conversa_' . $suffix);
        }
    }
    // Rename RAG chunks table if old name still exists
    global $wpdb;
    $old_table = $wpdb->prefix . 'fnd_frontdesk_chunks'; // already renamed in class
    $legacy    = $wpdb->prefix . 'fnd_conversa_chunks';
    if ($wpdb->get_var("SHOW TABLES LIKE '{$legacy}'") && !$wpdb->get_var("SHOW TABLES LIKE '{$old_table}'")) {
        $wpdb->query("RENAME TABLE `{$legacy}` TO `{$old_table}`");
    }
}, 1);

// -----------------------------------------------------------------------------
// Includes
// -----------------------------------------------------------------------------
require_once FND_FRONTDESK_PATH . 'includes/helpers.php';
require_once FND_FRONTDESK_PATH . 'includes/class-frontdesk-config.php';
require_once FND_FRONTDESK_PATH . 'includes/class-frontdesk-rate-limit.php';
require_once FND_FRONTDESK_PATH . 'includes/class-frontdesk-response.php';
require_once FND_FRONTDESK_PATH . 'includes/class-frontdesk-knowledge.php';
require_once FND_FRONTDESK_PATH . 'includes/providers/interface-frontdesk-ai-provider.php';
require_once FND_FRONTDESK_PATH . 'includes/providers/class-frontdesk-offline-provider.php';
require_once FND_FRONTDESK_PATH . 'includes/providers/class-frontdesk-openai-provider.php';
require_once FND_FRONTDESK_PATH . 'includes/class-frontdesk-chat-controller.php';
require_once FND_FRONTDESK_PATH . 'includes/class-frontdesk-contact-controller.php';
require_once FND_FRONTDESK_PATH . 'includes/class-frontdesk.php';
require_once FND_FRONTDESK_PATH . 'includes/class-frontdesk-admin.php';
require_once FND_FRONTDESK_PATH . 'includes/class-frontdesk-rag.php';
require_once FND_FRONTDESK_PATH . 'includes/class-frontdesk-onboarding.php';

register_activation_hook(__FILE__, ['Foundation_Frontdesk_Onboarding', 'on_activate']);

// -----------------------------------------------------------------------------
// Init
// -----------------------------------------------------------------------------
if (class_exists('Frontdesk_Config'))               Frontdesk_Config::init();
if (class_exists('Foundation_Frontdesk'))           Foundation_Frontdesk::init();
if (class_exists('Frontdesk_Chat_Controller'))      Frontdesk_Chat_Controller::init();
if (class_exists('Frontdesk_Contact_Controller'))   Frontdesk_Contact_Controller::init();
if (class_exists('Foundation_Frontdesk_RAG'))        Foundation_Frontdesk_RAG::init();
if (class_exists('Foundation_Frontdesk_Admin'))      Foundation_Frontdesk_Admin::init();
if (class_exists('Foundation_Frontdesk_Onboarding')) Foundation_Frontdesk_Onboarding::init();
