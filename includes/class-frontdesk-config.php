<?php
if (!defined('ABSPATH')) { exit; }

class Frontdesk_Config {
    const OPTION_KEY = 'fnd_frontdesk_options';
    const REMOVE_KEY_SENTINEL = '__REMOVE__';
    private static $cache = null;

    public static function init(): void {
        add_action('admin_post_frontdesk_test_openai', [__CLASS__, 'handle_test_openai']);
            }

    public static function defaults(): array {
        $admin_email = get_option('admin_email');
        $site_name = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);

        return [
            'product_name'            => 'Frontdesk AI',
            'bot_name'                => $site_name ?: 'Frontdesk AI',
            'header_byline'           => __('Support assistant', 'foundation-frontdesk'),
            'avatar_url'              => '',
            'staff_photos'            => '[]',
            'style_mode'              => 'preset',
            'style_preset'            => 'sea_glass',
            'ui_font_family'          => '"Plus Jakarta Sans", ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif',
            'greeting_text'           => __("Hi! I'm {bot_name}. How can I help today?\nHours: {hours}\nYou can also {contact}.", 'foundation-frontdesk'),
            'greeting_html'           => '',
            'input_placeholder'       => __('Type your message...', 'foundation-frontdesk'),
            'teaser_title'            => __('Got questions? Let us help.', 'foundation-frontdesk'),
            'teaser_body'             => __('Ask our assistant or browse the help centre.', 'foundation-frontdesk'),
            'launcher_label'          => __('Open chat', 'foundation-frontdesk'),
            'kb_button_label'         => __('Help Centre', 'foundation-frontdesk'),
            'contact_button_label'    => __('Contact us', 'foundation-frontdesk'),
            'contact_btn_color'       => '',
            'contact_btn_text_color'  => '#ffffff',
            'recaptcha_site_key'      => '',
            'recaptcha_secret_key'    => '',
            'offline_notice'          => __('Live AI is unavailable right now, but I can still help with FAQs and contact options.', 'foundation-frontdesk'),
            'error_message'           => __('Sorry, I could not get an answer just now. Please try again or contact the team.', 'foundation-frontdesk'),
            'display_mode'            => 'floating',
            'enable_floating'         => true,
            'enable_inline'           => true,
            'enable_contact'          => true,
            'ui_position_corner'      => 'bottom_right',
            'ui_offset_x'             => 20,
            'ui_offset_y'             => 20,
            'brand_color'             => '#04ad93',
            'ui_header_bg'            => '#1e6167',
            'ui_header_text'          => '#FFFFFF',
            'ui_button_color'         => '#04ad93',
            'ui_button_hover_color'   => '#038d78',
            'ui_button_text_color'    => '#FFFFFF',
            'ui_text_color'           => '#111111',
            'ui_radius'               => 18,
            'provider'                => 'offline',
            'fd_provider'             => 'offline',
            'openai_api_key'          => '',
            'openai_model'            => 'gpt-5-mini',
            'runtime_mode'            => 'offline',
            'fallback_enabled'        => true,
            'force_offline'           => false,
            'fd_force_offline'        => false,
            'max_input_chars'         => 1000,
            'max_output_tokens'       => 500,
            'temperature'             => 0.2,
            'faqs_json'               => '[]',
            'rag_enabled'             => true,
            'rag_post_types'          => 'post,page',
            'opening_hours'           => __("Mon–Fri: 9am–5pm\nSat–Sun: Closed", 'foundation-frontdesk'),
            'alt_contact'             => sprintf(__('email us at %s', 'foundation-frontdesk'), $admin_email),
            'contact_email'           => $admin_email,
            'send_user_confirmation'  => true,
            'contact_subject_prefix'  => __('Frontdesk AI', 'foundation-frontdesk'),
            'notification_email_intro' => __("You've received a new message via your website's {bot_name} widget.", 'foundation-frontdesk'),
            'confirmation_email_body'  => __('Thanks for getting in touch. We have received your message and will reply as soon as possible.', 'foundation-frontdesk'),
            'privacy_notice'          => __('We will use your details to reply to your enquiry.', 'foundation-frontdesk'),
            'store_transcripts'       => false,
            'transcript_retention_days' => 30,
            'offline_flow'            => '[]',
        ];
    }

    public static function get_all(): array {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $stored = get_option(self::OPTION_KEY, []);
        $stored = is_array($stored) ? $stored : [];
        $config = wp_parse_args($stored, self::defaults());

        $config['provider'] = self::sanitize_provider($stored['provider'] ?? $stored['fd_provider'] ?? $config['provider']);
        $config['fd_provider'] = $config['provider'];
        $config['force_offline'] = self::to_bool($stored['force_offline'] ?? $stored['fd_force_offline'] ?? $config['force_offline']);
        $config['fd_force_offline'] = $config['force_offline'];
        $config['enable_contact'] = self::to_bool($stored['enable_contact'] ?? $config['enable_contact']);
        $config['enable_floating'] = self::to_bool($stored['enable_floating'] ?? $config['enable_floating']);
        $config['enable_inline'] = self::to_bool($stored['enable_inline'] ?? $config['enable_inline']);
        $config['fallback_enabled'] = self::to_bool($stored['fallback_enabled'] ?? true);
        $config['send_user_confirmation'] = self::to_bool($stored['send_user_confirmation'] ?? true);
        $config['store_transcripts'] = self::to_bool($stored['store_transcripts'] ?? false);
        $config['rag_enabled'] = self::to_bool($stored['rag_enabled'] ?? true);

        $config['display_mode'] = self::sanitize_display_mode($stored['display_mode'] ?? self::derive_display_mode($config));
        $config['runtime_mode'] = self::sanitize_runtime_mode($stored['runtime_mode'] ?? self::derive_runtime_mode($config, $stored));
        $config['openai_model'] = self::sanitize_model($stored['openai_model'] ?? $config['openai_model']);
        if (in_array($config['openai_model'], ['gpt-4.1-mini', 'gpt-5.4-mini'], true)) {
            $config['openai_model'] = 'gpt-5-mini';
        }

        $config['ui_position_corner'] = self::sanitize_corner($stored['ui_position_corner'] ?? $config['ui_position_corner']);
        $config['ui_offset_x'] = max(0, (int) ($stored['ui_offset_x'] ?? $config['ui_offset_x']));
        $config['ui_offset_y'] = max(0, (int) ($stored['ui_offset_y'] ?? $config['ui_offset_y']));
        $config['ui_radius'] = max(10, min(32, (int) ($stored['ui_radius'] ?? $config['ui_radius'])));
        $config['max_input_chars'] = max(100, min(4000, (int) ($stored['max_input_chars'] ?? $config['max_input_chars'])));
        $config['max_output_tokens'] = max(100, min(2000, (int) ($stored['max_output_tokens'] ?? $config['max_output_tokens'])));
        $config['temperature'] = max(0, min(1, (float) ($stored['temperature'] ?? $config['temperature'])));
        $config['transcript_retention_days'] = max(1, min(365, (int) ($stored['transcript_retention_days'] ?? $config['transcript_retention_days'])));

        foreach (['brand_color', 'ui_header_bg', 'ui_header_text', 'ui_button_color', 'ui_button_hover_color', 'ui_button_text_color', 'ui_text_color'] as $color_key) {
            $config[$color_key] = self::sanitize_color($stored[$color_key] ?? $config[$color_key], self::defaults()[$color_key]);
        }

        $config['contact_email'] = sanitize_email($stored['contact_email'] ?? $config['contact_email']) ?: get_option('admin_email');
        $config['bot_name'] = sanitize_text_field($stored['bot_name'] ?? $config['bot_name']);
        $config['header_byline'] = sanitize_text_field($stored['header_byline'] ?? $config['header_byline']);
        $config['avatar_url'] = esc_url_raw($stored['avatar_url'] ?? $config['avatar_url']);
        $config['staff_photos'] = $stored['staff_photos'] ?? $config['staff_photos'];
        $config['style_mode'] = self::sanitize_style_mode($stored['style_mode'] ?? $config['style_mode']);
        $config['style_preset'] = self::sanitize_style_preset($stored['style_preset'] ?? $config['style_preset']);
        $config['ui_font_family'] = self::sanitize_font_family($stored['ui_font_family'] ?? $config['ui_font_family']);
        $config['input_placeholder'] = sanitize_text_field($stored['input_placeholder'] ?? $config['input_placeholder']);
        $config['teaser_title'] = sanitize_text_field($stored['teaser_title'] ?? $config['teaser_title']);
        $config['teaser_body'] = sanitize_text_field($stored['teaser_body'] ?? $config['teaser_body']);
        $config['launcher_label'] = sanitize_text_field($stored['launcher_label'] ?? $config['launcher_label']);
        $config['kb_button_label'] = sanitize_text_field($stored['kb_button_label'] ?? $config['kb_button_label']);
        $config['contact_button_label']   = sanitize_text_field($stored['contact_button_label'] ?? $config['contact_button_label']);
        $config['contact_btn_color']      = self::sanitize_color($stored['contact_btn_color'] ?? $config['contact_btn_color'], '');
        $config['contact_btn_text_color'] = self::sanitize_color($stored['contact_btn_text_color'] ?? $config['contact_btn_text_color'], '#ffffff');
        $config['recaptcha_site_key']     = sanitize_text_field($stored['recaptcha_site_key'] ?? $config['recaptcha_site_key']);
        $config['recaptcha_secret_key']   = sanitize_text_field($stored['recaptcha_secret_key'] ?? $config['recaptcha_secret_key']);
        $config['privacy_notice'] = sanitize_text_field($stored['privacy_notice'] ?? $config['privacy_notice']);
        $config['contact_subject_prefix']  = sanitize_text_field($stored['contact_subject_prefix'] ?? $config['contact_subject_prefix']);
        $config['notification_email_intro'] = sanitize_textarea_field($stored['notification_email_intro'] ?? $config['notification_email_intro']);
        $config['confirmation_email_body']  = sanitize_textarea_field($stored['confirmation_email_body'] ?? $config['confirmation_email_body']);
        $config['offline_notice'] = sanitize_text_field($stored['offline_notice'] ?? $config['offline_notice']);
        $config['error_message'] = sanitize_text_field($stored['error_message'] ?? $config['error_message']);
        $config['opening_hours'] = sanitize_textarea_field($stored['opening_hours'] ?? $config['opening_hours']);
        $config['alt_contact'] = sanitize_text_field($stored['alt_contact'] ?? $config['alt_contact']);
        $config['greeting_text'] = sanitize_textarea_field($stored['greeting_text'] ?? $config['greeting_text']);
        $config['greeting_html'] = wp_kses_post($stored['greeting_html'] ?? $config['greeting_html']);
        $faqs_raw = $stored['faqs_json'] ?? $config['faqs_json'];
        if (is_string($faqs_raw) && $faqs_raw !== '') {
            $config['faqs_json'] = $faqs_raw;
        } elseif (is_array($faqs_raw)) {
            $config['faqs_json'] = wp_json_encode($faqs_raw);
        } else {
            $config['faqs_json'] = '[]';
        }
        $config['rag_post_types'] = self::sanitize_post_types($stored['rag_post_types'] ?? $config['rag_post_types']);
        $offline_flow_raw = $stored['offline_flow'] ?? $config['offline_flow'];
        if (is_string($offline_flow_raw) && $offline_flow_raw !== '') {
            $config['offline_flow'] = $offline_flow_raw;
        } elseif (is_array($offline_flow_raw)) {
            $config['offline_flow'] = wp_json_encode($offline_flow_raw);
        } else {
            $config['offline_flow'] = '[]';
        }
        $config['openai_api_key'] = trim((string) ($stored['openai_api_key'] ?? $stored['api_key'] ?? ''));

        if ($config['style_mode'] === 'preset') {
            $preset = self::preset_values($config['style_preset']);
            foreach (['brand_color', 'ui_header_bg', 'ui_header_text', 'ui_button_color', 'ui_button_hover_color', 'ui_button_text_color', 'ui_text_color', 'ui_font_family'] as $key) {
                if (isset($preset[$key])) {
                    $config[$key] = $preset[$key];
                }
            }
        }

        self::$cache = $config;
        return $config;
    }

    public static function get(string $key, $default = null) {
        $config = self::get_all();
        return array_key_exists($key, $config) ? $config[$key] : $default;
    }

    public static function provider(): string {
        $config = self::get_all();
        if (!empty($config['force_offline'])) {
            return 'offline';
        }
        return $config['provider'];
    }

    public static function runtime_mode(): string {
        $config = self::get_all();
        if (!empty($config['force_offline'])) {
            return 'offline';
        }
        return self::sanitize_runtime_mode($config['runtime_mode'] ?? 'offline');
    }

    public static function has_openai_key(): bool {
        return trim((string) self::get('openai_api_key', '')) !== '';
    }

    public static function sanitize_for_option(array $input): array {
        $current = get_option(self::OPTION_KEY, []);
        $current = is_array($current) ? $current : [];
        $config = self::get_all();
        $style_mode = self::sanitize_style_mode($input['style_mode'] ?? $config['style_mode']);
        $style_preset = self::sanitize_style_preset($input['style_preset'] ?? $config['style_preset']);
        $ui_font_family = self::sanitize_font_family($input['ui_font_family'] ?? $config['ui_font_family']);
        $faqs_json = array_key_exists('faqs_json', $input)
            ? self::sanitize_faqs_json($input['faqs_json'])
            : (is_string($current['faqs_json'] ?? null) ? $current['faqs_json'] : $config['faqs_json']);
        $offline_flow = array_key_exists('offline_flow', $input)
            ? self::sanitize_offline_flow($input['offline_flow'])
            : (is_string($current['offline_flow'] ?? null) ? $current['offline_flow'] : $config['offline_flow']);

        $provider = self::sanitize_provider($input['provider'] ?? $input['fd_provider'] ?? $config['provider']);
        $force_offline = self::to_bool($input['force_offline'] ?? $input['fd_force_offline'] ?? $config['force_offline']);
        $enable_floating = self::to_bool($input['enable_floating'] ?? $config['enable_floating']);
        $enable_inline = self::to_bool($input['enable_inline'] ?? $config['enable_inline']);
        $display_mode = self::sanitize_display_mode($input['display_mode'] ?? self::derive_display_mode([
            'enable_floating' => $enable_floating,
            'enable_inline' => $enable_inline,
        ]));

        $key_input = isset($input['openai_api_key']) ? trim((string) $input['openai_api_key']) : '';
        $remove_key = self::to_bool($input['remove_openai_api_key'] ?? false) || $key_input === self::REMOVE_KEY_SENTINEL;
        if ($remove_key) {
            $openai_key = '';
        } elseif ($key_input === '') {
            $openai_key = trim((string) ($current['openai_api_key'] ?? $current['api_key'] ?? ''));
        } else {
            $openai_key = $key_input;
        }

        $saved = [
            'product_name'            => 'Frontdesk AI',
            'bot_name'                => sanitize_text_field($input['bot_name'] ?? $config['bot_name']),
            'header_byline'           => sanitize_text_field($input['header_byline'] ?? $config['header_byline']),
            'avatar_url'              => esc_url_raw($input['avatar_url'] ?? $config['avatar_url']),
            'staff_photos'            => self::sanitize_staff_photos($input['staff_photos'] ?? $current['staff_photos'] ?? '[]'),
            'style_mode'              => $style_mode,
            'style_preset'            => $style_preset,
            'ui_font_family'          => $ui_font_family,
            'greeting_text'           => sanitize_textarea_field($input['greeting_text'] ?? $config['greeting_text']),
            'greeting_html'           => wp_kses_post($input['greeting_html'] ?? $config['greeting_html']),
            'input_placeholder'       => sanitize_text_field($input['input_placeholder'] ?? $config['input_placeholder']),
            'teaser_title'            => sanitize_text_field($input['teaser_title'] ?? $config['teaser_title']),
            'teaser_body'             => sanitize_text_field($input['teaser_body'] ?? $config['teaser_body']),
            'launcher_label'          => sanitize_text_field($input['launcher_label'] ?? $config['launcher_label']),
            'kb_button_label'         => sanitize_text_field($input['kb_button_label'] ?? $config['kb_button_label']),
            'contact_button_label'    => sanitize_text_field($input['contact_button_label'] ?? $config['contact_button_label']),
            'contact_btn_color'       => self::sanitize_color($input['contact_btn_color'] ?? $config['contact_btn_color'], ''),
            'contact_btn_text_color'  => self::sanitize_color($input['contact_btn_text_color'] ?? $config['contact_btn_text_color'], '#ffffff'),
            'recaptcha_site_key'      => sanitize_text_field($input['recaptcha_site_key'] ?? $config['recaptcha_site_key']),
            'recaptcha_secret_key'    => sanitize_text_field($input['recaptcha_secret_key'] ?? $config['recaptcha_secret_key']),
            'offline_notice'          => sanitize_text_field($input['offline_notice'] ?? $config['offline_notice']),
            'error_message'           => sanitize_text_field($input['error_message'] ?? $config['error_message']),
            'display_mode'            => $display_mode,
            'enable_floating'         => $enable_floating,
            'enable_inline'           => $enable_inline,
            'enable_contact'          => self::to_bool($input['enable_contact'] ?? $config['enable_contact']),
            'ui_position_corner'      => self::sanitize_corner($input['ui_position_corner'] ?? $config['ui_position_corner']),
            'ui_offset_x'             => max(0, (int) ($input['ui_offset_x'] ?? $config['ui_offset_x'])),
            'ui_offset_y'             => max(0, (int) ($input['ui_offset_y'] ?? $config['ui_offset_y'])),
            'brand_color'             => self::sanitize_color($input['brand_color'] ?? $config['brand_color'], $config['brand_color']),
            'ui_header_bg'            => self::sanitize_color($input['ui_header_bg'] ?? $config['ui_header_bg'], $config['ui_header_bg']),
            'ui_header_text'          => self::sanitize_color($input['ui_header_text'] ?? $config['ui_header_text'], $config['ui_header_text']),
            'ui_button_color'         => self::sanitize_color($input['ui_button_color'] ?? $config['ui_button_color'], $config['ui_button_color']),
            'ui_button_hover_color'   => self::sanitize_color($input['ui_button_hover_color'] ?? $config['ui_button_hover_color'], $config['ui_button_hover_color']),
            'ui_button_text_color'    => self::sanitize_color($input['ui_button_text_color'] ?? $config['ui_button_text_color'], $config['ui_button_text_color']),
            'ui_text_color'           => self::sanitize_color($input['ui_text_color'] ?? $config['ui_text_color'], $config['ui_text_color']),
            'ui_radius'               => max(10, min(32, (int) ($input['ui_radius'] ?? $config['ui_radius']))),
            'provider'                => $provider,
            'fd_provider'             => $provider,
            'openai_api_key'          => $openai_key,
            'api_key'                 => $openai_key,
            'openai_model'            => self::sanitize_model($input['openai_model'] ?? $config['openai_model']),
            'runtime_mode'            => self::sanitize_runtime_mode($input['runtime_mode'] ?? $config['runtime_mode']),
            'fallback_enabled'        => self::to_bool($input['fallback_enabled'] ?? $config['fallback_enabled']),
            'force_offline'           => $force_offline,
            'fd_force_offline'        => $force_offline,
            'max_input_chars'         => max(100, min(4000, (int) ($input['max_input_chars'] ?? $config['max_input_chars']))),
            'max_output_tokens'       => max(100, min(2000, (int) ($input['max_output_tokens'] ?? $config['max_output_tokens']))),
            'temperature'             => max(0, min(1, (float) ($input['temperature'] ?? $config['temperature']))),
            'faqs_json'               => $faqs_json,
            'rag_enabled'             => self::to_bool($input['rag_enabled'] ?? $config['rag_enabled']),
            'rag_post_types'          => self::sanitize_post_types($input['rag_post_types'] ?? $config['rag_post_types']),
            'opening_hours'           => sanitize_textarea_field($input['opening_hours'] ?? $config['opening_hours']),
            'alt_contact'             => sanitize_text_field($input['alt_contact'] ?? $config['alt_contact']),
            'contact_email'            => sanitize_email($input['contact_email'] ?? $config['contact_email']) ?: get_option('admin_email'),
            'send_user_confirmation'   => self::to_bool($input['send_user_confirmation'] ?? $config['send_user_confirmation']),
            'contact_subject_prefix'   => sanitize_text_field($input['contact_subject_prefix'] ?? $config['contact_subject_prefix']),
            'notification_email_intro' => sanitize_textarea_field($input['notification_email_intro'] ?? $config['notification_email_intro']),
            'confirmation_email_body'  => sanitize_textarea_field($input['confirmation_email_body'] ?? $config['confirmation_email_body']),
            'privacy_notice'          => sanitize_text_field($input['privacy_notice'] ?? $config['privacy_notice']),
            'store_transcripts'       => self::to_bool($input['store_transcripts'] ?? $config['store_transcripts']),
            'transcript_retention_days' => max(1, min(365, (int) ($input['transcript_retention_days'] ?? $config['transcript_retention_days']))),
            'offline_flow'            => $offline_flow,
        ];

        if ($style_mode === 'preset') {
            $preset = self::preset_values($style_preset);
            foreach (['brand_color', 'ui_header_bg', 'ui_header_text', 'ui_button_color', 'ui_button_hover_color', 'ui_button_text_color', 'ui_text_color', 'ui_font_family'] as $key) {
                if (isset($preset[$key])) {
                    $saved[$key] = $preset[$key];
                }
            }
        }

        if (self::to_bool($input['use_flow_online'] ?? $current['use_flow_online'] ?? false)) {
            $saved['use_flow_online'] = true;
        } else {
            $saved['use_flow_online'] = false;
        }

        return array_merge($current, $saved);
    }

    public static function save(array $input): array {
        $normalized = self::sanitize_for_option($input);
        update_option(self::OPTION_KEY, $normalized, false);
        self::reset_cache();
        return self::get_all();
    }

    public static function admin_view(): array {
        $config = self::get_all();
        $view = $config;
        $view['openai_key_saved'] = self::has_openai_key();
        $view['openai_api_key'] = '';
        $view['validation'] = self::validate();
        $view['knowledge_status'] = class_exists('Frontdesk_Knowledge') ? Frontdesk_Knowledge::get_index_status() : [];
        return $view;
    }

    public static function public_boot(array $overrides = []): array {
        $config = self::get_all();
        $skip_faq_hydration = !empty($overrides['skipFaqHydration']);
        unset($overrides['skipFaqHydration']);
        $display_mode = $overrides['display_mode'] ?? $config['display_mode'];
        $provider = self::provider();
        $runtime_mode = self::runtime_mode();
        $instance_id = $overrides['instanceId'] ?? 'frontdesk-' . wp_generate_uuid4();

        $boot = [
            'instanceId' => $instance_id,
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'chatAction' => 'fnd_frontdesk_chat',
            'contactAction' => 'fnd_frontdesk_contact',
            'chatNonce' => wp_create_nonce('fnd_frontdesk_chat'),
            'contactNonce' => wp_create_nonce('fnd_frontdesk_contact'),
            'displayMode' => $display_mode,
            'provider' => $provider,
            'runtimeMode' => $runtime_mode,
            'fallbackEnabled' => (bool) $config['fallback_enabled'],
            'copy' => [
                'productName' => 'Frontdesk AI',
                'botName' => $config['bot_name'],
                'headerByline' => $config['header_byline'],
                'greeting' => self::interpolate($config['greeting_text']),
                'greetingHtml' => $config['greeting_html'] !== '' ? self::interpolate($config['greeting_html']) : '',
                'inputPlaceholder' => $config['input_placeholder'],
                'teaserTitle' => $config['teaser_title'],
                'teaserBody' => $config['teaser_body'],
                'launcherLabel' => $config['launcher_label'],
                'kbButtonLabel' => $config['kb_button_label'],
                'contactButtonLabel' => $config['contact_button_label'],
                'privacyNotice' => $config['privacy_notice'],
                'offlineNotice' => $config['offline_notice'],
                'errorMessage' => $config['error_message'],
            ],
            'ui' => [
                'position' => $config['ui_position_corner'],
                'offsetX' => (int) $config['ui_offset_x'],
                'offsetY' => (int) $config['ui_offset_y'],
                'brandColor' => $config['brand_color'],
                'headerBg' => $config['ui_header_bg'],
                'headerText' => $config['ui_header_text'],
                'buttonColor' => $config['ui_button_color'],
                'buttonHoverColor' => $config['ui_button_hover_color'],
                'buttonText' => $config['ui_button_text_color'],
                'textColor' => $config['ui_text_color'],
                'fontFamily' => $config['ui_font_family'],
                'radius' => (int) $config['ui_radius'],
            ],
            'features' => [
                'contact' => (bool) $config['enable_contact'],
                'knowledge' => true,
                'saveTranscript' => (bool) $config['store_transcripts'],
            ],
            'faqs' => $skip_faq_hydration ? [] : self::faqs_array($config['faqs_json']),
            'staffPhotos' => self::staff_photos_array($config['staff_photos'] ?? '[]'),
            'contactBtnColor'     => $config['contact_btn_color'],
            'contactBtnTextColor' => $config['contact_btn_text_color'],
            'recaptchaSiteKey'    => $config['recaptcha_site_key'],
            'hours' => $config['opening_hours'],
            'contactEmail' => $config['contact_email'],
            'homeUrl' => home_url('/'),
        ];

        foreach ($overrides as $key => $value) {
            if (in_array($key, ['copy', 'ui', 'features'], true) && is_array($value)) {
                $boot[$key] = array_merge($boot[$key] ?? [], $value);
            } else {
                $boot[$key] = $value;
            }
        }

        return $boot;
    }

    public static function validate(): array {
        $config = self::get_all();
        $warnings = [];

        if ($config['provider'] === 'openai' && trim((string) $config['openai_api_key']) === '') {
            $warnings[] = __('OpenAI is selected but no API key is saved.', 'foundation-frontdesk');
        }
        if (empty(self::faqs_array($config['faqs_json'])) && class_exists('Frontdesk_Knowledge')) {
            $status = Frontdesk_Knowledge::get_index_status();
            if (empty($status['available'])) {
                $warnings[] = __('No FAQs are configured and the site knowledge index is not available yet.', 'foundation-frontdesk');
            }
        }
        if (!self::to_bool($config['fallback_enabled']) && $config['provider'] === 'openai') {
            $warnings[] = __('Fallback is disabled. Visitors will see an error if OpenAI is unavailable.', 'foundation-frontdesk');
        }
        if (empty($config['contact_email']) || !is_email($config['contact_email'])) {
            $warnings[] = __('Contact capture is enabled without a valid recipient email.', 'foundation-frontdesk');
        }

        return [
            'provider' => $config['provider'],
            'runtimeMode' => $config['runtime_mode'],
            'hasOpenAIKey' => self::has_openai_key(),
            'warnings' => $warnings,
        ];
    }

    public static function interpolate(string $text): string {
        $config = self::get_all();
        $replacements = [
            '{bot_name}' => $config['bot_name'],
            '{site_name}' => wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES),
            '{hours}' => $config['opening_hours'],
            '{contact}' => $config['alt_contact'],
            '{admin_email}' => get_option('admin_email'),
        ];
        return strtr($text, $replacements);
    }

    public static function staff_photos_array($json = null): array {
        $json = $json ?? self::get('staff_photos', '[]');
        $rows = json_decode((string) $json, true);
        if (!is_array($rows)) return [];
        return array_values(array_slice(array_filter(array_map('esc_url_raw', $rows)), 0, 4));
    }

    private static function sanitize_staff_photos($input): string {
        $urls = [];
        if (is_array($input)) {
            $urls = $input;
        } elseif (is_string($input)) {
            $decoded = json_decode($input, true);
            if (is_array($decoded)) $urls = $decoded;
        }
        $urls = array_values(array_filter(array_slice(array_map('esc_url_raw', $urls), 0, 4)));
        return wp_json_encode($urls);
    }

    public static function faqs_array($json = null): array {
        $json = $json ?? self::get('faqs_json', '[]');
        $rows = json_decode((string) $json, true);
        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    public static function style_presets(): array {
        return [
            'sea_glass' => [
                'label' => __('Sea Glass', 'foundation-frontdesk'),
                'values' => [
                    'brand_color' => '#04ad93',
                    'ui_header_bg' => '#1e6167',
                    'ui_header_text' => '#FFFFFF',
                    'ui_button_color' => '#04ad93',
                    'ui_button_hover_color' => '#038d78',
                    'ui_button_text_color' => '#FFFFFF',
                    'ui_text_color' => '#111111',
                    'ui_font_family' => '"Plus Jakarta Sans", ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif',
                ],
            ],
            'midnight' => [
                'label' => __('Midnight', 'foundation-frontdesk'),
                'values' => [
                    'brand_color' => '#2946b6',
                    'ui_header_bg' => '#14204f',
                    'ui_header_text' => '#FFFFFF',
                    'ui_button_color' => '#2946b6',
                    'ui_button_hover_color' => '#1f368a',
                    'ui_button_text_color' => '#FFFFFF',
                    'ui_text_color' => '#0f172a',
                    'ui_font_family' => '"Sora", ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif',
                ],
            ],
            'warm_blush' => [
                'label' => __('Warm Blush', 'foundation-frontdesk'),
                'values' => [
                    'brand_color' => '#d97c8f',
                    'ui_header_bg' => '#8f4054',
                    'ui_header_text' => '#FFFFFF',
                    'ui_button_color' => '#f2b5c2',
                    'ui_button_hover_color' => '#dd97a8',
                    'ui_button_text_color' => '#4a1824',
                    'ui_text_color' => '#23151a',
                    'ui_font_family' => '"DM Sans", ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif',
                ],
            ],
        ];
    }

    public static function reset_cache(): void {
        self::$cache = null;
    }

    public static function handle_test_openai(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to do that.', 'foundation-frontdesk'));
        }
        check_admin_referer('frontdesk_test_openai');
        $result = ['ok' => false, 'message' => __('OpenAI test could not run.', 'foundation-frontdesk')];
        if (class_exists('Frontdesk_OpenAI_Provider')) {
            $provider = new Frontdesk_OpenAI_Provider();
            $result = $provider->validate(self::get_all());
        }
        $redirect = add_query_arg([
            'page' => 'foundation-frontdesk-settings',
            'frontdesk_test' => $result['ok'] ? 'ok' : 'fail',
            'frontdesk_message' => rawurlencode($result['message'] ?? ''),
        ], admin_url('admin.php'));
        wp_safe_redirect($redirect);
        exit;
    }

    public static function handle_rebuild_knowledge(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to do that.', 'foundation-frontdesk'));
        }
        check_admin_referer('frontdesk_rebuild_knowledge');
        $status = get_option('fnd_frontdesk_rag_status', []);
        if (class_exists('Foundation_Frontdesk_RAG')) {
            $post_types = class_exists('Frontdesk_Knowledge')
                ? Frontdesk_Knowledge::selected_post_types(self::get_all())
                : ['post', 'page'];
            $status = [
                'status' => 'queued',
                'indexed' => 0,
                'total' => 0,
                'post_types' => $post_types,
            ];
            update_option('fnd_frontdesk_rag_status', $status, false);
        }
        wp_safe_redirect(add_query_arg([
            'page' => 'foundation-frontdesk-settings',
            'frontdesk_knowledge' => 'queued',
        ], admin_url('admin.php')));
        exit;
    }

    private static function to_bool($value): bool {
        if (function_exists('fnd_frontdesk_bool')) {
            return fnd_frontdesk_bool($value);
        }
        return !empty($value);
    }

    private static function sanitize_provider($provider): string {
        $provider = sanitize_key((string) $provider);
        return in_array($provider, ['openai', 'offline'], true) ? $provider : 'offline';
    }

    private static function sanitize_runtime_mode($mode): string {
        $mode = sanitize_key((string) $mode);
        return in_array($mode, ['faq_only', 'site_index', 'ai_site_index', 'offline'], true) ? $mode : 'offline';
    }

    private static function sanitize_display_mode($mode): string {
        $mode = sanitize_key((string) $mode);
        return in_array($mode, ['floating', 'inline', 'both'], true) ? $mode : 'floating';
    }

    private static function sanitize_style_mode($mode): string {
        $mode = sanitize_key((string) $mode);
        return in_array($mode, ['preset', 'manual'], true) ? $mode : 'preset';
    }

    private static function sanitize_style_preset($preset): string {
        $preset = sanitize_key((string) $preset);
        return array_key_exists($preset, self::style_presets()) ? $preset : 'sea_glass';
    }

    private static function sanitize_font_family($font): string {
        $font = trim(preg_replace('/[\r\n\t]+/', ' ', (string) $font));
        $font = preg_replace('/[^a-zA-Z0-9 ,._"\'-]/', '', $font);
        return $font ?: self::defaults()['ui_font_family'];
    }

    private static function sanitize_model($model): string {
        $model = preg_replace('/[^a-zA-Z0-9._-]/', '', (string) $model);
        return $model ?: 'gpt-5-mini';
    }

    private static function sanitize_corner($corner): string {
        $corner = sanitize_key((string) $corner);
        return in_array($corner, ['bottom_right', 'bottom_left', 'top_right', 'top_left'], true) ? $corner : 'bottom_right';
    }

    private static function sanitize_color($color, $fallback): string {
        if (function_exists('fnd_frontdesk_sanitize_hex_color')) {
            return fnd_frontdesk_sanitize_hex_color($color, $fallback);
        }
        return $fallback;
    }

    private static function sanitize_post_types($value): string {
        $parts = is_array($value) ? $value : explode(',', (string) $value);
        $parts = array_filter(array_map('sanitize_key', array_map('trim', $parts)));
        $allowed = get_post_types(['public' => true], 'names');
        $parts = array_values(array_intersect($parts, $allowed));
        return implode(',', $parts ?: ['post', 'page']);
    }

    private static function sanitize_faqs_json($raw): string {
        $rows = json_decode((string) $raw, true);
        if (!is_array($rows)) {
            return '[]';
        }
        $clean = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $q = sanitize_text_field($row['q'] ?? '');
            $a = sanitize_textarea_field($row['a'] ?? '');
            $url = esc_url_raw($row['url'] ?? '');
            if ($q !== '' && $a !== '') {
                $clean[] = ['q' => $q, 'a' => $a, 'url' => $url];
            }
        }
        return wp_json_encode($clean);
    }

    private static function sanitize_offline_flow($raw): string {
        $rows = json_decode((string) $raw, true);
        if (!is_array($rows)) {
            return '[]';
        }
        $clean = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = sanitize_key($row['id'] ?? '');
            $message = sanitize_textarea_field($row['message'] ?? '');
            if ($id === '' || $message === '') {
                continue;
            }
            $buttons = [];
            if (!empty($row['buttons']) && is_array($row['buttons'])) {
                foreach ($row['buttons'] as $button) {
                    if (!is_array($button)) {
                        continue;
                    }
                    $label = sanitize_text_field($button['label'] ?? '');
                    $action = trim((string) ($button['action'] ?? ''));
                    if ($label === '') {
                        continue;
                    }
                    if (preg_match('/^next:([a-z0-9_-]+)$/i', $action, $matches)) {
                        $action = 'next:' . sanitize_key($matches[1]);
                    } elseif (!in_array($action, ['end', 'contact', 'search', 'faq'], true)) {
                        $action = 'end';
                    }
                    $buttons[] = [
                        'label' => $label,
                        'action' => $action,
                    ];
                }
            }
            $clean[] = [
                'id' => $id,
                'message' => $message,
                'buttons' => $buttons,
            ];
        }
        return wp_json_encode($clean);
    }

    private static function preset_values(string $preset): array {
        $presets = self::style_presets();
        return $presets[$preset]['values'] ?? $presets['sea_glass']['values'];
    }

    private static function derive_display_mode(array $config): string {
        $floating = !empty($config['enable_floating']);
        $inline = array_key_exists('enable_inline', $config) ? !empty($config['enable_inline']) : true;
        if ($floating && $inline) {
            return 'both';
        }
        if ($inline) {
            return 'inline';
        }
        return 'floating';
    }

    private static function derive_runtime_mode(array $config, array $stored): string {
        if (!empty($stored['runtime_mode'])) {
            return self::sanitize_runtime_mode($stored['runtime_mode']);
        }
        if (!empty($config['force_offline'])) {
            return 'offline';
        }
        if ($config['provider'] === 'openai' && !empty($config['openai_api_key'])) {
            return 'ai_site_index';
        }
        if (!empty(self::faqs_array($stored['faqs_json'] ?? '[]'))) {
            return 'faq_only';
        }
        return 'offline';
    }
}
