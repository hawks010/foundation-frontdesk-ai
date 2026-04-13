<?php
/**
 * Foundation: Conversa — Uninstall Cleanup
 * Removes plugin options and related transients when the plugin is uninstalled via WordPress.
 */

// Exit if accessed directly or if not called by WordPress uninstall
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Delete main options
delete_option( 'fnd_conversa_options' );

// Delete network-wide options on multisite (if any were stored)
if ( function_exists( 'is_multisite' ) && is_multisite() ) {
    delete_site_option( 'fnd_conversa_options' );
}

// Best-effort cleanup of transients created by this plugin
// (keeps DB tidy; safe to run even if none exist)
if ( function_exists( 'delete_transient' ) ) {
    global $wpdb;
    // delete matching transients in options
    $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%fnd_conversa%'" );
    $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_site_transient_%fnd_conversa%'" );
}
