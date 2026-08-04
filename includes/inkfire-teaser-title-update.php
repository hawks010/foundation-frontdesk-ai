<?php
/**
 * Inkfire Frontdesk AI — fix teaser title copy repetition.
 * Run via: wp eval-file /tmp/inkfire-teaser-title-update.php
 * Delete after running.
 */
$existing = get_option( 'fnd_frontdesk_options', [] );
$existing['teaser_title'] = 'Chat with our team 👋';
update_option( 'fnd_frontdesk_options', $existing, false );
echo "Inkfire teaser_title updated.\n";
