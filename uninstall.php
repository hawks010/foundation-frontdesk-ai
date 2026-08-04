<?php
/**
 * Foundation: Frontdesk AI — Uninstall Cleanup
 * Removes plugin options and related transients when the plugin is uninstalled via WordPress.
 */

// Exit if accessed directly or if not called by WordPress uninstall
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Delete main options
delete_option( 'fnd_frontdesk_options' );
delete_option( 'fnd_frontdesk_do_onboarding_redirect' );
delete_option( 'fnd_frontdesk_onboarding_dismissed' );
delete_option( 'fnd_frontdesk_rag_status' );

// Delete network-wide options on multisite (if any were stored)
if ( function_exists( 'is_multisite' ) && is_multisite() ) {
    delete_site_option( 'fnd_frontdesk_options' );
    delete_site_option( 'fnd_frontdesk_do_onboarding_redirect' );
    delete_site_option( 'fnd_frontdesk_onboarding_dismissed' );
    delete_site_option( 'fnd_frontdesk_rag_status' );
}

// Best-effort cleanup of transients created by this plugin
// (keeps DB tidy; safe to run even if none exist)
if ( function_exists( 'delete_transient' ) ) {
    global $wpdb;
    // delete matching transients in options
    $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%fnd_frontdesk%'" );
    $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_site_transient_%fnd_frontdesk%'" );
}
