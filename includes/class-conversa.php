<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Foundation: Frontdesk AI — Chat + Contact (inline CSS/JS)
 * Build: 2025-08-20 (patched)
 *
 * Highlights
 * - Compact composer (insulated from theme CSS)
 * - Knowledge Base header overlay with search + back; ESC to close
 * - Offline "AI-like" replies (flow-first, then smart fallbacks)
 * - Optional Woo upsell strip
 * - Secure AJAX for chat (demo echo) + contact
 */

if (!class_exists('Foundation_Frontdesk')):
class Foundation_Frontdesk {

    private static $css_printed   = false;
    private static $rendered_once = false;

    public static function init(): void {
        add_shortcode('foundation_frontdesk', [__CLASS__, 'shortcode']);
        add_shortcode('foundation_conversa', [__CLASS__, 'shortcode']); // legacy alias

        $auto = defined('FOUNDATION_CONVERSA_AUTO') ? (bool) FOUNDATION_CONVERSA_AUTO : false;
        if ($auto) add_action('wp_footer', [__CLASS__, 'inject_footer'], 99);

        // Online chat (optional)
        add_action('wp_ajax_fnd_conversa_chat', [__CLASS__, 'ajax_chat']);
        add_action('wp_ajax_nopriv_fnd_conversa_chat', [__CLASS__, 'ajax_chat']);

        // Contact (canonical) + legacy aliases
        add_action('wp_ajax_fnd_frontdesk_contact', [__CLASS__, 'ajax_contact']);
        add_action('wp_ajax_nopriv_fnd_frontdesk_contact', [__CLASS__, 'ajax_contact']);
        add_action('wp_ajax_fnd_conversa_contact', [__CLASS__, 'ajax_contact']);
        add_action('wp_ajax_nopriv_fnd_conversa_contact', [__CLASS__, 'ajax_contact']);
    }

    public static function shortcode($atts = [], $content = ''): string {
        $atts = shortcode_atts([
            'title'    => 'Frontdesk AI',
            'subtitle' => 'Foundation by <a href="https://inkfire.co.uk" target="_blank" rel="noopener">Inkfire Ltd</a>',
            'launcher' => '1',
        ], $atts, 'foundation_frontdesk');

        ob_start();
        self::print_css();
        self::render_widget([
            'title'         => (string)$atts['title'],
            'subtitle'      => (string)$atts['subtitle'],
            'greeting'      => (trim((string)$content) !== '' ? (string)$content : "Hi! I'm Frontdesk AI. How can I help today?\nHours: Mon–Fri: 9am–5pm\nSat–Sun: Closed"),
            'show_launcher' => $atts['launcher'] === '1',
        ]);
        return (string) ob_get_clean();
    }

    public static function inject_footer(): void {
        if (self::$rendered_once) return;
        if (self::page_has_shortcode('foundation_frontdesk') || self::page_has_shortcode('foundation_conversa')) return;

        self::print_css();
        self::render_widget([
            'title'         => 'Frontdesk AI',
            'subtitle'      => 'Foundation • <a href="https://inkfire.co.uk" target="_blank" rel="noopener">Inkfire Ltd</a>',
            'greeting'      => "Hi! I'm Frontdesk AI. How can I help today?\nHours: Mon–Fri: 9am–5pm\nSat–Sun: Closed",
            'show_launcher' => true,
        ]);
    }

    private static function page_has_shortcode(string $shortcode): bool {
        global $post;
        if (is_singular() && $post instanceof WP_Post) {
            if (has_shortcode((string)$post->post_content, $shortcode)) return true;
        }
        return false;
    }

    /** SEO image / Site Logo / Site Icon for avatar/header logo */
    private static function get_seo_image_url(): string {
        if (defined('FOUNDATION_FD_LOGO') && FOUNDATION_FD_LOGO) {
            return esc_url_raw(FOUNDATION_FD_LOGO);
        }
        $yoast = get_option('wpseo_social');
        if (is_array($yoast)) {
            foreach (['og_default_image', 'og_frontpage_image', 'twitter_image', 'twitter_default_image'] as $k) {
                if (!empty($yoast[$k]) && filter_var($yoast[$k], FILTER_VALIDATE_URL)) {
                    return esc_url_raw($yoast[$k]);
                }
            }
            foreach (['og_default_image_id', 'og_frontpage_image_id'] as $k) {
                if (!empty($yoast[$k]) && (int)$yoast[$k] > 0) {
                    $u = wp_get_attachment_url((int)$yoast[$k]);
                    if ($u) return esc_url_raw($u);
                }
            }
        }
        $rank_candidates = [
            get_option('rank-math-options-titles'),
            get_option('rank-math-options-general'),
            get_option('rank-math-options-social'),
        ];
        foreach ($rank_candidates as $opt) {
            if (!is_array($opt)) continue;
            $u = self::extract_first_image_url($opt);
            if ($u) return $u;
        }
        $logo_id = (int) get_theme_mod('custom_logo');
        if ($logo_id) {
            $src = wp_get_attachment_image_src($logo_id, 'thumbnail');
            if ($src && !empty($src[0])) { return esc_url_raw($src[0]); }
        }
        $icon = get_site_icon_url(64);
        if ($icon) { return esc_url_raw($icon); }
        return '';
    }
    private static function extract_first_image_url($arr): string {
        $re = '~https?://[^\s\'\"]+\.(?:png|jpe?g|webp|gif|svg)~i';
        foreach ($arr as $v) {
            if (is_string($v) && preg_match($re, $v, $m)) return esc_url_raw($m[0]);
            if (is_array($v)) { $u = self::extract_first_image_url($v); if ($u) return $u; }
        }
        return '';
    }
    private static function get_logo_html(): string {
        $url = self::get_seo_image_url();
        if ($url) {
            return '<span class="fnd-conversa__logo"><img src="' . esc_url($url) . '" alt="" width="32" height="32" decoding="async" fetchpriority="low" /></span>';
        }
        $svg = '<svg viewBox="0 0 24 24" role="img" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"><path d="M12 6a1 1 0 0 1 1-1h1a1 1 0 1 1 0 2h-1a1 1 0 0 1-1-1Zm-7 9.5a1.5 1.5 0 0 1 1.5-1.5h11a1.5 1.5 0 0 1 1.5 1.5V17H5v-1.5Zm1.36-3.62A6.5 6.5 0 0 1 11 9.04V8.5a1 1 0 1 1 2 0v.54a6.5 6.5 0 0 1 4.64 2.84l.22.34H6.14l.22-.34Z" fill="currentColor"/></svg>';
        return '<span class="fnd-conversa__logo fnd-conversa__logo--svg" aria-hidden="true">'.$svg.'</span>';
    }

