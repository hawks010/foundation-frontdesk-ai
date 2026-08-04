<?php
if (!defined('ABSPATH')) { exit; }

if (!class_exists('Foundation_Frontdesk')):
class Foundation_Frontdesk {
    private static $css_printed = false;
    private static $inline_rendered = false;
    private static $floating_rendered = false;

    public static function init(): void {
        add_shortcode('foundation_frontdesk', [__CLASS__, 'shortcode']);

        if (defined('FOUNDATION_FRONTDESK_AUTO') && FOUNDATION_FRONTDESK_AUTO) {
            add_action('wp_footer', [__CLASS__, 'inject_footer'], 99);
        }
    }

    public static function shortcode($atts = [], $content = ''): string {
        $atts = shortcode_atts([
            'title' => '',
            'subtitle' => '',
            'launcher' => '0',
            'mode' => 'inline',
            'label' => '',
            'position' => '',
        ], $atts, 'foundation_frontdesk');

        $launcher_requested = in_array(strtolower((string) $atts['launcher']), ['1', 'true', 'yes', 'header'], true)
            || strtolower((string) $atts['mode']) === 'launcher';

        $overrides = [
            'displayMode' => $launcher_requested ? 'floating' : 'inline',
            'instance_display' => $launcher_requested ? 'launcher' : 'inline',
        ];

        if ($launcher_requested) {
            $overrides['header_launcher'] = true;
            if (trim((string) $atts['label']) !== '') {
                $overrides['header_launcher_label'] = sanitize_text_field((string) $atts['label']);
            }
            if (trim((string) $atts['position']) !== '') {
                $overrides['ui'] = ['position' => sanitize_key((string) $atts['position'])];
            }
        }

        if (trim((string) $atts['title']) !== '') {
            $overrides['copy'] = ['botName' => sanitize_text_field((string) $atts['title'])];
        }
        if (trim((string) $atts['subtitle']) !== '') {
            $overrides['copy'] = array_merge($overrides['copy'] ?? [], ['headerByline' => sanitize_text_field((string) $atts['subtitle'])]);
        }
        if (trim((string) $content) !== '') {
            $overrides['copy'] = array_merge($overrides['copy'] ?? [], ['greeting' => Frontdesk_Config::interpolate(trim((string) $content))]);
        }

        self::$inline_rendered = true;
        return self::render_instance($overrides);
    }

    public static function inject_footer(): void {
        if (is_admin() || wp_doing_ajax()) {
            return;
        }

        if (self::$floating_rendered) {
            return;
        }

        $config = Frontdesk_Config::get_all();
        $mode = $config['display_mode'] ?? 'floating';
        if (!in_array($mode, ['floating', 'both'], true)) {
            return;
        }

        $has_shortcode = self::page_has_shortcode('foundation_frontdesk');
        if ($has_shortcode && $mode !== 'both') {
            return;
        }

        self::$floating_rendered = true;
        echo self::render_instance([
            'displayMode' => 'floating',
            'instance_display' => 'floating',
        ]);
    }

    private static function page_has_shortcode(string $shortcode): bool {
        global $post;
        return is_singular() && $post instanceof WP_Post && has_shortcode((string) $post->post_content, $shortcode);
    }

    public static function render_instance(array $overrides = []): string {
        $display = $overrides['instance_display'] ?? $overrides['displayMode'] ?? 'floating';
        $display = in_array($display, ['inline', 'launcher'], true) ? $display : 'floating';
        $is_inline = $display === 'inline';

        $copy_overrides = !empty($overrides['copy']) && is_array($overrides['copy']) ? $overrides['copy'] : [];
        $boot_overrides = $overrides;
        $boot_overrides['displayMode'] = $display === 'launcher' ? 'floating' : $display;
        unset($boot_overrides['instance_display'], $boot_overrides['copy']);

        $boot = Frontdesk_Config::public_boot($boot_overrides);
        if (!empty($copy_overrides)) {
            $boot['copy'] = array_merge($boot['copy'], $copy_overrides);
        }

        $args = [
            'title' => (string) ($boot['copy']['botName'] ?? ''),
            'subtitle' => (string) ($boot['copy']['headerByline'] ?? ''),
            'greeting' => (string) ($boot['copy']['greeting'] ?? ''),
            'show_launcher' => $display === 'floating',
            'header_launcher' => $display === 'launcher' || !empty($overrides['header_launcher']),
            'header_launcher_label' => (string) ($overrides['header_launcher_label'] ?? ''),
            'inline' => $is_inline,
            'boot' => $boot,
        ];

        ob_start();
        self::print_css();
        self::render_widget($args);
        return (string) ob_get_clean();
    }

    private static function get_logo_url(array $config): string {
        $avatar_url = trim((string) ($config['avatar_url'] ?? ''));
        if ($avatar_url !== '') {
            return esc_url_raw($avatar_url);
        }

        $custom_logo_id = (int) get_theme_mod('custom_logo');
        if ($custom_logo_id > 0) {
            $src = wp_get_attachment_image_src($custom_logo_id, 'thumbnail');
            if ($src && !empty($src[0])) {
                return esc_url_raw((string) $src[0]);
            }
        }

        $icon = get_site_icon_url(64);
        return $icon ? esc_url_raw($icon) : '';
    }

    private static function get_logo_html(array $config): string {
        $url = self::get_logo_url($config);
        if ($url !== '') {
            return '<span class="fnd-frontdesk__logo"><img src="' . esc_url($url) . '" alt="" decoding="async" fetchpriority="low" /></span>';
        }

        return '<span class="fnd-frontdesk__logo fnd-frontdesk__logo--svg" aria-hidden="true"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 6a1 1 0 0 1 1-1h1a1 1 0 1 1 0 2h-1a1 1 0 0 1-1-1Zm-7 9.5a1.5 1.5 0 0 1 1.5-1.5h11a1.5 1.5 0 0 1 1.5 1.5V17H5v-1.5Zm1.36-3.62A6.5 6.5 0 0 1 11 9.04V8.5a1 1 0 1 1 2 0v.54a6.5 6.5 0 0 1 4.64 2.84l.22.34H6.14l.22-.34Z" fill="currentColor"/></svg></span>';
    }

    private static function darken_hex(string $hex, int $pct): string {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6) return '#' . $hex;
        [$r, $g, $b] = [hexdec(substr($hex,0,2)), hexdec(substr($hex,2,2)), hexdec(substr($hex,4,2))];
        $r = max(0, (int) round($r * (1 - $pct / 100)));
        $g = max(0, (int) round($g * (1 - $pct / 100)));
        $b = max(0, (int) round($b * (1 - $pct / 100)));
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    private static function floating_position_style(array $boot, string $target, bool $inline): string {
        if ($inline) {
            return '';
        }

        $ui = $boot['ui'] ?? [];
        $corner = sanitize_key((string) ($ui['position'] ?? 'bottom_right'));
        if (!in_array($corner, ['bottom_right', 'bottom_left', 'top_right', 'top_left'], true)) {
            $corner = 'bottom_right';
        }

        $offset_x = max(0, (int) ($ui['offsetX'] ?? 24));
        $offset_y = max(0, (int) ($ui['offsetY'] ?? 24));
        $teaser_gap = 68;

        $style = [];
        if (str_contains($corner, 'bottom')) {
            $style['bottom'] = ($target === 'teaser' ? $offset_y + $teaser_gap : $offset_y) . 'px';
        } else {
            $style['top'] = ($target === 'teaser' ? $offset_y + $teaser_gap : $offset_y) . 'px';
        }

        if (str_contains($corner, 'right')) {
            $style['right'] = $offset_x . 'px';
        } else {
            $style['left'] = $offset_x . 'px';
        }

        $out = '';
        foreach ($style as $prop => $value) {
            $out .= $prop . ':' . sanitize_text_field((string) $value) . ';';
        }

        return $out;
    }

