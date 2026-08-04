<?php
if (!defined('ABSPATH')) { exit; }

class Frontdesk_Contact_Controller {
    public static function init(): void {
        add_action('wp_ajax_fnd_frontdesk_contact', [__CLASS__, 'handle']);
        add_action('wp_ajax_nopriv_fnd_frontdesk_contact', [__CLASS__, 'handle']);
    }

    public static function handle(): void {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field((string) $_POST['nonce']) : '';
        if (!wp_verify_nonce($nonce, 'fnd_frontdesk_contact')) {
            wp_send_json(['ok' => false, 'message' => __('Security check failed. Please refresh and try again.', 'foundation-frontdesk')], 403);
        }

        if (!Frontdesk_Rate_Limit::check('contact', 5, HOUR_IN_SECONDS)) {
            wp_send_json(['ok' => false, 'message' => __('You have sent too many messages recently. Please try again later.', 'foundation-frontdesk')], 429);
        }

        $honeypot = trim((string) ($_POST['website'] ?? $_POST['company'] ?? ''));
        if ($honeypot !== '') {
            wp_send_json(['ok' => false, 'message' => __('Your message could not be sent.', 'foundation-frontdesk')], 400);
        }

        // reCAPTCHA v3 — only enforced when secret key is configured
        $recaptcha_secret = trim((string) (Frontdesk_Config::get('recaptcha_secret_key', '')));
        if ($recaptcha_secret !== '') {
            $rc_token = sanitize_text_field((string) ($_POST['recaptcha_token'] ?? ''));
            if ($rc_token === '' || !self::verify_recaptcha($rc_token, $recaptcha_secret)) {
                wp_send_json(['ok' => false, 'message' => __('Security check failed. Please try again.', 'foundation-frontdesk')], 403);
            }
        }

        $name       = sanitize_text_field((string) ($_POST['name'] ?? ''));
        $email      = sanitize_email((string) ($_POST['email'] ?? ''));
        $message    = sanitize_textarea_field((string) ($_POST['message'] ?? ''));
        $page_url   = esc_url_raw((string) ($_POST['page_url'] ?? $_POST['page'] ?? ''));
        $page_title = sanitize_text_field((string) ($_POST['page_title'] ?? ''));
        $session_id = sanitize_key((string) ($_POST['frontdesktion_id'] ?? ''));

        if ($name === '' || $message === '' || !is_email($email)) {
            wp_send_json(['ok' => false, 'message' => __('Please provide your name, a valid email address, and a message.', 'foundation-frontdesk')], 422);
        }

        $config    = Frontdesk_Config::get_all();
        $to        = sanitize_email((string) ($config['contact_email'] ?? '')) ?: get_option('admin_email');
        $site_name = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
        $bot_name  = (string) ($config['bot_name'] ?? $site_name);
        $prefix    = sanitize_text_field((string) ($config['contact_subject_prefix'] ?? 'Frontdesk AI'));

        $vars = [
            'bot_name'   => $bot_name,
            'site_name'  => $site_name,
            'name'       => $name,
            'email'      => $email,
            'message'    => $message,
            'page_title' => $page_title,
            'page_url'   => $page_url,
        ];

        // --- Notification email to site owner ---
        $notify_intro = self::apply_placeholders(
            trim((string) ($config['notification_email_intro'] ?? ''))
                ?: __("You've received a new message via your website's {bot_name} widget.", 'foundation-frontdesk'),
            $vars
        );

        $meta = [__('From', 'foundation-frontdesk') => esc_html($name) . ' &lt;' . esc_html($email) . '&gt;'];
        if ($page_title !== '') {
            $meta[__('Page', 'foundation-frontdesk')] = esc_html($page_title);
        }
        if ($page_url !== '') {
            $meta[__('URL', 'foundation-frontdesk')] = '<a href="' . esc_url($page_url) . '" style="color:inherit">' . esc_html($page_url) . '</a>';
        }
        if ($session_id !== '') {
            $meta[__('Session', 'foundation-frontdesk')] = esc_html($session_id);
        }

        $notify_html = self::build_email_html([
            'heading'   => sprintf(__('New message from %s', 'foundation-frontdesk'), $name),
            'intro'     => $notify_intro,
            'meta'      => $meta,
            'message'   => $message,
            'cta_href'  => 'mailto:' . $email,
            'cta_label' => sprintf(__('Reply to %s', 'foundation-frontdesk'), $name),
        ], $config);

        $headers = [
            'Reply-To: ' . $name . ' <' . $email . '>',
            'Content-Type: text/html; charset=UTF-8',
        ];
        $sent = wp_mail($to, sprintf('%s: %s', $prefix, $name), $notify_html, $headers);

        // --- Confirmation email to visitor ---
        if ($sent && !empty($config['send_user_confirmation'])) {
            $confirm_intro = self::apply_placeholders(
                trim((string) ($config['confirmation_email_body'] ?? ''))
                    ?: __('Thanks for getting in touch. We have received your message and will reply as soon as possible.', 'foundation-frontdesk'),
                $vars
            );
            $confirm_html = self::build_email_html([
                'heading'   => sprintf(__('Thanks, %s!', 'foundation-frontdesk'), $name),
                'intro'     => $confirm_intro,
                'meta'      => [],
                'message'   => $message,
                'cta_href'  => '',
                'cta_label' => '',
            ], $config);
            wp_mail(
                $email,
                sprintf(__('Thanks for contacting %s', 'foundation-frontdesk'), $site_name),
                $confirm_html,
                ['Content-Type: text/html; charset=UTF-8']
            );
        }

        if (!$sent) {
            wp_send_json(['ok' => false, 'message' => __('Could not send your message right now. Please try again later.', 'foundation-frontdesk')], 500);
        }

        wp_send_json(['ok' => true, 'message' => __('Thanks! Your message has been sent.', 'foundation-frontdesk')], 200);
    }

