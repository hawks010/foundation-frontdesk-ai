<?php
/**
 * Inkfire Frontdesk AI — set rich HTML greeting.
 * Run via: wp eval-file /tmp/inkfire-greeting-html-update.php
 * Delete after running.
 */

$greeting_html = '👋 Hi! I\'m Inkfire\'s assistant — ask me anything about our <strong>accessibility</strong>, <strong>web</strong>, and <strong>digital services</strong>.'
    . '<div class="fnd-greeting-hours">🕐 <strong>Mon–Fri:</strong> 9am–5pm (UK time) &nbsp;·&nbsp; <strong>Sat–Sun:</strong> Closed</div>'
    . '📬 <a href="mailto:hello@inkfire.co.uk">hello@inkfire.co.uk</a> &nbsp;·&nbsp; 📞 <a href="tel:03336134653">0333 613 4653</a>'
    . '<div class="fnd-greeting-btns">'
    . '<a href="https://inkfire.co.uk/services/" class="fnd-greeting-btn">🌐 Services</a>'
    . '<a href="https://inkfire.co.uk/about/" class="fnd-greeting-btn">👥 About us</a>'
    . '<a href="https://inkfire.co.uk/accessibility/" class="fnd-greeting-btn">♿ Accessibility</a>'
    . '<a href="https://inkfire.co.uk/contact/" class="fnd-greeting-btn">📞 Contact</a>'
    . '</div>';

$existing = get_option( 'fnd_frontdesk_options', [] );
$existing['greeting_html'] = $greeting_html;
update_option( 'fnd_frontdesk_options', $existing, false );
echo "Inkfire greeting_html updated.\n";