    private static function print_css(): void {
        if (self::$css_printed) {
            return;
        }
        self::$css_printed = true;
        ?>
        <style id="foundation-frontdesk-inline-css">
            .fnd-frontdesk {
              --fnd-brand: #1e6167;
              --fnd-accent: #04ad93;
              --fnd-accent-hover: #038d78;
              --fnd-accent-text: #ffffff;
              --fnd-border: #e9ecef;
              --fnd-body-text: #1f2937;
              --fnd-muted: #6b7280;
              --fnd-radius-lg: 16px;
              --fnd-radius-md: 12px;
              position: fixed;
              right: 24px;
              bottom: 24px;
              width: clamp(360px, 40vw, 560px);
              max-width: calc(100vw - 32px);
              background: #fff;
              border: 1px solid var(--fnd-border);
              box-shadow: 0 8px 24px rgba(0,0,0,.1);
              border-radius: var(--fnd-radius-lg);
              overflow: hidden;
              font-family: var(--fnd-font, ui-sans-serif,system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif);
              color: var(--fnd-body-text);
              z-index: 2147483646;
              font-size: 14px;
            }
            .fnd-frontdesk[hidden] { display: none !important; }
            .fnd-frontdesk--inline {
              position: relative;
              right: auto;
              bottom: auto;
              width: min(100%, 560px);
              max-width: 100%;
            }
            .fnd-frontdesk__header {
              display: grid;
              grid-template-columns: 1fr auto;
              gap: 10px;
              align-items: center;
              padding: 11px 14px;
              background: var(--fnd-brand);
              color: #fff;
              border-bottom: 1px solid rgba(255,255,255,.08);
            }
            .fnd-frontdesk__brand {
              display: flex;
              gap: 10px;
              align-items: center;
              min-width: 0;
            }
            .fnd-frontdesk__logo {
              flex-shrink: 0;
              width: 36px;
              height: 36px;
              border-radius: 8px;
              background: rgba(255,255,255,.15);
              color: #fff;
              display: grid;
              place-items: center;
              overflow: hidden;
            }
            .fnd-frontdesk__logo img {
              width: 100%;
              height: 100%;
              object-fit: cover;
              display: block;
            }
            .fnd-frontdesk__logo--svg svg { width: 20px; height: 20px; }
            .fnd-frontdesk__title {
              font-size: 15px;
              font-weight: 700;
              overflow: hidden;
              text-overflow: ellipsis;
              white-space: nowrap;
            }
            .fnd-frontdesk__subtitle { font-size: 12px; opacity: .9; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
            .fnd-frontdesk__actions { display: flex; gap: 8px; align-items: center; }
            .fnd-kb-btn,
            .fnd-menu-btn {
              appearance: none;
              border: 0;
              background: rgba(255,255,255,0.12);
              color: #fff;
              height: 32px;
              border-radius: 8px;
              display: flex;
              align-items: center;
              justify-content: center;
              cursor: pointer;
              padding: 0 10px;
              font: inherit;
            }
            .fnd-kb-btn:hover,
            .fnd-menu-btn:hover { background: rgba(255,255,255,0.2); }
            .fnd-close-btn {
              appearance: none;
              border: 0;
              background: rgba(255,255,255,0.12);
              color: #fff;
              width: 32px;
              height: 32px;
              border-radius: 8px;
              display: flex;
              align-items: center;
              justify-content: center;
              cursor: pointer;
              padding: 0;
              font-family: inherit;
              flex-shrink: 0;
            }
            .fnd-close-btn:hover { background: rgba(255,255,255,0.22); }
            .fnd-frontdesk .fnd-close-btn:focus-visible {
              outline: 2px solid rgba(255,255,255,.9);
              outline-offset: 2px;
            }
            .fnd-fab:focus-visible {
              outline: 3px solid #fff;
              outline-offset: 3px;
            }
            .fnd-frontdesk button:focus-visible,
            .fnd-frontdesk input:focus-visible,
            .fnd-frontdesk textarea:focus-visible,
            .fnd-teaser .x:focus-visible {
              outline: 3px solid var(--fnd-brand, #1e6167);
              outline-offset: 2px;
            }
            .fnd-frontdesk .fnd-kb-btn:focus-visible,
            .fnd-frontdesk .fnd-menu-btn:focus-visible {
              outline: 2px solid rgba(255,255,255,.9);
              outline-offset: 2px;
            }
            .fnd-frontdesk .screen-reader-text {
              border: 0;
              clip: rect(1px, 1px, 1px, 1px);
              clip-path: inset(50%);
              height: 1px;
              margin: -1px;
              overflow: hidden;
              padding: 0;
              position: absolute;
              width: 1px;
              word-wrap: normal !important;
            }
            .fnd-menu-icon { display: inline-grid; gap: 3px; margin-left: 4px; }
            .fnd-menu-icon span { display: block; width: 4px; height: 4px; border-radius: 50%; background: #fff; }
            .fnd-menu {
              position: absolute;
              top: 38px;
              right: 0;
              background: #fff;
              border: 1px solid var(--fnd-border);
              border-radius: 12px;
              box-shadow: 0 8px 18px rgba(0,0,0,.12);
              min-width: 180px;
              overflow: hidden;
              z-index: 3;
            }
            .fnd-menu[hidden] { display: none !important; }
            .fnd-menu button {
              display: block;
              width: 100%;
              background: #fff;
              border: 0;
              text-align: left;
              padding: 12px 14px;
              cursor: pointer;
              color: #0f172a;
              font: inherit;
            }
            .fnd-menu button:hover,
            .fnd-menu button:focus { background: #eef2f7; color: #0f172a; }
            .fnd-frontdesk__body {
              display: grid;
              grid-template-rows: 1fr auto auto auto;
              height: min(560px, calc(100vh - 120px));
            }
            .fnd-kb {
              background: var(--fnd-brand);
              color: #fff;
              border-bottom: 1px solid rgba(255,255,255,.08);
              overflow: auto;
              position: absolute;
              left: 0;
              right: 0;
              bottom: 0;
              top: 0;
              display: none;
              z-index: 5;
            }
            .fnd-kb[aria-hidden="false"] { display: block; }
            .fnd-kb__wrap { padding: 14px; }
            .fnd-kb__head { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }
            .fnd-kb__title { font-weight: 800; }
            .fnd-kb__back {
              margin-left: auto;
              appearance: none;
              border: 0;
              background: rgba(255,255,255,.14);
              color: #fff;
              border-radius: 999px;
              padding: 6px 10px;
              cursor: pointer;
              font: inherit;
            }
            .fnd-kb__search { margin: 8px 0 10px 0; }
            .fnd-kb__search input {
              width: 100%;
              border-radius: 10px;
              border: 0;
              padding: 10px 12px;
              box-sizing: border-box;
              font: inherit;
            }
            .fnd-kb__list { display: grid; gap: 8px; }
            .fnd-kb__item { background: #fff; color: #0f172a; border-radius: 12px; overflow: hidden; }
            .fnd-kb__q {
              width: 100%;
              display: flex;
              align-items: center;
              justify-content: space-between;
              gap: 10px;
              border: 0;
              background: transparent;
              font-weight: 700;
              cursor: pointer;
              padding: 12px 14px;
              font: inherit;
              text-align: left;
              color: #0f172a;
              transition: background .15s, color .15s;
            }
            .fnd-kb__q:hover { background: var(--fnd-accent); color: var(--fnd-accent-text); }
            .fnd-kb__caret { transition: transform .18s ease; }
            .fnd-kb__q[aria-expanded="true"] .fnd-kb__caret { transform: rotate(180deg); }
            .fnd-kb__a {
              margin: 0;
              border-top: 1px solid rgba(0,0,0,.06);
              padding: 10px 12px;
              background: #fff;
              white-space: pre-wrap;
            }
            .fnd-kb__a a { color: var(--fnd-accent); text-decoration: none; }
            .fnd-frontdesk__messages {
              padding: 14px;
              overflow: auto;
              background: #fafbfc;
              border-bottom: 1px solid var(--fnd-border);
            }
            .fnd-row-msg {
              display: grid;
              grid-template-columns: 32px 1fr;
              gap: 8px;
              align-items: flex-start;
              margin: 10px 0;
            }
            .fnd-row-msg--user { grid-template-columns: 1fr 32px; }
            .fnd-row-msg--user .fnd-avatar { order: 2; }
            .fnd-row-msg--user .fnd-bubble {
              order: 1;
              justify-self: end;
              background: var(--fnd-accent);
              border-color: var(--fnd-accent);
              color: var(--fnd-accent-text);
            }
            .fnd-avatar {
              width: 32px;
              height: 32px;
              border-radius: 50%;
              overflow: hidden;
              background: #fff;
              border: 1px solid var(--fnd-border);
              display: flex;
              align-items: center;
              justify-content: center;
              color: var(--fnd-brand);
            }
            .fnd-avatar img { width: 100%; height: 100%; object-fit: cover; }
            .fnd-bubble {
              background: #f0fdf9;
              border: 1px solid rgba(4,173,147,.15);
              padding: 12px 14px;
              border-radius: 12px;
              max-width: 100%;
              font-size: 14px;
              line-height: 1.6;
              white-space: pre-wrap;
              text-align: left;
            }
            .fnd-bubble--fallback {
              background: #fffbeb;
              border-left: 3px solid #f59e0b;
            }
            .fnd-stamp { display: block; margin-top: 4px; font-size: 11px; color: var(--fnd-muted); }
            .fnd-row-msg--user .fnd-stamp { color: rgba(255,255,255,.72); }
            .fnd-bubble--html { white-space: normal; }
            .fnd-bubble a { color: var(--fnd-accent); font-weight: 600; text-underline-offset: 2px; }
            .fnd-bubble a:hover { opacity: .82; }
            .fnd-greeting-hours {
              display: block;
              background: rgba(4,173,147,.08);
              border: 1px solid rgba(4,173,147,.22);
              border-radius: 8px;
              padding: 7px 12px;
              margin: 8px 0;
              font-size: 13px;
              line-height: 1.6;
            }
            .fnd-greeting-btns { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; }
            .fnd-greeting-btn {
              display: inline-flex; align-items: center;
              background: var(--fnd-accent); color: var(--fnd-accent-text) !important;
              text-decoration: none !important;
              padding: 5px 12px; border-radius: 999px;
              font-size: 12px; font-weight: 700;
              transition: filter .15s; white-space: nowrap;
            }
            .fnd-greeting-btn:hover { filter: brightness(.9); }
            .fnd-inline-actions,
            .fnd-inline-sources { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
            .fnd-inline-btn,
            .fnd-inline-source {
              border: 1px solid var(--fnd-brand, #1e6167);
              background: transparent;
              border-radius: 9999px;
              padding: 6px 12px;
              cursor: pointer;
              font-weight: 600;
              text-decoration: none;
              color: var(--fnd-brand, #1e6167);
              font: inherit;
              transition: background .15s ease, color .15s ease;
            }
            .fnd-inline-btn:hover { background: var(--fnd-brand, #1e6167); color: var(--fnd-accent-text); }
            .fnd-inline-source:hover { background: var(--fnd-brand, #1e6167); color: var(--fnd-accent-text); }
            .fnd-frontdesk__composer {
              display: grid;
              grid-template-columns: 1fr auto;
              gap: 8px;
              padding: 10px;
              background: #fff;
              border-top: 1px solid var(--fnd-border);
              align-items: center;
            }
            .fnd-frontdesk__input,
            .fnd-frontdesk__contact input,
            .fnd-frontdesk__contact textarea {
              background: #f8f9fa;
              border: 1px solid var(--fnd-border);
              border-radius: 10px;
              box-sizing: border-box;
              font: inherit;
            }
            .fnd-frontdesk__input {
              padding: 6px 10px;
              font-size: 13px;
              line-height: 1.3;
              height: 36px;
              min-height: 36px;
            }
            .fnd-frontdesk__send,
            .fnd-btn {
              appearance: none;
              border: 0;
              background: var(--fnd-accent);
              color: var(--fnd-accent-text);
              padding: 0 12px;
              border-radius: 10px;
              font-weight: 700;
              cursor: pointer;
              font-size: 13px;
              height: 36px;
              line-height: 36px;
              font-family: inherit;
            }
            .fnd-frontdesk__send:hover,
            .fnd-btn:hover { background: var(--fnd-accent-hover); }
            .fnd-frontdesk__send:disabled,
            .fnd-btn:disabled {
              cursor: wait;
              opacity: .7;
            }
            .fnd-frontdesk__note { border-top: 1px solid var(--fnd-border); }
            .fnd-frontdesk__noteBtn {
              width: 100%; border: 0;
              background: var(--fnd-contact-btn, var(--fnd-accent));
              color: var(--fnd-contact-btn-text, var(--fnd-accent-text));
              font-weight: 700; padding: 12px 16px; cursor: pointer; font: inherit;
              display: flex; align-items: center; justify-content: center; gap: 8px;
              transition: background .15s, color .15s;
            }
            .fnd-frontdesk__noteBtn:hover,
            .fnd-frontdesk__noteBtn:focus-visible {
              background: var(--fnd-contact-btn-hover, var(--fnd-accent-hover, #038d78));
              color: var(--fnd-contact-btn-text, var(--fnd-accent-text));
              outline: none;
            }
            .fnd-frontdesk__noteBtn[aria-expanded="true"] {
              background: var(--fnd-contact-btn-hover, var(--fnd-accent-hover, #038d78));
            }
            .fnd-chevron { display: inline-block; transition: transform .2s; line-height: 0; }
            .fnd-frontdesk__noteBtn[aria-expanded="true"] .fnd-chevron { transform: rotate(180deg); }
            .fnd-frontdesk__contact {
              padding: 14px;
              border-top: 1px solid var(--fnd-border);
              max-height: 44vh;
              overflow: auto;
            }
            .fnd-frontdesk__contact[hidden] { display: none !important; }
            .fnd-frontdesk__contact .fnd-field { margin-bottom: 12px; }
            .fnd-frontdesk__contact label { display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px; }
            .fnd-frontdesk__contact input,
            .fnd-frontdesk__contact textarea {
              width: 100%; padding: 10px 12px;
              border: 1px solid var(--fnd-border, #e2e8f0); border-radius: 8px;
              font: inherit; font-size: 13px; background: #f8fafc;
              transition: border-color .15s, box-shadow .15s;
            }
            .fnd-frontdesk__contact input:focus,
            .fnd-frontdesk__contact textarea:focus {
              outline: none; border-color: var(--fnd-accent); background: #fff;
              box-shadow: 0 0 0 3px rgba(4,173,147,.12);
            }
            .fnd-frontdesk__contact textarea { min-height: 90px; resize: vertical; }
            .fnd-contact-cancel {
              width: 100%; background: transparent; border: 0; cursor: pointer; font: inherit;
              color: var(--fnd-muted, #64748b); font-size: 12px; padding: 10px 14px;
              text-align: center; border-top: 1px solid var(--fnd-border);
              transition: color .15s, background .15s;
            }
            .fnd-contact-cancel:hover { color: var(--fnd-text, #0f172a); background: #f8fafc; }
            .fnd-status--ok { color: #059669; }
            .fnd-status--err { color: #dc2626; }
            .fnd-status-badge {
              display: inline-block;
              margin-left: 8px;
              padding: 2px 8px;
              border-radius: 9999px;
              font-size: 11px;
              font-weight: 700;
            }
            .fnd-status-online { background: #83BE56; color: #052b27; }
            .fnd-status-offline { background: #DE7450; color: #fff; }
            .fnd-status { font-size: 12px; }
            .fnd-status--ok { color: #166534; }
            .fnd-status--err { color: #b91c1c; }
            .fnd-fab {
              position: fixed;
              right: 24px;
              bottom: 24px;
              width: 56px;
              height: 56px;
              border-radius: 50%;
              display: flex;
              align-items: center;
              justify-content: center;
              background: var(--fnd-accent);
              color: var(--fnd-accent-text);
              border: 0;
              box-shadow: 0 8px 24px rgba(0,0,0,.12);
              cursor: pointer;
              z-index: 2147483647;
              font-size: 22px;
              font-family: inherit;
            }
            .fnd-fab[hidden] { display: none !important; }
            @keyframes fnd-pulse {
              0%   { box-shadow: 0 0 0 0 rgba(var(--fnd-accent-rgb, 4,173,147),.5); }
              70%  { box-shadow: 0 0 0 12px rgba(var(--fnd-accent-rgb, 4,173,147),0); }
              100% { box-shadow: 0 0 0 0 rgba(var(--fnd-accent-rgb, 4,173,147),0); }
            }
            .fnd-fab--pulse { animation: fnd-pulse 1.2s ease-out 3; }
            @media (prefers-reduced-motion: reduce) { .fnd-fab--pulse { animation: none; } }
            .fnd-header-launcher {
              display: inline-flex;
              align-items: center;
              justify-content: center;
              gap: 8px;
              border: 0;
              border-radius: 999px;
              background: var(--fnd-accent, #04ad93);
              color: var(--fnd-accent-text, #fff);
              font: inherit;
              font-weight: 700;
              line-height: 1;
              min-height: 40px;
              padding: 0 16px;
              cursor: pointer;
              box-shadow: 0 6px 18px rgba(0,0,0,.12);
            }
            .fnd-header-launcher:hover { background: var(--fnd-accent-hover, #038d78); }
            .fnd-header-launcher:focus-visible { outline: 3px solid #ffbf47; outline-offset: 3px; }
            .fnd-header-launcher__icon { font-size: 16px; line-height: 1; }
            /* Teaser — desktop grid (photos + status share a row, buttons side by side) */
            .fnd-teaser {
              position: fixed;
              right: 24px; bottom: 92px;
              width: clamp(340px, 52vw, 500px);
              max-width: calc(100vw - 32px);
              background: #fff;
              border-radius: 20px;
              box-shadow: 0 12px 40px rgba(0,0,0,.18), 0 2px 8px rgba(0,0,0,.06);
              padding: 20px;
              z-index: 2147483647;
              display: flex;
              flex-direction: column;
              gap: 14px;
            }
            .fnd-teaser[hidden] { display: none !important; }
            .fnd-teaser .hdr { display: flex; align-items: center; gap: 8px; }
            .fnd-teaser .title { font-weight: 800; font-size: 18px; line-height: 1.25; flex: 1; color: #0f172a; }
            .fnd-teaser .x {
              flex-shrink: 0; background: #f1f5f9; border: 0; border-radius: 50%;
              width: 28px; height: 28px; display: grid; place-items: center;
              cursor: pointer; line-height: 1; font-size: 16px; font-weight: 700;
              color: #64748b; padding: 0; font-family: inherit;
            }
            .fnd-teaser .x:hover { background: #e2e8f0; color: #0f172a; }
            .fnd-teaser .row { display: flex; align-items: center; gap: 10px; }
            .fnd-teaser .stack { display: flex; align-items: center; gap: 8px; }
            .fnd-teaser .stack .dot { width: 9px; height: 9px; background: #22c55e; border-radius: 50%; flex-shrink: 0; box-shadow: 0 0 0 3px rgba(34,197,94,.18); }
            .fnd-teaser .sub { color: #374151; font-size: 14px; font-weight: 600; white-space: nowrap; }
            .fnd-teaser .agents { display: flex; align-items: center; }
            .fnd-teaser .agents img {
              width: 36px; height: 36px; border-radius: 50%;
              border: 2px solid #fff; margin-left: -8px;
              object-fit: cover; display: block; box-shadow: 0 1px 4px rgba(0,0,0,.16);
            }
            .fnd-teaser .agents img:first-child { margin-left: 0; }
            .fnd-teaser .actions { display: flex; flex-direction: row; gap: 8px; }
            .fnd-teaser .btn {
              flex: 1; border: 0; border-radius: 12px; padding: 13px 16px;
              font-weight: 700; font-size: 15px; cursor: pointer; font-family: inherit;
              text-align: center; transition: transform .15s ease, box-shadow .15s ease, background .15s ease;
            }
            .fnd-teaser .btn:hover { transform: translateY(-1px); }
            .fnd-teaser .btn-primary { background: var(--fnd-accent); color: var(--fnd-accent-text); box-shadow: 0 4px 14px rgba(0,0,0,.12); }
            .fnd-teaser .btn-primary:hover { background: var(--fnd-accent-hover); box-shadow: 0 6px 20px rgba(0,0,0,.18); }
            .fnd-teaser .btn-secondary { background: #f1f5f9; color: #374151; border: 1px solid #e2e8f0; }
            .fnd-teaser .btn-secondary:hover { background: #e2e8f0; }
            .typing-indicator { display: inline-flex; align-items: center; height: 20px; }
            .typing-indicator span {
              display: inline-block;
              width: 8px;
              height: 8px;
              background-color: var(--fnd-muted);
              border-radius: 50%;
              margin-right: 4px;
              animation: typing-bounce 1.4s infinite ease-in-out;
            }
            .typing-indicator span:nth-child(2) { animation-delay: .2s; }
            .typing-indicator span:nth-child(3) { animation-delay: .4s; }
            @keyframes typing-bounce {
              0%,60%,100% { transform: translateY(0); }
              30% { transform: translateY(-5px); }
            }
            @media (max-width: 480px) {
              .fnd-frontdesk:not(.fnd-frontdesk--inline) {
                right: 0; left: 0; bottom: 0; width: 100%;
                border-radius: 20px 20px 0 0;
                max-height: 90vh;
              }
              .fnd-fab { right: 16px; bottom: 16px; }
              .fnd-teaser {
                right: 12px; left: 12px; bottom: 80px;
                width: auto; min-width: 0; gap: 12px;
              }
              .fnd-teaser .actions { flex-direction: column; gap: 8px; }
              .fnd-teaser .btn { flex: none; }
            }
            @media (prefers-reduced-motion: reduce) {
              .fnd-frontdesk *, .fnd-fab, .fnd-teaser * { animation: none !important; transition: none !important; }
            }
        </style>
        <?php
    }

    private static function render_widget(array $args): void {
        $config = Frontdesk_Config::get_all();
        $boot = !empty($args['boot']) && is_array($args['boot']) ? $args['boot'] : Frontdesk_Config::public_boot([
            'displayMode' => !empty($args['inline']) ? 'inline' : 'floating',
            'instance_display' => !empty($args['inline']) ? 'inline' : 'floating',
        ]);

        $offline = Frontdesk_Config::provider() === 'offline'
            || Frontdesk_Config::runtime_mode() === 'offline'
            || Frontdesk_Config::runtime_mode() === 'faq_only'
            || !Frontdesk_Config::has_openai_key();

        $widget_id = 'fnd-frontdesk-' . wp_generate_uuid4();
        $launcher_id = $widget_id . '-launcher';
        $show_header_launcher = !empty($args['header_launcher']) && empty($args['inline']);
        $show_launcher = !empty($args['show_launcher']) && empty($args['inline']) && !$show_header_launcher;
        $has_launcher = $show_launcher || $show_header_launcher;
        $contact_nonce = wp_create_nonce('fnd_frontdesk_contact');
        $chat_nonce = wp_create_nonce('fnd_frontdesk_chat');
        $title = (string) ($args['title'] ?? $boot['copy']['botName']);
        $subtitle = (string) ($args['subtitle'] ?? $boot['copy']['headerByline']);
        $greeting = (string) ($args['greeting'] ?? $boot['copy']['greeting']);
        $greeting_html = (string) ($boot['copy']['greetingHtml'] ?? '');
        $logo_url = self::get_logo_url($config);
        $staff_photos = Frontdesk_Config::staff_photos_array($config['staff_photos'] ?? '[]');
        $hours = (string) ($config['opening_hours'] ?? '');
        $position_style = self::floating_position_style($boot, 'widget', !empty($args['inline']));
        $launcher_style = self::floating_position_style($boot, 'launcher', false);
        $teaser_style = self::floating_position_style($boot, 'teaser', false);
        $title_id = $widget_id . '-title';
        $header_launcher_label = trim((string) ($args['header_launcher_label'] ?? '')) ?: (string) ($boot['copy']['launcherLabel'] ?? __('Open chat', 'foundation-frontdesk'));
        ?>
        <?php if ($show_header_launcher) : ?>
            <button
                id="<?php echo esc_attr($launcher_id); ?>"
                class="fnd-header-launcher"
                type="button"
                aria-haspopup="dialog"
                aria-expanded="false"
                aria-controls="<?php echo esc_attr($widget_id); ?>"
                style="<?php echo esc_attr('--fnd-accent:' . ($boot['ui']['buttonColor'] ?? '#04ad93') . ';--fnd-accent-hover:' . ($boot['ui']['buttonHoverColor'] ?? '#038d78') . ';--fnd-accent-text:' . ($boot['ui']['buttonText'] ?? '#ffffff') . ';'); ?>"
            ><span class="fnd-header-launcher__icon" aria-hidden="true">💬</span><span><?php echo esc_html($header_launcher_label); ?></span></button>
        <?php endif; ?>
        <div
            class="fnd-frontdesk<?php echo !empty($args['inline']) ? ' fnd-frontdesk--inline' : ''; ?>"
            id="<?php echo esc_attr($widget_id); ?>"
            style="<?php
                echo esc_attr(
                    '--fnd-brand:' . ($boot['ui']['headerBg'] ?? '#1e6167') . ';' .
                    '--fnd-accent:' . ($boot['ui']['buttonColor'] ?? '#04ad93') . ';' .
                    '--fnd-accent-hover:' . ($boot['ui']['buttonHoverColor'] ?? '#038d78') . ';' .
                    '--fnd-accent-text:' . ($boot['ui']['buttonText'] ?? '#ffffff') . ';' .
                    '--fnd-body-text:' . ($boot['ui']['textColor'] ?? '#1f2937') . ';' .
                    '--fnd-font:' . ($boot['ui']['fontFamily'] ?? '"Plus Jakarta Sans", ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif') . ';' .
                    ( ($boot['contactBtnColor'] ?? '') !== '' ? '--fnd-contact-btn:' . $boot['contactBtnColor'] . ';--fnd-contact-btn-text:' . ($boot['contactBtnTextColor'] ?? '#ffffff') . ';--fnd-contact-btn-hover:' . self::darken_hex($boot['contactBtnColor'], 12) . ';' : '' ) .
                    ($has_launcher ? '' : 'display:block;') .
                    $position_style
                );
            ?>"
            <?php echo $has_launcher ? 'hidden' : ''; ?>
            role="<?php echo !empty($args['inline']) ? 'region' : 'dialog'; ?>"
            aria-labelledby="<?php echo esc_attr($title_id); ?>"
            <?php echo empty($args['inline']) ? 'aria-modal="false"' : ''; ?>
            data-chat-action="<?php echo esc_attr($boot['chatAction']); ?>"
            data-contact-action="<?php echo esc_attr($boot['contactAction']); ?>"
            data-ajax-url="<?php echo esc_url($boot['ajaxUrl']); ?>"
            data-chat-nonce="<?php echo esc_attr($chat_nonce); ?>"
            data-contact-nonce="<?php echo esc_attr($contact_nonce); ?>"
            data-error-message="<?php echo esc_attr($boot['copy']['errorMessage']); ?>"
            data-contact-label="<?php echo esc_attr($boot['copy']['contactButtonLabel']); ?>"
            data-kb-label="<?php echo esc_attr($boot['copy']['kbButtonLabel']); ?>"
            data-teaser-title="<?php echo esc_attr($boot['copy']['teaserTitle']); ?>"
            data-teaser-body="<?php echo esc_attr($boot['copy']['teaserBody']); ?>"
            data-launcher-label="<?php echo esc_attr($boot['copy']['launcherLabel']); ?>"
            data-input-placeholder="<?php echo esc_attr($boot['copy']['inputPlaceholder']); ?>"
            data-hours="<?php echo esc_attr($hours); ?>"
            data-faqs="<?php echo esc_attr(wp_json_encode($boot['faqs'])); ?>"
            data-recaptcha-key="<?php echo esc_attr($boot['recaptchaSiteKey'] ?? ''); ?>"
        >
            <div class="fnd-frontdesk__header">
                <div class="fnd-frontdesk__brand">
                    <?php echo self::get_logo_html($config); ?>
                    <div>
                        <div class="fnd-frontdesk__title" id="<?php echo esc_attr($title_id); ?>">
                            <?php echo esc_html($title); ?>
                            <?php if (!$offline) : ?>
                            <span class="fnd-status-badge fnd-status-online">
                                <?php esc_html_e('Online', 'foundation-frontdesk'); ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        <div class="fnd-frontdesk__subtitle"><?php echo wp_kses_post($subtitle); ?></div>
                    </div>
                </div>
                <div class="fnd-frontdesk__actions">
                    <button type="button" class="fnd-kb-btn" data-toggle-kb aria-controls="<?php echo esc_attr($widget_id); ?>-kb" aria-expanded="false">
                        <?php echo esc_html($boot['copy']['kbButtonLabel']); ?>
                    </button>
                    <div class="fnd-frontdesk__menu" style="position:relative">
                        <button type="button" class="fnd-menu-btn" aria-haspopup="true" aria-expanded="false" aria-controls="<?php echo esc_attr($widget_id); ?>-menu">
                            <span class="fnd-menu-icon" aria-hidden="true"><span></span><span></span><span></span></span>
                            <span class="screen-reader-text"><?php esc_html_e('Open menu', 'foundation-frontdesk'); ?></span>
                        </button>
                        <div id="<?php echo esc_attr($widget_id); ?>-menu" class="fnd-menu" role="menu" hidden>
                            <button type="button" data-menu-action="save" role="menuitem"><?php esc_html_e('Save transcript', 'foundation-frontdesk'); ?></button>
                        </div>
                    </div>
                    <?php if ($has_launcher) : ?>
                    <button type="button" class="fnd-close-btn" data-widget-close
                            aria-label="<?php esc_attr_e('Close chat', 'foundation-frontdesk'); ?>">
                        <svg width="13" height="13" viewBox="0 0 13 13" fill="none" aria-hidden="true" focusable="false">
                            <path d="M1 1l11 11M12 1L1 12" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
                        </svg>
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <div id="<?php echo esc_attr($widget_id); ?>-kb" class="fnd-kb" role="region" aria-labelledby="<?php echo esc_attr($widget_id); ?>-kb-title" aria-hidden="true">
                <div class="fnd-kb__wrap">
                    <div class="fnd-kb__head">
                        <div id="<?php echo esc_attr($widget_id); ?>-kb-title" class="fnd-kb__title"><?php echo esc_html($boot['copy']['kbButtonLabel']); ?></div>
                        <button type="button" class="fnd-kb__back" data-kb-back><?php esc_html_e('Back to chat', 'foundation-frontdesk'); ?></button>
                    </div>
                    <div class="fnd-kb__search">
                        <input type="search" placeholder="<?php esc_attr_e('Search FAQs…', 'foundation-frontdesk'); ?>" aria-label="<?php esc_attr_e('Search FAQs', 'foundation-frontdesk'); ?>" data-kb-filter>
                    </div>
                    <div class="fnd-kb__list" data-kb-list></div>
                    <div class="screen-reader-text" aria-live="polite" data-kb-status></div>
                </div>
            </div>

            <div class="fnd-frontdesk__body">
                <div class="fnd-frontdesk__messages" data-messages role="log" aria-live="polite" aria-relevant="additions text" aria-label="<?php esc_attr_e('Chat messages', 'foundation-frontdesk'); ?>">
                    <div class="fnd-row-msg" data-role="bot" data-time="<?php echo esc_attr(current_time('H:i')); ?>">
                        <div class="fnd-avatar">
                            <?php if ($logo_url !== '') : ?>
                                <img src="<?php echo esc_url($logo_url); ?>" alt="">
                            <?php else : ?>
                                <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><circle cx="12" cy="8" r="4" fill="currentColor"/><path d="M4 20c0-4 4-6 8-6s8 2 8 6" fill="currentColor"/></svg>
                            <?php endif; ?>
                        </div>
                        <?php if ($greeting_html !== '') : ?>
                        <div class="fnd-bubble fnd-bubble--html"><?php echo wp_kses_post($greeting_html); ?><small class="fnd-stamp"><?php echo esc_html(current_time('H:i')); ?></small></div>
                        <?php else : ?>
                        <div class="fnd-bubble"><?php echo esc_html($greeting); ?><small class="fnd-stamp"><?php echo esc_html(current_time('H:i')); ?></small></div>
                        <?php endif; ?>
                    </div>
                </div>

                <form class="fnd-frontdesk__composer" data-composer>
                    <label class="screen-reader-text" for="<?php echo esc_attr($widget_id); ?>-input"><?php esc_html_e('Type your message', 'foundation-frontdesk'); ?></label>
                    <input id="<?php echo esc_attr($widget_id); ?>-input" class="fnd-frontdesk__input" type="text" placeholder="<?php echo esc_attr($boot['copy']['inputPlaceholder']); ?>" autocomplete="off">
                    <button class="fnd-frontdesk__send" type="submit"><?php esc_html_e('Send', 'foundation-frontdesk'); ?></button>
                </form>

                <?php if (!empty($boot['features']['contact'])) : ?>
                    <div>
                        <div class="fnd-frontdesk__note">
                            <button type="button" class="fnd-frontdesk__noteBtn" data-toggle-contact aria-expanded="false" aria-controls="<?php echo esc_attr($widget_id); ?>-contact">
                                <?php echo esc_html($boot['copy']['contactButtonLabel']); ?>
                                <span class="fnd-chevron" aria-hidden="true"><svg width="12" height="12" viewBox="0 0 24 24" fill="none"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                            </button>
                        </div>

                        <div class="fnd-frontdesk__contact" id="<?php echo esc_attr($widget_id); ?>-contact" data-contact hidden>
                            <form class="fnd-frontdesk__contactForm" data-contact-form method="post" novalidate>
                                <div class="fnd-field">
                                    <label for="<?php echo esc_attr($widget_id); ?>-name"><?php esc_html_e('Your name', 'foundation-frontdesk'); ?></label>
                                    <input id="<?php echo esc_attr($widget_id); ?>-name" type="text" name="name" required placeholder="<?php esc_attr_e('Jane Doe', 'foundation-frontdesk'); ?>">
                                </div>
                                <div class="fnd-field">
                                    <label for="<?php echo esc_attr($widget_id); ?>-email"><?php esc_html_e('Your email', 'foundation-frontdesk'); ?></label>
                                    <input id="<?php echo esc_attr($widget_id); ?>-email" type="email" name="email" required placeholder="you@example.com">
                                </div>
                                <div class="fnd-field">
                                    <label for="<?php echo esc_attr($widget_id); ?>-message"><?php esc_html_e('Message', 'foundation-frontdesk'); ?></label>
                                    <textarea id="<?php echo esc_attr($widget_id); ?>-message" name="message" required placeholder="<?php esc_attr_e('How can we help?', 'foundation-frontdesk'); ?>"></textarea>
                                </div>
                                <input type="hidden" name="page_url" value="<?php echo esc_url(get_permalink() ?: home_url('/')); ?>">
                                <input type="hidden" name="page_title" value="<?php echo esc_attr(wp_get_document_title()); ?>">
                                <input type="text" name="website" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0;pointer-events:none;">
                                <?php if (!empty($boot['recaptchaSiteKey'])) : ?>
                                <input type="hidden" name="recaptcha_token" data-recaptcha-token>
                                <?php endif; ?>
                                <div class="fnd-actions">
                                    <button type="submit" class="fnd-btn" data-contact-send><?php esc_html_e('Send message', 'foundation-frontdesk'); ?></button>
                                    <span class="fnd-status" data-status aria-live="polite"></span>
                                </div>
                            </form>
                            <button type="button" class="fnd-contact-cancel" data-contact-cancel><?php esc_html_e('← Cancel', 'foundation-frontdesk'); ?></button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($show_launcher) : ?>
            <button id="<?php echo esc_attr($launcher_id); ?>" class="fnd-fab" type="button"
                    style="<?php echo esc_attr(
                        $launcher_style .
                        '--fnd-accent:'       . ($boot['ui']['buttonColor']      ?? '#04ad93') . ';' .
                        '--fnd-accent-hover:' . ($boot['ui']['buttonHoverColor'] ?? '#038d78') . ';' .
                        '--fnd-accent-text:'  . ($boot['ui']['buttonText']       ?? '#ffffff') . ';'
                    ); ?>"
                    aria-haspopup="dialog" aria-expanded="false"
                    aria-controls="<?php echo esc_attr($widget_id); ?>"
                    aria-label="<?php echo esc_attr($boot['copy']['launcherLabel']); ?>"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" fill="currentColor"/></svg></button>
            <?php
            $teaser_photos = !empty($staff_photos) ? $staff_photos : ($logo_url !== '' ? [$logo_url] : []);
            ?>
            <div class="fnd-teaser" id="<?php echo esc_attr($launcher_id); ?>-teaser" style="<?php echo esc_attr($teaser_style . '--fnd-accent:' . ($boot['ui']['buttonColor'] ?? '#04ad93') . ';--fnd-accent-hover:' . ($boot['ui']['buttonHoverColor'] ?? '#038d78') . ';--fnd-accent-text:' . ($boot['ui']['buttonText'] ?? '#ffffff') . ';'); ?>" hidden>
                <div class="hdr">
                    <div class="title"><?php echo esc_html($boot['copy']['teaserTitle']); ?></div>
                    <button type="button" class="x" data-teaser-close aria-label="<?php esc_attr_e('Dismiss', 'foundation-frontdesk'); ?>">×</button>
                </div>
                <div class="row">
                    <div class="stack">
                        <span class="dot" aria-hidden="true"></span>
                        <span class="sub"><?php echo esc_html($offline ? __('Here to help', 'foundation-frontdesk') : __('Team is online', 'foundation-frontdesk')); ?></span>
                    </div>
                    <?php if (!empty($teaser_photos)) : ?>
                    <div class="agents" aria-hidden="true">
                        <?php foreach (array_slice($teaser_photos, 0, 4) as $photo_url) : ?>
                            <img src="<?php echo esc_url($photo_url); ?>" alt="">
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="actions">
                    <button type="button" class="btn btn-primary" data-teaser-action="open"><?php echo esc_html($boot['copy']['launcherLabel']); ?></button>
                    <button type="button" class="btn btn-secondary" data-teaser-action="kb"><?php echo esc_html($boot['copy']['kbButtonLabel']); ?></button>
                </div>
            </div>
        <?php endif; ?>

        <script>
        (function() {
            var root = document.getElementById(<?php echo wp_json_encode($widget_id); ?>);
            if (!root || root.dataset.bound === '1') return;
            root.dataset.bound = '1';

            var hasLauncher = <?php echo $has_launcher ? 'true' : 'false'; ?>;
            var launcherIsHeader = <?php echo $show_header_launcher ? 'true' : 'false'; ?>;
            var form = root.querySelector('[data-composer]');
            var input = form ? form.querySelector('.fnd-frontdesk__input') : null;
            var msgs = root.querySelector('[data-messages]');
            var launch = hasLauncher ? document.getElementById(<?php echo wp_json_encode($launcher_id); ?>) : null;
            var teaser = hasLauncher ? document.getElementById(<?php echo wp_json_encode($launcher_id . '-teaser'); ?>) : null;
            var noteBtn = root.querySelector('[data-toggle-contact]');
            var cancelBtn = root.querySelector('[data-contact-cancel]');
            var drawer = root.querySelector('[data-contact]');
            var cform = root.querySelector('[data-contact-form]');
            var csend = root.querySelector('[data-contact-send]');
            var cstat = root.querySelector('[data-status]');
            var recaptchaKey = root.getAttribute('data-recaptcha-key') || '';
            var closeBtn = root.querySelector('[data-widget-close]');
            var menuBtn = root.querySelector('.fnd-menu-btn');
            var menu = root.querySelector('.fnd-menu');
            var header = root.querySelector('.fnd-frontdesk__header');
            var kbBtn = root.querySelector('[data-toggle-kb]');
            var kb = document.getElementById(<?php echo wp_json_encode($widget_id . '-kb'); ?>);
            var kbBack = kb ? kb.querySelector('[data-kb-back]') : null;
            var kbList = kb ? kb.querySelector('[data-kb-list]') : null;
            var kbFilter = kb ? kb.querySelector('[data-kb-filter]') : null;
            var kbStatus = kb ? kb.querySelector('[data-kb-status]') : null;

            var ajaxUrl = root.getAttribute('data-ajax-url');
            var chatAction = root.getAttribute('data-chat-action');
            var contactAction = root.getAttribute('data-contact-action');
            var chatNonce = root.getAttribute('data-chat-nonce');
            var contactNonce = root.getAttribute('data-contact-nonce');
            var errorMessage = root.getAttribute('data-error-message') || 'Sorry, something went wrong.';
            var hoursText = root.getAttribute('data-hours') || '';
            var faqs = [];
            var sending = false;
            try { faqs = JSON.parse(root.getAttribute('data-faqs') || '[]') || []; } catch (e) { faqs = []; }

            function buildAvatarSvg() {
                var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                svg.setAttribute('viewBox', '0 0 24 24');
                svg.setAttribute('width', '18');
                svg.setAttribute('height', '18');
                svg.setAttribute('aria-hidden', 'true');
                var circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
                circle.setAttribute('cx', '12');
                circle.setAttribute('cy', '8');
                circle.setAttribute('r', '4');
                circle.setAttribute('fill', 'currentColor');
                var path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                path.setAttribute('d', 'M4 20c0-4 4-6 8-6s8 2 8 6');
                path.setAttribute('fill', 'currentColor');
                svg.appendChild(circle);
                svg.appendChild(path);
                return svg;
            }

            function timeNow() {
                try { return new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }); }
                catch (e) { return '—:—'; }
            }

            function createAvatarNode(type) {
                var src = <?php echo wp_json_encode($logo_url !== '' ? esc_url($logo_url) : null); ?>;
                var avatar = document.createElement('div');
                avatar.className = 'fnd-avatar';
                if (type === 'bot' && src) {
                    var img = document.createElement('img');
                    img.src = src;
                    img.alt = '';
                    avatar.appendChild(img);
                    return avatar;
                }
                avatar.appendChild(buildAvatarSvg());
                return avatar;
            }

            function addUserMsg(text) {
                var row = document.createElement('div');
                row.className = 'fnd-row-msg fnd-row-msg--user';
                var bubble = document.createElement('div');
                bubble.className = 'fnd-bubble';
                bubble.textContent = text;
                var stamp = document.createElement('small');
                stamp.className = 'fnd-stamp';
                stamp.textContent = timeNow();
                bubble.appendChild(stamp);
                var av = createAvatarNode('user');
                row.appendChild(bubble);
                row.appendChild(av);
                msgs.appendChild(row);
                msgs.scrollTop = msgs.scrollHeight;
                return row;
            }

            function appendSources(bubble, sources) {
                if (!Array.isArray(sources) || !sources.length) return;
                var wrap = document.createElement('div');
                wrap.className = 'fnd-inline-sources';
                sources.forEach(function(source) {
                    if (!source || !source.title) return;
                    var node = document.createElement(source.url ? 'a' : 'span');
                    node.className = 'fnd-inline-source';
                    node.textContent = source.title;
                    if (source.url) {
                        node.href = source.url;
                        node.target = '_blank';
                        node.rel = 'noopener';
                    }
                    wrap.appendChild(node);
                });
                if (wrap.childElementCount) bubble.appendChild(wrap);
            }

            function appendActions(bubble, actions) {
                if (!Array.isArray(actions) || !actions.length) return;
                var wrap = document.createElement('div');
                wrap.className = 'fnd-inline-actions';
                actions.forEach(function(action) {
                    if (!action || !action.label) return;
                    var button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'fnd-inline-btn';
                    button.textContent = action.label;
                    button.dataset.actionType = action.type || '';
                    wrap.appendChild(button);
                });
                if (wrap.childElementCount) bubble.appendChild(wrap);
            }

            function addBotMsg(text, response) {
                var row = document.createElement('div');
                row.className = 'fnd-row-msg';
                var av = createAvatarNode('bot');
                var bubble = document.createElement('div');
                bubble.className = 'fnd-bubble';
                if (response && response.error_code === 'offline_no_match') {
                    bubble.classList.add('fnd-bubble--fallback');
                }
                bubble.textContent = text;
                appendSources(bubble, response && response.sources ? response.sources : []);
                appendActions(bubble, response && response.actions ? response.actions : []);
                var stamp = document.createElement('small');
                stamp.className = 'fnd-stamp';
                stamp.textContent = timeNow();
                bubble.appendChild(stamp);
                row.appendChild(av);
                row.appendChild(bubble);
                msgs.appendChild(row);
                msgs.scrollTop = msgs.scrollHeight;
                return row;
            }

            function typingBubble() {
                var row = document.createElement('div');
                row.className = 'fnd-row-msg';
                var bubble = document.createElement('div');
                bubble.className = 'fnd-bubble';
                var indicator = document.createElement('div');
                indicator.className = 'typing-indicator';
                for (var i = 0; i < 3; i++) {
                    indicator.appendChild(document.createElement('span'));
                }
                bubble.appendChild(indicator);
                row.appendChild(createAvatarNode('bot'));
                row.appendChild(bubble);
                msgs.appendChild(row);
                msgs.scrollTop = msgs.scrollHeight;
                return row;
            }

            function openWidget() {
                if (!hasLauncher) return;
                root.hidden = false;
                if (launch) {
                    if (!launcherIsHeader) launch.hidden = true;
                    launch.setAttribute('aria-expanded', 'true');
                }
                hideTeaserImmediate();
                if (input) setTimeout(function() { input.focus(); }, 10);
            }

            function closeWidget() {
                if (!hasLauncher) return;
                root.hidden = true;
                if (launch) {
                    if (!launcherIsHeader) launch.hidden = false;
                    launch.setAttribute('aria-expanded', 'false');
                    launch.focus();
                }
                if (drawer && !drawer.hidden) {
                    drawer.hidden = true;
                    if (noteBtn) noteBtn.setAttribute('aria-expanded', 'false');
                }
                hideMenu();
                kbClose();
            }

            function kbHidden() {
                return !kb || kb.getAttribute('aria-hidden') !== 'false';
            }

            function buildKB(items) {
                if (!kbList) return;
                while (kbList.firstChild) kbList.removeChild(kbList.firstChild);
                if (!Array.isArray(items) || !items.length) {
                    var empty = document.createElement('div');
                    empty.className = 'fnd-kb__item';
                    empty.textContent = <?php echo wp_json_encode(__('No FAQs yet.', 'foundation-frontdesk')); ?>;
                    kbList.appendChild(empty);
                    return;
                }
                items.forEach(function(faq, i) {
                    var item = document.createElement('div');
                    item.className = 'fnd-kb__item';
                    var qId = 'kbq-' + i + '-' + Math.random().toString(36).slice(2);
                    var aId = 'kba-' + i + '-' + Math.random().toString(36).slice(2);
                    var qButton = document.createElement('button');
                    qButton.type = 'button';
                    qButton.className = 'fnd-kb__q';
                    qButton.id = qId;
                    qButton.setAttribute('aria-expanded', 'false');
                    qButton.setAttribute('aria-controls', aId);

                    var qText = document.createElement('span');
                    qText.textContent = faq.q || 'FAQ';
                    var qCaret = document.createElement('span');
                    qCaret.className = 'fnd-kb__caret';
                    qCaret.setAttribute('aria-hidden', 'true');
                    qCaret.textContent = '▾';
                    qButton.appendChild(qText);
                    qButton.appendChild(qCaret);

                    var answer = document.createElement('div');
                    answer.className = 'fnd-kb__a';
                    answer.id = aId;
                    answer.hidden = true;

                    var answerText = document.createElement('div');
                    answerText.textContent = faq.a || '';
                    answer.appendChild(answerText);

                    if (faq.url) {
                        try {
                            var faqUrl = new URL(faq.url, window.location.origin);
                            var learnMoreWrap = document.createElement('div');
                            learnMoreWrap.style.marginTop = '6px';
                            var learnMoreLink = document.createElement('a');
                            learnMoreLink.href = faqUrl.toString();
                            learnMoreLink.target = '_blank';
                            learnMoreLink.rel = 'noopener';
                            learnMoreLink.textContent = <?php echo wp_json_encode(__('Learn more', 'foundation-frontdesk')); ?>;
                            learnMoreWrap.appendChild(learnMoreLink);
                            answer.appendChild(learnMoreWrap);
                        } catch (err) {}
                    }

                    item.appendChild(qButton);
                    item.appendChild(answer);
                    kbList.appendChild(item);
                });
            }

            function kbOpen() {
                if (!kb) return;
                if (kbList && !kbList.childElementCount) buildKB(faqs);
                if (header) {
                    kb.style.top = (header.offsetTop + header.offsetHeight) + 'px';
                }
                kb.style.left = '0';
                kb.style.right = '0';
                kb.style.bottom = '0';
                kb.style.position = 'absolute';
                kb.setAttribute('aria-hidden', 'false');
                if (kbBtn) kbBtn.setAttribute('aria-expanded', 'true');
                if (drawer && !drawer.hidden) {
                    drawer.hidden = true;
                    if (noteBtn) noteBtn.setAttribute('aria-expanded', 'false');
                }
                if (kbFilter) kbFilter.focus();
            }

            function kbClose() {
                if (!kb) return;
                kb.setAttribute('aria-hidden', 'true');
                if (kbBtn) kbBtn.setAttribute('aria-expanded', 'false');
                if (input) input.focus();
            }

            function hideMenu() {
                if (menu) {
                    menu.hidden = true;
                    if (menuBtn) menuBtn.setAttribute('aria-expanded', 'false');
                }
            }

            function toggleContact(forceOpen) {
                if (!noteBtn || !drawer) return;
                var isExpanded = noteBtn.getAttribute('aria-expanded') === 'true';
                var nextState = typeof forceOpen === 'boolean' ? forceOpen : !isExpanded;
                noteBtn.setAttribute('aria-expanded', nextState ? 'true' : 'false');
                drawer.hidden = !nextState;
                if (nextState) {
                    kbClose();
                    var first = drawer.querySelector('input, textarea');
                    if (first) setTimeout(function() { first.focus(); }, 80);
                }
            }

            var hideTimer = null;
            function showTeaserSticky() {
                if (!teaser) return;
                teaser.hidden = false;
                if (hideTimer) {
                    clearTimeout(hideTimer);
                    hideTimer = null;
                }
            }
            function scheduleHideTeaser() {
                if (!teaser) return;
                if (hideTimer) clearTimeout(hideTimer);
                hideTimer = setTimeout(function() {
                    var overFab = launch && (launch.matches(':hover') || launch === document.activeElement);
                    var overTeaser = teaser && (teaser.matches(':hover') || teaser.contains(document.activeElement));
                    if (!overFab && !overTeaser) teaser.hidden = true;
                }, 220);
            }
            function hideTeaserImmediate() {
                if (!teaser) return;
                if (hideTimer) clearTimeout(hideTimer);
                teaser.hidden = true;
            }

            async function sendChat(message) {
                sending = true;
                if (form) form.setAttribute('aria-busy', 'true');
                var sendButton = form ? form.querySelector('.fnd-frontdesk__send') : null;
                if (sendButton) sendButton.disabled = true;
                if (input) input.disabled = true;
                var typing = typingBubble();
                try {
                    var body = new URLSearchParams();
                    body.append('action', chatAction);
                    body.append('nonce', chatNonce);
                    body.append('message', message);
                    body.append('page_url', window.location.href);
                    body.append('page_title', document.title);
                    body.append('instance_id', root.id);
                    body.append('frontdesktion_id', root.id + '-' + Date.now());
                    var res = await fetch(ajaxUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                        body: body.toString()
                    });
                    var data = await res.json();
                    try { msgs.removeChild(typing); } catch (e) {}
                    if (data && data.answer) {
                        addBotMsg(data.answer, data);
                    } else {
                        addBotMsg(errorMessage, null);
                    }
                } catch (e) {
                    try { msgs.removeChild(typing); } catch (_) {}
                    addBotMsg(errorMessage, null);
                } finally {
                    sending = false;
                    if (form) form.removeAttribute('aria-busy');
                    if (sendButton) sendButton.disabled = false;
                    if (input) {
                        input.disabled = false;
                        input.focus();
                    }
                }
            }

            if (form && input && msgs) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    if (sending) return;
                    var text = (input.value || '').trim();
                    if (!text) return;
                    addUserMsg(text);
                    input.value = '';
                    sendChat(text);
                });
            }

            msgs.addEventListener('click', function(e) {
                var button = e.target.closest('.fnd-inline-btn');
                if (!button) return;
                var type = button.getAttribute('data-action-type');
                if (type === 'contact') {
                    toggleContact(true);
                    return;
                }
                if (type === 'faq') {
                    kbOpen();
                    return;
                }
                if (type === 'hours' && hoursText) {
                    addBotMsg(hoursText, null);
                }
            });

            if (kbBtn) kbBtn.addEventListener('click', function() { if (kbHidden()) kbOpen(); else kbClose(); });
            if (kbBack) kbBack.addEventListener('click', kbClose);
            if (kb && kbFilter) {
                window.addEventListener('resize', function() {
                    if (!kbHidden() && header) kb.style.top = (header.offsetTop + header.offsetHeight) + 'px';
                });
                kbFilter.addEventListener('input', function() {
                    var q = (kbFilter.value || '').toLowerCase().trim();
                    var filtered = !q ? faqs : faqs.filter(function(f) {
                        return String(f.q || '').toLowerCase().indexOf(q) !== -1 || String(f.a || '').toLowerCase().indexOf(q) !== -1;
                    });
                    buildKB(filtered);
                    if (kbStatus) kbStatus.textContent = filtered.length + ' <?php echo esc_js(__('result(s)', 'foundation-frontdesk')); ?>';
                });
                kb.addEventListener('click', function(e) {
                    var qbtn = e.target.closest('.fnd-kb__q');
                    if (!qbtn) return;
                    var ans = document.getElementById(qbtn.getAttribute('aria-controls'));
                    var exp = qbtn.getAttribute('aria-expanded') === 'true';
                    qbtn.setAttribute('aria-expanded', exp ? 'false' : 'true');
                    if (ans) ans.hidden = exp;
                });
                kb.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        e.stopPropagation();
                        kbClose();
                    }
                });
            }

            if (closeBtn) closeBtn.addEventListener('click', closeWidget);
            if (noteBtn) noteBtn.addEventListener('click', function() { toggleContact(); });
            if (cancelBtn) cancelBtn.addEventListener('click', function() { toggleContact(false); });

            if (cform && csend) {
                cform.addEventListener('submit', function(e) {
                    e.preventDefault();
                    var doSend = function(token) {
                        var fd = new FormData(cform);
                        fd.set('action', contactAction);
                        fd.set('nonce', contactNonce);
                        if (token) {
                            fd.set('recaptcha_token', token);
                            var tokenInput = cform.querySelector('[data-recaptcha-token]');
                            if (tokenInput) tokenInput.value = token;
                        }
                        var params = new URLSearchParams(fd);
                        csend.disabled = true;
                        csend.textContent = <?php echo wp_json_encode(__('Sending...', 'foundation-frontdesk')); ?>;
                        if (cstat) { cstat.textContent = ''; cstat.className = 'fnd-status'; }
                        fetch(ajaxUrl, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                            body: params.toString()
                        })
                        .then(function(r) { return r.json(); })
                        .then(function(data) {
                            var ok = !!(data && data.ok);
                            var msg = data && data.message ? data.message : errorMessage;
                            if (cstat) {
                                cstat.textContent = msg;
                                cstat.className = 'fnd-status ' + (ok ? 'fnd-status--ok' : 'fnd-status--err');
                            }
                            if (ok) { cform.reset(); setTimeout(function() { toggleContact(false); }, 2200); }
                        })
                        .catch(function() {
                            if (cstat) {
                                cstat.textContent = errorMessage;
                                cstat.className = 'fnd-status fnd-status--err';
                            }
                        })
                        .finally(function() {
                            csend.disabled = false;
                            csend.textContent = <?php echo wp_json_encode(__('Send message', 'foundation-frontdesk')); ?>;
                        });
                    };
                    if (recaptchaKey && typeof grecaptcha !== 'undefined') {
                        grecaptcha.ready(function() {
                            grecaptcha.execute(recaptchaKey, {action: 'contact'})
                                .then(doSend)
                                .catch(function() { doSend(''); });
                        });
                    } else {
                        doSend('');
                    }
                });
            }

            if (menuBtn && menu) {
                menuBtn.addEventListener('click', function() {
                    var isOpen = menu.hidden === false;
                    menu.hidden = isOpen;
                    menuBtn.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
                });
                document.addEventListener('click', function(e) {
                    if (!menu.contains(e.target) && !menuBtn.contains(e.target)) hideMenu();
                });
                menu.addEventListener('click', function(e) {
                    var action = e.target.closest('button');
                    if (!action) return;
                    var type = action.getAttribute('data-menu-action');
                    if (type === 'close') closeWidget();
                    if (type === 'save') {
                        var rows = msgs.querySelectorAll('.fnd-row-msg');
                        var out = [];
                        rows.forEach(function(r) {
                            var role = r.classList.contains('fnd-row-msg--user') ? 'User' : 'Bot';
                            var bubble = r.querySelector('.fnd-bubble');
                            if (!bubble) return;
                            var clone = bubble.cloneNode(true);
                            clone.querySelectorAll('.fnd-stamp, .fnd-inline-actions, .fnd-inline-sources').forEach(function(n) { n.remove(); });
                            out.push(role + ': ' + (clone.textContent || '').trim());
                        });
                        var blob = new Blob([out.join('\\n')], { type: 'text/plain' });
                        var link = document.createElement('a');
                        link.href = URL.createObjectURL(blob);
                        link.download = 'frontdesk-transcript.txt';
                        link.click();
                        setTimeout(function() { URL.revokeObjectURL(link.href); }, 2000);
                    }
                });
            }

            if (hasLauncher && launch) {
                launch.addEventListener('click', function() {
                    if (launcherIsHeader && root.hidden === false) closeWidget();
                    else openWidget();
                });
                if (teaser) {
                    launch.addEventListener('mouseenter', showTeaserSticky);
                    launch.addEventListener('focus', showTeaserSticky);
                    launch.addEventListener('mouseleave', scheduleHideTeaser);
                    launch.addEventListener('blur', scheduleHideTeaser);
                    teaser.addEventListener('mouseenter', showTeaserSticky);
                    teaser.addEventListener('mouseleave', scheduleHideTeaser);
                    teaser.addEventListener('click', function(e) {
                        if (e.target.closest('[data-teaser-close]')) { hideTeaserImmediate(); return; }
                        var action = e.target.closest('[data-teaser-action]');
                        if (!action) return;
                        if (action.getAttribute('data-teaser-action') === 'open') openWidget();
                        if (action.getAttribute('data-teaser-action') === 'kb') { openWidget(); kbOpen(); }
                    });
                }
                setTimeout(function() {
                    launch.classList.add('fnd-fab--pulse');
                    setTimeout(function() { launch.classList.remove('fnd-fab--pulse'); }, 4000);
                }, 800);
            }

            if (hasLauncher) {
                document.addEventListener('click', function(e) {
                    if (root.hidden) return;
                    if (root.contains(e.target)) return;
                    if (launch && launch.contains(e.target)) return;
                    if (teaser && teaser.contains(e.target)) return;
                    closeWidget();
                });
            }

            root.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    e.stopPropagation();
                    if (!kbHidden()) kbClose();
                    else closeWidget();
                }
            });
        })();
        </script>
        <?php if (!empty($boot['recaptchaSiteKey'])) : ?>
        <script src="https://www.google.com/recaptcha/api.js?render=<?php echo esc_attr($boot['recaptchaSiteKey']); ?>" async defer></script>
        <?php endif; ?>
        <?php
    }
}
endif;
