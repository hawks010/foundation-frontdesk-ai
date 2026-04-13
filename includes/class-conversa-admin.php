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
        $parent_slug = self::parent_menu_slug();

        if (!self::use_core_parent_menu() && empty($admin_page_hooks[$parent_slug])) {
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

    private static function use_core_parent_menu(): bool {
        return function_exists('fnd_conversa_core_is_available') && fnd_conversa_core_is_available();
    }

    private static function parent_menu_slug(): string {
        return self::use_core_parent_menu() ? 'foundation-core' : 'foundation-by-inkfire';
    }

    public static function enqueue_admin_assets($hook) {
        if (strpos($hook, 'foundation-conversa-settings') === false) {
            return;
        }

        $asset_version = defined('FND_CONVERSA_VERSION') ? FND_CONVERSA_VERSION : time();
        $asset_base = trailingslashit(FND_CONVERSA_URL) . 'assets/admin/';
        $opts = get_option('fnd_conversa_options', []);

        wp_enqueue_style(
            'foundation-admin-shell',
            $asset_base . 'foundation-admin-shell.css',
            [],
            $asset_version
        );

        wp_enqueue_script(
            'foundation-admin-shell',
            $asset_base . 'foundation-admin-shell.js',
            ['wp-element'],
            $asset_version,
            true
        );

        wp_enqueue_script(
            'foundation-frontdesk-admin',
            $asset_base . 'frontdesk-admin.js',
            [],
            $asset_version,
            true
        );

        wp_add_inline_script(
            'foundation-admin-shell',
            'window.foundationAdminShellData = ' . wp_json_encode(self::get_shell_config($opts)) . ';',
            'before'
        );

        wp_localize_script(
            'foundation-frontdesk-admin',
            'foundationFrontdeskAdmin',
            [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('fnd_conversa_admin'),
                'faqSeed' => self::default_faq_pack(),
            ]
        );
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

    private static function get_shell_config($opts) {
        $provider = isset($opts['fd_provider']) ? sanitize_key($opts['fd_provider']) : 'offline';
        $forced_offline = !empty($opts['fd_force_offline']);
        $floating = !array_key_exists('enable_floating', $opts) || !empty($opts['enable_floating']);
        $contact = !array_key_exists('enable_contact', $opts) || !empty($opts['enable_contact']);
        $kb_mode = isset($opts['kb_mode']) ? sanitize_key($opts['kb_mode']) : 'faqs';
        $rag_status = get_option('fnd_conversa_rag_status', []);
        $wizard_slug = class_exists('Foundation_Conversa_Onboarding')
            ? Foundation_Conversa_Onboarding::PAGE_SLUG
            : 'foundation-conversa-onboarding';

        $provider_label = $forced_offline || $provider === 'offline'
            ? __('Offline', 'foundation-conversa')
            : strtoupper($provider);

        return [
            'plugin' => 'frontdesk',
            'rootId' => 'foundation-admin-app',
            'eyebrow' => __('Foundation command centre', 'foundation-conversa'),
            'title' => __('Foundation: Frontdesk AI', 'foundation-conversa'),
            'description' => __('This keeps the existing Frontdesk settings schema, flow builder, FAQ builder, and RAG actions intact while moving the admin into the same Foundation shell pattern used elsewhere.', 'foundation-conversa'),
            'badge' => sprintf(__('v%s', 'foundation-conversa'), defined('FND_CONVERSA_VERSION') ? FND_CONVERSA_VERSION : '1.0.0'),
            'themeStorageKey' => 'foundation-frontdesk-theme',
            'defaultTheme' => !empty($opts['admin_dark_mode']) ? 'dark' : 'light',
            'actions' => [
                [
                    'label' => __('Run startup wizard', 'foundation-conversa'),
                    'href' => admin_url('admin.php?page=' . $wizard_slug),
                    'variant' => 'solid',
                ],
                [
                    'label' => __('GitHub backup', 'foundation-conversa'),
                    'href' => 'https://github.com/hawks010/foundation-frontdesk-ai',
                    'target' => '_blank',
                    'variant' => 'ghost',
                ],
            ],
            'metrics' => [
                [
                    'label' => __('Provider', 'foundation-conversa'),
                    'value' => $provider_label,
                    'meta' => $forced_offline ? __('Forced offline mode is enabled.', 'foundation-conversa') : __('Current chat answer source.', 'foundation-conversa'),
                ],
                [
                    'label' => __('Floating bubble', 'foundation-conversa'),
                    'value' => $floating ? __('Enabled', 'foundation-conversa') : __('Hidden', 'foundation-conversa'),
                    'meta' => __('Controls the global launcher visibility.', 'foundation-conversa'),
                    'tone' => $floating ? 'accent' : '',
                ],
                [
                    'label' => __('Contact form', 'foundation-conversa'),
                    'value' => $contact ? __('Enabled', 'foundation-conversa') : __('Disabled', 'foundation-conversa'),
                    'meta' => __('Lets the chat hand off to inbox capture.', 'foundation-conversa'),
                ],
                [
                    'label' => __('Knowledge base', 'foundation-conversa'),
                    'value' => $kb_mode === 'url' ? __('External URL', 'foundation-conversa') : __('Inline FAQs', 'foundation-conversa'),
                    'meta' => isset($rag_status['status']) ? sprintf(__('Index: %s', 'foundation-conversa'), strtoupper((string) $rag_status['status'])) : __('FAQ and RAG answer mode.', 'foundation-conversa'),
                ],
            ],
            'sections' => [
                [
                    'id' => 'frontdesk-overview',
                    'navLabel' => __('Overview', 'foundation-conversa'),
                    'eyebrow' => __('Setup', 'foundation-conversa'),
                    'title' => __('Current assistant state', 'foundation-conversa'),
                    'description' => __('Use the guided setup link, then review the live toggles and knowledge state before editing the deeper settings.', 'foundation-conversa'),
                    'templateId' => 'foundation-frontdesk-overview',
                ],
                [
                    'id' => 'frontdesk-workspace',
                    'navLabel' => __('Workspace', 'foundation-conversa'),
                    'eyebrow' => __('Assistant workspace', 'foundation-conversa'),
                    'title' => __('Settings, flows, FAQs, and knowledge index', 'foundation-conversa'),
                    'description' => __('Everything below still saves through the existing `fnd_conversa_options_group` options flow.', 'foundation-conversa'),
                    'templateId' => 'foundation-frontdesk-workspace',
                ],
            ],
        ];
    }

    private static function render_template($id, $html) {
        printf('<template id="%1$s">%2$s</template>', esc_attr($id), $html);
    }

    private static function get_overview_markup($wizard_slug, $provider_label, $bubble_on, $contact_on, $kb_mode, $rag_status) {
        $rag_text = !empty($rag_status['status']) ? strtoupper((string) $rag_status['status']) : __('IDLE', 'foundation-conversa');
        ob_start();
        ?>
        <div class="fp-card">
            <h2><?php esc_html_e('Guided setup', 'foundation-conversa'); ?></h2>
            <p class="description"><?php esc_html_e('Launch the full-screen onboarding flow when you want a guided pass through copy, colours, positioning, and provider setup.', 'foundation-conversa'); ?></p>
            <div class="fd-flow-toolbar">
                <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=' . $wizard_slug)); ?>">
                    <?php esc_html_e('Run startup wizard', 'foundation-conversa'); ?>
                </a>
                <span class="fd-subtle"><?php esc_html_e('The React shell is new, but the wizard and settings keys are the same ones Frontdesk already uses.', 'foundation-conversa'); ?></span>
            </div>
        </div>

        <div class="fp-card">
            <h2><?php esc_html_e('Live summary', 'foundation-conversa'); ?></h2>
            <div class="fp-stats-grid">
                <div class="fp-stat-box">
                    <span class="fp-stat-value"><?php echo esc_html($provider_label); ?></span>
                    <span class="fp-stat-label"><?php esc_html_e('Provider', 'foundation-conversa'); ?></span>
                </div>
                <div class="fp-stat-box">
                    <span class="fp-stat-value"><?php echo $bubble_on ? esc_html__('Enabled', 'foundation-conversa') : esc_html__('Hidden', 'foundation-conversa'); ?></span>
                    <span class="fp-stat-label"><?php esc_html_e('Floating bubble', 'foundation-conversa'); ?></span>
                </div>
                <div class="fp-stat-box">
                    <span class="fp-stat-value"><?php echo $contact_on ? esc_html__('Enabled', 'foundation-conversa') : esc_html__('Disabled', 'foundation-conversa'); ?></span>
                    <span class="fp-stat-label"><?php esc_html_e('Contact handoff', 'foundation-conversa'); ?></span>
                </div>
                <div class="fp-stat-box">
                    <span class="fp-stat-value"><?php echo esc_html($rag_text); ?></span>
                    <span class="fp-stat-label"><?php echo $kb_mode === 'url' ? esc_html__('KB mode: external URL', 'foundation-conversa') : esc_html__('KB mode: inline FAQs', 'foundation-conversa'); ?></span>
                </div>
            </div>
            <p class="description"><?php esc_html_e('This is a shell refresh, not a data model rewrite. The chat widget, offline flow, FAQ structure, and embeddings index all keep their current storage shape.', 'foundation-conversa'); ?></p>
        </div>
        <?php
        return ob_get_clean();
    }

    private static function get_workspace_markup(array $view) {
        extract($view, EXTR_SKIP);
        ob_start();
        ?>
        <form method="post" action="options.php" id="fnd-admin-form">
            <?php settings_fields('fnd_conversa_options_group'); ?>
            <input type="hidden" id="fnd-dark-hidden" name="fnd_conversa_options[admin_dark_mode]" value="<?php echo $dark_mode ? '1' : '0'; ?>" />
            <input type="hidden" id="fnd-faqs-json" name="fnd_conversa_options[faqs_json]" value="<?php echo esc_attr($faqs_json); ?>" />

            <div class="fp-card">
                <h2><?php esc_html_e('Basics', 'foundation-conversa'); ?></h2>
                <table class="form-table"><tbody>
                    <tr>
                        <th><label for="conversa_personality"><?php esc_html_e('Default personality', 'foundation-conversa'); ?></label></th>
                        <td>
                            <select id="conversa_personality" name="fnd_conversa_options[default_personality]">
                                <?php foreach ($personalities as $key => $label) : ?>
                                    <option value="<?php echo esc_attr($key); ?>" <?php selected($personality, $key); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="conversa_bot_name"><?php esc_html_e('Bot name', 'foundation-conversa'); ?></label></th>
                        <td><input type="text" id="conversa_bot_name" name="fnd_conversa_options[bot_name]" value="<?php echo esc_attr($bot_name); ?>" placeholder="<?php esc_attr_e('Frontdesk AI', 'foundation-conversa'); ?>" /></td>
                    </tr>
                    <tr>
                        <th><label for="fd_provider"><?php esc_html_e('Provider', 'foundation-conversa'); ?></label></th>
                        <td>
                            <select id="fd_provider" name="fnd_conversa_options[fd_provider]">
                                <option value="offline" <?php selected($fd_provider, 'offline'); ?>><?php esc_html_e('Offline (no API)', 'foundation-conversa'); ?></option>
                                <option value="openai" <?php selected($fd_provider, 'openai'); ?>><?php esc_html_e('OpenAI', 'foundation-conversa'); ?></option>
                                <option value="gemini" <?php selected($fd_provider, 'gemini'); ?>><?php esc_html_e('Google Gemini', 'foundation-conversa'); ?></option>
                            </select>
                            <label style="display:block;margin-top:10px">
                                <input type="checkbox" id="fd_force_off" name="fnd_conversa_options[fd_force_offline]" value="1" <?php checked($fd_force_off); ?> />
                                <?php esc_html_e('Force offline mode', 'foundation-conversa'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('If provider keys are empty or forced offline is enabled, the widget falls back to offline mode.', 'foundation-conversa'); ?></p>

                            <div id="fd-provider-keys" class="fd-provider-keys" style="margin-top:10px;<?php echo ($fd_provider !== 'offline' && !$fd_force_off) ? '' : 'display:none;'; ?>">
                                <div class="fd-key-row">
                                    <label for="fd_openai_key"><?php esc_html_e('OpenAI API key', 'foundation-conversa'); ?></label>
                                    <input type="password" id="fd_openai_key" class="regular-text" name="fnd_conversa_options[openai_api_key]" value="<?php echo esc_attr($openai_api_key); ?>" placeholder="<?php esc_attr_e('sk-...', 'foundation-conversa'); ?>" />
                                    <p class="fd-help-mini"><?php echo wp_kses_post(sprintf(__('Generate a key at <a href="%s" target="_blank" rel="noopener noreferrer">platform.openai.com/api-keys</a>.', 'foundation-conversa'), esc_url('https://platform.openai.com/api-keys'))); ?></p>
                                </div>
                                <div class="fd-key-row">
                                    <label for="fd_gemini_key"><?php esc_html_e('Google Gemini API key', 'foundation-conversa'); ?></label>
                                    <input type="password" id="fd_gemini_key" class="regular-text" name="fnd_conversa_options[gemini_api_key]" value="<?php echo esc_attr($gemini_api_key); ?>" placeholder="<?php esc_attr_e('AIza...', 'foundation-conversa'); ?>" />
                                    <p class="fd-help-mini"><?php echo wp_kses_post(sprintf(__('Create a key at <a href="%s" target="_blank" rel="noopener noreferrer">ai.google.dev</a>.', 'foundation-conversa'), esc_url('https://ai.google.dev/'))); ?></p>
                                </div>
                                <label style="display:block;margin-top:10px">
                                    <input type="checkbox" name="fnd_conversa_options[use_flow_online]" value="1" <?php checked($use_flow_online); ?> />
                                    <?php esc_html_e('Also use Offline Flow as fallback when online.', 'foundation-conversa'); ?>
                                </label>
                            </div>
                        </td>
                    </tr>
                </tbody></table>
            </div>

            <div class="fp-card">
                <h2><?php esc_html_e('Offline Flow Builder', 'foundation-conversa'); ?></h2>
                <p class="description"><?php esc_html_e('Define a quick fallback conversation for Offline mode. The first step becomes the entry point.', 'foundation-conversa'); ?></p>
                <div class="fd-flow-toolbar">
                    <button type="button" class="fd-btn fd-btn--add" id="fd-add-step"><?php esc_html_e('Add step', 'foundation-conversa'); ?></button>
                    <button type="button" class="fd-btn" id="fd-load-template"><?php esc_html_e('Load starter template', 'foundation-conversa'); ?></button>
                    <button type="button" class="fd-btn fd-btn--del" id="fd-clear"><?php esc_html_e('Clear', 'foundation-conversa'); ?></button>
                    <span class="fd-subtle"><?php esc_html_e('Tip: keep it short, usually two to four steps.', 'foundation-conversa'); ?></span>
                </div>
                <div class="fd-flow-list" id="fd-flow-list" aria-live="polite"></div>
                <textarea id="fd-offline-flow-json" name="fnd_conversa_options[offline_flow]" rows="5" style="display:none;"><?php echo esc_textarea($offline_flow_json ?: '[]'); ?></textarea>
                <p class="fd-bridge-note"><?php esc_html_e('The greeting below appears when there is no flow, or after a flow ends.', 'foundation-conversa'); ?></p>
            </div>

            <div class="fp-card">
                <h2><?php esc_html_e('Branding & Colours', 'foundation-conversa'); ?></h2>
                <table class="form-table"><tbody>
                    <tr>
                        <th><label for="conversa_brand_color"><?php esc_html_e('Brand colour', 'foundation-conversa'); ?></label></th>
                        <td>
                            <input type="text" id="conversa_brand_color" name="fnd_conversa_options[brand_color]" value="<?php echo esc_attr($brand_color); ?>" placeholder="#DF157C" />
                            <p class="description"><?php esc_html_e('HEX value, for example #DF157C.', 'foundation-conversa'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="ui_header_bg"><?php esc_html_e('Header background', 'foundation-conversa'); ?></label></th>
                        <td><input type="text" id="ui_header_bg" name="fnd_conversa_options[ui_header_bg]" value="<?php echo esc_attr($ui_header_bg); ?>" placeholder="#1e6167" /></td>
                    </tr>
                    <tr>
                        <th><label for="ui_header_text"><?php esc_html_e('Header text colour', 'foundation-conversa'); ?></label></th>
                        <td><input type="text" id="ui_header_text" name="fnd_conversa_options[ui_header_text]" value="<?php echo esc_attr($ui_header_text); ?>" placeholder="#FFFFFF" /></td>
                    </tr>
                    <tr>
                        <th><label for="ui_text_color"><?php esc_html_e('Body text colour', 'foundation-conversa'); ?></label></th>
                        <td><input type="text" id="ui_text_color" name="fnd_conversa_options[ui_text_color]" value="<?php echo esc_attr($ui_text_color); ?>" placeholder="#111111" /></td>
                    </tr>
                    <tr>
                        <th><label for="ui_button_color"><?php esc_html_e('Button colour', 'foundation-conversa'); ?></label></th>
                        <td><input type="text" id="ui_button_color" name="fnd_conversa_options[ui_button_color]" value="<?php echo esc_attr($ui_button_color); ?>" placeholder="#04AD93" /></td>
                    </tr>
                    <tr>
                        <th><label for="ui_button_hover_color"><?php esc_html_e('Button hover colour', 'foundation-conversa'); ?></label></th>
                        <td><input type="text" id="ui_button_hover_color" name="fnd_conversa_options[ui_button_hover_color]" value="<?php echo esc_attr($ui_button_hover_color); ?>" placeholder="#038D78" /></td>
                    </tr>
                </tbody></table>
            </div>

            <div class="fp-card">
                <h2><?php esc_html_e('Launcher & Teaser', 'foundation-conversa'); ?></h2>
                <table class="form-table"><tbody>
                    <tr>
                        <th><label for="teaser_title"><?php esc_html_e('Teaser headline', 'foundation-conversa'); ?></label></th>
                        <td><input type="text" id="teaser_title" name="fnd_conversa_options[teaser_title]" value="<?php echo esc_attr($teaser_title); ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="kb_button_label"><?php esc_html_e('Knowledge base button label', 'foundation-conversa'); ?></label></th>
                        <td><input type="text" id="kb_button_label" name="fnd_conversa_options[kb_button_label]" value="<?php echo esc_attr($kb_button_label); ?>"></td>
                    </tr>
                    <tr>
                        <th><label><?php esc_html_e('Knowledge base action', 'foundation-conversa'); ?></label></th>
                        <td>
                            <label><input type="radio" name="fnd_conversa_options[kb_mode]" value="faqs" <?php checked($kb_mode, 'faqs'); ?>> <?php esc_html_e('Show FAQs in chat', 'foundation-conversa'); ?></label><br>
                            <label><input type="radio" name="fnd_conversa_options[kb_mode]" value="url" <?php checked($kb_mode, 'url'); ?>> <?php esc_html_e('Open external knowledge base URL', 'foundation-conversa'); ?></label>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="kb_url"><?php esc_html_e('Knowledge base URL', 'foundation-conversa'); ?></label></th>
                        <td><input type="url" id="kb_url" name="fnd_conversa_options[kb_url]" value="<?php echo esc_attr($kb_url); ?>" placeholder="https://example.com/help"></td>
                    </tr>
                </tbody></table>
            </div>

            <div class="fp-card">
                <h2><?php esc_html_e('Chatbox Behaviour', 'foundation-conversa'); ?></h2>
                <table class="form-table"><tbody>
                    <tr>
                        <th><?php esc_html_e('Show contact form', 'foundation-conversa'); ?></th>
                        <td><label class="fp-switch"><input type="checkbox" name="fnd_conversa_options[enable_contact]" value="1" <?php checked($contact_on); ?> /><span class="fp-toggle-slider"></span></label></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Floating bubble', 'foundation-conversa'); ?></th>
                        <td><label class="fp-switch"><input type="checkbox" name="fnd_conversa_options[enable_floating]" value="1" <?php checked($bubble_on); ?> /><span class="fp-toggle-slider"></span></label></td>
                    </tr>
                </tbody></table>
            </div>

            <div class="fp-card">
                <h2><?php esc_html_e('Floating & Position', 'foundation-conversa'); ?></h2>
                <table class="form-table"><tbody>
                    <tr>
                        <th><label for="ui_position_corner"><?php esc_html_e('Floating position', 'foundation-conversa'); ?></label></th>
                        <td>
                            <select id="ui_position_corner" name="fnd_conversa_options[ui_position_corner]">
                                <option value="bottom_right" <?php selected($ui_position_corner, 'bottom_right'); ?>><?php esc_html_e('Bottom Right', 'foundation-conversa'); ?></option>
                                <option value="bottom_left" <?php selected($ui_position_corner, 'bottom_left'); ?>><?php esc_html_e('Bottom Left', 'foundation-conversa'); ?></option>
                                <option value="top_right" <?php selected($ui_position_corner, 'top_right'); ?>><?php esc_html_e('Top Right', 'foundation-conversa'); ?></option>
                                <option value="top_left" <?php selected($ui_position_corner, 'top_left'); ?>><?php esc_html_e('Top Left', 'foundation-conversa'); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e('Choose the corner where the chat floats and fine-tune with offsets.', 'foundation-conversa'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="ui_offset_x"><?php esc_html_e('Horizontal offset (px)', 'foundation-conversa'); ?></label></th>
                        <td><input type="number" id="ui_offset_x" name="fnd_conversa_options[ui_offset_x]" value="<?php echo esc_attr($ui_offset_x); ?>" min="0" step="1" /></td>
                    </tr>
                    <tr>
                        <th><label for="ui_offset_y"><?php esc_html_e('Vertical offset (px)', 'foundation-conversa'); ?></label></th>
                        <td><input type="number" id="ui_offset_y" name="fnd_conversa_options[ui_offset_y]" value="<?php echo esc_attr($ui_offset_y); ?>" min="0" step="1" /></td>
                    </tr>
                </tbody></table>
            </div>

            <div class="fp-card">
                <h2><?php esc_html_e('Greeting & Contact', 'foundation-conversa'); ?></h2>
                <p class="description"><?php esc_html_e('This greeting appears when there is no Offline Flow, and after a flow completes.', 'foundation-conversa'); ?></p>
                <table class="form-table"><tbody>
                    <tr>
                        <th><label for="conversa_contact_email"><?php esc_html_e('Contact email', 'foundation-conversa'); ?></label></th>
                        <td><input type="email" id="conversa_contact_email" name="fnd_conversa_options[contact_email]" value="<?php echo esc_attr($contact_to); ?>" placeholder="you@example.com" /></td>
                    </tr>
                    <tr>
                        <th><label for="greeting_text"><?php esc_html_e('Opening greeting', 'foundation-conversa'); ?></label></th>
                        <td>
                            <textarea id="greeting_text" name="fnd_conversa_options[greeting_text]" rows="3" placeholder="<?php esc_attr_e('Hi! I\'m {bot_name}. How can I help today?\nHours: {hours}\nYou can also {contact}.', 'foundation-conversa'); ?>"><?php echo esc_textarea($greeting_text); ?></textarea>
                            <p class="description"><?php esc_html_e('Tokens: {bot_name}, {site_name}, {hours}, {contact}.', 'foundation-conversa'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="opening_hours"><?php esc_html_e('Opening hours', 'foundation-conversa'); ?></label></th>
                        <td><textarea id="opening_hours" name="fnd_conversa_options[opening_hours]" rows="3" placeholder="<?php esc_attr_e('Mon–Fri: 9am–5pm\nSat–Sun: Closed', 'foundation-conversa'); ?>"><?php echo esc_textarea($opening_hours); ?></textarea></td>
                    </tr>
                    <tr>
                        <th><label for="alt_contact"><?php esc_html_e('Alternative contact', 'foundation-conversa'); ?></label></th>
                        <td>
                            <input type="text" id="alt_contact" name="fnd_conversa_options[alt_contact]" value="<?php echo esc_attr($alt_contact); ?>" placeholder="<?php esc_attr_e('call 01234 567890 or email {admin_email}', 'foundation-conversa'); ?>" />
                            <p class="description"><?php esc_html_e('Token {admin_email} is replaced with the site admin email.', 'foundation-conversa'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="header_byline"><?php esc_html_e('Header byline', 'foundation-conversa'); ?></label></th>
                        <td><input type="text" id="header_byline" name="fnd_conversa_options[header_byline]" value="<?php echo esc_attr($header_byline); ?>" placeholder="<?php esc_attr_e('by Inkfire Ltd', 'foundation-conversa'); ?>" /></td>
                    </tr>
                </tbody></table>
            </div>

            <div class="fp-card">
                <h2><?php esc_html_e('Knowledge Base (FAQs)', 'foundation-conversa'); ?></h2>
                <p class="description"><?php esc_html_e('Add collapsible FAQs displayed in the chat. Drag to reorder.', 'foundation-conversa'); ?></p>
                <div class="fd-faq-toolbar">
                    <button type="button" class="fd-btn fd-btn--add" id="faq-add"><?php esc_html_e('Add FAQ', 'foundation-conversa'); ?></button>
                    <button type="button" class="fd-btn" id="faq-load"><?php esc_html_e('Load starter FAQs', 'foundation-conversa'); ?></button>
                    <button type="button" class="fd-btn fd-btn--del" id="faq-clear"><?php esc_html_e('Clear all', 'foundation-conversa'); ?></button>
                    <span class="fd-subtle"><?php esc_html_e('Keep questions short and answers concise.', 'foundation-conversa'); ?></span>
                </div>
                <div class="fd-faq-list" id="faq-list" aria-live="polite"></div>
            </div>

            <div class="fp-card">
                <h2><?php esc_html_e('Knowledge Index (Embeddings)', 'foundation-conversa'); ?></h2>
                <p class="description"><?php esc_html_e('Build an embeddings index of your site content so the chatbot answers from your website.', 'foundation-conversa'); ?></p>
                <table class="form-table"><tbody>
                    <tr>
                        <th><label for="rag_post_types"><?php esc_html_e('Post types to index', 'foundation-conversa'); ?></label></th>
                        <td>
                            <?php
                            $pt = get_post_types(['public' => true], 'names');
                            unset($pt['attachment']);
                            $saved_pt = array_map('trim', explode(',', (string)($opts['rag_post_types'] ?? 'post,page')));
                            ?>
                            <select id="rag_post_types" multiple size="5" style="max-width:480px" aria-describedby="rag-pt-help">
                                <?php foreach ($pt as $slug) : ?>
                                    <option value="<?php echo esc_attr($slug); ?>" <?php selected(in_array($slug, $saved_pt, true)); ?>><?php echo esc_html($slug); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" id="rag_post_types_hidden" name="fnd_conversa_options[rag_post_types]" value="<?php echo esc_attr($opts['rag_post_types'] ?? 'post,page'); ?>">
                            <p class="description" id="rag-pt-help"><?php esc_html_e('Hold Ctrl/Cmd to select multiple. Defaults to posts and pages.', 'foundation-conversa'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Actions', 'foundation-conversa'); ?></th>
                        <td>
                            <button type="button" class="button button-primary" id="fnd-rag-start"><?php esc_html_e('Build / Rebuild Index', 'foundation-conversa'); ?></button>
                            <button type="button" class="button" id="fnd-rag-stop" style="margin-left:8px"><?php esc_html_e('Stop', 'foundation-conversa'); ?></button>
                            <div class="fp-scan-progress-bar-container" style="margin-top:10px">
                                <div id="fnd-rag-bar" class="fp-scan-progress-bar"><span id="fnd-rag-bar-fill"></span></div>
                                <div id="fnd-rag-status" style="margin-top:8px;color:inherit;opacity:.9"></div>
                            </div>
                        </td>
                    </tr>
                </tbody></table>
            </div>

            <?php submit_button(__('Save Frontdesk AI settings', 'foundation-conversa')); ?>
        </form>
        <?php
        return ob_get_clean();
    }

    public static function render_settings_page() {
        $opts = get_option('fnd_conversa_options', []);
        $get  = function ($key, $default = '') use ($opts) {
            return isset($opts[$key]) ? $opts[$key] : $default;
        };

        $faqs_json = $get('faqs_json', '');
        if (empty($faqs_json) || $faqs_json === '[]') {
            $seed = self::default_faq_pack();
            $faqs_json = wp_json_encode($seed);
            $opts['faqs_json'] = $faqs_json;
            update_option('fnd_conversa_options', $opts, false);
        }

        $api_key     = $get('api_key', '');
        $personality = $get('default_personality', 'friendly_support');
        $bot_name    = $get('bot_name', 'Frontdesk AI');
        $brand_color = $get('brand_color', '#4F46E5');
        $contact_on  = (bool) $get('enable_contact', true);
        $bubble_on   = (bool) $get('enable_floating', true);
        $contact_to  = $get('contact_email', get_option('admin_email'));
        $dark_mode   = (bool) $get('admin_dark_mode', false);

        $fd_provider     = $get('fd_provider', 'offline');
        $fd_force_off    = (bool) $get('fd_force_offline', false);
        $openai_api_key  = $get('openai_api_key', ($api_key ?: ''));
        $gemini_api_key  = $get('gemini_api_key', '');
        $use_flow_online = (bool) $get('use_flow_online', true);

        $ui_button_color       = $get('ui_button_color', $brand_color);
        $ui_button_hover_color = $get('ui_button_hover_color', '#4338CA');
        $ui_text_color         = $get('ui_text_color', '#111111');
        $ui_header_bg          = $get('ui_header_bg', $brand_color);
        $ui_header_text        = $get('ui_header_text', '#FFFFFF');

        $ui_position_corner = $get('ui_position_corner', 'bottom_right');
        $ui_offset_x        = (int) $get('ui_offset_x', 20);
        $ui_offset_y        = (int) $get('ui_offset_y', 20);

        $greeting_text = $get('greeting_text', "Hi! I'm {bot_name}. How can I help today?\nHours: {hours}\nYou can also {contact}.");
        $opening_hours = $get('opening_hours', "Mon–Fri: 9am–5pm\nSat–Sun: Closed");
        $alt_contact   = $get('alt_contact', 'email us at {admin_email}');
        $header_byline = $get('header_byline', 'by Inkfire Ltd');

        $teaser_title    = $get('teaser_title', __('Got questions? Let us help.', 'foundation-conversa'));
        $kb_button_label = $get('kb_button_label', __('Knowledge Base', 'foundation-conversa'));
        $kb_mode         = $get('kb_mode', 'faqs');
        $kb_url          = $get('kb_url', '');
        $offline_flow_json = $get('offline_flow', '[]');

        $wizard_slug = class_exists('Foundation_Conversa_Onboarding')
            ? Foundation_Conversa_Onboarding::PAGE_SLUG
            : 'foundation-conversa-onboarding';

        $personalities = function_exists('fnd_conversa_personalities') ? fnd_conversa_personalities() : [
            'friendly_support' => __('Friendly Customer Support', 'foundation-conversa')
        ];

        $provider_label = ($fd_force_off || $fd_provider === 'offline') ? __('Offline', 'foundation-conversa') : strtoupper((string) $fd_provider);
        $rag_status = get_option('fnd_conversa_rag_status', []);

        $view = compact(
            'opts',
            'faqs_json',
            'personality',
            'bot_name',
            'brand_color',
            'contact_on',
            'bubble_on',
            'contact_to',
            'dark_mode',
            'fd_provider',
            'fd_force_off',
            'openai_api_key',
            'gemini_api_key',
            'use_flow_online',
            'ui_button_color',
            'ui_button_hover_color',
            'ui_text_color',
            'ui_header_bg',
            'ui_header_text',
            'ui_position_corner',
            'ui_offset_x',
            'ui_offset_y',
            'greeting_text',
            'opening_hours',
            'alt_contact',
            'header_byline',
            'teaser_title',
            'kb_button_label',
            'kb_mode',
            'kb_url',
            'offline_flow_json',
            'personalities'
        );
        ?>
        <div class="wrap foundation-admin-wrap">
            <div id="foundation-admin-app">
                <p><?php esc_html_e('Loading Foundation shell...', 'foundation-conversa'); ?></p>
            </div>
            <?php
            self::render_template(
                'foundation-frontdesk-overview',
                self::get_overview_markup($wizard_slug, $provider_label, $bubble_on, $contact_on, $kb_mode, $rag_status)
            );
            self::render_template(
                'foundation-frontdesk-workspace',
                self::get_workspace_markup($view)
            );
            ?>
        </div>
        <?php
    }
}

endif;