    private static function verify_recaptcha(string $token, string $secret, float $min_score = 0.5): bool {
        $response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', [
            'timeout' => 8,
            'body'    => [
                'secret'   => $secret,
                'response' => $token,
                'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
            ],
        ]);
        if (is_wp_error($response)) return false;
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($body) || empty($body['success'])) return false;
        $score = (float) ($body['score'] ?? 0.0);
        return $score >= $min_score;
    }

    private static function apply_placeholders(string $text, array $vars): string {
        $map = [];
        foreach ($vars as $key => $val) {
            $map['{' . $key . '}'] = (string) $val;
        }
        return strtr($text, $map);
    }

    private static function build_email_html(array $data, array $config): string {
        $hbg  = esc_attr($config['ui_header_bg']        ?? '#1e6167');
        $htx  = esc_attr($config['ui_header_text']       ?? '#FFFFFF');
        $btn  = esc_attr($config['ui_button_color']      ?? '#04ad93');
        $btx  = esc_attr($config['ui_button_text_color'] ?? '#FFFFFF');
        $bot  = esc_html($config['bot_name']             ?? get_bloginfo('name'));
        $site = esc_html(wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES));
        $year = gmdate('Y');

        $heading  = esc_html($data['heading'] ?? '');
        $intro    = nl2br(esc_html($data['intro'] ?? ''));
        $meta     = (array) ($data['meta'] ?? []);
        $msg      = nl2br(esc_html($data['message'] ?? ''));
        $cta_url  = esc_url($data['cta_href'] ?? '');
        $cta_lbl  = esc_html($data['cta_label'] ?? '');

        // Meta rows (already-escaped HTML values)
        $meta_rows = '';
        foreach ($meta as $label => $value) {
            $meta_rows .= '
                <tr>
                    <td style="padding:5px 16px 5px 0;font-size:11px;font-weight:700;color:#64748b;white-space:nowrap;vertical-align:top;text-transform:uppercase;letter-spacing:.05em">' . esc_html($label) . '</td>
                    <td style="padding:5px 0;font-size:13px;color:#1a1d23;vertical-align:top;word-break:break-all">' . $value . '</td>
                </tr>';
        }
        $meta_block = $meta_rows ? '
            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:20px 0 0;border-collapse:collapse;width:100%">
                ' . $meta_rows . '
            </table>' : '';

        // Visitor message
        $msg_block = $msg ? '
            <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin:22px 0 0">
                <tr>
                    <td style="background:#f8fafc;border:1px solid #e2e8f0;border-left:4px solid ' . $btn . ';border-radius:0 6px 6px 0;padding:14px 16px;font-size:14px;line-height:1.65;color:#374151">' . $msg . '</td>
                </tr>
            </table>' : '';

        // CTA button
        $cta_block = ($cta_url && $cta_lbl) ? '
            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:26px 0 0">
                <tr>
                    <td style="background:' . $btn . ';border-radius:8px">
                        <a href="' . $cta_url . '" style="display:inline-block;padding:11px 24px;font-size:14px;font-weight:700;color:' . $btx . ';text-decoration:none;letter-spacing:.02em;border-radius:8px">' . $cta_lbl . '</a>
                    </td>
                </tr>
            </table>' : '';

        return '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<meta http-equiv="X-UA-Compatible" content="IE=edge" />
<title>' . $heading . '</title>
</head>
<body style="margin:0;padding:0;background:#f0f2f5;font-family:\'Plus Jakarta Sans\',\'Segoe UI\',ui-sans-serif,system-ui,sans-serif;-webkit-font-smoothing:antialiased">
<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background:#f0f2f5">
    <tr>
        <td align="center" style="padding:32px 16px 48px">
            <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="max-width:560px">

                <!-- Header -->
                <tr>
                    <td style="background:' . $hbg . ';border-radius:14px 14px 0 0;padding:26px 28px">
                        <p style="margin:0 0 6px;font-size:11px;font-weight:700;color:' . $htx . ';opacity:.75;text-transform:uppercase;letter-spacing:.08em">' . $bot . '</p>
                        <h1 style="margin:0;font-size:20px;font-weight:800;color:' . $htx . ';line-height:1.3">' . $heading . '</h1>
                    </td>
                </tr>

                <!-- Body -->
                <tr>
                    <td style="background:#ffffff;padding:28px 28px 32px;border-radius:0 0 14px 14px;border:1px solid #e0e3e8;border-top:none">
                        <p style="margin:0;font-size:14px;line-height:1.7;color:#374151">' . $intro . '</p>
                        ' . $meta_block . '
                        ' . $msg_block . '
                        ' . $cta_block . '
                        <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin:32px 0 0;border-top:1px solid #e2e8f0">
                            <tr>
                                <td style="padding:16px 0 0;font-size:11px;color:#94a3b8;text-align:center;line-height:1.6">
                                    Sent via <strong style="color:#64748b">' . $bot . '</strong> &nbsp;&middot;&nbsp; ' . $site . ' &nbsp;&middot;&nbsp; &copy; ' . $year . '
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

            </table>
        </td>
    </tr>
</table>
</body>
</html>';
    }
}
