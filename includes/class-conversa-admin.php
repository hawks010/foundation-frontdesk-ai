<?php
/**
 * Admin UI for Foundation: Frontdesk AI (CLEAN / FIXED)
 * - Full-screen startup wizard link (uses class-fp-onboarding.php)
 * - Dark-mode working for all controls
 * - Pill-style wizard bar inside main container (keeps 35px gap)
 * - All settings persist (Flow/FAQs/RAG serialized before submit)
 * - A11y-friendly, responsive admin layout
 */

if (!defined('ABSPATH')) { exit; }

if (!class_exists('Foundation_Conversa_Admin')):

class Foundation_Conversa_Admin {

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_admin_menu']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_assets']);
    }

    /** Register consolidated options with sanitize callback */
    public static function register_settings() {
        register_setting(
            'fnd_conversa_options_group',
            'fnd_conversa_options',
            [
                'sanitize_callback' => [__CLASS__, 'sanitize_options'],
            ]
        );
    }

    /** Sanitize all options */
    public static function sanitize_options($in) {
        $out = [];

        $hex = function($c, $fallback = '#4F46E5') {
            $c = is_string($c) ? trim($c) : '';
            $c = ltrim($c, '#');
            if (preg_match('/^([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6}|[A-Fa-f0-9]{8})$/', $c)) {
                return '#' . $c;
            }
            return $fallback;
        };

        $bool = function($v) {
            if (is_bool($v)) return $v;
            if (is_numeric($v)) return (bool)$v;
            if (is_string($v)) {
                $v = strtolower(trim($v));
                return in_array($v, ['1','true','on','yes'], true);
            }
            return false;
        };

        // Keys
        $out['api_key']             = isset($in['api_key']) ? trim((string)$in['api_key']) : '';
        $out['openai_api_key']      = isset($in['openai_api_key']) ? trim((string)$in['openai_api_key']) : '';
        $out['gemini_api_key']      = isset($in['gemini_api_key']) ? trim((string)$in['gemini_api_key']) : '';

        // Provider + flags
        $out['fd_provider']         = isset($in['fd_provider']) ? sanitize_key($in['fd_provider']) : 'offline';
        $out['fd_force_offline']    = $bool($in['fd_force_offline'] ?? false);
        $out['use_flow_online']     = $bool($in['use_flow_online'] ?? true);

        // Basics
        $out['default_personality'] = isset($in['default_personality']) ? sanitize_key($in['default_personality']) : 'friendly_support';
        $out['bot_name']            = isset($in['bot_name']) ? sanitize_text_field($in['bot_name']) : 'Frontdesk AI';

        // Teaser / KB
        $out['teaser_title']        = isset($in['teaser_title']) ? sanitize_text_field($in['teaser_title']) : __('Got questions? Let us help.','foundation-conversa');
        $out['kb_button_label']     = isset($in['kb_button_label']) ? sanitize_text_field($in['kb_button_label']) : __('Knowledge Base','foundation-conversa');
        $out['kb_mode']             = isset($in['kb_mode']) ? sanitize_key($in['kb_mode']) : 'faqs'; // faqs | url
        $out['kb_url']              = isset($in['kb_url']) ? esc_url_raw($in['kb_url']) : '';

        // Colors
        $out['brand_color']          = isset($in['brand_color']) ? $hex($in['brand_color']) : '#4F46E5';
        $out['ui_header_bg']         = isset($in['ui_header_bg']) ? $hex($in['ui_header_bg'], $out['brand_color']) : $out['brand_color'];
        $out['ui_header_text']       = isset($in['ui_header_text']) ? $hex($in['ui_header_text'], '#FFFFFF') : '#FFFFFF';
        $out['ui_text_color']        = isset($in['ui_text_color']) ? $hex($in['ui_text_color'], '#111111') : '#111111';
        $out['ui_button_color']      = isset($in['ui_button_color']) ? $hex($in['ui_button_color'], $out['brand_color']) : $out['brand_color'];
        $out['ui_button_hover_color']= isset($in['ui_button_hover_color']) ? $hex($in['ui_button_hover_color'], '#4338CA') : '#4338CA';

        // Toggles
        $out['enable_contact']       = $bool($in['enable_contact'] ?? true);
        $out['enable_floating']      = $bool($in['enable_floating'] ?? true);

        // Contact + dark mode
        $out['contact_email']        = isset($in['contact_email']) ? sanitize_email($in['contact_email']) : sanitize_email(get_option('admin_email'));
        $out['admin_dark_mode']      = $bool($in['admin_dark_mode'] ?? false);

        // Position
        $allowed_corners = ['bottom_right','bottom_left','top_right','top_left'];
        $corner = isset($in['ui_position_corner']) ? sanitize_key($in['ui_position_corner']) : 'bottom_right';
        $out['ui_position_corner']   = in_array($corner, $allowed_corners, true) ? $corner : 'bottom_right';
        $out['ui_offset_x']          = isset($in['ui_offset_x']) ? max(0, intval($in['ui_offset_x'])) : 20;
        $out['ui_offset_y']          = isset($in['ui_offset_y']) ? max(0, intval($in['ui_offset_y'])) : 20;

        // Text areas
        $out['greeting_text']        = isset($in['greeting_text']) ? sanitize_textarea_field($in['greeting_text']) : "Hi! I'm {bot_name}. How can I help today?\nHours: {hours}\nYou can also {contact}.";
        $out['opening_hours']        = isset($in['opening_hours']) ? sanitize_textarea_field($in['opening_hours']) : "Mon–Fri: 9am–5pm\nSat–Sun: Closed";
        $out['alt_contact']          = isset($in['alt_contact']) ? sanitize_text_field($in['alt_contact']) : 'email us at {admin_email}';
        $out['header_byline']        = isset($in['header_byline']) ? sanitize_text_field($in['header_byline']) : 'by Inkfire Ltd';

        // FAQs JSON (ordered array of {q,a,url?})
        $out['faqs_json']            = self::sanitize_faqs_json($in['faqs_json'] ?? '');

        // RAG post types (optional)
        if (isset($in['rag_post_types'])) {
            $rpt = $in['rag_post_types'];
            if (is_array($rpt)) {
                $rpt = array_map('sanitize_key', $rpt);
                $rpt = array_filter($rpt);
                $out['rag_post_types'] = implode(',', $rpt);
            } elseif (is_string($rpt)) {
                $out['rag_post_types'] = implode(',', array_map('sanitize_key', array_map('trim', explode(',', $rpt))));
            }
        }

        // Offline flow JSON
        $out['offline_flow'] = self::sanitize_offline_flow($in['offline_flow'] ?? '');

        return $out;
    }

    /** Basic validator for the offline flow JSON */
    private static function sanitize_offline_flow($raw) {
        if (!is_string($raw) || $raw === '') return '[]';
        $data = json_decode($raw, true);
        if (!is_array($data)) return '[]';

        $clean = [];
        foreach ($data as $node) {
            if (!is_array($node)) continue;
            $id  = isset($node['id']) ? sanitize_key($node['id']) : '';
            $msg = isset($node['message']) ? sanitize_textarea_field($node['message']) : '';
            if ($id === '' || $msg === '') continue;

            $buttons = [];
            if (!empty($node['buttons']) && is_array($node['buttons'])) {
                foreach ($node['buttons'] as $btn) {
                    if (!is_array($btn)) continue;
                    $label = isset($btn['label']) ? sanitize_text_field($btn['label']) : '';
                    $action = isset($btn['action']) ? (string)$btn['action'] : 'end';
                    $action = trim($action);

                    if (preg_match('/^next:([a-z0-9_\-]+)$/i', $action, $m)) {
                        $action = 'next:' . sanitize_key($m[1]);
                    } elseif (!in_array($action, ['end','contact','search'], true)) {
                        $action = 'end';
                    }
                    if ($label !== '') $buttons[] = ['label'=>$label, 'action'=>$action];
                }
            }
            $clean[] = ['id'=>$id, 'message'=>$msg, 'buttons'=>$buttons];
        }
        return wp_json_encode(array_values($clean));
    }

    /** Sanitize FAQs JSON */
    private static function sanitize_faqs_json($raw) {
        if (!is_string($raw) || $raw === '') return '[]';
        $data = json_decode($raw, true);
        if (!is_array($data)) return '[]';
        $clean = [];
        foreach ($data as $row) {
            if (!is_array($row)) continue;
            $q = isset($row['q']) ? sanitize_text_field($row['q']) : '';
            $a = isset($row['a']) ? sanitize_textarea_field($row['a']) : '';
            $u = isset($row['url']) ? esc_url_raw($row['url']) : '';
            if ($q !== '' && $a !== '') $clean[] = ['q'=>$q,'a'=>$a,'url'=>$u];
        }
        return wp_json_encode(array_values($clean));
    }

    public static function add_admin_menu() {
        global $admin_page_hooks;
        $parent_slug = 'foundation-by-inkfire';

        if (empty($admin_page_hooks[$parent_slug])) {
            add_menu_page(
                __('Foundation','foundation-conversa'),
                __('Foundation','foundation-conversa'),
                'manage_options',
                $parent_slug,
                null,
                'dashicons-hammer',
                12
            );
            remove_submenu_page($parent_slug, $parent_slug);
        }

        add_submenu_page(
            $parent_slug,
            __('Frontdesk AI','foundation-conversa'),
            __('Frontdesk AI','foundation-conversa'),
            'manage_options',
            'foundation-conversa-settings',
            [__CLASS__, 'render_settings_page']
        );
    }

    public static function enqueue_admin_assets($hook) {
        if (strpos($hook, 'foundation-conversa-settings') === false) return;

        // Base + dark-mode + pill bar
        $css = '
        /* Container */
        #fp-dashboard-wrapper{margin:35px;padding:35px;background:#ffffff;border-radius:35px;max-width:unset}
        #fp-dashboard-wrapper .fp-header{display:flex;align-items:center;justify-content:space-between;margin:0 0 12px}
        #fp-dashboard-wrapper .fp-branding-text h1{margin:0;font-size:1.45rem;color:#1f2937}
        #fp-dashboard-wrapper .fp-byline{margin:2px 0 0;color:#4b5563}
        #fp-dashboard-wrapper .fp-badge{display:inline-block;padding:.15rem .5rem;border-radius:6px;font-size:.75rem;background:#212121;color:#fff}
        #fp-dashboard-wrapper .fp-theme-toggle{display:flex;align-items:center;gap:8px}

        /* Pill wizard bar (inside wrapper), 35px gap */
        #fp-dashboard-wrapper .fp-setup-pill{
          margin:35px 0 35px 0; display:flex; align-items:center; gap:12px; flex-wrap:wrap;
          background:#0b1720; color:#fff; border-radius:9999px; padding:10px 14px;
        }
        #fp-dashboard-wrapper .fp-setup-pill .pill-title{font-weight:700}
        #fp-dashboard-wrapper .fp-setup-pill .pill-sub{opacity:.85}
        #fp-dashboard-wrapper .fp-setup-pill .button-primary{background:#04ad93;border-color:#04ad93}

        /* Cards, tables, fields */
        #fp-dashboard-wrapper .fp-card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:18px;margin:35px 0}
        #fp-dashboard-wrapper .fp-card h2{margin:.2rem 0 1rem;font-size:1.1rem;color:#111827}
        #fp-dashboard-wrapper .form-table{margin-top:0}
        #fp-dashboard-wrapper .form-table th{width:260px;vertical-align:top;color:#111827}
        #fp-dashboard-wrapper .form-table label{color:#111827}
        #fp-dashboard-wrapper .form-table input[type=text],
        #fp-dashboard-wrapper .form-table input[type=url],
        #fp-dashboard-wrapper .form-table input[type=email],
        #fp-dashboard-wrapper .form-table input[type=password],
        #fp-dashboard-wrapper .form-table input[type=number],
        #fp-dashboard-wrapper .form-table select,
        #fp-dashboard-wrapper .form-table textarea{max-width:820px;width:100%}
        #fp-dashboard-wrapper .form-table .description{color:#4b5563}

        /* Toggle */
        #fp-dashboard-wrapper .fp-switch{position:relative;display:inline-flex;align-items:center;gap:.6rem;cursor:pointer}
        #fp-dashboard-wrapper .fp-switch input{position:absolute!important;opacity:0!important;width:1px;height:1px;margin:0;pointer-events:none}
        #fp-dashboard-wrapper .fp-toggle-slider{position:relative;width:42px;height:24px;border-radius:999px;background:#cfd3d8;transition:background .2s}
        #fp-dashboard-wrapper .fp-toggle-slider:after{content:"";position:absolute;top:3px;left:3px;width:18px;height:18px;border-radius:999px;background:#ffffff;box-shadow:0 1px 2px rgba(0,0,0,.2);transition:transform .2s}
        #fp-dashboard-wrapper .fp-switch input:checked + .fp-toggle-slider{background:#07a079}
        #fp-dashboard-wrapper .fp-switch input:checked + .fp-toggle-slider:after{transform:translateX(18px)}

        /* Flow builder */
        #fp-dashboard-wrapper .fd-flow-toolbar{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin:0 0 10px}
        #fp-dashboard-wrapper .fd-flow-list{display:flex;flex-direction:column;gap:12px}
        #fp-dashboard-wrapper .fd-step{background:#fafafa;border:1px solid #e5e7eb;border-radius:12px;padding:14px;position:relative}
        #fp-dashboard-wrapper .fd-step .fd-row{display:grid;grid-template-columns:180px 1fr auto;gap:12px;align-items:center;margin:8px 0}
        #fp-dashboard-wrapper .fd-step textarea{width:100%;min-height:70px}
        #fp-dashboard-wrapper .fd-actions{display:flex;gap:6px}
        #fp-dashboard-wrapper .fd-btn{border:1px solid #d1d5db;background:#fff;border-radius:8px;padding:6px 10px;cursor:pointer}
        #fp-dashboard-wrapper .fd-btn--add{background:#04ad93;color:#fff;border-color:#04ad93}
        #fp-dashboard-wrapper .fd-btn--del{background:#fee2e2;border-color:#fecaca}
        #fp-dashboard-wrapper .fd-btn--ghost{background:#fff}
        #fp-dashboard-wrapper .fd-help{margin:6px 0 0;color:#6b7280;font-size:12px}
        #fp-dashboard-wrapper .fd-subtle{font-size:12px;color:#6b7280}

        /* FAQ builder */
        #fp-dashboard-wrapper .fd-faq-toolbar{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin:0 0 10px}
        #fp-dashboard-wrapper .fd-faq-list{display:flex;flex-direction:column;gap:12px}
        #fp-dashboard-wrapper .fd-faq{background:#fafafa;border:1px solid #e5e7eb;border-radius:12px;padding:14px}
        #fp-dashboard-wrapper .fd-faq .fd-row{display:grid;grid-template-columns:1fr auto;gap:12px;align-items:center;margin:8px 0}
        #fp-dashboard-wrapper .fd-faq textarea{width:100%;min-height:70px}

        /* RAG progress */
        #fp-dashboard-wrapper .fp-scan-progress-bar-container{margin-top:8px}
        #fp-dashboard-wrapper .fp-scan-progress-bar{height:10px;background:#e5e7eb;border-radius:6px;overflow:hidden}
        #fp-dashboard-wrapper .fp-scan-progress-bar > span{display:block;width:0;height:100%;background:#4F46E5}

        /* Dark mode */
        #fp-dashboard-wrapper.fp-dark-mode{background:#1a1b1e;color:#f5f7fb}
        #fp-dashboard-wrapper.fp-dark-mode .fp-branding-text h1,
        #fp-dashboard-wrapper.fp-dark-mode .fp-card h2,
        #fp-dashboard-wrapper.fp-dark-mode .fp-byline{color:#f2f2f2}
        #fp-dashboard-wrapper.fp-dark-mode .fp-setup-pill{background:#111826;color:#fff}
        #fp-dashboard-wrapper.fp-dark-mode .fp-card{background:#171717;border-color:#2b3036}
        #fp-dashboard-wrapper.fp-dark-mode .form-table th,
        #fp-dashboard-wrapper.fp-dark-mode .form-table label{color:#f0f2f6}
        #fp-dashboard-wrapper.fp-dark-mode .form-table input[type=text],
        #fp-dashboard-wrapper.fp-dark-mode .form-table input[type=url],
        #fp-dashboard-wrapper.fp-dark-mode .form-table input[type=email],
        #fp-dashboard-wrapper.fp-dark-mode .form-table input[type=password],
        #fp-dashboard-wrapper.fp-dark-mode .form-table input[type=number],
        #fp-dashboard-wrapper.fp-dark-mode .form-table select,
        #fp-dashboard-wrapper.fp-dark-mode .form-table textarea{
          background:#212121;color:#f5f7fb;border:1px solid #07a079
        }
        #fp-dashboard-wrapper.fp-dark-mode .form-table .description,
        #fp-dashboard-wrapper.fp-dark-mode .fd-subtle,
        #fp-dashboard-wrapper.fp-dark-mode .fd-help{color:#cbd5e1}
        #fp-dashboard-wrapper.fp-dark-mode a{color:#df157c}
        #fp-dashboard-wrapper.fp-dark-mode a:hover{color:#b9c7ff}
        #fp-dashboard-wrapper.fp-dark-mode .button-primary{background:#4F46E5;border-color:#4F46E5;color:#ffffff}
        ';
        wp_add_inline_style('common', $css);

        wp_localize_script('jquery-core', 'conversa_admin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('fnd_conversa_admin'),
        ]);
    }

    /** Seed default FAQs if empty */
    private static function default_faq_pack() {
        $site  = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
        $hours = "Mon–Fri: 9am–5pm\nSat–Sun: Closed";
        return [
            ['q'=>"What is {$site}?", 'a'=>"We’re a small team focused on modern WordPress, design and fast sites."],
            ['q'=>"What are your opening hours?", 'a'=>"Our hours are:\n{$hours}"],
            ['q'=>"How do I contact support?", 'a'=>"Use the contact form in the chat, or email {admin_email}. We'll get back ASAP."],
            ['q'=>"Can you build custom features?", 'a'=>"Yes. Share your goals and timeline and we’ll recommend an approach."],
            ['q'=>"Do you work with WooCommerce?", 'a'=>"Absolutely. We can help with store setup, performance and UX."]
        ];
    }

    public static function render_settings_page() {
        $opts = get_option('fnd_conversa_options', []);
        $get  = function($key, $default='') use ($opts){ return isset($opts[$key]) ? $opts[$key] : $default; };

        // Auto-create FAQ pack if empty
        $faqs_json = $get('faqs_json','');
        if (empty($faqs_json) || $faqs_json === '[]') {
            $seed = self::default_faq_pack();
            $faqs_json = wp_json_encode($seed);
            $opts['faqs_json'] = $faqs_json;
            update_option('fnd_conversa_options', $opts, false);
        }

        $api_key     = $get('api_key',''); // legacy OpenAI field
        $personality = $get('default_personality','friendly_support');
        $bot_name    = $get('bot_name','Frontdesk AI');
        $brand_color = $get('brand_color','#4F46E5');
        $contact_on  = (bool)$get('enable_contact', true);
        $bubble_on   = (bool)$get('enable_floating', true);
        $contact_to  = $get('contact_email', get_option('admin_email'));
        $dark_mode   = (bool)$get('admin_dark_mode', false);

        // Provider/keys
        $fd_provider     = $get('fd_provider', 'offline');
        $fd_force_off    = (bool)$get('fd_force_offline', false);
        $openai_api_key  = $get('openai_api_key', ($api_key ?: ''));
        $gemini_api_key  = $get('gemini_api_key', '');
        $use_flow_online = (bool)$get('use_flow_online', true);

        // UI options
        $ui_button_color       = $get('ui_button_color', $brand_color);
        $ui_button_hover_color = $get('ui_button_hover_color', '#4338CA');
        $ui_text_color         = $get('ui_text_color', '#111111');
        $ui_header_bg          = $get('ui_header_bg', $brand_color);
        $ui_header_text        = $get('ui_header_text', '#FFFFFF');

        // Position
        $ui_position_corner    = $get('ui_position_corner', 'bottom_right');
        $ui_offset_x           = (int)$get('ui_offset_x', 20);
        $ui_offset_y           = (int)$get('ui_offset_y', 20);

        // Greeting
        $greeting_text = $get('greeting_text', "Hi! I'm {bot_name}. How can I help today?\nHours: {hours}\nYou can also {contact}.");
        $opening_hours = $get('opening_hours', "Mon–Fri: 9am–5pm\nSat–Sun: Closed");
        $alt_contact   = $get('alt_contact', 'email us at {admin_email}');
        $header_byline = $get('header_byline', 'by Inkfire Ltd');

        // Teaser/KB
        $teaser_title    = $get('teaser_title', __('Got questions? Let us help.','foundation-conversa'));
        $kb_button_label = $get('kb_button_label', __('Knowledge Base','foundation-conversa'));
        $kb_mode         = $get('kb_mode', 'faqs');
        $kb_url          = $get('kb_url', '');

        // Offline flow JSON
        $offline_flow_json = $get('offline_flow', '[]');

        // Wizard slug
        $wizard_slug = class_exists('Foundation_Conversa_Onboarding')
            ? Foundation_Conversa_Onboarding::PAGE_SLUG
            : 'foundation-conversa-onboarding';

        $personalities = function_exists('fnd_conversa_personalities') ? fnd_conversa_personalities() : [
            'friendly_support' => __('Friendly Customer Support','foundation-conversa')
        ];
        $version = defined('FND_CONVERSA_VERSION') ? FND_CONVERSA_VERSION : '1.1.1';
        ?>
        <div id="fp-dashboard-wrapper" class="wrap<?php echo $dark_mode ? ' fp-dark-mode' : ''; ?>">
            <div class="fp-header">
                <div class="fp-branding-text">
                    <h1><?php esc_html_e('Foundation: Frontdesk AI','foundation-conversa'); ?></h1>
                    <p class="fp-byline"><?php esc_html_e('Chat + contact widget. Use [foundation_frontdesk] or enable the floating bubble.', 'foundation-conversa'); ?></p>
                </div>
                <div class="fp-theme-toggle">
                    <label for="fnd-dark-switch" class="screen-reader-text"><?php esc_html_e('Toggle dark mode', 'foundation-conversa'); ?></label>
                    <label class="fp-switch"><input type="checkbox" id="fnd-dark-switch" <?php checked($dark_mode); ?> /><span class="fp-toggle-slider"></span></label>
                    <span class="fp-badge">v<?php echo esc_html($version); ?></span>
                </div>
            </div>

            <!-- Pill wizard bar -->
            <div class="fp-setup-pill" role="region" aria-label="<?php esc_attr_e('Quick setup','foundation-conversa'); ?>">
                <span class="pill-title"><?php esc_html_e('Set up Frontdesk quickly','foundation-conversa'); ?></span>
                <a class="button button-primary" href="<?php echo esc_url( admin_url('admin.php?page='.$wizard_slug) ); ?>">
                    <?php esc_html_e('⚡ Run startup wizard','foundation-conversa'); ?>
                </a>
                <span class="pill-sub"><?php esc_html_e('Launches the full-screen onboarding we already use elsewhere.','foundation-conversa'); ?></span>
            </div>

            <form method="post" action="options.php" id="fnd-admin-form">
                <?php settings_fields('fnd_conversa_options_group'); ?>
                <input type="hidden" id="fnd-dark-hidden" name="fnd_conversa_options[admin_dark_mode]" value="<?php echo $dark_mode ? '1' : '0'; ?>" />
                <input type="hidden" id="fnd-faqs-json" name="fnd_conversa_options[faqs_json]" value="<?php echo esc_attr($faqs_json); ?>" />

                <!-- BASICS -->
                <div class="fp-card">
                    <h2><?php esc_html_e('Basics','foundation-conversa'); ?></h2>
                    <table class="form-table"><tbody>
                        <tr>
                            <th><label for="conversa_personality"><?php esc_html_e('Default Personality','foundation-conversa'); ?></label></th>
                            <td>
                                <select id="conversa_personality" name="fnd_conversa_options[default_personality]">
                                    <?php foreach ($personalities as $key=>$label): ?>
                                        <option value="<?php echo esc_attr($key); ?>" <?php selected($personality, $key); ?>><?php echo esc_html($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="conversa_bot_name"><?php esc_html_e('Bot Name','foundation-conversa'); ?></label></th>
                            <td><input type="text" id="conversa_bot_name" name="fnd_conversa_options[bot_name]" value="<?php echo esc_attr($bot_name); ?>" placeholder="<?php esc_attr_e('Frontdesk AI','foundation-conversa'); ?>" /></td>
                        </tr>

                        <!-- Provider & stacked API keys -->
                        <tr>
                            <th><label for="fd_provider"><?php esc_html_e('Provider','foundation-conversa'); ?></label></th>
                            <td>
                                <select id="fd_provider" name="fnd_conversa_options[fd_provider]">
                                    <option value="offline" <?php selected($fd_provider,'offline'); ?>><?php esc_html_e('Offline (no API)', 'foundation-conversa'); ?></option>
                                    <option value="openai"  <?php selected($fd_provider,'openai');  ?>><?php esc_html_e('OpenAI', 'foundation-conversa'); ?></option>
                                    <option value="gemini"  <?php selected($fd_provider,'gemini');  ?>><?php esc_html_e('Google Gemini', 'foundation-conversa'); ?></option>
                                </select>
                                <label style="margin-left:10px"><input type="checkbox" id="fd_force_off" name="fnd_conversa_options[fd_force_offline]" value="1" <?php checked($fd_force_off); ?> /> <?php esc_html_e('Force Offline Mode', 'foundation-conversa'); ?></label>
                                <p class="description"><?php esc_html_e('If provider keys are empty or Force Offline is enabled, the widget runs in Offline mode.', 'foundation-conversa'); ?></p>

                                <div id="fd-provider-keys" class="fd-provider-keys" style="margin-top:10px;<?php echo ($fd_provider!=='offline' && !$fd_force_off) ? '' : 'display:none;'; ?>">
                                    <div class="fd-key-row">
                                        <label for="fd_openai_key"><?php esc_html_e('OpenAI API Key','foundation-conversa'); ?></label>
                                        <input type="password" id="fd_openai_key" class="regular-text" name="fnd_conversa_options[openai_api_key]" value="<?php echo esc_attr($openai_api_key); ?>" placeholder="<?php esc_attr_e('sk-...','foundation-conversa'); ?>" />
                                        <p class="fd-help-mini">
                                            <?php echo wp_kses_post(sprintf(
                                                __('Generate a key at <a href="%s" target="_blank" rel="noopener noreferrer">platform.openai.com/api-keys</a>. We never transmit keys to Inkfire or third parties.', 'foundation-conversa'),
                                                esc_url('https://platform.openai.com/api-keys')
                                            )); ?>
                                        </p>
                                    </div>
                                    <div class="fd-key-row">
                                        <label for="fd_gemini_key"><?php esc_html_e('Google Gemini API Key','foundation-conversa'); ?></label>
                                        <input type="password" id="fd_gemini_key" class="regular-text" name="fnd_conversa_options[gemini_api_key]" value="<?php echo esc_attr($gemini_api_key); ?>" placeholder="<?php esc_attr_e('AIza...','foundation-conversa'); ?>" />
                                        <p class="fd-help-mini">
                                            <?php echo wp_kses_post(sprintf(
                                                __('Create a key at <a href="%s" target="_blank" rel="noopener noreferrer">ai.google.dev</a> (Google AI Studio).', 'foundation-conversa'),
                                                esc_url('https://ai.google.dev/')
                                            )); ?>
                                        </p>
                                    </div>
                                    <label style="display:block;margin-top:4px">
                                        <input type="checkbox" name="fnd_conversa_options[use_flow_online]" value="1" <?php checked($use_flow_online); ?> />
                                        <?php esc_html_e('Also use Offline Flow as fallback when online (e.g., timeouts or “I don’t know”).', 'foundation-conversa'); ?>
                                    </label>
                                </div>
                            </td>
                        </tr>
                    </tbody></table>
                </div>

                <!-- OFFLINE FLOW BUILDER -->
                <div class="fp-card">
                    <h2><?php esc_html_e('Offline Flow Builder (drag to reorder)','foundation-conversa'); ?></h2>
                    <p class="description">
                        <?php esc_html_e('Define a quick fallback conversation for Offline mode (and optionally as an online fallback). Each “Step” is a bubble the bot will send, with buttons that can go to the next step, open the contact form, search the site, or end. The first step is the entry point.', 'foundation-conversa'); ?>
                    </p>
                    <div class="fd-flow-toolbar">
                        <button type="button" class="fd-btn fd-btn--add" id="fd-add-step"><?php esc_html_e('Add step','foundation-conversa'); ?></button>
                        <button type="button" class="fd-btn" id="fd-load-template"><?php esc_html_e('Load starter template','foundation-conversa'); ?></button>
                        <button type="button" class="fd-btn fd-btn--del" id="fd-clear"><?php esc_html_e('Clear','foundation-conversa'); ?></button>
                        <span class="fd-subtle"><?php esc_html_e('Tip: keep it short—2–4 steps is ideal.','foundation-conversa'); ?></span>
                    </div>
                    <div class="fd-flow-list" id="fd-flow-list" aria-live="polite"></div>
                    <textarea id="fd-offline-flow-json" name="fnd_conversa_options[offline_flow]" rows="5" style="display:none;"><?php echo esc_textarea($offline_flow_json ?: '[]'); ?></textarea>

                    <p class="fd-bridge-note">
                        <?php esc_html_e('The greeting (below) is shown when there is no flow, or after a flow ends. If you prefer, copy your greeting into the first flow step.', 'foundation-conversa'); ?>
                    </p>
                </div>

                <!-- BRANDING & COLOURS -->
                <div class="fp-card">
                    <h2><?php esc_html_e('Branding & Colours','foundation-conversa'); ?></h2>
                    <table class="form-table"><tbody>
                        <tr>
                            <th><label for="conversa_brand_color"><?php esc_html_e('Brand Colour','foundation-conversa'); ?></label></th>
                            <td>
                                <input type="text" id="conversa_brand_color" name="fnd_conversa_options[brand_color]" value="<?php echo esc_attr($brand_color); ?>" placeholder="#DF157C" />
                                <p class="description"><?php esc_html_e('HEX (e.g. #DF157C).','foundation-conversa'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="ui_header_bg"><?php esc_html_e('Header Background','foundation-conversa'); ?></label></th>
                            <td><input type="text" id="ui_header_bg" name="fnd_conversa_options[ui_header_bg]" value="<?php echo esc_attr($ui_header_bg); ?>" placeholder="#1e6167" /></td>
                        </tr>
                        <tr>
                            <th><label for="ui_header_text"><?php esc_html_e('Header Text Colour','foundation-conversa'); ?></label></th>
                            <td><input type="text" id="ui_header_text" name="fnd_conversa_options[ui_header_text]" value="<?php echo esc_attr($ui_header_text); ?>" placeholder="#FFFFFF" /></td>
                        </tr>
                        <tr>
                            <th><label for="ui_text_color"><?php esc_html_e('Body Text Colour','foundation-conversa'); ?></label></th>
                            <td><input type="text" id="ui_text_color" name="fnd_conversa_options[ui_text_color]" value="<?php echo esc_attr($ui_text_color); ?>" placeholder="#111111" /></td>
                        </tr>
                        <tr>
                            <th><label for="ui_button_color"><?php esc_html_e('Button Colour','foundation-conversa'); ?></label></th>
                            <td><input type="text" id="ui_button_color" name="fnd_conversa_options[ui_button_color]" value="<?php echo esc_attr($ui_button_color); ?>" placeholder="#04AD93" /></td>
                        </tr>
                        <tr>
                            <th><label for="ui_button_hover_color"><?php esc_html_e('Button Hover Colour','foundation-conversa'); ?></label></th>
                            <td><input type="text" id="ui_button_hover_color" name="fnd_conversa_options[ui_button_hover_color]" value="<?php echo esc_attr($ui_button_hover_color); ?>" placeholder="#038D78" /></td>
                        </tr>
                    </tbody></table>
                </div>

                <!-- LAUNCHER / TEASER -->
                <div class="fp-card">
                    <h2><?php esc_html_e('Launcher & Teaser','foundation-conversa'); ?></h2>
                    <table class="form-table"><tbody>
                        <tr>
                            <th><label for="teaser_title"><?php esc_html_e('Teaser headline','foundation-conversa'); ?></label></th>
                            <td><input type="text" id="teaser_title" name="fnd_conversa_options[teaser_title]" value="<?php echo esc_attr($teaser_title); ?>"></td>
                        </tr>
                        <tr>
                            <th><label for="kb_button_label"><?php esc_html_e('KB button label','foundation-conversa'); ?></label></th>
                            <td><input type="text" id="kb_button_label" name="fnd_conversa_options[kb_button_label]" value="<?php echo esc_attr($kb_button_label); ?>"></td>
                        </tr>
                        <tr>
                            <th><label><?php esc_html_e('KB action','foundation-conversa'); ?></label></th>
                            <td>
                                <label><input type="radio" name="fnd_conversa_options[kb_mode]" value="faqs" <?php checked($kb_mode,'faqs'); ?>> <?php esc_html_e('Show FAQs in chat (accordion)','foundation-conversa'); ?></label><br>
                                <label><input type="radio" name="fnd_conversa_options[kb_mode]" value="url"  <?php checked($kb_mode,'url');  ?>> <?php esc_html_e('Open external Knowledge Base URL','foundation-conversa'); ?></label>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="kb_url"><?php esc_html_e('KB URL (if action = Open URL)','foundation-conversa'); ?></label></th>
                            <td><input type="url" id="kb_url" name="fnd_conversa_options[kb_url]" value="<?php echo esc_attr($kb_url); ?>" placeholder="https://example.com/help"></td>
                        </tr>
                    </tbody></table>
                </div>

                <!-- CHATBOX BEHAVIOUR -->
                <div class="fp-card">
                    <h2><?php esc_html_e('Chatbox Behaviour','foundation-conversa'); ?></h2>
                    <table class="form-table"><tbody>
                        <tr>
                            <th><?php esc_html_e('Show Contact Form','foundation-conversa'); ?></th>
                            <td><label class="fp-switch"><input type="checkbox" name="fnd_conversa_options[enable_contact]" value="1" <?php checked($contact_on); ?> /><span class="fp-toggle-slider"></span></label></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Floating Bubble','foundation-conversa'); ?></th>
                            <td><label class="fp-switch"><input type="checkbox" name="fnd_conversa_options[enable_floating]" value="1" <?php checked($bubble_on); ?> /><span class="fp-toggle-slider"></span></label></td>
                        </tr>
                    </tbody></table>
                </div>

                <!-- FLOATING & POSITION -->
                <div class="fp-card">
                    <h2><?php esc_html_e('Floating & Position','foundation-conversa'); ?></h2>
                    <table class="form-table"><tbody>
                        <tr>
                            <th><label for="ui_position_corner"><?php esc_html_e('Floating Position','foundation-conversa'); ?></label></th>
                            <td>
                                <select id="ui_position_corner" name="fnd_conversa_options[ui_position_corner]">
                                    <option value="bottom_right" <?php selected($ui_position_corner, 'bottom_right'); ?>><?php esc_html_e('Bottom Right','foundation-conversa'); ?></option>
                                    <option value="bottom_left"  <?php selected($ui_position_corner, 'bottom_left');  ?>><?php esc_html_e('Bottom Left','foundation-conversa'); ?></option>
                                    <option value="top_right"    <?php selected($ui_position_corner, 'top_right');    ?>><?php esc_html_e('Top Right','foundation-conversa'); ?></option>
                                    <option value="top_left"     <?php selected($ui_position_corner, 'top_left');     ?>><?php esc_html_e('Top Left','foundation-conversa'); ?></option>
                                </select>
                                <p class="description"><?php esc_html_e('Choose the corner where the chat floats. Use offsets to fine-tune.', 'foundation-conversa'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="ui_offset_x"><?php esc_html_e('Horizontal Offset (px)','foundation-conversa'); ?></label></th>
                            <td><input type="number" id="ui_offset_x" name="fnd_conversa_options[ui_offset_x]" value="<?php echo esc_attr($ui_offset_x); ?>" min="0" step="1" /></td>
                        </tr>
                        <tr>
                            <th><label for="ui_offset_y"><?php esc_html_e('Vertical Offset (px)','foundation-conversa'); ?></label></th>
                            <td><input type="number" id="ui_offset_y" name="fnd_conversa_options[ui_offset_y]" value="<?php echo esc_attr($ui_offset_y); ?>" min="0" step="1" /></td>
                        </tr>
                    </tbody></table>
                </div>

                <!-- GREETING & CONTACT -->
                <div class="fp-card">
                    <h2><?php esc_html_e('Greeting & Contact','foundation-conversa'); ?></h2>
                    <p class="description">
                        <?php esc_html_e('This greeting appears when there is no Offline Flow, and after a flow completes. You can also copy it into your first flow step for a seamless experience.', 'foundation-conversa'); ?>
                    </p>
                    <table class="form-table"><tbody>
                        <tr>
                            <th><label for="conversa_contact_email"><?php esc_html_e('Contact Email','foundation-conversa'); ?></label></th>
                            <td><input type="email" id="conversa_contact_email" name="fnd_conversa_options[contact_email]" value="<?php echo esc_attr($contact_to); ?>" placeholder="you@example.com" /></td>
                        </tr>
                        <tr>
                            <th><label for="greeting_text"><?php esc_html_e('Opening Greeting','foundation-conversa'); ?></label></th>
                            <td>
                                <textarea id="greeting_text" name="fnd_conversa_options[greeting_text]" rows="3" placeholder="<?php esc_attr_e('Hi! I\'m {bot_name}. How can I help today?\nHours: {hours}\nYou can also {contact}.','foundation-conversa'); ?>"><?php echo esc_textarea($greeting_text); ?></textarea>
                                <p class="description" style="font-size:12px"><?php esc_html_e('Tokens: {bot_name}, {site_name}, {hours}, {contact}. Use line breaks to split lines.', 'foundation-conversa'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="opening_hours"><?php esc_html_e('Opening Hours','foundation-conversa'); ?></label></th>
                            <td><textarea id="opening_hours" name="fnd_conversa_options[opening_hours]" rows="3" placeholder="<?php esc_attr_e('Mon–Fri: 9am–5pm\nSat–Sun: Closed','foundation-conversa'); ?>"><?php echo esc_textarea($opening_hours); ?></textarea></td>
                        </tr>
                        <tr>
                            <th><label for="alt_contact"><?php esc_html_e('Alternative Contact','foundation-conversa'); ?></label></th>
                            <td><input type="text" id="alt_contact" name="fnd_conversa_options[alt_contact]"value="<?php echo esc_attr($alt_contact); ?>"placeholder="<?php esc_attr_e('call 01234 567890 or email {admin_email}','foundation-conversa'); ?>" />
                                <p class="description"><?php esc_html_e('Token {admin_email} is replaced with the site admin email.', 'foundation-conversa'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="header_byline"><?php esc_html_e('Header Byline','foundation-conversa'); ?></label></th>
                            <td><input type="text" id="header_byline" name="fnd_conversa_options[header_byline]" value="<?php echo esc_attr($header_byline); ?>" placeholder="<?php esc_attr_e('by Inkfire Ltd','foundation-conversa'); ?>" /></td>
                        </tr>
                    </tbody></table>
                </div>

                <!-- FAQs BUILDER -->
                <div class="fp-card">
                    <h2><?php esc_html_e('Knowledge Base (FAQs)','foundation-conversa'); ?></h2>
                    <p class="description"><?php esc_html_e('Add collapsible FAQs displayed in the chat. Drag to reorder.','foundation-conversa'); ?></p>

                    <div class="fd-faq-toolbar">
                        <button type="button" class="fd-btn fd-btn--add" id="faq-add"><?php esc_html_e('Add FAQ','foundation-conversa'); ?></button>
                        <button type="button" class="fd-btn" id="faq-load"><?php esc_html_e('Load starter FAQs','foundation-conversa'); ?></button>
                        <button type="button" class="fd-btn fd-btn--del" id="faq-clear"><?php esc_html_e('Clear all','foundation-conversa'); ?></button>
                        <span class="fd-subtle"><?php esc_html_e('Tip: keep questions short; answers 1–3 lines.','foundation-conversa'); ?></span>
                    </div>

                    <div class="fd-faq-list" id="faq-list" aria-live="polite"></div>
                </div>

                <!-- KNOWLEDGE INDEX (Embeddings) -->
                <div class="fp-card">
                    <h2><?php esc_html_e('Knowledge Index (Embeddings)','foundation-conversa'); ?></h2>
                    <p class="description"><?php esc_html_e('Build an embeddings index of your site content (posts, pages, CPTs) so the chatbot answers strictly from your website. You can re-run this any time after content updates.', 'foundation-conversa'); ?></p>
                    <table class="form-table"><tbody>
                        <tr>
                            <th><label for="rag_post_types"><?php esc_html_e('Post Types to Index','foundation-conversa'); ?></label></th>
                            <td>
                                <?php
                                $pt = get_post_types(['public'=>true],'names');
                                unset($pt['attachment']);
                                $saved_pt = array_map('trim', explode(',', (string)($opts['rag_post_types'] ?? 'post,page')));
                                ?>
                                <select id="rag_post_types" multiple size="5" style="max-width:480px" aria-describedby="rag-pt-help">
                                    <?php foreach($pt as $slug): ?>
                                        <option value="<?php echo esc_attr($slug); ?>" <?php selected(in_array($slug,$saved_pt,true)); ?>><?php echo esc_html($slug); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="hidden" id="rag_post_types_hidden" name="fnd_conversa_options[rag_post_types]" value="<?php echo esc_attr($opts['rag_post_types'] ?? 'post,page'); ?>">
                                <p class="description" id="rag-pt-help"><?php esc_html_e('Hold Ctrl/Cmd to select multiple. Defaults to posts and pages.','foundation-conversa'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Actions','foundation-conversa'); ?></th>
                            <td>
                                <button type="button" class="button button-primary" id="fnd-rag-start"><?php esc_html_e('Build / Rebuild Index','foundation-conversa'); ?></button>
                                <button type="button" class="button" id="fnd-rag-stop" style="margin-left:8px"><?php esc_html_e('Stop','foundation-conversa'); ?></button>
                                <div class="fp-scan-progress-bar-container" style="margin-top:10px">
                                    <div id="fnd-rag-bar" class="fp-scan-progress-bar"><span id="fnd-rag-bar-fill"></span></div>
                                    <div id="fnd-rag-status" style="margin-top:8px;color:inherit;opacity:.9"></div>
                                </div>
                            </td>
                        </tr>
                    </tbody></table>
                </div>

                <?php submit_button(); ?>
            </form>
        </div>

        <script>
        (function(){
          // Dark mode toggle
          const wrap = document.getElementById('fp-dashboard-wrapper');
          const sw   = document.getElementById('fnd-dark-switch');
          const hid  = document.getElementById('fnd-dark-hidden');
          if (wrap && sw && hid) {
            const apply = (on) => { wrap.classList.toggle('fp-dark-mode', !!on); hid.value = on ? '1' : '0'; };
            apply(<?php echo $dark_mode ? 'true' : 'false'; ?>);
            sw.addEventListener('change', function(){ apply(this.checked); });
          }

          // Show/hide stacked API key rows based on provider/force offline
          const providerSel = document.getElementById('fd_provider');
          const forceChk    = document.getElementById('fd_force_off');
          const keysWrap    = document.getElementById('fd-provider-keys');
          function toggleKeys(){
            const prov  = providerSel ? providerSel.value : 'offline';
            const force = forceChk && forceChk.checked;
            const show  = (prov !== 'offline') && !force;
            if (keysWrap) keysWrap.style.display = show ? 'block' : 'none';
          }
          if (providerSel) providerSel.addEventListener('change', toggleKeys);
          if (forceChk)    forceChk.addEventListener('change',   toggleKeys);
          toggleKeys();

          // --- Offline Flow Builder ---
          const jsonField = document.getElementById('fd-offline-flow-json');
          const list = document.getElementById('fd-flow-list');
          const addBtn = document.getElementById('fd-add-step');
          const loadBtn = document.getElementById('fd-load-template');
          const clearBtn = document.getElementById('fd-clear');

          let flow = [];
          try { flow = JSON.parse(jsonField.value || '[]') || []; } catch(e){ flow = []; }
          if (!Array.isArray(flow)) flow = [];

          function newId() {
            let i = 1;
            const used = new Set(flow.map(n => n.id));
            while (used.has('s'+i) || used.has(i.toString())) i++;
            return 's' + i;
          }

          function renderFlow() {
            list.innerHTML = '';
            const ids = flow.map(n => n.id);

            flow.forEach((node, idx) => {
              const el = document.createElement('div');
              el.className = 'fd-step';
              el.dataset.index = idx;

              el.innerHTML = `
                <div class="fd-row" style="grid-template-columns:180px 1fr auto">
                  <div class="fd-id">
                    <label class="fd-subtle"><?php echo esc_html__('Step ID','foundation-conversa'); ?></label>
                    <input type="text" class="fd-input-id" value="${node.id||''}" placeholder="<?php echo esc_attr__('e.g. welcome, hours, pricing','foundation-conversa'); ?>" />
                  </div>
                  <div class="fd-col">
                    <label class="fd-subtle"><?php echo esc_html__('Bot message (bubble text)','foundation-conversa'); ?></label>
                    <textarea class="fd-input-msg" rows="2" placeholder="<?php echo esc_attr__('What should the bot say in this step?','foundation-conversa'); ?>">${node.message || ''}</textarea>
                    <p class="fd-help"><?php echo esc_html__('Keep it short and friendly. Add buttons below for next steps, contact form, or site search.', 'foundation-conversa'); ?></p>
                  </div>
                  <div class="fd-actions">
                    <button type="button" class="fd-btn fd-btn--ghost fd-move-up" aria-label="Move up">▲</button>
                    <button type="button" class="fd-btn fd-btn--ghost fd-move-down" aria-label="Move down">▼</button>
                    <button type="button" class="fd-btn fd-btn--del fd-del-step"><?php echo esc_html__('Delete','foundation-conversa'); ?></button>
                  </div>
                </div>
                <div class="fd-row" style="grid-template-columns:1fr;">
                  <div class="fd-col">
                    <label class="fd-subtle"><?php echo esc_html__('Buttons','foundation-conversa'); ?></label>
                    <div class="fd-buttons"></div>
                    <button type="button" class="fd-btn fd-add-btn" style="margin-top:6px"><?php echo esc_html__('Add button','foundation-conversa'); ?></button>
                    <p class="fd-help"><?php echo esc_html__('Actions: End • Contact form • Search site • Next → [step].', 'foundation-conversa'); ?></p>
                  </div>
                </div>
              `;

              const btnWrap = el.querySelector('.fd-buttons');
              function renderButtons() {
                btnWrap.innerHTML = '';
                (node.buttons || []).forEach((b, bi) => {
                  const row = document.createElement('div');
                  row.className = 'fd-row';
                  let options = `
                    <option value="end"${b.action==='end'?' selected':''}><?php echo esc_html__('End','foundation-conversa'); ?></option>
                    <option value="contact"${b.action==='contact'?' selected':''}><?php echo esc_html__('Contact form','foundation-conversa'); ?></option>
                    <option value="search"${b.action==='search'?' selected':''}><?php echo esc_html__('Search site','foundation-conversa'); ?></option>
                    <option disabled>──────────</option>
                  `;
                  ids.forEach(idVal=>{
                    const val = 'next:'+idVal;
                    const selected = b.action===val ? ' selected' : '';
                    options += `<option value="${val}"${selected}>→ ${idVal}</option>`;
                  });

                  row.innerHTML = `
                    <input type="text" class="fd-btn-label" placeholder="<?php echo esc_attr__('e.g. Contact support','foundation-conversa'); ?>" value="${b.label||''}" style="width:240px" />
                    <select class="fd-btn-action" style="min-width:220px">${options}</select>
                    <button type="button" class="fd-btn fd-btn--del fd-del-btn" aria-label="Delete button">×</button>
                  `;
                  btnWrap.appendChild(row);

                  row.querySelector('.fd-btn-label').addEventListener('input', e=>{ b.label = e.target.value; });
                  row.querySelector('.fd-btn-action').addEventListener('change', e=>{ b.action = e.target.value; });
                  row.querySelector('.fd-del-btn').addEventListener('click', ()=>{ node.buttons.splice(bi,1); renderButtons(); });
                });
              }
              renderButtons();

              el.querySelector('.fd-add-btn').addEventListener('click', ()=>{
                node.buttons = node.buttons || [];
                node.buttons.push({label:'', action:'end'});
                renderButtons();
              });

              el.querySelector('.fd-input-id').addEventListener('input', e=>{
                node.id = (e.target.value||'').toLowerCase().replace(/[^a-z0-9_\-]/g,'').substring(0,32);
              });
              el.querySelector('.fd-input-msg').addEventListener('input', e=>{
                node.message = e.target.value;
              });

              el.querySelector('.fd-move-up').addEventListener('click', ()=>{
                if (idx>0){ const t=flow[idx]; flow[idx]=flow[idx-1]; flow[idx-1]=t; renderFlow(); }
              });
              el.querySelector('.fd-move-down').addEventListener('click', ()=>{
                if (idx<flow.length-1){ const t=flow[idx]; flow[idx]=flow[idx+1]; flow[idx+1]=t; renderFlow(); }
              });
              el.querySelector('.fd-del-step').addEventListener('click', ()=>{ flow.splice(idx,1); renderFlow(); });

              list.appendChild(el);
            });
          }

          function loadFlowTemplate() {
            flow = [
              {
                id: "welcome",
                message: "I might not have all the answers yet. What would you like to do?",
                buttons: [
                  { label: "Find a page", action: "search" },
                  { label: "Contact support", action: "contact" },
                  { label: "Opening hours", action: "next:hours" }
                ]
              },
              {
                id: "hours",
                message: "Our hours are Mon–Fri 9am–5pm, Sat–Sun Closed.",
                buttons: [
                  { label: "Contact support", action: "contact" },
                  { label: "Back", action: "next:welcome" }
                ]
              }
            ];
            renderFlow();
          }

          if (addBtn)   addBtn.addEventListener('click', ()=>{ flow.push({ id: newId(), message: "", buttons: [] }); renderFlow(); });
          if (loadBtn)  loadBtn.addEventListener('click', loadFlowTemplate);
          if (clearBtn) clearBtn.addEventListener('click', ()=>{ flow = []; renderFlow(); });

          // serialize on submit (Flow, FAQs, RAG)
          const adminForm = document.getElementById('fnd-admin-form');
          const ptSel    = document.getElementById('rag_post_types');
          const ragHidden= document.getElementById('rag_post_types_hidden');

          function selPostTypes(){ try { return Array.from(ptSel ? ptSel.selectedOptions : []).map(o=>o.value); } catch(e) { return []; } }

          if (adminForm) adminForm.addEventListener('submit', function(){
            document.getElementById('fd-offline-flow-json').value = JSON.stringify(flow);

            // Save FAQs from DOM
            const faqList  = document.getElementById('faq-list');
            const faqField = document.getElementById('fnd-faqs-json');
            if (faqList && faqField) {
              const rows = Array.from(faqList.querySelectorAll('.fd-faq'));
              const faqs = rows.map(row => ({
                q: (row.querySelector('.fd-faq-q').value || '').trim(),
                a: (row.querySelector('.fd-faq-a').value || '').trim(),
                url: (row.querySelector('.fd-faq-url').value || '').trim()
              })).filter(x => x.q && x.a);
              faqField.value = JSON.stringify(faqs);
            }

            if (ragHidden) ragHidden.value = selPostTypes().join(',');
          });

          // initial render
          renderFlow();

          // --- FAQ Builder ---
          const faqField = document.getElementById('fnd-faqs-json');
          const faqList  = document.getElementById('faq-list');
          const faqAdd   = document.getElementById('faq-add');
          const faqLoad  = document.getElementById('faq-load');
          const faqClear = document.getElementById('faq-clear');

          function parseFAQs(){
            try { return JSON.parse(faqField.value || '[]') || []; } catch(e){ return []; }
          }
          let faqs = parseFAQs();

          function faqRowTemplate(f, i){
            const div = document.createElement('div');
            div.className = 'fd-faq';
            div.innerHTML = `
              <div class="fd-row">
                <input type="text" class="fd-faq-q" placeholder="<?php echo esc_attr__('Question','foundation-conversa'); ?>" value="${f.q || ''}">
                <div class="fd-actions">
                  <button type="button" class="fd-btn fd-btn--ghost fd-up" aria-label="Move up">▲</button>
                  <button type="button" class="fd-btn fd-btn--ghost fd-down" aria-label="Move down">▼</button>
                  <button type="button" class="fd-btn fd-btn--del fd-del-faq" aria-label="Delete">×</button>
                </div>
              </div>
              <div class="fd-row" style="grid-template-columns:1fr;">
                <textarea class="fd-faq-a" rows="2" placeholder="<?php echo esc_attr__('Answer','foundation-conversa'); ?>">${f.a || ''}</textarea>
              </div>
              <div class="fd-row">
                <input type="url" class="fd-faq-url" placeholder="<?php echo esc_attr__('Optional link (Learn more)','foundation-conversa'); ?>" value="${f.url || ''}">
              </div>
            `;
            div.querySelector('.fd-up').addEventListener('click', ()=>{ if (i>0){ const t=faqs[i]; faqs[i]=faqs[i-1]; faqs[i-1]=t; renderFAQs(); } });
            div.querySelector('.fd-down').addEventListener('click', ()=>{ if (i<faqs.length-1){ const t=faqs[i]; faqs[i]=faqs[i+1]; faqs[i+1]=t; renderFAQs(); } });
            div.querySelector('.fd-del-faq').addEventListener('click', ()=>{ faqs.splice(i,1); renderFAQs(); });
            return div;
          }

          function renderFAQs(){
            faqList.innerHTML = '';
            faqs.forEach((f,i)=>{
              const row = faqRowTemplate(f,i);
              row.querySelector('.fd-faq-q').addEventListener('input', e=>{ faqs[i].q = e.target.value; });
              row.querySelector('.fd-faq-a').addEventListener('input', e=>{ faqs[i].a = e.target.value; });
              row.querySelector('.fd-faq-url').addEventListener('input', e=>{ faqs[i].url = e.target.value; });
              faqList.appendChild(row);
            });
          }

          if (faqAdd)  faqAdd.addEventListener('click', ()=>{ faqs.push({q:'',a:'',url:''}); renderFAQs(); });
          if (faqLoad) faqLoad.addEventListener('click', ()=>{ faqs = <?php echo wp_json_encode( self::default_faq_pack() ); ?>; renderFAQs(); });
          if (faqClear) faqClear.addEventListener('click', ()=>{ faqs = []; renderFAQs(); });
          renderFAQs();

          // --- RAG controls ---
          const startBtn = document.getElementById('fnd-rag-start');
          const stopBtn  = document.getElementById('fnd-rag-stop');
          const bar      = document.getElementById('fnd-rag-bar-fill');
          const statusEl = document.getElementById('fnd-rag-status');
          let poll;

          function updateUI(st){
            const total = parseInt(st.total||0,10)||0;
            const done  = parseInt(st.indexed||0,10)||0;
            const pct   = total>0 ? Math.min(100, Math.round(done/total*100)) : (st.status==='complete'?100:0);
            if (bar) bar.style.width = pct+'%';
            if (statusEl) statusEl.textContent = (st.status||'idle').toUpperCase() + (total? ` — ${done}/${total} (${pct}%)` : '');
            if (startBtn) startBtn.disabled = st.status==='scanning';
          }
          function pollStatus(){
            fetch(conversa_admin.ajax_url, {
              method:'POST',
              headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},
              body:new URLSearchParams({ action:'fnd_conversa_rag_status', nonce:conversa_admin.nonce })
            }).then(r=>r.json()).then(j=>{
              if(j && j.success && j.data){
                updateUI(j.data);
                if(j.data.status==='scanning'){ poll=setTimeout(pollStatus, 1200); }
              }
            }).catch(()=>{});
          }
          if (startBtn) startBtn.addEventListener('click', function(){
            const body = new URLSearchParams({ action:'fnd_conversa_rag_start', nonce:conversa_admin.nonce });
            const ptSel = document.getElementById('rag_post_types');
            if (ptSel) Array.from(ptSel.selectedOptions).forEach(v=>body.append('post_types[]', v.value));
            fetch(conversa_admin.ajax_url, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'}, body })
              .then(r=>r.json()).then(j=>{ if(j && j.success && j.data){ updateUI(j.data); pollStatus(); } });
          });
          if (stopBtn) stopBtn.addEventListener('click', function(){
            fetch(conversa_admin.ajax_url, {
              method:'POST',
              headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},
              body:new URLSearchParams({ action:'fnd_conversa_rag_stop', nonce:conversa_admin.nonce })
            }).then(r=>r.json()).then(j=>{ if(j && j.success && j.data){ updateUI(j.data); if(poll) clearTimeout(poll); } });
          });
        })();
        </script>
        <?php
    }
}

endif;
