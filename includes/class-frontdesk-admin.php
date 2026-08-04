<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'Foundation_Frontdesk_Admin' ) ) :

class Foundation_Frontdesk_Admin {

    const PAGE_SLUG    = 'foundation-frontdesk-settings';
    const PARENT_SLUG  = 'foundation-by-inkfire';
    const SAVE_ACTION  = 'fnd_save_settings';
    const SAVE_NONCE   = 'fnd_save_settings_nonce';

    public static function init(): void {
        add_action( 'admin_menu',                                 [ __CLASS__, 'register_menu' ] );
        add_action( 'admin_init',                                 [ __CLASS__, 'register_settings_api' ] );
        add_action( 'admin_post_' . self::SAVE_ACTION,           [ __CLASS__, 'handle_save' ] );
        add_action( 'admin_post_frontdesk_rebuild_knowledge',     [ __CLASS__, 'handle_rebuild_knowledge' ] );
        add_action( 'admin_enqueue_scripts',                      [ __CLASS__, 'enqueue_page_assets' ] );
    }

    public static function enqueue_page_assets( string $hook ): void {
        if ( str_contains( $hook, self::PAGE_SLUG ) ) {
            wp_enqueue_media();
        }
    }

    // ─── Menu ────────────────────────────────────────────────────────────────

    public static function register_menu(): void {
        global $admin_page_hooks;

        if ( empty( $admin_page_hooks[ self::PARENT_SLUG ] ) ) {
            add_menu_page(
                __( 'Foundation', 'foundation-frontdesk' ),
                __( 'Foundation', 'foundation-frontdesk' ),
                'manage_options',
                self::PARENT_SLUG,
                '__return_null',
                'dashicons-format-chat',
                12
            );
            remove_submenu_page( self::PARENT_SLUG, self::PARENT_SLUG );
        }

        add_submenu_page(
            self::PARENT_SLUG,
            __( 'Frontdesk AI', 'foundation-frontdesk' ),
            __( 'Frontdesk AI', 'foundation-frontdesk' ),
            'manage_options',
            self::PAGE_SLUG,
            [ __CLASS__, 'render' ]
        );
    }

    public static function register_settings_api(): void {
        register_setting( 'fnd_frontdesk_group', Frontdesk_Config::OPTION_KEY, [
            'sanitize_callback' => [ 'Frontdesk_Config', 'sanitize_for_option' ],
        ] );
    }

    // ─── Page ────────────────────────────────────────────────────────────────

