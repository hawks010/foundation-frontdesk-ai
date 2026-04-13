<?php
/*
Plugin Name: Foundation: Frontdesk AI
Plugin URI: https://github.com/hawks010/foundation-frontdesk-ai
Description: A minimal, accessible chat & contact widget. Self-hosted, fast, and private—route messages to your inbox or (optionally) your AI assistant. Part of the Foundation plugin series by Inkfire Limited.
Version: 1.0.10
Author: Sonny x Inkfire
Author URI: https://inkfire.co.uk/
Text Domain: foundation-frontdesk
Domain Path: /languages
Requires at least: 5.5
Tested up to: 6.8.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Update URI: https://github.com/hawks010/foundation-frontdesk-ai
*/

if (!defined('ABSPATH')) exit;

// -----------------------------------------------------------------------------
// Core constants
// -----------------------------------------------------------------------------
if (!defined('FND_CONVERSA_VERSION')) define('FND_CONVERSA_VERSION', '1.0.10');
if (!defined('FND_CONVERSA_FILE'))    define('FND_CONVERSA_FILE', __FILE__);
if (!defined('FND_CONVERSA_PATH'))    define('FND_CONVERSA_PATH', plugin_dir_path(__FILE__));
if (!defined('FND_CONVERSA_URL'))     define('FND_CONVERSA_URL',  plugin_dir_url(__FILE__));

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
// Init
// -----------------------------------------------------------------------------
// These classes do not self-init.
if (class_exists('Foundation_Frontdesk_Github_Updater')) Foundation_Frontdesk_Github_Updater::instance();
if (class_exists('Foundation_Conversa_Admin'))      Foundation_Conversa_Admin::init();
if (class_exists('Foundation_Conversa_Ajax'))       Foundation_Conversa_Ajax::init();
if (class_exists('Foundation_Conversa_Onboarding')) Foundation_Conversa_Onboarding::init();