    private static function print_css(): void {
        if (self::$css_printed) return;
        self::$css_printed = true; ?>
        <style id="foundation-frontdesk-inline-css">
            :root {
              --fnd-brand: #1e6167;
              --fnd-accent: #04ad93;
              --fnd-accent-hover: #038d78;
              --fnd-border:#e9ecef; --fnd-body-text:#1f2937; --fnd-muted:#6b7280;
              --fnd-radius-lg:16px; --fnd-radius-md:12px;
            }
            .fnd-conversa{
              position:fixed; right:24px; bottom:24px;
              width:clamp(360px, 40vw, 560px);
              max-width:calc(100vw - 32px);
              background:#fff;border:1px solid var(--fnd-border);
              box-shadow:0 8px 24px rgba(0,0,0,.1);
              border-radius:var(--fnd-radius-lg);overflow:hidden;
              font-family:"Atkinson Hyperlegible Next","Atkinson Hyperlegible",ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;
              color:var(--fnd-body-text);z-index:2147483646;font-size:14px
            }
            .fnd-conversa[hidden]{display:none!important}

            .fnd-conversa__header{display:grid;grid-template-columns:1fr auto auto;gap:12px;align-items:center;padding:12px 14px;background:#1e6167;color:#fff;border-bottom:1px solid rgba(255,255,255,.08)}
            .fnd-conversa__brand{display:grid;grid-template-columns:32px 1fr;gap:12px;align-items:center}
            .fnd-conversa__logo{width:32px;height:32px;border-radius:8px;background:#fff;color:#1e6167;display:grid;place-items:center;overflow:hidden}
            .fnd-conversa__logo img{width:100%;height:100%;object-fit:contain;display:block}
            .fnd-conversa__logo--svg svg{width:18px;height:18px}
            .fnd-conversa__title{font-size:16px;font-weight:700}
            .fnd-conversa__subtitle{font-size:12px;opacity:.95}
            .fnd-conversa__subtitle a{color:#fff;text-decoration:underline}

            /* Header actions */
            .fnd-conversa__actions{display:flex;gap:8px;align-items:center}
            .fnd-kb-btn,.fnd-menu-btn{appearance:none;border:0;background:rgba(255,255,255,0.12);color:#fff;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;cursor:pointer;padding:0 10px}
            .fnd-kb-btn:hover,.fnd-menu-btn:hover{background:rgba(255,255,255,0.2)}
            .fnd-kb-btn:focus-visible,.fnd-menu-btn:focus-visible{outline:2px solid #fff;outline-offset:2px}
            .fnd-menu-icon{display:inline-grid;gap:3px;margin-left:4px}
            .fnd-menu-icon span{display:block;width:4px;height:4px;border-radius:50%;background:#fff}
            .fnd-menu{position:absolute;top:38px;right:0;background:#ffffff;border:1px solid var(--fnd-border);border-radius:12px;box-shadow:0 8px 18px rgba(0,0,0,.12);min-width:180px;overflow:hidden;z-index:3}
            .fnd-menu[hidden]{display:none!important}
            .fnd-menu button{display:block;width:100%;background:#fff;border:0;text-align:left;padding:12px 14px;cursor:pointer;color:#0f172a}
            .fnd-menu button:hover,.fnd-menu button:focus{background:#eef2f7;color:#0f172a}

            /* Layout */
            .fnd-conversa__body{display:grid;grid-template-rows:1fr auto auto auto;height:min(560px,calc(100vh - 120px))}

            /* KB panel (full overlay from header down) */
            .fnd-kb{ background:#1e6167; color:#fff; border-bottom:1px solid rgba(255,255,255,.08); overflow:auto; position:absolute; left:0; right:0; bottom:0; top:0; display:none; z-index:5; }
            .fnd-kb[aria-hidden="false"]{ display:block }
            .fnd-kb__wrap{ padding:14px }
            .fnd-kb__head{display:flex;align-items:center;gap:10px;margin-bottom:8px}
            .fnd-kb__title{font-weight:800}
            .fnd-kb__back{margin-left:auto;appearance:none;border:0;background:#0e4f54;color:#fff;border-radius:9999px;padding:6px 10px;cursor:pointer}
            .fnd-kb__search{margin:8px 0 10px 0}
            .fnd-kb__search input{width:100%;border-radius:10px;border:0;padding:10px 12px}
            .fnd-kb__list{display:grid;gap:8px}
            .fnd-kb__item{background:#fff;color:#0f172a;border-radius:12px;overflow:hidden}
            .fnd-kb__q{width:100%;display:flex;align-items:center;justify-content:space-between;gap:10px;border:0;background:transparent;font-weight:700;cursor:pointer;padding:12px 14px;transition:background .18s ease,color .18s ease}
            .fnd-kb__q:hover{background:var(--fnd-accent);color:#fff}
            .fnd-kb__caret{transition:transform .18s ease}
            .fnd-kb__q[aria-expanded="true"] .fnd-kb__caret{transform:rotate(180deg)}
            .fnd-kb__a{margin:0;border-top:1px solid rgba(0,0,0,.06);padding:10px 12px;background:#ffffff}
            .fnd-kb__a a{color:var(--fnd-accent);text-decoration:none}

            .fnd-conversa__messages{padding:14px;overflow:auto;background:#fafbfc;border-bottom:1px solid var(--fnd-border)}

            /* Message rows */
            .fnd-row-msg{display:grid;grid-template-columns:32px 1fr;gap:8px;align-items:flex-start;margin:10px 0}
            .fnd-row-msg--user{grid-template-columns:1fr 32px}
            .fnd-row-msg--user .fnd-avatar{order:2}
            .fnd-row-msg--user .fnd-bubble{order:1;justify-self:end;background:#e6f7f4;border-color:#cdeee7}
            .fnd-avatar{width:32px;height:32px;border-radius:50%;overflow:hidden;background:#fff;border:1px solid var(--fnd-border);display:flex;align-items:center;justify-content:center}
            .fnd-avatar img{width:100%;height:100%;object-fit:cover}
            .fnd-bubble{background:#f5f7f9;border:1px solid var(--fnd-border);padding:12px 14px;border-radius:12px;max-width:100%;font-size:14px;line-height:1.6;white-space:normal;text-align:left}
            .fnd-stamp{display:block;margin-top:4px;font-size:11px;color:var(--fnd-muted)}

            .fnd-inline-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:8px}
            .fnd-inline-btn{border:1px solid var(--fnd-border);background:#fff;border-radius:9999px;padding:6px 10px;cursor:pointer;font-weight:600}

            /* Composer — compact + insulated from theme CSS */
            .fnd-conversa__composer{display:grid;grid-template-columns:1fr auto;gap:8px;padding:10px;background:#fff;border-top:1px solid var(--fnd-border);align-items:center;height:auto!important;min-height:0!important}
            .fnd-conversa__input{background:#f8f9fa;border:1px solid var(--fnd-border);border-radius:10px;padding:6px 10px;font-size:13px;line-height:1.3;height:36px!important;min-height:0!important;box-sizing:border-box}
            .fnd-conversa__send{appearance:none;border:0;background:var(--fnd-accent);color:#fff;padding:0 12px;border-radius:10px;font-weight:700;cursor:pointer;font-size:13px;height:36px;line-height:36px}
            .fnd-conversa input,.fnd-conversa button,.fnd-conversa textarea{font:inherit}

            .fnd-conversa__note{border-top:1px solid var(--fnd-border)}
            .fnd-conversa__noteBtn{width:100%;border:0;background:var(--fnd-accent);color:#fff;font-weight:700;padding:12px 14px;cursor:pointer}
            .fnd-conversa__contact{padding:12px;border-top:1px dashed var(--fnd-border);max-height:42vh;overflow:auto}
            .fnd-conversa__contact[hidden]{display:none!important}
            .fnd-conversa__contact .fnd-field{margin-bottom:12px}
            .fnd-conversa__contact input,.fnd-conversa__contact textarea{width:100%;background:#f8f9fa;border:1px solid var(--fnd-border);border-radius:10px;padding:12px}

            .fnd-status-badge{display:inline-block;margin-left:8px;padding:2px 8px;border-radius:9999px;font-size:11px;font-weight:700}
            .fnd-status-online{background:#83BE56;color:#052b27}
            .fnd-status-offline{background:#DE7450;color:#fff}
            .fnd-status{font-size:12px}

            .fnd-btn{appearance:none;border:0;background:var(--fnd-accent);color:#fff;padding:12px 16px;border-radius:12px;font-weight:700;cursor:pointer}

            /* Floating action button */
            .fnd-fab{position:fixed;right:24px;bottom:24px;width:56px;height:56px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:var(--fnd-accent);color:#fff;border:0;box-shadow:0 8px 24px rgba(0,0,0,.12);cursor:pointer;z-index:2147483647;font-size:22px}
            .fnd-fab[hidden]{display:none!important}

            /* Teaser */
            .fnd-teaser{position:fixed;right:24px;bottom:92px;width:clamp(360px, 42vw, 560px);max-width:calc(100vw - 32px);background:#fff;border:1px solid var(--fnd-border);border-radius:16px;box-shadow:0 10px 30px rgba(0,0,0,.15);padding:14px;z-index:2147483647}
            .fnd-teaser[hidden]{display:none!important}
            .fnd-teaser .hdr{display:flex;align-items:center;gap:10px;margin-bottom:6px}
            .fnd-teaser .title{font-weight:800}
            .fnd-teaser .x{margin-left:auto;background:#eef2f7;border-radius:50%;width:24px;height:24px;display:grid;place-items:center;cursor:pointer;line-height:1;font-size:18px;font-weight:700;color:#0f172a}
            .fnd-teaser .x:focus-visible{outline:2px solid #0f172a;outline-offset:2px}
            .fnd-teaser .sub{color:#6b7280;font-size:12px}
            .fnd-teaser .stack{display:flex;align-items:center;gap:6px;margin:8px 0}
            .fnd-teaser .stack .dot{width:8px;height:8px;background:#22c55e;border-radius:50%}
            .fnd-teaser .agents{display:flex}
            .fnd-teaser .agents img{width:24px;height:24px;border-radius:50%;border:2px solid #fff;margin-left:-6px}
            .fnd-teaser .actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}
            .fnd-teaser .btn{flex:1 1 160px;border:0;border-radius:9999px;padding:10px 12px;font-weight:700;cursor:pointer}
            .fnd-teaser .btn-primary{background:var(--fnd-accent);color:#fff}
            .fnd-teaser .btn-secondary{background:#f5f7f9;color:#0f172a}

            .typing-indicator{display:inline-flex;align-items:center;height:20px}
            .typing-indicator span{display:inline-block;width:8px;height:8px;background-color:var(--fnd-muted);border-radius:50%;margin-right:4px;animation:typing-bounce 1.4s infinite ease-in-out}
            .typing-indicator span:nth-child(2){animation-delay:.2s}.typing-indicator span:nth-child(3){animation-delay:.4s}
            @keyframes typing-bounce{0%,60%,100%{transform:translateY(0)}30%{transform:translateY(-5px)}}

            @media (max-width:480px){
              .fnd-conversa{right:12px;left:12px;width:auto}
              .fnd-fab{right:12px;bottom:12px}
              .fnd-teaser{right:12px;bottom:80px;width:auto}
            }
        </style>
        <?php
    }

    private static function render_widget(array $args): void {
        if (self::$rendered_once) return;
        self::$rendered_once = true;

        $defaults = [
            'title'         => 'Frontdesk AI',
            'subtitle'      => 'Foundation • <a href="https://inkfire.co.uk" target="_blank" rel="noopener">Inkfire Ltd</a>',
            'greeting'      => "Hi! I'm Frontdesk AI. How can I help today?\nHours: Mon–Fri: 9am–5pm\nSat–Sun: Closed",
            'show_launcher' => true,
        ];
        $args = array_merge($defaults, $args);

        $opts        = get_option('fnd_conversa_options', []);
        $provider    = is_array($opts) && !empty($opts['fd_provider']) ? sanitize_key($opts['fd_provider']) : 'offline';
        $force_off   = !empty($opts['fd_force_offline']);
        $openai_key  = is_array($opts) ? (trim((string)($opts['openai_api_key'] ?? '')) ?: trim((string)($opts['api_key'] ?? ''))) : '';
        $gemini_key  = is_array($opts) ? trim((string)($opts['gemini_api_key'] ?? '')) : '';
        $flow_json   = is_array($opts) ? (string)($opts['offline_flow'] ?? '[]') : '[]';
        $use_flow_online = !empty($opts['use_flow_online']);

        // Knowledge Base (FAQs) — JSON array of {q, a, url?}
        $faqs_json   = is_array($opts) ? (string)($opts['faqs_json'] ?? '[]') : '[]';

        // Optional business hours (safe defaults)
        $biz_hours   = is_array($opts) && !empty($opts['business_hours']) ? $opts['business_hours'] : [
            'monday' => ['09:00','17:00'], 'tuesday' => ['09:00','17:00'], 'wednesday' => ['09:00','17:00'], 'thursday' => ['09:00','17:00'], 'friday' => ['09:00','17:00'], 'saturday' => 'closed', 'sunday' => 'closed'
        ];
        $tz_string   = get_option('timezone_string') ?: 'UTC';

        // Woo upsell (optional)
        $upsell_enabled = !empty($opts['upsell_enabled']);
        $upsell_ids_raw = is_array($opts) ? trim((string)($opts['upsell_products'] ?? '')) : '';
        $upsell_ids = array_filter(array_map('intval', array_map('trim', explode(',', $upsell_ids_raw))));

        $offline = (defined('FOUNDATION_FD_OFFLINE') && FOUNDATION_FD_OFFLINE) || $force_off
                   || $provider === 'offline'
                   || ($provider === 'openai' && $openai_key === '')
                   || ($provider === 'gemini' && $gemini_key === '');

        $show_offline_admin = current_user_can('manage_options') && $offline;

        $widget_id   = 'fnd-frontdesk-' . wp_generate_uuid4();
        $launcher_id = $widget_id . '-launcher';

        $contact_nonce = wp_create_nonce('fnd_frontdesk_contact');
        $chat_nonce    = wp_create_nonce('fnd_conversa');

        $bot_avatar = self::get_seo_image_url();
        $site_name  = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);

        // Upsell card strip
        $upsell_html = '';
        if ($upsell_enabled && !empty($upsell_ids) && function_exists('wc_get_product')) {
            $cards = '';
            foreach ($upsell_ids as $pid) {
                $p = wc_get_product($pid);
                if (!$p) continue;
                $link = get_permalink($pid);
                $title = $p->get_name();
                $img = get_the_post_thumbnail_url($pid, 'medium') ?: wc_placeholder_img_src('medium');
                $cards .= '<div class="fnd-upsell-card">'
                        . '<a href="'.esc_url($link).'" target="_blank" rel="noopener"><img src="'.esc_url($img).'" alt=""></a>'
                        . '<div class="cap"><a href="'.esc_url($link).'" target="_blank" rel="noopener">'.esc_html($title).'</a></div>'
                        . '</div>';
            }
            if ($cards) {
                $upsell_html = '<div class="fnd-upsell"><p class="fnd-upsell-title">'.esc_html__('Recommended for you','foundation-frontdesk').'</p><div class="fnd-upsell-list">'.$cards.'</div></div>';
            }
        }

        $time = date_i18n(get_option('time_format') ?: 'H:i');
        ?>

        <div class="fnd-conversa"
             id="<?php echo esc_attr($widget_id); ?>"
             <?php echo $args['show_launcher'] ? 'hidden' : ''; ?>
             role="dialog"
             aria-label="<?php echo esc_attr__('Chat Interface', 'foundation-frontdesk'); ?>"
             aria-live="polite"
             data-offline="<?php echo $offline ? '1' : '0'; ?>"
             data-has-launcher="<?php echo $args['show_launcher'] ? '1' : '0'; ?>"
             data-provider="<?php echo esc_attr($provider); ?>"
             data-flow-online="<?php echo esc_attr($use_flow_online ? '1' : '0'); ?>"
             data-flow="<?php echo esc_attr($flow_json); ?>"
             data-faqs="<?php echo esc_attr($faqs_json); ?>"
             data-ajax-url="<?php echo esc_url( admin_url('admin-ajax.php') ); ?>"
             data-chat-nonce="<?php echo esc_attr($chat_nonce); ?>"
             data-site-name="<?php echo esc_attr($site_name); ?>"
             data-biz-hours="<?php echo esc_attr(wp_json_encode($biz_hours)); ?>"
             data-tz="<?php echo esc_attr($tz_string); ?>"
        >
            <div class="fnd-conversa__header">
                <div class="fnd-conversa__brand">
                    <?php echo self::get_logo_html(); ?>
                    <div>
                        <div class="fnd-conversa__title">
                            <?php echo esc_html($args['title']); ?>
                            <span class="fnd-status-badge <?php echo $show_offline_admin ? 'fnd-status-offline' : 'fnd-status-online'; ?>">
                                <?php echo esc_html($show_offline_admin ? __('Offline (admin)', 'foundation-frontdesk') : __('Online', 'foundation-frontdesk')); ?>
                            </span>
                        </div>
                        <div class="fnd-conversa__subtitle"><?php echo wp_kses_post($args['subtitle']); ?></div>
                    </div>
                </div>
                <div class="fnd-conversa__actions">
                    <button type="button" class="fnd-kb-btn" data-toggle-kb aria-controls="<?php echo esc_attr($widget_id); ?>-kb" aria-expanded="false">
                        <?php esc_html_e('Knowledge Base', 'foundation-frontdesk'); ?>
                    </button>
                    <div class="fnd-conversa__menu" style="position:relative">
                        <button type="button" class="fnd-menu-btn" aria-haspopup="true" aria-expanded="false" aria-controls="<?php echo esc_attr($widget_id); ?>-menu">
                            <span class="fnd-menu-icon" aria-hidden="true"><span></span><span></span><span></span></span>
                            <span class="screen-reader-text"><?php esc_html_e('Open menu','foundation-frontdesk'); ?></span>
                        </button>
                        <div id="<?php echo esc_attr($widget_id); ?>-menu" class="fnd-menu" role="menu" hidden>
                            <button type="button" data-menu-action="save" role="menuitem"><?php esc_html_e('Save transcript','foundation-frontdesk'); ?></button>
                            <button type="button" data-menu-action="close" role="menuitem"><?php esc_html_e('Close','foundation-frontdesk'); ?></button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Knowledge Base panel (expands under header) -->
            <div id="<?php echo esc_attr($widget_id); ?>-kb" class="fnd-kb" role="region" aria-labelledby="<?php echo esc_attr($widget_id); ?>-kb-title" aria-hidden="true">
                <div class="fnd-kb__wrap">
                    <div class="fnd-kb__head">
                        <div id="<?php echo esc_attr($widget_id); ?>-kb-title" class="fnd-kb__title"><?php esc_html_e('Knowledge Base', 'foundation-frontdesk'); ?></div>
                        <button type="button" class="fnd-kb__back" data-kb-back><?php esc_html_e('Back to chat', 'foundation-frontdesk'); ?></button>
                    </div>
                    <div class="fnd-kb__search"><input type="search" placeholder="<?php echo esc_attr__('Search FAQs…','foundation-frontdesk'); ?>" aria-label="<?php echo esc_attr__('Search FAQs','foundation-frontdesk'); ?>" data-kb-filter></div>
                    <div class="fnd-kb__list" data-kb-list></div>
                    <div class="screen-reader-text" aria-live="polite" data-kb-status></div>
                </div>
            </div>

            <div class="fnd-conversa__body">
                <div class="fnd-conversa__messages" data-messages role="log" aria-label="<?php echo esc_attr__('Chat messages', 'foundation-frontdesk'); ?>">
                    <div class="fnd-row-msg" data-role="bot" data-time="<?php echo esc_attr($time); ?>">
                        <div class="fnd-avatar">
                            <?php if ($bot_avatar): ?>
                                <img src="<?php echo esc_url($bot_avatar); ?>" alt="">
                            <?php else: ?>
                                <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><circle cx="12" cy="8" r="4" fill="#6c757d"/><path d="M4 20c0-4 4-6 8-6s8 2 8 6" fill="#6c757d"/></svg>
                            <?php endif; ?>
                        </div>
                        <div class="fnd-bubble">
                            <?php echo nl2br(esc_html($args['greeting'])); ?>
                            <small class="fnd-stamp"><?php echo esc_html($time); ?></small>
                        </div>
                    </div>
                </div>

                <?php echo $upsell_html; ?>

                <form class="fnd-conversa__composer" data-composer>
                    <label class="screen-reader-text" for="<?php echo esc_attr($widget_id); ?>-input"><?php echo esc_html__('Type your message', 'foundation-frontdesk'); ?></label>
                    <input id="<?php echo esc_attr($widget_id); ?>-input" class="fnd-conversa__input" type="text" placeholder="<?php echo esc_attr__('Type your message...', 'foundation-frontdesk'); ?>" autocomplete="off" />
                    <button class="fnd-conversa__send" type="submit"><?php echo esc_html__('Send', 'foundation-frontdesk'); ?></button>
                </form>

                <div>
                    <div class="fnd-conversa__note">
                        <button type="button" class="fnd-conversa__noteBtn" data-toggle-contact aria-expanded="false" aria-controls="<?php echo esc_attr($widget_id); ?>-contact">
                            <?php echo esc_html__('Contact us', 'foundation-frontdesk'); ?>
                        </button>
                    </div>

                    <div class="fnd-conversa__contact" id="<?php echo esc_attr($widget_id); ?>-contact" data-contact hidden>
                        <form class="fnd-conversa__contactForm" data-contact-form method="post" novalidate>
                            <input type="hidden" name="action" value="fnd_frontdesk_contact">
                            <input type="hidden" name="nonce" value="<?php echo esc_attr($contact_nonce); ?>">
                            <input type="hidden" name="page" value="">
                            <div class="fnd-field">
                                <label for="<?php echo esc_attr($widget_id); ?>-name"><?php echo esc_html__('Your name', 'foundation-frontdesk'); ?></label>
                                <input id="<?php echo esc_attr($widget_id); ?>-name" type="text" name="name" required placeholder="<?php esc_attr_e('Jane Doe','foundation-frontdesk'); ?>">
                            </div>
                            <div class="fnd-field">
                                <label for="<?php echo esc_attr($widget_id); ?>-email"><?php echo esc_html__('Your email', 'foundation-frontdesk'); ?></label>
                                <input id="<?php echo esc_attr($widget_id); ?>-email" type="email" name="email" required placeholder="you@example.com">
                            </div>
                            <div class="fnd-field">
                                <label for="<?php echo esc_attr($widget_id); ?>-message"><?php echo esc_html__('Message', 'foundation-frontdesk'); ?></label>
                                <textarea id="<?php echo esc_attr($widget_id); ?>-message" name="message" required placeholder="<?php esc_attr_e('How can we help?','foundation-frontdesk'); ?>"></textarea>
                            </div>
                            <div class="fnd-actions">
                                <button type="submit" class="fnd-btn" data-contact-send><?php echo esc_html__('Send message', 'foundation-frontdesk'); ?></button>
                                <span class="fnd-status" data-status aria-live="polite"></span>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($args['show_launcher']) : ?>
            <button id="<?php echo esc_attr($launcher_id); ?>" class="fnd-fab" type="button" aria-haspopup="dialog" aria-controls="<?php echo esc_attr($widget_id); ?>" aria-label="<?php esc_attr_e('Open chat','foundation-frontdesk'); ?>">💬</button>
            <div class="fnd-teaser" id="<?php echo esc_attr($launcher_id); ?>-teaser" hidden>
                <div class="hdr">
                    <div class="title"><?php esc_html_e('Got questions? Let us help.','foundation-frontdesk'); ?></div>
                    <div class="x" role="button" tabindex="0" data-teaser-close aria-label="<?php esc_attr_e('Dismiss','foundation-frontdesk'); ?>">×</div>
                </div>
                <div class="stack">
                    <span class="dot" aria-hidden="true"></span>
                    <span class="sub"><?php esc_html_e('Team is online','foundation-frontdesk'); ?></span>
                    <div class="agents" aria-hidden="true" style="margin-left:auto">
                        <img src="<?php echo esc_url( get_site_icon_url(32) ?: 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==' ); ?>" alt="">
                    </div>
                </div>
                 <div class="actions">
                     <button type="button" class="btn btn-primary" data-teaser-action="open"><?php esc_html_e('Start a chat','foundation-frontdesk'); ?></button>
                     <button type="button" class="btn btn-secondary" data-teaser-action="kb"><?php esc_html_e('Find an answer','foundation-frontdesk'); ?></button>
                </div>
            </div>
        <?php endif; ?>

        <script>
        (function(){
            var root   = document.getElementById('<?php echo esc_js($widget_id); ?>');
            if (!root) return;

            var hasLauncher = root.getAttribute('data-has-launcher') === '1';
            var form    = root.querySelector('[data-composer]');
            var input   = form ? form.querySelector('.fnd-conversa__input') : null;
            var msgs    = root.querySelector('[data-messages]');
            var launch  = hasLauncher ? document.getElementById('<?php echo esc_js($launcher_id); ?>') : null;
            var teaser  = hasLauncher ? document.getElementById('<?php echo esc_js($launcher_id); ?>-teaser') : null;
            var noteBtn = root.querySelector('[data-toggle-contact]');
            var drawer  = root.querySelector('[data-contact]');
            var cform   = root.querySelector('[data-contact-form]');
            var csend   = root.querySelector('[data-contact-send]');
            var cstat   = root.querySelector('[data-status]');
            var offline = root.getAttribute('data-offline') === '1';
            var useFlowOnline = root.getAttribute('data-flow-online') === '1';
            var ajaxUrl = root.getAttribute('data-ajax-url');
            var chatNonce = root.getAttribute('data-chat-nonce');
            var siteName = root.getAttribute('data-site-name') || 'our site';

            var menuBtn = root.querySelector('.fnd-menu-btn');
            var menu    = root.querySelector('.fnd-menu');
            var header  = root.querySelector('.fnd-conversa__header');

            var kbBtn   = root.querySelector('[data-toggle-kb]');
            var kb      = document.getElementById('<?php echo esc_js($widget_id); ?>-kb');
            var kbBack  = kb ? kb.querySelector('[data-kb-back]') : null;
            var kbList  = kb ? kb.querySelector('[data-kb-list]') : null;
            var kbFilter= kb ? kb.querySelector('[data-kb-filter]') : null;
            var kbStatus= kb ? kb.querySelector('[data-kb-status]') : null;

            // Flow + FAQs
            var flow   = []; try { flow = JSON.parse(root.getAttribute('data-flow') || '[]') || []; } catch(e){ flow = []; }
            if (!Array.isArray(flow)) flow = [];
            var faqs   = []; try { faqs = JSON.parse(root.getAttribute('data-faqs') || '[]') || []; } catch(e){ faqs = []; }

            // Accessibility shortcuts / ESC behavior
            root.addEventListener('keydown', function(e){
                if (e.key === 'Escape'){
                    e.stopPropagation();
                    if (!kbHidden()) { kbClose(); } else { closeWidget(); }
                }
            });

            function openWidget(){ if (!hasLauncher) return; root.hidden = false; if (launch) launch.hidden = true; hideTeaserImmediate(); if (input) setTimeout(function(){ input.focus(); }, 10); }
            function closeWidget(){ if (!hasLauncher) return; root.hidden = true; if (launch) { launch.hidden = false; launch.focus(); } if (drawer && !drawer.hidden) { drawer.hidden = true; if (noteBtn) noteBtn.setAttribute('aria-expanded','false'); } hideMenu(); kbClose(); }
            if (launch) launch.addEventListener('click', openWidget);

            // KB helpers
            function kbHidden(){ return !kb || kb.getAttribute('aria-hidden') !== 'false'; }
            function kbOpen(){
                if (!kb) return;
                if (kbList && !kbList.childElementCount) buildKB(faqs);
                if (header){ kb.style.top = (header.offsetTop + header.offsetHeight) + 'px'; }
                kb.style.left='0'; kb.style.right='0'; kb.style.bottom='0'; kb.style.position='absolute';
                kb.setAttribute('aria-hidden','false');
                if (kbBtn) kbBtn.setAttribute('aria-expanded','true');
                if (drawer && !drawer.hidden) { drawer.hidden = true; if (noteBtn) noteBtn.setAttribute('aria-expanded','false'); }
                if (kbFilter) kbFilter.focus();
            }
            function kbClose(){ if (!kb) return; kb.setAttribute('aria-hidden','true'); if (kbBtn) kbBtn.setAttribute('aria-expanded','false'); if (input) input.focus(); }
            function buildKB(items){
                kbList.innerHTML = '';
                if (!Array.isArray(items) || !items.length){
                    var empty = document.createElement('div'); empty.className = 'fnd-kb__item';
                    empty.textContent = '<?php echo esc_js(__('No FAQs yet.','foundation-frontdesk')); ?>' + (<?php echo current_user_can('manage_options') ? 'true':'false'; ?> ? ' — <?php echo esc_js(__('add some in Settings → Frontdesk AI','foundation-frontdesk')); ?>' : '');
                    kbList.appendChild(empty); return;
                }
                items.forEach(function(f,i){
                    var item = document.createElement('div'); item.className='fnd-kb__item';
                    var qId = 'kbq-'+i+'-'+Math.random().toString(36).slice(2);
                    var aId = 'kba-'+i+'-'+Math.random().toString(36).slice(2);
                    item.innerHTML = '<button type="button" class="fnd-kb__q" id="'+qId+'" aria-expanded="false" aria-controls="'+aId+'">'
                                   +   '<span>'+esc(f.q||'FAQ')+'</span>'
                                   +   '<span class="fnd-kb__caret" aria-hidden="true">▾</span>'
                                   + '</button>'
                                   + '<div class="fnd-kb__a" id="'+aId+'" hidden>'
                                   +   '<div>'+esc(f.a||'')+'</div>'
                                   +   (f.url ? '<div style="margin-top:6px"><a href="'+f.url+'" target="_blank" rel="noopener"><?php echo esc_js(__('Learn more','foundation-frontdesk')); ?></a></div>' : '')
                                   + '</div>';
                    kbList.appendChild(item);
                });
            }
            function esc(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

            if (kbBtn) kbBtn.addEventListener('click', function(){ if (kbHidden()) kbOpen(); else kbClose(); });
            if (kbBack) kbBack.addEventListener('click', kbClose);
            if (kb && kbFilter){
                function resizeObs(){ if (!kbHidden() && header){ kb.style.top = (header.offsetTop + header.offsetHeight) + 'px'; } }
                window.addEventListener('resize', resizeObs);
                kbFilter.addEventListener('input', function(){
                    var q = (kbFilter.value||'').toLowerCase().trim();
                    var filtered = !q ? faqs : faqs.filter(function(f){
                        return String(f.q||'').toLowerCase().includes(q) || String(f.a||'').toLowerCase().includes(q);
                    });
                    buildKB(filtered);
                    if (kbStatus) kbStatus.textContent = filtered.length + ' <?php echo esc_js(__('result(s)','foundation-frontdesk')); ?>';
                });
                kb.addEventListener('click', function(e){
                    var qbtn = e.target.closest('.fnd-kb__q'); if (!qbtn) return;
                    var ans = document.getElementById(qbtn.getAttribute('aria-controls'));
                    var exp = qbtn.getAttribute('aria-expanded')==='true';
                    qbtn.setAttribute('aria-expanded', exp?'false':'true');
                    if (ans) ans.hidden = exp;
                });
                kb.addEventListener('keydown', function(e){ if (e.key==='Escape'){ e.stopPropagation(); kbClose(); }});
            }

            // Teaser behavior
            var hideTimer = null;
            function showTeaserSticky(){ if (!teaser) return; teaser.hidden = false; if (hideTimer) { clearTimeout(hideTimer); hideTimer = null; } }
            function scheduleHideTeaser(){ if (!teaser) return; if (hideTimer) clearTimeout(hideTimer); hideTimer = setTimeout(function(){ var overFab = launch && (launch.matches(':hover') || launch === document.activeElement); var overTeaser = teaser && (teaser.matches(':hover') || teaser.contains(document.activeElement)); if (!overFab && !overTeaser) teaser.hidden = true; }, 220); }
            function hideTeaserImmediate(){ if (!teaser) return; if (hideTimer) { clearTimeout(hideTimer); hideTimer = null; } teaser.hidden = true; }
            if (launch && teaser){
                launch.addEventListener('mouseenter', showTeaserSticky);
                launch.addEventListener('focus', showTeaserSticky);
                launch.addEventListener('mouseleave', scheduleHideTeaser);
                launch.addEventListener('blur', scheduleHideTeaser);
                teaser.addEventListener('mouseenter', showTeaserSticky);
                teaser.addEventListener('mouseleave', scheduleHideTeaser);
                teaser.addEventListener('click', function(e){
                    if (e.target.closest('[data-teaser-close]')){ hideTeaserImmediate(); return; }
                    var a = e.target.closest('[data-teaser-action]'); if (!a) return;
                    if (a.getAttribute('data-teaser-action')==='open') { openWidget(); return; }
                    if (a.getAttribute('data-teaser-action')==='kb') { openWidget(); kbOpen(); return; }
                });
                var closer = teaser.querySelector('[data-teaser-close]'); if (closer){ closer.addEventListener('keydown', function(e){ if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); hideTeaserImmediate(); } }); }
            }

            // ===== Offline AI-like responder =====
            function calculateTypingDelay(text){ var base=600; var wpm=180; var words=(String(text||'').trim().split(/\s+/)||[]).length; return Math.max(base, Math.round((words/wpm)*60*1000)); }

            function getBusinessHoursResponse(){
                var now = new Date(); var h = now.getHours(); var d = now.getDay();
                var weekend = (d===0||d===6);
                var base = "\uD83D\uDCC5 Our business hours are:\nMon–Fri: 9am–5pm\nSat–Sun: Closed";
                if (weekend) base += "\n\n\u26D4 We're closed for the weekend, but you can leave a message and we'll reply Monday.";
                else if (h < 9) base += "\n\n\uD83C\uDF05 We'll be opening soon. Feel free to leave a message.";
                else if (h >= 17) base += "\n\n\uD83C\uDF19 We've closed for the day. Leave a message and we'll get back to you tomorrow.";
                else base += "\n\n\u2705 We're open right now!";
                return { text: base, actions: ['contact','faq'], delay: 700 };
            }

            function mapAction(action, label){ var lower=(String(label||'').toLowerCase()); if (action==='search' || /search/.test(lower)) return 'search'; if (action==='contact' || /contact/.test(lower)) return 'contact'; if (action==='hours' || /hour/.test(lower)) return 'hours'; if (action==='faq' || /faq/.test(lower)) return 'faq'; return (action||''); }
            function formatFlowResponse(step){ if (!step) return null; var actions=[]; if (Array.isArray(step.buttons)){ step.buttons.forEach(function(btn){ var a = mapAction(btn.action, btn.text); if (a) actions.push(a); }); } return { text: step.message || step.text || '', actions: actions, delay: 800, flowStep: step.id, isFlow:true }; }
            function checkFlowMatch(originalText, lowerText){
                for (var i=0;i<flow.length;i++){
                    var step=flow[i]; if (!step || !Array.isArray(step.triggers)) continue;
                    for (var j=0;j<step.triggers.length;j++){
                        var t=(step.triggers[j]||'').toLowerCase(); if (t && lowerText.indexOf(t)!==-1) return formatFlowResponse(step);
                    }
                }
                var entry = flow.find(function(s){ return s && (s.entry===true || s.id==='welcome'); }) || flow[0];
                if (entry && /^(hi|hello|hey|hiya|howdy|start|begin)/.test(lowerText)) return formatFlowResponse(entry);
                return null;
            }
            function intelligentResponse(text){
                var lower=(text||'').trim().toLowerCase();
                var hour=new Date().getHours();
                if (/^(hi|hello|hey|hiya|howdy|good\s+(morning|afternoon|evening))\b/.test(lower)){
                    var tg = hour<12?'Good morning':hour<17?'Good afternoon':'Good evening';
                    var options=[ tg+"! Welcome to "+siteName+" — how can we help?", "Hello! Thanks for visiting "+siteName+". What can I help you discover today?", "Hi there! I'm your assistant for "+siteName+". How can I make your visit easier?" ];
                    return { text: options[Math.floor(Math.random()*options.length)], actions:['search','contact','faq'], delay: 900 };
                }
                if (/(hours?|open|close|time|when.*open)/.test(lower)) return getBusinessHoursResponse();
                if (/(contact|email|phone|support|help|speak|talk)/.test(lower)) return { text: "I can connect you with the right person or help right here. What works best?", actions:['contact','faq','hours'], delay: 900, followUp: 'Tell me a bit about what you need and I can route you faster.' };
                if (/(product|service|what.*do|offer|buy|price|cost)/.test(lower)) return { text: "Happy to help! I can search the site or get you in touch for tailored suggestions.", actions:['search','contact'], delay: 900, followUp: 'Are you after something specific or a quick overview?' };
                if (/(help|support|problem|issue|trouble|stuck|broken|error|fix)/.test(lower)) return { text: "I'm on it. I can look for solutions or connect you with support.", actions:['search','contact','faq'], delay: 800, followUp:'What seems to be the issue? Any details help.' };
                if (/(thank|thanks|great|good|awesome|excellent|love|perfect|amazing)/.test(lower)) return { text: "You're welcome! Anything else I can do for you at "+siteName+"?", actions:['search','faq'], delay: 600 };
                if (/(bad|terrible|awful|hate|disappointed|frustrated|angry|complain)/.test(lower)) return { text: "I'm sorry about that experience. Let me connect you with someone who can help right away.", actions:['contact'], delay: 700, autoAction:'contact' };
                var fallbacks=["I want to give the best answer. Could you share a bit more?", "I'm here to help you find exactly what you need — what topic should we look at?", "I can search the site, open FAQs, or connect you with our team."]; 
                return { text: fallbacks[Math.floor(Math.random()*fallbacks.length)], actions:['search','contact','faq'], delay: 900 };
            }
            function generateResponse(text){ var lower=(text||'').toLowerCase(); if (flow && flow.length){ var matched = checkFlowMatch(text, lower); if (matched) return matched; } return intelligentResponse(text); }

            function typingBubble(){ var row=document.createElement('div'); row.className='fnd-row-msg'; row.setAttribute('data-role','bot'); row.innerHTML = '<div class="fnd-avatar">'+botAvatar()+'</div><div class="fnd-bubble"><div class="typing-indicator"><span></span><span></span><span></span></div></div>'; msgs.appendChild(row); msgs.scrollTop=msgs.scrollHeight; return row; }
            function botAvatar(){ var url = <?php echo $bot_avatar ? json_encode(esc_url($bot_avatar)) : 'null'; ?>; if (url) return '<img src="'+url+'" alt="">'; return '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><circle cx="12" cy="8" r="4" fill="#6c757d"/><path d="M4 20c0-4 4-6 8-6s8 2 8 6" fill="#6c757d"/></svg>'; }
            function userAvatar(){ return '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><circle cx="12" cy="8" r="4" fill="#6c757d"/><path d="M4 20c0-4 4-6 8-6s8 2 8 6" fill="#6c757d"/></svg>'; }
            function timeNow(){ try{ return new Date().toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}); }catch(e){ return '—:—'; } }

            function addUserMsg(text){ var row=document.createElement('div'); row.className='fnd-row-msg fnd-row-msg--user'; row.setAttribute('data-role','user'); row.setAttribute('data-time', timeNow()); var bubble=document.createElement('div'); bubble.className='fnd-bubble'; bubble.textContent=text+'\n'; var stamp=document.createElement('small'); stamp.className='fnd-stamp'; stamp.textContent=timeNow(); bubble.appendChild(stamp); var av=document.createElement('div'); av.className='fnd-avatar'; av.innerHTML=userAvatar(); row.appendChild(bubble); row.appendChild(av); msgs.appendChild(row); msgs.scrollTop=msgs.scrollHeight; return row; }
            function addBotMsg(text, isHTML){ var row=document.createElement('div'); row.className='fnd-row-msg'; row.setAttribute('data-role','bot'); row.setAttribute('data-time', timeNow()); var bubble=document.createElement('div'); bubble.className='fnd-bubble'; if(isHTML) bubble.innerHTML=text; else bubble.textContent=text+'\n'; var stamp=document.createElement('small'); stamp.className='fnd-stamp'; stamp.textContent=timeNow(); bubble.appendChild(stamp); var av=document.createElement('div'); av.className='fnd-avatar'; av.innerHTML=botAvatar(); row.appendChild(av); row.appendChild(bubble); msgs.appendChild(row); msgs.scrollTop=msgs.scrollHeight; return row; }

            function inlineActions(container, actions){ if (!actions || !actions.length) return; var wrap=document.createElement('div'); wrap.className='fnd-inline-actions'; var map={ 'search':'<button class="fnd-inline-btn" data-act="search">Search site</button>', 'contact':'<button class="fnd-inline-btn" data-act="contact">Open contact form</button>', 'faq':'<button class="fnd-inline-btn" data-act="faq">Browse FAQs</button>', 'hours':'<button class="fnd-inline-btn" data-act="hours">Business hours</button>' }; actions.forEach(function(a){ if(map[a]) wrap.innerHTML += map[a]; }); container.appendChild(wrap); }

            // Site REST search helper (core WP; dual endpoint for permalink/rest_route setups)
            function wpRestSearch(term){
              var base = window.location.origin;
              var url1 = base + '/wp-json/wp/v2/search?search=' + encodeURIComponent(term) + '&per_page=5';
              var url2 = base + '/?rest_route=/wp/v2/search&search=' + encodeURIComponent(term) + '&per_page=5';
              return fetch(url1).then(function(r){ if(!r.ok) throw 0; return r.json(); })
                .catch(function(){ return fetch(url2).then(function(r){ if(!r.ok) throw 0; return r.json(); }); });
            }

            // Message submit
            if (form && input && msgs){
                form.addEventListener('submit', function(e){ e.preventDefault(); var text=(input.value||'').trim(); if(!text) return; addUserMsg(text); input.value=''; input.focus();
                    if (offline || useFlowOnline){ handleOffline(text); } else { sendChat(text); }
                });
            }

            function handleOffline(text){ var resp = generateResponse(text); var typing = typingBubble(); setTimeout(function(){ try{msgs.removeChild(typing);}catch(_){} var reply = addBotMsg(resp.text, false); inlineActions(reply.querySelector('.fnd-bubble'), resp.actions); if (resp.autoAction==='contact'){ setTimeout(function(){ toggleContact(true); }, 800); } if (resp.followUp){ setTimeout(function(){ var t2=typingBubble(); setTimeout(function(){ try{msgs.removeChild(t2);}catch(_){} addBotMsg(resp.followUp, false); }, 600); }, 1800); } }, calculateTypingDelay(resp.text||'Okay.')); }

            // Click handlers for inline actions
            msgs.addEventListener('click', function(e){ var b = e.target.closest('.fnd-inline-btn'); if(!b) return; var act=b.getAttribute('data-act'); if (act==='contact'){ toggleContact(true); return; } if (act==='faq'){ kbOpen(); return; } if (act==='hours'){ var r=getBusinessHoursResponse(); addBotMsg(r.text,false); return; } if (act==='search'){ var lastUser = msgs.querySelector('.fnd-row-msg[data-role="user"]:last-of-type .fnd-bubble'); var q = lastUser ? lastUser.childNodes[0].textContent.trim() : ''; addBotMsg('Searching the site…', false); wpRestSearch(q).then(function(results){ if (Array.isArray(results) && results.length){ var html='<div>Here are some pages that might help:</div><ul style="margin:8px 0 0 0;padding-left:20px;">'; results.forEach(function(it){ var url=it.url||it.link||'#'; var title=(it.title && it.title.rendered)?it.title.rendered:(it.title||(it.slug?it.slug.replace(/-/g,' '):'Untitled')); html+='<li style="margin-bottom:6px;"><a href="'+url+'" target="_blank" rel="noopener" style="color:var(--fnd-accent);text-decoration:none;">'+title+'</a></li>'; }); html+='</ul>'; addBotMsg(html,true); } else { addBotMsg('I couldn’t find anything specific. You can open the contact form below.', false); } }).catch(function(){ addBotMsg('Site search isn’t available right now. You can still use the contact form.', false); }); return; } });

            // Contact drawer
            function toggleContact(open){ if (!noteBtn || !drawer) return; var expanded = (open === undefined) ? (noteBtn.getAttribute('aria-expanded') === 'true') : !open; var newState=!expanded; noteBtn.setAttribute('aria-expanded', newState?'true':'false'); drawer.hidden=!newState; if (newState){ kbClose(); var first=drawer.querySelector('input, textarea'); if (first) setTimeout(function(){ first.focus(); }, 80); } }
            if (noteBtn) noteBtn.addEventListener('click', function(){ toggleContact(); });

            // Pre-fill contact "page" field with the current URL
            if (cform){ try { var pageField = cform.querySelector('input[name="page"]'); if (pageField) pageField.value = window.location.href; } catch(e){} }

            // Contact submit
            if (cform && csend){ cform.addEventListener('submit', function(e){ e.preventDefault(); var fd=new FormData(cform); var params=new URLSearchParams(fd); csend.disabled=true; csend.textContent='<?php echo esc_js(__('Sending...', 'foundation-frontdesk')); ?>'; if (cstat) { cstat.textContent=''; cstat.className='fnd-status'; }
                fetch(ajaxUrl, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'}, body: params.toString() })
                .then(function(r){ return r.json(); })
                .then(function(data){ if (data && data.success){ if (cstat){ cstat.textContent = (data.data && data.data.message) ? data.data.message : 'Thank you! We have received your message.'; cstat.className='fnd-status fnd-status--ok'; } cform.reset(); } else { var msg=(data && data.data && data.data.message)?data.data.message:'Sorry, something went wrong.'; if (cstat){ cstat.textContent = msg; cstat.className='fnd-status fnd-status--err'; } } })
                .catch(function(){ if (cstat){ cstat.textContent='Network error. Please try again.'; cstat.className='fnd-status fnd-status--err'; } })
                .finally(function(){ csend.disabled=false; csend.textContent='<?php echo esc_js(__('Send message', 'foundation-frontdesk')); ?>'; });
            }); }

            // Online mode (demo)
            async function sendChat(message){ var typing = typingBubble(); try { var body = new URLSearchParams(); body.append('action','fnd_conversa_chat'); body.append('nonce', chatNonce); body.append('message', message); var res = await fetch(ajaxUrl, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'}, body: body.toString() }); var data = await res.json(); msgs.removeChild(typing); if (data && data.success && data.data && data.data.reply){ addBotMsg(data.data.reply, true); } else { addBotMsg('Sorry — I couldn’t get an answer just now. Try the Knowledge Base or the contact form below.', false); } } catch(e){ try { msgs.removeChild(typing);} catch(_){} addBotMsg('Something went wrong talking to the assistant. You can still use the contact form below.', false); } }

            // Menu
            function hideMenu(){ if(menu){ menu.hidden = true; if (menuBtn) menuBtn.setAttribute('aria-expanded','false'); } }
            if (menuBtn && menu){
                menuBtn.addEventListener('click', function(){
                    var isOpen = menu.hidden === false;
                    menu.hidden = isOpen;
                    menuBtn.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
                });
                document.addEventListener('click', function(e){
                    if (!menu.contains(e.target) && !menuBtn.contains(e.target)) hideMenu();
                });
                menu.addEventListener('click', function(e){
                    var a = e.target.closest('button'); if(!a) return;
                    var act=a.getAttribute('data-menu-action');
                    if(act==='close'){ closeWidget(); }
                    if(act==='save'){ saveTranscript(); }
                });
            }

            function saveTranscript(){ var rows = msgs.querySelectorAll('.fnd-row-msg'); var out = []; rows.forEach(function(r){ var role = r.getAttribute('data-role') || 'bot'; var t = r.getAttribute('data-time') || ''; var bubble = r.querySelector('.fnd-bubble'); if (!bubble) return; var text = bubble.cloneNode(true); text.querySelectorAll('.fnd-stamp, .fnd-inline-actions').forEach(function(n){ n.remove(); }); var content = (text.textContent || '').trim(); out.push('['+t+'] '+ (role==='user'?'User':'Bot') +': '+ content); }); var blob = new Blob([out.join('\n')], {type: 'text/plain'}); var a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = 'frontdesk-transcript.txt'; a.click(); setTimeout(function(){ URL.revokeObjectURL(a.href); }, 2000); }
        })();
        </script>
        <?php
    }

    public static function ajax_chat(): void {
        check_ajax_referer('fnd_conversa', 'nonce');
        $message = sanitize_text_field($_POST['message'] ?? '');
        wp_send_json_success(['reply' => sprintf('You said: %s', esc_html($message))]);
    }

    public static function ajax_contact(): void {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field((string) $_POST['nonce']) : '';
        $nonce_ok = wp_verify_nonce($nonce, 'fnd_frontdesk_contact') || wp_verify_nonce($nonce, 'fnd_conversa_contact');
        if (!$nonce_ok) {
            wp_send_json_error(['message' => __('Security check failed. Please refresh and try again.', 'foundation-frontdesk')], 400);
        }

        $name    = isset($_POST['name'])    ? sanitize_text_field((string) $_POST['name'])       : '';
        $email   = isset($_POST['email'])   ? sanitize_email((string) $_POST['email'])           : '';
        $message = isset($_POST['message']) ? sanitize_textarea_field((string) $_POST['message']): '';
        $page    = isset($_POST['page'])    ? esc_url_raw((string) $_POST['page'])               : '';

        if ($name === '' || $email === '' || $message === '') {
            wp_send_json_error(['message' => __('Please fill in your name, email, and message.', 'foundation-frontdesk')], 422);
        }
        if (!is_email($email)) {
            wp_send_json_error(['message' => __('Please enter a valid email address.', 'foundation-frontdesk')], 422);
        }

        $admin_email = get_option('admin_email');
        if (!$admin_email || !is_email($admin_email)) {
            $admin_email = 'admin@' . parse_url(home_url(), PHP_URL_HOST);
        }
        $site_name = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);

        $admin_subject = sprintf(__('New Frontdesk AI contact from %s — %s', 'foundation-frontdesk'), $name, $site_name);
        $admin_body  = '<html><body>';
        $admin_body .= '<h2 style="margin:0 0 10px 0;">' . esc_html($admin_subject) . '</h2>';
        $admin_body .= '<p><strong>' . esc_html__('From:', 'foundation-frontdesk') . '</strong> ' . esc_html($name) . ' &lt;' . esc_html($email) . '&gt;</p>';
        if (!empty($page)) { $admin_body .= '<p><strong>' . esc_html__('Page:', 'foundation-frontdesk') . '</strong> <a href="' . esc_url($page) . '">' . esc_html($page) . '</a></p>'; }
        $admin_body .= '<p><strong>' . esc_html__('Message:', 'foundation-frontdesk') . '</strong></p>';
        $admin_body .= '<div style="white-space:pre-wrap;border:1px solid #e9ecef;padding:12px;border-radius:10px;">' . nl2br(esc_html($message)) . '</div>';
        $admin_body .= '<p style="margin-top:16px;color:#6c757d;">' . esc_html__('Sent by the Frontdesk AI contact form.', 'foundation-frontdesk') . '</p>';
        $admin_body .= '</body></html>';
        $headers_admin = [ 'Content-Type: text/html; charset=UTF-8', 'Reply-To: ' . $name . ' <' . $email . '>' ];

        $user_subject = sprintf(__('Thanks for contacting %s', 'foundation-frontdesk'), $site_name);
        $user_body  = '<html><body>';
        $user_body .= '<p>' . esc_html(sprintf(__('Hi %s,', 'foundation-frontdesk'), $name)) . '</p>';
        $user_body .= '<p>' . esc_html__('Thanks for getting in touch. We’ve received your message and will reply as soon as possible.', 'foundation-frontdesk') . '</p>';
        $user_body .= '<p><strong>' . esc_html__('Your message:', 'foundation-frontdesk') . '</strong></p>';
        $user_body .= '<div style="white-space:pre-wrap;border:1px solid #e9ecef;padding:12px;border-radius:10px;">' . nl2br(esc_html($message)) . '</div>';
        $user_body .= '<p style="margin-top:16px;color:#6c757d;">' . esc_html__('This is an automatic confirmation from the Frontdesk AI widget — no need to reply.', 'foundation-frontdesk') . '</p>';
        $user_body .= '</body></html>';
        $headers_user = [ 'Content-Type: text/html; charset=UTF-8' ];

        $ok_admin = wp_mail($admin_email, $admin_subject, $admin_body, $headers_admin);
        $ok_user  = wp_mail($email,       $user_subject,  $user_body,  $headers_user);

        if ($ok_admin) wp_send_json_success(['message' => __('Thanks! Your message has been sent.', 'foundation-frontdesk')]);
        wp_send_json_error(['message' => __('Could not send email right now. Please try again later.', 'foundation-frontdesk')], 500);
    }
}

Foundation_Frontdesk::init();
endif;