    public static function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'foundation-frontdesk' ) );
        }

        $v        = Frontdesk_Config::admin_view();
        $presets  = Frontdesk_Config::style_presets();
        $faqs_len = strlen( (string) $v['faqs_json'] );
        $faq_edit = isset( $_GET['faq_edit'] ) && sanitize_key( (string) wp_unslash( $_GET['faq_edit'] ) ) === '1';

        // Flash notices from redirects
        $saved     = ! empty( $_GET['frontdesk_saved'] );
        $test_ok   = isset( $_GET['frontdesk_test'] ) && sanitize_key( (string) wp_unslash( $_GET['frontdesk_test'] ) ) === 'ok';
        $test_fail = isset( $_GET['frontdesk_test'] ) && sanitize_key( (string) wp_unslash( $_GET['frontdesk_test'] ) ) === 'fail';
        $flash_msg = ! empty( $_GET['frontdesk_message'] ) ? rawurldecode( sanitize_text_field( (string) wp_unslash( $_GET['frontdesk_message'] ) ) ) : '';
        $k_queued  = ! empty( $_GET['frontdesk_knowledge'] );

        // Should the wizard auto-open?
        $auto_wizard = get_option( 'fnd_frontdesk_wizard_open' ) === '1';
        if ( $auto_wizard ) {
            delete_option( 'fnd_frontdesk_wizard_open' );
        }
        ?>

        <div class="fnd-admin-page" id="fnd-settings-root">

        <?php /* ── Inline styles ───────────────────────────────────────────── */ ?>
        <style>
        /* ── Reset / base ──────────────────────────────────────────── */
        .fnd-admin-page *{box-sizing:border-box}
        .fnd-admin-page{--c-bg:#f0f2f5;--c-surface:#ffffff;--c-border:#e0e3e8;--c-text:#1a1d23;
          --c-muted:#64748b;--c-accent:#04ad93;--c-accent-dark:#028870;--c-red:#dc2626;
          --c-amber:#d97706;--c-green:#059669;--radius:14px;--shadow:0 2px 8px rgba(0,0,0,.07);
          background:var(--c-bg);min-height:100vh;padding:28px 28px 60px;
          font:14px/1.55 "Plus Jakarta Sans",system-ui,sans-serif;color:var(--c-text)}

        /* ── Page header ────────────────────────────────────────────── */
        .fnd-page-head{display:flex;align-items:flex-start;justify-content:space-between;
          flex-wrap:wrap;gap:14px;margin-bottom:24px}
        .fnd-page-head h1{margin:0;font-size:22px;font-weight:800;color:var(--c-text)}
        .fnd-page-head p{margin:4px 0 0;color:var(--c-muted);font-size:13px}
        .fnd-head-actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap}

        /* Status pills */
        .fnd-pills{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:20px}
        .fnd-pill{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:999px;
          font-size:11px;font-weight:700;letter-spacing:.03em}
        .fnd-pill--ok{background:#d1fae5;color:#065f46}
        .fnd-pill--warn{background:#fef3c7;color:#92400e}
        .fnd-pill--info{background:#e0f2fe;color:#0c4a6e}
        .fnd-pill--neutral{background:#f1f5f9;color:#475569}
        .fnd-pill::before{content:'';width:6px;height:6px;border-radius:50%;background:currentColor}

        /* ── Flash notices ──────────────────────────────────────────── */
        .fnd-notice{display:flex;align-items:center;gap:10px;padding:12px 16px;border-radius:var(--radius);
          margin-bottom:18px;font-size:13px;font-weight:500}
        .fnd-notice--ok{background:#d1fae5;color:#065f46}
        .fnd-notice--warn{background:#fef3c7;color:#92400e}
        .fnd-notice--info{background:#e0f2fe;color:#0c4a6e}

        /* ── Two-column shell ───────────────────────────────────────── */
        .fnd-shell{display:grid;grid-template-columns:minmax(0,1.35fr) 360px;gap:20px;align-items:start}
        @media(max-width:1140px){.fnd-shell{grid-template-columns:1fr}}

        /* ── Cards ──────────────────────────────────────────────────── */
        .fnd-card{background:var(--c-surface);border:1px solid var(--c-border);
          border-radius:var(--radius);margin-bottom:16px;overflow:hidden;
          box-shadow:var(--shadow)}
        .fnd-card__head{padding:16px 20px 0;border-bottom:1px solid var(--c-border);
          padding-bottom:13px;display:flex;align-items:center;justify-content:space-between}
        .fnd-card__head h2{margin:0;font-size:13px;font-weight:700;text-transform:uppercase;
          letter-spacing:.07em;color:var(--c-muted)}
        .fnd-card__body{padding:18px 20px}
        .fnd-card__body + .fnd-card__body{border-top:1px solid var(--c-border);padding-top:16px}

        /* ── Form rows ──────────────────────────────────────────────── */
        .fnd-grid{display:grid;gap:14px}
        .fnd-grid-2{grid-template-columns:1fr 1fr}
        .fnd-grid-3{grid-template-columns:1fr 1fr 1fr}
        @media(max-width:640px){.fnd-grid-2,.fnd-grid-3{grid-template-columns:1fr}}
        .fnd-field{display:grid;gap:5px}
        .fnd-label{font-size:12px;font-weight:700;color:var(--c-text)}
        .fnd-desc{font-size:11px;color:var(--c-muted);margin-top:2px}
        .fnd-input,.fnd-select,.fnd-textarea{width:100%;padding:9px 12px;border:1px solid var(--c-border);
          border-radius:9px;font-size:13px;color:var(--c-text);background:#fafbfc;
          font-family:inherit;transition:border-color .15s}
        .fnd-input:focus,.fnd-select:focus,.fnd-textarea:focus{outline:none;border-color:var(--c-accent);
          box-shadow:0 0 0 3px rgba(4,173,147,.15)}
        .fnd-textarea{min-height:80px;resize:vertical}
        .fnd-color-row{display:grid;grid-template-columns:auto 1fr;gap:8px;align-items:center}
        .fnd-color-swatch{width:38px;height:38px;border-radius:8px;border:1px solid var(--c-border);cursor:pointer;padding:0}
        .fnd-check-row{display:flex;align-items:center;gap:8px;padding:4px 0}
        .fnd-check-row label{font-size:13px;font-weight:500;cursor:pointer}
        .fnd-check-row input[type=checkbox]{width:15px;height:15px;accent-color:var(--c-accent)}

        /* Preset tiles */
        .fnd-presets{display:flex;gap:8px;flex-wrap:wrap;margin-top:6px}
        .fnd-preset-tile{flex:1;min-width:100px;border:2px solid var(--c-border);border-radius:10px;
          padding:10px 12px;cursor:pointer;text-align:center;transition:border-color .15s;background:#fafbfc}
        .fnd-preset-tile.is-active{border-color:var(--c-accent);background:#f0fdf9}
        .fnd-preset-tile__swatch{height:22px;border-radius:5px;margin-bottom:6px}
        .fnd-preset-tile__name{font-size:11px;font-weight:700;color:var(--c-text)}

        /* ── Buttons ────────────────────────────────────────────────── */
        .fnd-btn{display:inline-flex;align-items:center;gap:6px;padding:9px 16px;border-radius:9px;
          font-size:13px;font-weight:700;border:none;cursor:pointer;font-family:inherit;
          transition:filter .15s,background .15s}
        .fnd-btn:focus-visible{outline:3px solid var(--c-accent);outline-offset:2px}
        .fnd-btn--primary{background:var(--c-accent);color:#fff}
        .fnd-btn--primary:hover{filter:brightness(.92)}
        .fnd-btn--secondary{background:#f1f5f9;color:var(--c-text);border:1px solid var(--c-border)}
        .fnd-btn--secondary:hover{background:#e7ecf2}
        .fnd-btn--ghost{background:transparent;color:var(--c-muted);border:1px solid var(--c-border)}
        .fnd-btn--ghost:hover{background:#f8fafc}
        .fnd-btn--danger{background:#fee2e2;color:#991b1b;border:1px solid #fecaca}
        .fnd-btn--sm{padding:6px 12px;font-size:12px}

        /* ── Divider ────────────────────────────────────────────────── */
        .fnd-divider{height:1px;background:var(--c-border);margin:16px 0}

        /* ── Advanced accordion ─────────────────────────────────────── */
        .fnd-details{border:1px solid var(--c-border);border-radius:var(--radius);overflow:hidden;margin-bottom:16px}
        .fnd-details summary{padding:14px 20px;font-size:12px;font-weight:700;text-transform:uppercase;
          letter-spacing:.07em;color:var(--c-muted);cursor:pointer;list-style:none;background:#fafbfc;
          display:flex;align-items:center;justify-content:space-between}
        .fnd-details summary::after{content:'▾';font-size:14px;transition:transform .2s}
        .fnd-details[open] summary::after{transform:rotate(180deg)}
        .fnd-details__body{padding:18px 20px;background:var(--c-surface);display:grid;gap:14px}

        /* ── Sticky preview sidebar ─────────────────────────────────── */
        .fnd-sidebar{position:sticky;top:32px}
        .fnd-preview-wrap{border-radius:var(--radius);overflow:hidden;background:#f0f2f5;
          border:1px solid var(--c-border)}

        /* Mini chatbox preview */
        .fnd-chat-preview{font-family:"Plus Jakarta Sans",system-ui,sans-serif;
          display:flex;flex-direction:column;max-height:480px}
        .fnd-cp-header{padding:13px 16px;display:flex;align-items:center;justify-content:space-between;gap:10px}
        .fnd-cp-header-left{display:flex;align-items:center;gap:10px}
        .fnd-cp-logo{width:34px;height:34px;border-radius:8px;background:rgba(255,255,255,.2);
          display:grid;place-items:center;flex-shrink:0}
        .fnd-cp-name{font-weight:800;font-size:14px}
        .fnd-cp-byline{font-size:11px;opacity:.85}
        .fnd-cp-badge{padding:4px 8px;border-radius:999px;font-size:10px;font-weight:700;
          background:#ee744d;color:#fff}
        .fnd-cp-actions{display:flex;gap:6px}
        .fnd-cp-kbbtn{padding:6px 10px;border-radius:8px;border:1px solid rgba(255,255,255,.25);
          font-size:11px;font-weight:700;background:rgba(255,255,255,.12);color:inherit}
        .fnd-cp-menubtn{width:28px;height:28px;border-radius:7px;border:1px solid rgba(255,255,255,.25);
          background:rgba(255,255,255,.12);display:grid;place-items:center}
        .fnd-cp-body{padding:14px 16px;flex:1;overflow:auto;background:#f8fafc;min-height:120px}
        .fnd-cp-bubble{background:#fff;border:1px solid #e8ecf0;border-radius:12px;
          padding:12px 14px;color:#0f172a;font-size:13px;max-width:90%;line-height:1.5}
        .fnd-cp-time{font-size:10px;color:#94a3b8;margin-top:5px}
        .fnd-cp-footer{border-top:1px solid var(--c-border);padding:12px 14px;background:#fff}
        .fnd-cp-input-row{display:grid;grid-template-columns:1fr auto;gap:10px;margin-bottom:10px;align-items:center}
        .fnd-cp-input{padding:8px 11px;border:1px solid var(--c-border);border-radius:8px;
          font-size:12px;color:#64748b;background:#f8fafc;width:100%}
        .fnd-cp-send{border:none;border-radius:8px;padding:8px 14px;font-size:12px;
          font-weight:700;cursor:pointer}
        .fnd-cp-cta{width:100%;border:none;border-radius:10px;padding:10px;font-size:12px;
          font-weight:700;cursor:pointer}

        /* ── Save bar ───────────────────────────────────────────────── */
        .fnd-save-bar{margin-top:20px;display:flex;gap:10px;align-items:center;flex-wrap:wrap}

        /* ── FAQ editor ─────────────────────────────────────────────── */
        .fnd-faq-code{width:100%;min-height:200px;font-family:ui-monospace,"Cascadia Code","Fira Code",monospace;
          font-size:11px;background:#0f172a;color:#e2e8f0;border:1px solid #334155;
          border-radius:9px;padding:12px;resize:vertical}

        /* ── Shortcode chips ─────────────────────────────────────────── */
        .fnd-code-chip{display:inline-block;background:#f1f5f9;border:1px solid var(--c-border);
          border-radius:6px;padding:4px 8px;font-family:monospace;font-size:11px;
          color:var(--c-text);margin:2px 0}

        /* ── Validation warnings ────────────────────────────────────── */
        .fnd-warn-list{background:#fef3c7;border:1px solid #fcd34d;border-radius:10px;
          padding:12px 14px;margin-bottom:16px;font-size:12px;color:#92400e}
        .fnd-warn-list li{margin:3px 0 3px 14px;list-style:disc}
        </style>

        <?php /* ── Form ─────────────────────────────────────────────────────── */ ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="fnd-settings-form">
        <?php wp_nonce_field( self::SAVE_NONCE, self::SAVE_NONCE ); ?>
        <input type="hidden" name="action" value="<?php echo esc_attr( self::SAVE_ACTION ); ?>" />

        <?php /* ── Page header ────────────────────────────────────────────── */ ?>
        <div class="fnd-page-head">
            <div>
                <h1>Frontdesk AI</h1>
                <p>Configure your visitor chat assistant.</p>
            </div>
            <div class="fnd-head-actions">
                <button type="button" class="fnd-btn fnd-btn--ghost fnd-btn--sm" id="fnd-wizard-trigger">
                    ✨ Setup Wizard
                </button>
                <button type="submit" class="fnd-btn fnd-btn--primary">Save settings</button>
            </div>
        </div>

        <?php /* ── Status pills ────────────────────────────────────────────── */ ?>
        <div class="fnd-pills">
            <?php
            $prov  = esc_html( ucfirst( $v['provider'] ) );
            $rmode = esc_html( str_replace( '_', ' ', $v['runtime_mode'] ) );
            $kstat = $v['knowledge_status']['status'] ?? 'unknown';
            $kcount= (int)( $v['knowledge_status']['indexed'] ?? 0 );
            ?>
            <span class="fnd-pill <?php echo $v['provider'] === 'openai' ? 'fnd-pill--ok' : 'fnd-pill--info'; ?>">
                <?php echo $prov; ?> provider
            </span>
            <span class="fnd-pill fnd-pill--neutral"><?php echo $rmode; ?> mode</span>
            <span class="fnd-pill <?php echo $v['openai_key_saved'] ? 'fnd-pill--ok' : 'fnd-pill--warn'; ?>">
                <?php echo $v['openai_key_saved'] ? 'OpenAI key saved' : 'No OpenAI key'; ?>
            </span>
            <span class="fnd-pill <?php echo $kstat === 'available' ? 'fnd-pill--ok' : 'fnd-pill--warn'; ?>">
                Knowledge: <?php echo esc_html( $kstat ); ?><?php echo $kcount > 0 ? ' (' . $kcount . ' chunks)' : ''; ?>
            </span>
        </div>

        <?php /* ── Notices ─────────────────────────────────────────────────── */ ?>
        <?php if ( $saved ) : ?>
            <div class="fnd-notice fnd-notice--ok">✅ Settings saved successfully.</div>
        <?php endif; ?>
        <?php if ( $k_queued ) : ?>
            <div class="fnd-notice fnd-notice--info">🔄 Knowledge index rebuild queued — this runs in the background.</div>
        <?php endif; ?>
        <?php if ( $test_ok ) : ?>
            <div class="fnd-notice fnd-notice--ok">✅ <?php echo esc_html( $flash_msg ?: 'OpenAI connection OK.' ); ?></div>
        <?php elseif ( $test_fail ) : ?>
            <div class="fnd-notice fnd-notice--warn">⚠️ <?php echo esc_html( $flash_msg ?: 'OpenAI connection failed.' ); ?></div>
        <?php elseif ( $flash_msg ) : ?>
            <div class="fnd-notice fnd-notice--info"><?php echo esc_html( $flash_msg ); ?></div>
        <?php endif; ?>
        <?php
        $warnings = $v['validation']['warnings'] ?? [];
        if ( ! empty( $warnings ) ) :
        ?>
            <ul class="fnd-warn-list">
                <?php foreach ( $warnings as $w ) : ?>
                    <li style="display:flex;align-items:baseline;justify-content:space-between;gap:8px">
                        <span><?php echo esc_html( $w ); ?></span>
                        <button type="button"
                                onclick="this.closest('li').remove()"
                                aria-label="<?php esc_attr_e( 'Dismiss', 'foundation-frontdesk' ); ?>"
                                style="flex-shrink:0;background:none;border:none;cursor:pointer;font-weight:800;color:currentColor;padding:0 2px;line-height:1;font-size:14px">&#215;</button>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php /* ── Two-column shell ─────────────────────────────────────────── */ ?>
        <div class="fnd-shell">

        <?php /* ════════════ LEFT COLUMN ════════════════════════════════════════ */ ?>
        <div class="fnd-left">

            <?php /* ── Identity ─────────────────────────────────────────────── */ ?>
            <div class="fnd-card">
                <div class="fnd-card__head"><h2>Identity</h2></div>
                <div class="fnd-card__body">
                    <div class="fnd-grid fnd-grid-2">
                        <div class="fnd-field">
                            <label class="fnd-label" for="s_bot_name">Assistant name</label>
                            <input class="fnd-input" id="s_bot_name" type="text" name="s[bot_name]" value="<?php echo esc_attr( $v['bot_name'] ); ?>" />
                            <span class="fnd-desc">Shown in the chat header and greeting.</span>
                        </div>
                        <div class="fnd-field">
                            <label class="fnd-label" for="s_header_byline">Header byline</label>
                            <input class="fnd-input" id="s_header_byline" type="text" name="s[header_byline]" value="<?php echo esc_attr( $v['header_byline'] ); ?>" />
                        </div>
                    </div>
                    <div class="fnd-field" style="margin-top:14px">
                        <label class="fnd-label" for="s_avatar_url">Avatar or logo URL</label>
                        <div style="display:flex;gap:8px;align-items:center">
                            <input class="fnd-input" id="s_avatar_url" type="url" name="s[avatar_url]"
                                   value="<?php echo esc_attr( $v['avatar_url'] ); ?>"
                                   placeholder="https://example.com/logo.png"
                                   style="flex:1;min-width:0" />
                            <button type="button" class="fnd-btn fnd-btn--secondary fnd-btn--sm"
                                    id="fnd-avatar-media-btn" style="white-space:nowrap;flex-shrink:0">
                                <?php esc_html_e( 'Select image', 'foundation-frontdesk' ); ?>
                            </button>
                        </div>
                        <span class="fnd-desc">Leave blank to use the site logo automatically.</span>
                    </div>
                    <div class="fnd-field" style="margin-top:14px">
                        <label class="fnd-label">Staff photos (shown in the chat popup)</label>
                        <span class="fnd-desc" style="display:block;margin-bottom:8px">Up to 4 circular team photos displayed above the teaser title. Leave slots blank to omit.</span>
                        <?php
                        $staff_photos_arr = Frontdesk_Config::staff_photos_array( $v['staff_photos'] ?? '[]' );
                        while ( count( $staff_photos_arr ) < 4 ) { $staff_photos_arr[] = ''; }
                        foreach ( $staff_photos_arr as $sp_idx => $sp_url ) : ?>
                        <div style="display:flex;gap:8px;align-items:center;margin-top:6px">
                            <input class="fnd-input fnd-staff-photo-input" type="url"
                                   name="s[staff_photos][]"
                                   value="<?php echo esc_attr( $sp_url ); ?>"
                                   placeholder="<?php echo esc_attr( sprintf( 'https://example.com/person-%d.jpg', $sp_idx + 1 ) ); ?>"
                                   style="flex:1;min-width:0"
                                   data-photo-slot="<?php echo esc_attr( $sp_idx ); ?>" />
                            <button type="button" class="fnd-btn fnd-btn--secondary fnd-btn--sm fnd-staff-photo-btn"
                                    data-slot="<?php echo esc_attr( $sp_idx ); ?>" style="white-space:nowrap;flex-shrink:0">
                                <?php esc_html_e( 'Select', 'foundation-frontdesk' ); ?>
                            </button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="fnd-field" style="margin-top:14px">
                        <label class="fnd-label" for="s_greeting_text">Opening greeting (plain text)</label>
                        <textarea class="fnd-textarea" id="s_greeting_text" name="s[greeting_text]" style="min-height:90px"><?php echo esc_textarea( $v['greeting_text'] ); ?></textarea>
                        <span class="fnd-desc">Placeholders: <code>{bot_name}</code> <code>{hours}</code> <code>{contact}</code> — put each line on its own line. Used only if the HTML greeting below is empty.</span>
                    </div>
                    <div class="fnd-field" style="margin-top:14px">
                        <label class="fnd-label" for="s_greeting_html">Opening greeting (HTML — overrides plain text)</label>
                        <textarea class="fnd-textarea" id="s_greeting_html" name="s[greeting_html]" style="min-height:130px;font-family:monospace;font-size:12px"><?php echo esc_textarea( $v['greeting_html'] ?? '' ); ?></textarea>
                        <span class="fnd-desc">Optional. Supports HTML: links, <code>&lt;div class="fnd-greeting-hours"&gt;</code> for a styled hours box, <code>&lt;div class="fnd-greeting-btns"&gt;</code> with <code>&lt;a class="fnd-greeting-btn"&gt;</code> for quick-link pills. Leave blank to use plain text above.</span>
                    </div>
                </div>
            </div>

            <?php /* ── Appearance ───────────────────────────────────────────── */ ?>
            <div class="fnd-card">
                <div class="fnd-card__head"><h2>Appearance</h2></div>
                <div class="fnd-card__body">
                    <div class="fnd-field">
                        <label class="fnd-label">Style preset</label>
                        <div class="fnd-presets" id="fnd-preset-tiles">
                            <?php foreach ( $presets as $key => $preset ) :
                                $vals = $preset['values'];
                            ?>
                                <label class="fnd-preset-tile<?php echo $v['style_preset'] === $key ? ' is-active' : ''; ?>"
                                       data-preset-key="<?php echo esc_attr( $key ); ?>"
                                       data-preset-hbg="<?php echo esc_attr( $vals['ui_header_bg'] ); ?>"
                                       data-preset-htx="<?php echo esc_attr( $vals['ui_header_text'] ); ?>"
                                       data-preset-btn="<?php echo esc_attr( $vals['ui_button_color'] ); ?>"
                                       data-preset-btx="<?php echo esc_attr( $vals['ui_button_text_color'] ); ?>"
                                       data-preset-brd="<?php echo esc_attr( $vals['brand_color'] ); ?>"
                                       data-preset-txt="<?php echo esc_attr( $vals['ui_text_color'] ); ?>">
                                    <input type="radio" name="s[style_preset]" value="<?php echo esc_attr( $key ); ?>"
                                           <?php checked( $v['style_preset'], $key ); ?> style="display:none">
                                    <div class="fnd-preset-tile__swatch"
                                         style="background:linear-gradient(135deg,<?php echo esc_attr( $vals['ui_header_bg'] ); ?> 50%,<?php echo esc_attr( $vals['ui_button_color'] ); ?> 50%)"></div>
                                    <div class="fnd-preset-tile__name"><?php echo esc_html( $preset['label'] ); ?></div>
                                </label>
                            <?php endforeach; ?>
                            <label class="fnd-preset-tile<?php echo $v['style_mode'] === 'manual' ? ' is-active' : ''; ?>"
                                   data-preset-key="manual">
                                <input type="radio" name="s[style_preset]" value="__manual__"
                                       <?php checked( $v['style_mode'], 'manual' ); ?> style="display:none">
                                <div class="fnd-preset-tile__swatch" style="background:linear-gradient(135deg,#334155 50%,#64748b 50%)"></div>
                                <div class="fnd-preset-tile__name">Custom</div>
                            </label>
                        </div>
                        <input type="hidden" name="s[style_mode]" id="s_style_mode" value="<?php echo esc_attr( $v['style_mode'] ); ?>" />
                    </div>

                    <div id="fnd-manual-colours" style="<?php echo $v['style_mode'] !== 'manual' ? 'display:none' : ''; ?>">
                        <div class="fnd-divider"></div>
                        <div class="fnd-grid fnd-grid-2" style="gap:12px">
                            <?php
                            $colour_fields = [
                                [ 'ui_header_bg',         'Header background' ],
                                [ 'ui_header_text',       'Header text' ],
                                [ 'ui_button_color',      'Button background' ],
                                [ 'ui_button_text_color', 'Button text' ],
                                [ 'brand_color',          'Brand / accent colour' ],
                                [ 'ui_text_color',        'Body text colour' ],
                            ];
                            foreach ( $colour_fields as [$ckey, $clabel] ) : ?>
                                <div class="fnd-field">
                                    <label class="fnd-label"><?php echo esc_html( $clabel ); ?></label>
                                    <div class="fnd-color-row">
                                        <input class="fnd-color-swatch" type="color"
                                               value="<?php echo esc_attr( $v[$ckey] ); ?>"
                                               data-text-target="s_<?php echo esc_attr( $ckey ); ?>">
                                        <input class="fnd-input" type="text" id="s_<?php echo esc_attr( $ckey ); ?>"
                                               name="s[<?php echo esc_attr( $ckey ); ?>]"
                                               value="<?php echo esc_attr( $v[$ckey] ); ?>">
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="fnd-divider"></div>
                    <div class="fnd-grid fnd-grid-2">
                        <div class="fnd-field">
                            <label class="fnd-label" for="s_ui_position_corner">Widget position</label>
                            <select class="fnd-select" id="s_ui_position_corner" name="s[ui_position_corner]">
                                <?php
                                $corners = [ 'bottom_right' => 'Bottom right', 'bottom_left' => 'Bottom left', 'top_right' => 'Top right', 'top_left' => 'Top left' ];
                                foreach ( $corners as $cv => $cl ) : ?>
                                    <option value="<?php echo esc_attr( $cv ); ?>" <?php selected( $v['ui_position_corner'], $cv ); ?>><?php echo esc_html( $cl ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="fnd-field">
                            <label class="fnd-label" for="s_display_mode">Widget placement</label>
                            <select class="fnd-select" id="s_display_mode" name="s[display_mode]">
                                <option value="floating" <?php selected( $v['display_mode'], 'floating' ); ?>>Floating site-wide</option>
                                <option value="inline"   <?php selected( $v['display_mode'], 'inline' ); ?>>Inline shortcode only</option>
                                <option value="both"     <?php selected( $v['display_mode'], 'both' ); ?>>Floating + shortcodes</option>
                            </select>
                        </div>
                        <div class="fnd-field">
                            <label class="fnd-label" for="s_ui_font_family">Font family</label>
                            <input class="fnd-input" id="s_ui_font_family" type="text" name="s[ui_font_family]" value="<?php echo esc_attr( $v['ui_font_family'] ); ?>" />
                        </div>
                        <div class="fnd-field">
                            <label class="fnd-label" for="s_ui_radius">Corner radius (px)</label>
                            <input class="fnd-input" id="s_ui_radius" type="number" min="0" max="32" name="s[ui_radius]" value="<?php echo esc_attr( (string)$v['ui_radius'] ); ?>" />
                        </div>
                    </div>
                </div>
            </div>

            <?php /* ── Teaser & launcher ──────────────────────────────────────── */ ?>
            <div class="fnd-card">
                <div class="fnd-card__head"><h2>Teaser &amp; Launcher</h2></div>
                <div class="fnd-card__body">
                    <div class="fnd-grid fnd-grid-2">
                        <div class="fnd-field">
                            <label class="fnd-label" for="s_teaser_title">Teaser heading</label>
                            <input class="fnd-input" id="s_teaser_title" type="text" name="s[teaser_title]" value="<?php echo esc_attr( $v['teaser_title'] ); ?>" />
                            <span class="fnd-desc">Bold line in the popup above the launcher button.</span>
                        </div>
                        <div class="fnd-field">
                            <label class="fnd-label" for="s_teaser_body">Teaser body</label>
                            <input class="fnd-input" id="s_teaser_body" type="text" name="s[teaser_body]" value="<?php echo esc_attr( $v['teaser_body'] ); ?>" />
                        </div>
                        <div class="fnd-field">
                            <label class="fnd-label" for="s_launcher_label">Launcher button label</label>
                            <input class="fnd-input" id="s_launcher_label" type="text" name="s[launcher_label]" value="<?php echo esc_attr( $v['launcher_label'] ); ?>" />
                        </div>
                        <div class="fnd-field">
                            <label class="fnd-label" for="s_kb_button_label">Help Centre button label</label>
                            <input class="fnd-input" id="s_kb_button_label" type="text" name="s[kb_button_label]" value="<?php echo esc_attr( $v['kb_button_label'] ); ?>" />
                        </div>
                        <div class="fnd-field">
                            <label class="fnd-label" for="s_input_placeholder">Message input placeholder</label>
                            <input class="fnd-input" id="s_input_placeholder" type="text" name="s[input_placeholder]" value="<?php echo esc_attr( $v['input_placeholder'] ); ?>" />
                        </div>
                        <div class="fnd-field">
                            <label class="fnd-label" for="s_contact_button_label">Contact button label</label>
                            <input class="fnd-input" id="s_contact_button_label" type="text" name="s[contact_button_label]" value="<?php echo esc_attr( $v['contact_button_label'] ); ?>" />
                        </div>
                        <div class="fnd-field">
                            <label class="fnd-label" for="s_contact_btn_color">Contact button colour <span class="fnd-desc" style="font-weight:400">(leave blank to use accent colour)</span></label>
                            <div style="display:flex;gap:8px;align-items:center">
                                <input type="color" id="s_contact_btn_color_picker" value="<?php echo esc_attr( $v['contact_btn_color'] ?: '#04ad93' ); ?>" style="width:36px;height:36px;padding:2px;border:1px solid #e2e8f0;border-radius:6px;cursor:pointer">
                                <input class="fnd-input" id="s_contact_btn_color" type="text" name="s[contact_btn_color]" value="<?php echo esc_attr( $v['contact_btn_color'] ); ?>" placeholder="e.g. #DE7450 — blank = use accent" style="font-family:monospace">
                            </div>
                        </div>
                        <div class="fnd-field">
                            <label class="fnd-label" for="s_contact_btn_text_color">Contact button text colour</label>
                            <div style="display:flex;gap:8px;align-items:center">
                                <input type="color" id="s_contact_btn_text_picker" value="<?php echo esc_attr( $v['contact_btn_text_color'] ?: '#ffffff' ); ?>" style="width:36px;height:36px;padding:2px;border:1px solid #e2e8f0;border-radius:6px;cursor:pointer">
                                <input class="fnd-input" id="s_contact_btn_text_color" type="text" name="s[contact_btn_text_color]" value="<?php echo esc_attr( $v['contact_btn_text_color'] ); ?>" placeholder="#ffffff" style="font-family:monospace">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php /* ── Security ─────────────────────────────────────────────────── */ ?>
            <div class="fnd-card">
                <div class="fnd-card__head"><h2>Security &amp; Spam Protection</h2></div>
                <div class="fnd-card__body">
                    <p class="fnd-desc" style="margin:0 0 14px">Honeypot and rate limiting are always active. Add reCAPTCHA v3 for an extra layer — <a href="https://www.google.com/recaptcha/admin/create" target="_blank" rel="noopener">get free keys here</a>.</p>
                    <div class="fnd-grid fnd-grid-2">
                        <div class="fnd-field">
                            <label class="fnd-label" for="s_recaptcha_site_key">reCAPTCHA v3 site key</label>
                            <input class="fnd-input" id="s_recaptcha_site_key" type="text" name="s[recaptcha_site_key]" value="<?php echo esc_attr( $v['recaptcha_site_key'] ); ?>" placeholder="6Le…" autocomplete="off" />
                            <span class="fnd-desc">Paste your site key (public). Leave blank to disable reCAPTCHA.</span>
                        </div>
                        <div class="fnd-field">
                            <label class="fnd-label" for="s_recaptcha_secret_key">reCAPTCHA v3 secret key</label>
                            <input class="fnd-input" id="s_recaptcha_secret_key" type="password" name="s[recaptcha_secret_key]" value="<?php echo esc_attr( $v['recaptcha_secret_key'] ); ?>" placeholder="6Le…" autocomplete="new-password" />
                            <span class="fnd-desc">Stored server-side only. Used to verify tokens.</span>
                        </div>
                    </div>
                </div>
            </div>

            <?php /* ── AI & Knowledge ──────────────────────────────────────────── */ ?>
            <div class="fnd-card">
                <div class="fnd-card__head"><h2>AI &amp; Knowledge</h2></div>
                <div class="fnd-card__body">
                    <div class="fnd-grid fnd-grid-2">
                        <div class="fnd-field">
                            <label class="fnd-label" for="s_provider">Reply engine</label>
                            <select class="fnd-select" id="s_provider" name="s[provider]">
                                <option value="offline" <?php selected( $v['provider'], 'offline' ); ?>>Offline — site content + FAQs</option>
                                <option value="openai"  <?php selected( $v['provider'], 'openai' ); ?>>OpenAI</option>
                            </select>
                        </div>
                        <div class="fnd-field">
                            <label class="fnd-label" for="s_runtime_mode">Knowledge source</label>
                            <select class="fnd-select" id="s_runtime_mode" name="s[runtime_mode]">
                                <option value="faq_only"      <?php selected( $v['runtime_mode'], 'faq_only' ); ?>>FAQs only</option>
                                <option value="site_index"    <?php selected( $v['runtime_mode'], 'site_index' ); ?>>Site index</option>
                                <option value="ai_site_index" <?php selected( $v['runtime_mode'], 'ai_site_index' ); ?>>OpenAI + site index</option>
                                <option value="offline"       <?php selected( $v['runtime_mode'], 'offline' ); ?>>Offline fallback</option>
                            </select>
                        </div>
                        <div class="fnd-field" id="fnd-openai-key-field">
                            <label class="fnd-label" for="s_openai_api_key">OpenAI API key</label>
                            <input class="fnd-input" id="s_openai_api_key" type="password" autocomplete="new-password"
                                   name="s[openai_api_key]" value=""
                                   placeholder="<?php echo $v['openai_key_saved'] ? esc_attr__( 'Leave blank to keep saved key', 'foundation-frontdesk' ) : 'sk-…'; ?>" />
                            <span class="fnd-desc">Stored server-side only — never sent to visitors.</span>
                        </div>
                        <div class="fnd-field" id="fnd-openai-model-field">
                            <label class="fnd-label" for="s_openai_model">OpenAI model</label>
                            <div style="display:flex;gap:8px;align-items:center">
                                <input class="fnd-input" id="s_openai_model" type="text" name="s[openai_model]" value="<?php echo esc_attr( $v['openai_model'] ); ?>" style="flex:1;min-width:0" />
                                <button type="button" class="fnd-btn fnd-btn--secondary fnd-btn--sm"
                                        id="fnd-test-openai-btn"
                                        data-nonce="<?php echo esc_attr( wp_create_nonce( 'frontdesk_test_openai' ) ); ?>"
                                        data-action="frontdesk_test_openai"
                                        title="<?php esc_attr_e( 'Send a test prompt to OpenAI to verify the connection', 'foundation-frontdesk' ); ?>"><?php esc_html_e( 'Test', 'foundation-frontdesk' ); ?></button>
                            </div>
                        </div>
                    </div>
                    <div class="fnd-divider"></div>
                    <div class="fnd-grid">
                        <div class="fnd-check-row">
                            <input type="hidden" name="s[fallback_enabled]" value="0" />
                            <input type="checkbox" id="s_fallback" name="s[fallback_enabled]" value="1" <?php checked( $v['fallback_enabled'] ); ?> />
                            <label for="s_fallback">Enable offline fallback if OpenAI is unavailable</label>
                        </div>
                        <div class="fnd-check-row">
                            <input type="hidden" name="s[force_offline]" value="0" />
                            <input type="checkbox" id="s_force_offline" name="s[force_offline]" value="1" <?php checked( $v['force_offline'] ); ?> />
                            <label for="s_force_offline">Force offline mode globally (disables OpenAI)</label>
                        </div>
                    </div>
                    <div class="fnd-divider"></div>
                    <div class="fnd-grid fnd-grid-2">
                        <div class="fnd-field">
                            <label class="fnd-label" for="s_rag_post_types">Indexed post types <span class="fnd-desc" style="display:inline">(comma-separated)</span></label>
                            <input class="fnd-input" id="s_rag_post_types" type="text" name="s[rag_post_types]" value="<?php echo esc_attr( $v['rag_post_types'] ); ?>" />
                        </div>
                        <div class="fnd-field" style="align-self:end">
                            <button type="button" class="fnd-btn fnd-btn--secondary" style="width:100%"
                                    id="fnd-rebuild-btn"
                                    data-nonce="<?php echo esc_attr( wp_create_nonce( 'frontdesk_rebuild_knowledge' ) ); ?>"
                                    data-action="frontdesk_rebuild_knowledge">🔄 Rebuild knowledge index</button>
                        </div>
                    </div>
                </div>
            </div>

            <?php /* ── Business info ─────────────────────────────────────────── */ ?>
            <div class="fnd-card">
                <div class="fnd-card__head"><h2>Business Info</h2></div>
                <div class="fnd-card__body">
                    <div class="fnd-grid fnd-grid-2">
                        <div class="fnd-field">
                            <label class="fnd-label" for="s_opening_hours">Opening hours</label>
                            <textarea class="fnd-textarea" id="s_opening_hours" name="s[opening_hours]" style="min-height:70px"><?php echo esc_textarea( $v['opening_hours'] ); ?></textarea>
                            <span class="fnd-desc">Each day range on its own line. Used by <code>{hours}</code> in greeting.</span>
                        </div>
                        <div class="fnd-field">
                            <label class="fnd-label" for="s_alt_contact">Alternative contact text</label>
                            <input class="fnd-input" id="s_alt_contact" type="text" name="s[alt_contact]" value="<?php echo esc_attr( $v['alt_contact'] ); ?>" />
                            <span class="fnd-desc">Used by <code>{contact}</code> in greeting.</span>
                        </div>
                    </div>
                    <div class="fnd-divider"></div>
                    <div class="fnd-field">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
                            <label class="fnd-label">FAQ dataset</label>
                            <span class="fnd-desc"><?php echo esc_html( number_format( $faqs_len ) ); ?> characters &nbsp;·&nbsp;
                                <a href="<?php echo esc_url( add_query_arg( [ 'page' => self::PAGE_SLUG, 'faq_edit' => '1' ], admin_url( 'admin.php' ) ) ); ?>">Edit JSON</a>
                            </span>
                        </div>
                        <?php if ( $faq_edit ) : ?>
                            <textarea class="fnd-faq-code" name="s[faqs_json]" id="s_faqs_json"><?php echo esc_textarea( $v['faqs_json'] ); ?></textarea>
                            <span class="fnd-desc">JSON array of <code>{"q":"...","a":"...","url":"..."}</code> objects.</span>
                        <?php else : ?>
                            <span class="fnd-desc">JSON not shown by default to keep the page fast. Click "Edit JSON" above to expand.</span>
                            <input type="hidden" name="s[faqs_json]" value="<?php echo esc_attr( $v['faqs_json'] ); ?>" />
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php /* ── Lead capture ─────────────────────────────────────────── */ ?>
            <div class="fnd-card">
                <div class="fnd-card__head"><h2>Lead Capture</h2></div>
                <div class="fnd-card__body">
                    <div class="fnd-check-row" style="margin-bottom:12px">
                        <input type="hidden" name="s[enable_contact]" value="0" />
                        <input type="checkbox" id="s_enable_contact" name="s[enable_contact]" value="1" <?php checked( $v['enable_contact'] ); ?> />
                        <label for="s_enable_contact">Enable Contact us / lead capture form</label>
                    </div>
                    <div class="fnd-grid fnd-grid-2">
                        <div class="fnd-field">
                            <label class="fnd-label" for="s_contact_email">Notification email</label>
                            <input class="fnd-input" id="s_contact_email" type="email" name="s[contact_email]" value="<?php echo esc_attr( $v['contact_email'] ); ?>" />
                        </div>
                        <div class="fnd-field">
                            <label class="fnd-label" for="s_privacy_notice">Privacy notice</label>
                            <input class="fnd-input" id="s_privacy_notice" type="text" name="s[privacy_notice]" value="<?php echo esc_attr( $v['privacy_notice'] ); ?>" />
                        </div>
                        <div class="fnd-field">
                            <label class="fnd-label" for="s_contact_subject_prefix">Email subject prefix</label>
                            <input class="fnd-input" id="s_contact_subject_prefix" type="text" name="s[contact_subject_prefix]" value="<?php echo esc_attr( $v['contact_subject_prefix'] ); ?>" />
                        </div>
                        <div class="fnd-field" style="align-self:center;padding-top:18px">
                            <div class="fnd-check-row">
                                <input type="hidden" name="s[send_user_confirmation]" value="0" />
                                <input type="checkbox" id="s_send_confirm" name="s[send_user_confirmation]" value="1" <?php checked( $v['send_user_confirmation'] ); ?> />
                                <label for="s_send_confirm">Send confirmation email to visitor</label>
                            </div>
                        </div>
                    </div>
                    <div class="fnd-divider"></div>
                    <div class="fnd-grid">
                        <div class="fnd-field">
                            <label class="fnd-label" for="s_notification_email_intro">Notification email intro</label>
                            <textarea class="fnd-textarea" id="s_notification_email_intro" name="s[notification_email_intro]" style="min-height:75px"><?php echo esc_textarea( $v['notification_email_intro'] ?? '' ); ?></textarea>
                            <span class="fnd-desc">Opening paragraph of the email <em>you</em> receive when someone submits the form. Placeholders: <code>{bot_name}</code> <code>{site_name}</code> <code>{name}</code> <code>{email}</code> <code>{page_title}</code> <code>{page_url}</code></span>
                        </div>
                        <div class="fnd-field">
                            <label class="fnd-label" for="s_confirmation_email_body">Confirmation email body</label>
                            <textarea class="fnd-textarea" id="s_confirmation_email_body" name="s[confirmation_email_body]" style="min-height:75px"><?php echo esc_textarea( $v['confirmation_email_body'] ?? '' ); ?></textarea>
                            <span class="fnd-desc">Message sent to the visitor to confirm receipt. Placeholders: <code>{name}</code> <code>{bot_name}</code> <code>{site_name}</code></span>
                        </div>
                    </div>
                </div>
            </div>

            <?php /* ── Advanced (collapsed) ───────────────────────────────────── */ ?>
            <details class="fnd-details">
                <summary>Advanced settings</summary>
                <div class="fnd-details__body">
                    <div class="fnd-grid fnd-grid-3">
                        <div class="fnd-field">
                            <label class="fnd-label" for="s_temperature">Temperature (0–1)</label>
                            <input class="fnd-input" id="s_temperature" type="number" step="0.05" min="0" max="1" name="s[temperature]" value="<?php echo esc_attr( (string) $v['temperature'] ); ?>" />
                        </div>
                        <div class="fnd-field">
                            <label class="fnd-label" for="s_max_output_tokens">Max output tokens</label>
                            <input class="fnd-input" id="s_max_output_tokens" type="number" min="100" max="2000" name="s[max_output_tokens]" value="<?php echo esc_attr( (string) $v['max_output_tokens'] ); ?>" />
                        </div>
                        <div class="fnd-field">
                            <label class="fnd-label" for="s_max_input_chars">Max visitor input (chars)</label>
                            <input class="fnd-input" id="s_max_input_chars" type="number" min="100" max="4000" name="s[max_input_chars]" value="<?php echo esc_attr( (string) $v['max_input_chars'] ); ?>" />
                        </div>
                        <div class="fnd-field">
                            <label class="fnd-label" for="s_ui_offset_x">Horizontal offset (px)</label>
                            <input class="fnd-input" id="s_ui_offset_x" type="number" min="0" name="s[ui_offset_x]" value="<?php echo esc_attr( (string) $v['ui_offset_x'] ); ?>" />
                        </div>
                        <div class="fnd-field">
                            <label class="fnd-label" for="s_ui_offset_y">Vertical offset (px)</label>
                            <input class="fnd-input" id="s_ui_offset_y" type="number" min="0" name="s[ui_offset_y]" value="<?php echo esc_attr( (string) $v['ui_offset_y'] ); ?>" />
                        </div>
                        <div class="fnd-field">
                            <label class="fnd-label" for="s_offline_notice">Offline / no-match message</label>
                            <input class="fnd-input" id="s_offline_notice" type="text" name="s[offline_notice]" value="<?php echo esc_attr( $v['offline_notice'] ); ?>" />
                        </div>
                    </div>
                    <div class="fnd-grid fnd-grid-2" style="margin-top:4px">
                        <div class="fnd-check-row">
                            <input type="hidden" name="s[store_transcripts]" value="0" />
                            <input type="checkbox" id="s_transcripts" name="s[store_transcripts]" value="1" <?php checked( $v['store_transcripts'] ); ?> />
                            <label for="s_transcripts">Store frontdesktion transcripts</label>
                        </div>
                        <div class="fnd-field">
                            <label class="fnd-label" for="s_transcript_days">Transcript retention (days)</label>
                            <input class="fnd-input" id="s_transcript_days" type="number" min="1" max="365" name="s[transcript_retention_days]" value="<?php echo esc_attr( (string) $v['transcript_retention_days'] ); ?>" />
                        </div>
                    </div>
                </div>
            </details>

            <?php /* ── Shortcodes ───────────────────────────────────────────── */ ?>
            <div class="fnd-card">
                <div class="fnd-card__head"><h2>Shortcodes</h2></div>
                <div class="fnd-card__body">
                    <div class="fnd-grid" style="gap:8px">
                        <div>
                            <code class="fnd-code-chip">[foundation_frontdesk]</code>
                            <span class="fnd-desc" style="display:inline;margin-left:8px">Inline chat embed.</span>
                        </div>
                        <div>
                            <code class="fnd-code-chip">[foundation_frontdesk launcher="1" label="Ask us"]</code>
                            <span class="fnd-desc" style="display:inline;margin-left:8px">Compact launcher for header or nav.</span>
                        </div>
                        <div>
                            <code class="fnd-code-chip">[foundation_frontdesk]</code>
                            <span class="fnd-desc" style="display:inline;margin-left:8px">Legacy alias — kept for compatibility.</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="fnd-save-bar">
                <button type="submit" class="fnd-btn fnd-btn--primary">Save settings</button>
                <button type="button" class="fnd-btn fnd-btn--ghost fnd-btn--sm" id="fnd-wizard-trigger-2">✨ Setup Wizard</button>
            </div>
        </div>

        <?php /* ════════════ RIGHT COLUMN — sticky preview ═══════════════════════ */ ?>
        <aside class="fnd-sidebar">
            <div class="fnd-card">
                <div class="fnd-card__head">
                    <h2>Widget Preview</h2>
                    <span class="fnd-desc">Updates on save</span>
                </div>
                <div class="fnd-card__body" style="padding:0">
                    <?php
                    $hbg   = esc_attr( $v['ui_header_bg'] );
                    $htx   = esc_attr( $v['ui_header_text'] );
                    $btnbg = esc_attr( $v['ui_button_color'] );
                    $btntx = esc_attr( $v['ui_button_text_color'] );
                    $name  = esc_html( $v['bot_name'] );
                    $kblbl = esc_html( $v['kb_button_label'] );
                    $ctlbl = esc_html( $v['contact_button_label'] );
                    $greet = esc_html( Frontdesk_Config::interpolate( $v['greeting_text'] ) );
                    $phld  = esc_attr( $v['input_placeholder'] );
                    $byline = esc_html( $v['header_byline'] );
                    $is_offline = $v['provider'] === 'offline';
                    $lines = explode( "\n", wp_kses_post( Frontdesk_Config::interpolate( $v['greeting_text'] ) ) );
                    ?>
                    <div class="fnd-chat-preview">
                        <div class="fnd-cp-header" style="background:<?php echo $hbg; ?>;color:<?php echo $htx; ?>">
                            <div class="fnd-cp-header-left">
                                <div class="fnd-cp-logo">
                                    <?php if ( $v['avatar_url'] ) : ?>
                                        <img src="<?php echo esc_url( $v['avatar_url'] ); ?>" width="28" height="28" style="border-radius:6px;object-fit:cover" alt="" />
                                    <?php else : ?>
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="<?php echo $btnbg; ?>"><circle cx="12" cy="12" r="10"/></svg>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div class="fnd-cp-name" style="color:<?php echo $htx; ?>"><?php echo $name; ?></div>
                                    <div class="fnd-cp-byline" style="color:<?php echo $htx; ?>"><?php echo $byline; ?></div>
                                </div>
                                <span class="fnd-cp-badge" style="background:<?php echo $is_offline ? '#ee744d' : '#059669'; ?>">
                                    <?php echo $is_offline ? 'Offline' : 'Online'; ?>
                                </span>
                            </div>
                            <div class="fnd-cp-actions">
                                <button type="button" class="fnd-cp-kbbtn" style="color:<?php echo $htx; ?>"><?php echo $kblbl; ?></button>
                                <button type="button" class="fnd-cp-menubtn" aria-label="Menu">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="<?php echo $htx; ?>">
                                        <circle cx="5" cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <div class="fnd-cp-body">
                            <div class="fnd-cp-bubble">
                                <?php foreach ( $lines as $i => $line ) : ?>
                                    <?php if ( $i === 0 ) : ?><strong><?php echo esc_html( $line ); ?></strong><?php else : ?><br><?php echo esc_html( $line ); ?><?php endif; ?>
                                <?php endforeach; ?>
                                <div class="fnd-cp-time">Just now</div>
                            </div>
                        </div>
                        <div class="fnd-cp-footer">
                            <div class="fnd-cp-input-row">
                                <div class="fnd-cp-input"><?php echo esc_html( $v['input_placeholder'] ); ?></div>
                                <button type="button" class="fnd-cp-send" style="background:<?php echo $btnbg; ?>;color:<?php echo $btntx; ?>">Send</button>
                            </div>
                            <button type="button" class="fnd-cp-cta" style="background:<?php echo $btnbg; ?>;color:<?php echo $btntx; ?>"><?php echo $ctlbl; ?></button>
                        </div>
                    </div>
                </div>
            </div>
        </aside>

        </div><!-- /.fnd-shell -->
        </form><!-- /#fnd-settings-form -->

        <?php /* ── Wizard overlay (hidden) ─────────────────────────────────── */ ?>
        <?php Foundation_Frontdesk_Onboarding::render_wizard_html( $v, $presets ); ?>

        <?php /* ── Inline JS ────────────────────────────────────────────────── */ ?>
        <script>
        (function(){
            const tiles = document.querySelectorAll('.fnd-preset-tile');
            const styleModeInput = document.getElementById('s_style_mode');
            const manualDiv = document.getElementById('fnd-manual-colours');

            // ── Live preview (debounced 300ms) ────────────────────────────────
            let _pt;
            function updatePreview() {
                const hbg  = document.getElementById('s_ui_header_bg')?.value  || '';
                const htx  = document.getElementById('s_ui_header_text')?.value || '';
                const btn  = document.getElementById('s_ui_button_color')?.value || '';
                const btx  = document.getElementById('s_ui_button_text_color')?.value || '';
                const name = document.getElementById('s_bot_name')?.value || '';
                const kbl  = document.getElementById('s_kb_button_label')?.value || '';
                const hdr  = document.querySelector('.fnd-cp-header');
                const nmEl = document.querySelector('.fnd-cp-name');
                const snEl = document.querySelector('.fnd-cp-send');
                const ctEl = document.querySelector('.fnd-cp-cta');
                const kbEl = document.querySelector('.fnd-cp-kbbtn');
                if (hdr)  { hdr.style.background = hbg; hdr.style.color = htx; }
                if (nmEl && name) nmEl.textContent = name;
                if (snEl && btn) { snEl.style.background = btn; snEl.style.color = btx; }
                if (ctEl && btn) { ctEl.style.background = btn; ctEl.style.color = btx; }
                if (kbEl && kbl) kbEl.textContent = kbl;
            }
            function debouncePreview() { clearTimeout(_pt); _pt = setTimeout(updatePreview, 300); }
            ['s_bot_name','s_ui_header_bg','s_ui_header_text','s_ui_button_color','s_ui_button_text_color','s_kb_button_label'].forEach(id => {
                document.getElementById(id)?.addEventListener('input', debouncePreview);
            });

            // ── Preset tile selection + colour population ─────────────────────
            const colourMap = {
                hbg: 's_ui_header_bg',  htx: 's_ui_header_text',
                btn: 's_ui_button_color', btx: 's_ui_button_text_color',
                brd: 's_brand_color',   txt: 's_ui_text_color',
            };
            tiles.forEach(tile => {
                tile.addEventListener('click', () => {
                    tiles.forEach(t => t.classList.remove('is-active'));
                    tile.classList.add('is-active');
                    const key = tile.getAttribute('data-preset-key');
                    const radio = tile.querySelector('input[type=radio]');
                    if (radio) radio.checked = true;

                    if (key === 'manual') {
                        styleModeInput.value = 'manual';
                        manualDiv.style.display = '';
                    } else {
                        styleModeInput.value = 'preset';
                        manualDiv.style.display = 'none';
                        // Populate the six colour inputs from the preset data attributes
                        Object.entries(colourMap).forEach(([attr, id]) => {
                            const val = tile.getAttribute('data-preset-' + attr);
                            if (!val) return;
                            const txt = document.getElementById(id);
                            if (txt) {
                                txt.value = val;
                                const sw = txt.closest('.fnd-color-row')?.querySelector('input[type=color]');
                                if (sw) sw.value = val;
                            }
                        });
                        debouncePreview();
                    }
                });
            });

            // ── Colour swatch ↔ hex text sync ────────────────────────────────
            document.querySelectorAll('.fnd-color-swatch').forEach(sw => {
                const target = document.getElementById(sw.getAttribute('data-text-target'));
                if (!target) return;
                sw.addEventListener('input', () => { target.value = sw.value; debouncePreview(); });
                target.addEventListener('input', () => {
                    if (/^#[0-9a-f]{6}$/i.test(target.value)) sw.value = target.value;
                    debouncePreview();
                });
            });

            // ── Provider show/hide ────────────────────────────────────────────
            const provSel  = document.getElementById('s_provider');
            const oaiKey   = document.getElementById('fnd-openai-key-field');
            const oaiModel = document.getElementById('fnd-openai-model-field');
            function syncProvider() {
                const ai = provSel && provSel.value === 'openai';
                if (oaiKey)   oaiKey.style.display   = ai ? '' : 'none';
                if (oaiModel) oaiModel.style.display = ai ? '' : 'none';
            }
            if (provSel) { syncProvider(); provSel.addEventListener('change', syncProvider); }

            // ── Wizard open/close ─────────────────────────────────────────────
            const overlay = document.getElementById('fnd-wizard-overlay');
            function openWizard() {
                if (overlay) {
                    overlay.style.display = 'block';
                    document.body.style.overflow = 'hidden';
                    // Move focus into overlay for keyboard/AT users
                    const first = overlay.querySelector('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
                    if (first) first.focus();
                }
            }
            let _wizardOpener = null;
            function closeWizard() {
                if (overlay) { overlay.style.display = 'none'; document.body.style.overflow = ''; }
                if (_wizardOpener) { _wizardOpener.focus(); _wizardOpener = null; }
            }
            const _wizardTriggers = ['fnd-wizard-trigger', 'fnd-wizard-trigger-2'].map(id => document.getElementById(id)).filter(Boolean);
            _wizardTriggers.forEach(btn => btn.addEventListener('click', () => { _wizardOpener = btn; openWizard(); }));
            document.getElementById('fnd-wizard-close')?.addEventListener('click', closeWizard);
            overlay?.addEventListener('click', e => { if (e.target === overlay) closeWizard(); });
            document.addEventListener('keydown', e => {
                if (!overlay || overlay.style.display === 'none') return;
                if (e.key === 'Escape') { closeWizard(); (_wizardTriggers[0] || document.body).focus(); return; }
                if (e.key === 'Tab') {
                    const focusable = Array.from(overlay.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'));
                    if (!focusable.length) return;
                    const first = focusable[0], last = focusable[focusable.length - 1];
                    if (e.shiftKey ? document.activeElement === first : document.activeElement === last) {
                        e.preventDefault();
                        (e.shiftKey ? last : first).focus();
                    }
                }
            });

            // ── Out-of-form POST buttons (avoid nested-form HTML issue) ─────────
            function submitAdminPost(action, nonce) {
                const f = document.createElement('form');
                f.method = 'post';
                f.action = <?php echo wp_json_encode( admin_url( 'admin-post.php' ) ); ?>;
                [['action', action], ['_wpnonce', nonce]].forEach(([n, v]) => {
                    const i = document.createElement('input');
                    i.type = 'hidden'; i.name = n; i.value = v;
                    f.appendChild(i);
                });
                document.body.appendChild(f);
                f.submit();
            }
            document.getElementById('fnd-test-openai-btn')?.addEventListener('click', function() {
                submitAdminPost(this.dataset.action, this.dataset.nonce);
            });
            document.getElementById('fnd-rebuild-btn')?.addEventListener('click', function() {
                submitAdminPost(this.dataset.action, this.dataset.nonce);
            });

            // ── Auto-open on first install (readyState-safe) ──────────────────
            <?php if ( $auto_wizard ) : ?>
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', openWizard);
            } else {
                openWizard();
            }
            <?php endif; ?>

            // ── Media library for staff photos ───────────────────────────────
            document.querySelectorAll('.fnd-staff-photo-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    if (typeof wp === 'undefined' || typeof wp.media === 'undefined') { return; }
                    var slot = btn.dataset.slot;
                    var frame = wp.media({
                        title:    <?php echo wp_json_encode( __( 'Select staff photo', 'foundation-frontdesk' ) ); ?>,
                        button:   { text: <?php echo wp_json_encode( __( 'Use this photo', 'foundation-frontdesk' ) ); ?> },
                        multiple: false,
                        library:  { type: 'image' }
                    });
                    frame.on('select', function() {
                        var attachment = frame.state().get('selection').first().toJSON();
                        var url = (attachment.sizes && attachment.sizes.medium && attachment.sizes.medium.url)
                                   || attachment.url || '';
                        var inp = document.querySelector('.fnd-staff-photo-input[data-photo-slot="' + slot + '"]');
                        if (inp && url) {
                            inp.value = url;
                            inp.dispatchEvent(new Event('input'));
                        }
                    });
                    frame.open();
                });
            });

            // ── Media library for avatar / logo ───────────────────────────────
            document.getElementById('fnd-avatar-media-btn')?.addEventListener('click', function() {
                if (typeof wp === 'undefined' || typeof wp.media === 'undefined') { return; }
                var frame = wp.media({
                    title:    <?php echo wp_json_encode( __( 'Select or upload an image', 'foundation-frontdesk' ) ); ?>,
                    button:   { text: <?php echo wp_json_encode( __( 'Use this image', 'foundation-frontdesk' ) ); ?> },
                    multiple: false,
                    library:  { type: 'image' }
                });
                frame.on('select', function() {
                    var attachment = frame.state().get('selection').first().toJSON();
                    var url = (attachment.sizes && attachment.sizes.thumbnail && attachment.sizes.thumbnail.url)
                               || attachment.url || '';
                    var inp = document.getElementById('s_avatar_url');
                    if (inp && url) {
                        inp.value = url;
                        inp.dispatchEvent(new Event('input'));
                    }
                });
                frame.open();
            });
        })();
        </script>

        </div><!-- /.fnd-admin-page -->
        <?php
    }

    // ─── Handlers ────────────────────────────────────────────────────────────

    public static function handle_save(): void {
        if ( ! current_user_can( 'manage_options' ) ) wp_die();
        check_admin_referer( self::SAVE_NONCE, self::SAVE_NONCE );
        $input = isset( $_POST['s'] ) && is_array( $_POST['s'] ) ? wp_unslash( $_POST['s'] ) : [];
        Frontdesk_Config::save( $input );
        wp_safe_redirect( add_query_arg( [ 'page' => self::PAGE_SLUG, 'frontdesk_saved' => '1' ], admin_url( 'admin.php' ) ) );
        exit;
    }

    public static function handle_rebuild_knowledge(): void {
        if ( ! current_user_can( 'manage_options' ) ) wp_die();
        check_admin_referer( 'frontdesk_rebuild_knowledge' );
        $post_types = class_exists( 'Frontdesk_Knowledge' )
            ? Frontdesk_Knowledge::selected_post_types( Frontdesk_Config::get_all() )
            : [ 'post', 'page' ];
        update_option( 'fnd_frontdesk_rag_status', [
            'status'     => 'queued',
            'indexed'    => 0,
            'total'      => 0,
            'post_types' => $post_types,
        ], false );
        wp_safe_redirect( add_query_arg( [ 'page' => self::PAGE_SLUG, 'frontdesk_knowledge' => 'queued' ], admin_url( 'admin.php' ) ) );
        exit;
    }
}

endif;
