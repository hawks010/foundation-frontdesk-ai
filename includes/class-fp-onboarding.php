<?php
if (!defined('ABSPATH')) exit;

/**
 * Foundation: Frontdesk AI — First-Run Onboarding Wizard (FULLSCREEN)
 */
class Foundation_Conversa_Onboarding {

    const REDIRECT_FLAG = 'fnd_conversa_do_onboarding_redirect';
    const PAGE_SLUG     = 'foundation-conversa-onboarding';

    /** Boot */
    public static function init() {
        // Activation hook for first-run redirect
        $file = self::plugin_file_guess();
        if ($file && function_exists('register_activation_hook')) {
            register_activation_hook($file, [__CLASS__, 'on_activate']);
        }
        add_action('admin_init',  [__CLASS__, 'maybe_redirect_to_wizard']);
        add_action('admin_menu',  [__CLASS__, 'add_hidden_wizard_page']);
        add_action('wp_ajax_fnd_conversa_save_wizard', [__CLASS__, 'ajax_save_wizard']);
    }

    /** Try to guess main plugin file for activation hook */
    private static function plugin_file_guess() {
        if (defined('FND_CONVERSA_MAIN_FILE') && file_exists(FND_CONVERSA_MAIN_FILE)) return FND_CONVERSA_MAIN_FILE;
        $root = dirname(__FILE__, 2) . '/';
        foreach ([$root.'foundation-frontdesk.php',$root.'foundation-conversa.php',$root.'frontdesk-ai.php'] as $c) {
            if (file_exists($c)) return $c;
        }
        return null;
    }

    public static function on_activate() {
        add_option(self::REDIRECT_FLAG, '1', '', false);
    }

    public static function maybe_redirect_to_wizard() {
        if (!is_admin() || !current_user_can('manage_options')) return;
        if (get_option(self::REDIRECT_FLAG) !== '1') return;
        delete_option(self::REDIRECT_FLAG); // one-time
        if (defined('DOING_AJAX') && DOING_AJAX) return;
        if (isset($_GET['page']) && $_GET['page'] === self::PAGE_SLUG) return;
        wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE_SLUG));
        exit;
    }

    public static function add_hidden_wizard_page() {
        $parent_slug = function_exists('fnd_conversa_core_is_available') && fnd_conversa_core_is_available()
            ? 'foundation-core'
            : 'foundation-by-inkfire';
        if (empty($GLOBALS['admin_page_hooks'][$parent_slug])) $parent_slug = 'options-general.php';

        add_submenu_page(
            $parent_slug,
            __('Frontdesk AI · Setup Wizard', 'foundation-frontdesk'),
            __('Frontdesk AI · Setup Wizard', 'foundation-frontdesk'),
            'manage_options',
            self::PAGE_SLUG,
            [__CLASS__, 'render_wizard_page']
        );

        // Hide menu entry if attached under Settings
        add_action('admin_head', function () use ($parent_slug) {
            if ($parent_slug === 'options-general.php') {
                echo '<style>#submenu a[href$="' . esc_attr(self::PAGE_SLUG) . '"]{display:none!important;}</style>';
            }
        });
    }

    /** Wizard page */
    public static function render_wizard_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 'foundation-frontdesk'));
        }

        $opts = get_option('fnd_conversa_options', []);
        $blog = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);

        // Wizard state
        $state = [
            // Identity & copy
            'brand_name'      => isset($opts['bot_name']) ? (string)$opts['bot_name'] : ($blog ?: 'Frontdesk AI'),
            'greeting'        => isset($opts['greeting_text']) ? (string)$opts['greeting_text'] : "Hi! I'm {bot_name}. How can I help today?\nOpening hours: {hours}\nYou can also {contact}.",
            'opening'         => '',
            'tips'            => '',

            // Colours
            'header_bg'       => isset($opts['ui_header_bg'])         ? (string)$opts['ui_header_bg']         : '#1e6167',
            'header_text'     => isset($opts['ui_header_text'])       ? (string)$opts['ui_header_text']       : '#ffffff',
            'button_bg'       => isset($opts['ui_button_color'])      ? (string)$opts['ui_button_color']      : '#04ad93',
            'button_text'     => isset($opts['ui_button_text_color']) ? (string)$opts['ui_button_text_color'] : '#07332f',
            'body_text'       => isset($opts['ui_text_color'])        ? (string)$opts['ui_text_color']        : '#0f172a',

            // Position
            'position'        => (function($c){ $c = $c ?: 'bottom_right'; return str_replace('_','-',$c); })(isset($opts['ui_position_corner']) ? (string)$opts['ui_position_corner'] : 'bottom_right'),

            // Provider (single source of truth)
            'provider'        => isset($opts['fd_provider']) ? (string)$opts['fd_provider'] : 'offline', // openai|gemini|offline
            'openai_api_key'  => isset($opts['openai_api_key']) ? (string)$opts['openai_api_key'] : (isset($opts['api_key']) ? (string)$opts['api_key'] : ''),
            'gemini_api_key'  => isset($opts['gemini_api_key']) ? (string)$opts['gemini_api_key'] : '',

            // Launcher / Help Centre
            'teaser_title'    => isset($opts['teaser_title'])    ? (string)$opts['teaser_title']    : __('Got questions? Let us help.','foundation-frontdesk'),
            'kb_button_label' => isset($opts['kb_button_label']) ? (string)$opts['kb_button_label'] : __('Knowledge Base','foundation-frontdesk'),
            'kb_mode'         => isset($opts['kb_mode'])         ? (string)$opts['kb_mode']         : 'faqs',
            'kb_url'          => isset($opts['kb_url'])          ? (string)$opts['kb_url']          : '',
        ];

        $nonce = wp_create_nonce('fnd_conversa_wizard_save');
        ?>
        <div class="wrap fnd-conversa-wizard-wrap" id="fnd-conversa-wizard" data-theme="light" style="margin:0;">
            <style>
                :root{
                  /* LIGHT */
                  --fnd-bg:#eef2f7; --fnd-panel:#ffffff; --fnd-panel-alt:#f8fafc; --fnd-text:#0f172a;
                  --fnd-muted:#64748b; --fnd-accent:#04ad93; --fnd-border:#e5e7eb; --fnd-focus:#4c9ffe;
                  --fnd-secondary:#e9eef5; --fnd-preview-bg:var(--fnd-panel); --fnd-contrast:#0f172a;
                }
                /* DARK */
                #fnd-conversa-wizard[data-theme="dark"]{
                  --fnd-bg:#0c111a; --fnd-panel:#161b22; --fnd-panel-alt:#1c2330; --fnd-text:#f5f7fb;
                  --fnd-muted:#9aa4b2; --fnd-accent:#04ad93; --fnd-border:#2a2f3c; --fnd-focus:#4c9ffe;
                  --fnd-secondary:#2b3243; --fnd-preview-bg:#0d0f14; --fnd-contrast:#e6e8ee;
                }

                /* FULLSCREEN overlay */
                .fnd-conversa-wizard-wrap{position:fixed;inset:0;background:var(--fnd-bg);z-index:999999;color:var(--fnd-text);
                  font:16px/1.5 system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,"Helvetica Neue",Arial,"Noto Sans","Apple Color Emoji","Segoe UI Emoji";
                  overflow:auto;}
                #wpcontent,#wpbody-content{padding:0!important}
                #adminmenumain,#wpadminbar,#screen-meta,#screen-meta-links{display:none!important}
                html.wp-toolbar{padding-top:0!important}

                .fnd-wiz-shell{max-width:95%;margin:35px;padding:0 16px}
                .fnd-wiz-header{display:flex;align-items:center;gap:12px;justify-content:space-between;margin:4px 0 14px}
                .fnd-wiz-title{font-size:1.65rem;font-weight:800;letter-spacing:.2px}

                .fnd-wiz-steps{display:grid;grid-template-columns:repeat(6,1fr);gap:6px;margin:12px 0 18px}
                .fnd-wiz-stepdot{height:8px;border-radius:999px;background:#cbd5e1;transition:background .2s ease}
                #fnd-conversa-wizard[data-theme="dark"] .fnd-wiz-stepdot{background:#2f3545}
                #fnd-conversa-wizard .fnd-wiz-stepdot.is-active{background:var(--fnd-accent)!important}

                .fnd-card{background:var(--fnd-panel);border:1px solid var(--fnd-border);border-radius:16px;padding:20px;
                  box-shadow:0 10px 30px rgba(0,0,0,.08);overflow:hidden}
                #fnd-conversa-wizard[data-theme="dark"] .fnd-card{box-shadow:0 10px 30px rgba(0,0,0,.35)}
                .fnd-wiz-body{min-height:420px;max-height:calc(100vh - 240px);overflow:auto}

                /* Grid spacing */
                .fnd-row{display:grid;gap:15px;column-gap:35px;margin:12px 0}
                @media (min-width:760px){
                  .fnd-row.cols-2{grid-template-columns:1.5fr 1fr}
                  .fnd-row.cols-3{grid-template-columns:repeat(3,1fr)}
                }

                label.fnd-label{font-weight:700;color:var(--fnd-text);margin-bottom:6px;display:inline-block}
                .fnd-input,.fnd-select,.fnd-textarea{width:100%;background:var(--fnd-panel-alt);color:var(--fnd-text);
                  border:1px solid var(--fnd-border);border-radius:12px;padding:12px 14px;outline:none}
                .fnd-input:focus-visible,.fnd-select:focus-visible,.fnd-textarea:focus-visible{border-color:var(--fnd-focus);box-shadow:0 0 0 3px rgba(76,159,254,.25)}
                .fnd-help{color:var(--fnd-muted);font-size:.92rem;margin-top:6px}
                .fnd-swatch-row{display:flex;flex-wrap:wrap;gap:8px;margin-top:8px}
                .fnd-swatch{width:32px;height:32px;border-radius:8px;border:1px solid var(--fnd-border);cursor:pointer}
                .fnd-swatch:focus-visible{outline:3px solid var(--fnd-focus);outline-offset:2px}

                .fnd-btnbar{display:flex;justify-content:space-between;gap:12px;margin-top:18px}
                .fnd-btn{border:0;border-radius:12px;padding:12px 16px;cursor:pointer;font-weight:800}
                .fnd-btn:focus-visible{outline:3px solid var(--fnd-focus);outline-offset:2px}
                .fnd-btn-primary{background:var(--fnd-accent);color:#ffffff}
                .fnd-btn-primary:hover{filter:brightness(0.95)}
                .fnd-btn-secondary{background:var(--fnd-secondary);color:var(--fnd-text)}
                .fnd-btn-ghost{background:transparent;color:var(--fnd-muted)}
                .fnd-right{margin-left:auto}

                /* PREVIEW COLUMN: sticky on wide screens */
                .fnd-preview-col{align-self:start}
                @media (min-width: 1100px){
                  .fnd-preview-col{position:sticky;top:0}
                  .fnd-preview-col .fnd-live-preview{height:calc(100vh - 300px);min-height:520px}
                }

                /* Live chat preview (replicates real chatbox) */
                .fnd-live-preview{border-radius:18px;border:1px solid var(--fnd-border);background:var(--fnd-preview-bg);overflow:hidden}
                .fnd-chat{display:flex;flex-direction:column;height:100%;background:#f6f9fb}
                #fnd-conversa-wizard[data-theme="dark"] .fnd-chat{background:#0e141a}
                .fnd-chat-header{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 16px;border-bottom:1px solid var(--fnd-border)}
                .fnd-chat-meta{display:flex;align-items:center;gap:12px}
                .fnd-chat-logo{width:38px;height:38px;border-radius:10px;overflow:hidden;background:#fff;display:grid;place-items:center}
                .fnd-chat-title{font-weight:800}
                .fnd-chat-sub{font-size:.85rem;opacity:.9}
                .fnd-pill{padding:6px 10px;border-radius:999px;font-weight:700;font-size:.78rem;background:#ee744d;color:#fff;opacity:.95}
                .fnd-actions{display:flex;align-items:center;gap:10px;}
                .fnd-kb-btn{padding:8px 12px;border-radius:12px;border:1px solid rgba(255,255,255,.25);font-weight:700;background:rgba(255,255,255,.1)}
                .fnd-kb-btn.alt{border-color:rgba(0,0,0,.12);background:rgba(0,0,0,.06)}
                .fnd-icon-btn{width:36px;height:36px;border-radius:10px;border:1px solid rgba(255,255,255,.25);background:rgba(255,255,255,.1);display:grid;place-items:center}
                .fnd-icon-btn.alt{border-color:rgba(0,0,0,.12);background:rgba(0,0,0,.06)}
                .fnd-chat-body{padding:14px 16px;flex:1;overflow:auto;color:var(--fnd-contrast)}
                .fnd-bubble{background:#eef1f5;border:1px solid #e2e6ee;border-radius:14px;padding:14px 16px;color:#0f172a;max-width:520px}
                #fnd-conversa-wizard[data-theme="dark"] .fnd-bubble{background:#121922;border-color:#222b38;color:#e6e8ee}
                .fnd-bubble-time{font-size:.8rem;color:#8a94a6;margin-top:6px}
                .fnd-chat-footer{border-top:1px solid var(--fnd-border);padding:16px;background:rgba(0,0,0,0.02)}
                #fnd-conversa-wizard[data-theme="dark"] .fnd-chat-footer{background:rgba(255,255,255,0.02)}
                .fnd-composer{display:grid;grid-template-columns:1fr auto;gap:16px;align-items:center;margin-bottom:16px}
                .fnd-composer textarea{resize:vertical;min-height:40px;max-height:45px;padding:10px 12px;border-radius:10px;border:1px solid #d6dde5;background:#fff;color:#0f172a;font:inherit}
                #fnd-conversa-wizard[data-theme="dark"] .fnd-composer textarea{background:#0f141b;border-color:#2a3443;color:#e8eaef}
                .fnd-send{border-radius:12px;padding:13px 25px;font-weight:800;border:0}
                .fnd-bottom-cta{width:100%;display:block;border-radius:14px;padding:14px 16px;text-align:center;font-weight:800;border:0}

                /* Headings readable in dark mode */
                #fnd-conversa-wizard h1,#fnd-conversa-wizard h2,#fnd-conversa-wizard h3,#fnd-conversa-wizard h4,#fnd-conversa-wizard h5,#fnd-conversa-wizard h6{color:var(--fnd-text)}
                #fnd-conversa-wizard[data-theme="dark"] h1,#fnd-conversa-wizard[data-theme="dark"] h2,#fnd-conversa-wizard[data-theme="dark"] h3,#fnd-conversa-wizard[data-theme="dark"] h4,#fnd-conversa-wizard[data-theme="dark"] h5,#fnd-conversa-wizard[data-theme="dark"] h6{color:var(--fnd-text)!important}

                /* Step hero */
                .fnd-step-hero{background:var(--fnd-accent);color:#fff;padding:16px 20px;margin:-20px -20px 16px}
                .fnd-step-hero h2{color:#fff!important;margin:0 0 6px 0;font-weight:800}
                .fnd-step-hero p{color:#fff;opacity:.96;margin:0}

                /* Review summary */
                .fnd-summary{background:var(--fnd-panel-alt);border:1px dashed var(--fnd-border);border-radius:14px;padding:18px}
                .fnd-kv{display:grid;grid-template-columns:auto 1fr;gap:10px 18px;align-items:start}
                .fnd-kv dt{font-weight:700;opacity:.95}
                .fnd-kv dd{margin:0}
                .fnd-chips{display:flex;flex-wrap:wrap;gap:8px;margin-top:8px}
                .fnd-chip{display:inline-flex;align-items:center;gap:8px;padding:8px 10px;border-radius:999px;border:1px solid var(--fnd-border);background:var(--fnd-panel)}
                .fnd-chip-solid{border:none}
                .fnd-chip-solid .chip-label{font-weight:800;font-size:.85rem}
                .fnd-mini-link{font-size:.85rem;margin-left:8px}
                .fnd-change{font-size:.85rem;padding:6px 10px;border-radius:8px;background:var(--fnd-secondary);border:0;cursor:pointer}

                /* Utilities */
                .sr-only{position:absolute!important;height:1px;width:1px;overflow:hidden;clip:rect(1px,1px,1px,1px);white-space:nowrap;border:0;padding:0;margin:-1px}
                .fnd-danger{color:#d14343}.fnd-success{color:#159f6b}.fnd-divider{height:1px;background:var(--fnd-border);margin:14px 0}

                @media (prefers-reduced-motion: reduce){
                  *{animation-duration:.001ms!important;animation-iteration-count:1!important;transition-duration:.001ms!important;scroll-behavior:auto!important;}
                }
            </style>

            <div class="fnd-wiz-shell" role="application" aria-labelledby="fnd-wiz-title">
                <div class="fnd-wiz-header">
                    <div class="fnd-wiz-title" id="fnd-wiz-title"><?php echo esc_html__('Frontdesk AI · Setup Wizard ✨','foundation-frontdesk'); ?></div>
                    <div style="display:flex;gap:10px;align-items:center">
                        <button class="fnd-btn fnd-btn-ghost" id="fnd-theme-toggle" aria-pressed="false"><?php esc_html_e('Dark mode','foundation-frontdesk'); ?></button>
                        <a class="fnd-btn fnd-btn-ghost" href="<?php echo esc_url(admin_url('admin.php?page=foundation-conversa-settings')); ?>"><?php esc_html_e('Skip for now','foundation-frontdesk'); ?></a>
                    </div>
                </div>

                <div class="fnd-wiz-steps" aria-hidden="true">
                    <div class="fnd-wiz-stepdot is-active" data-stepdot="0"></div>
                    <div class="fnd-wiz-stepdot" data-stepdot="1"></div>
                    <div class="fnd-wiz-stepdot" data-stepdot="2"></div>
                    <div class="fnd-wiz-stepdot" data-stepdot="3"></div>
                    <div class="fnd-wiz-stepdot" data-stepdot="4"></div>
                    <div class="fnd-wiz-stepdot" data-stepdot="5"></div>
                </div>

                <div id="fnd-progress-aria" class="sr-only" role="status" aria-live="polite"></div>

                <div class="fnd-card fnd-wiz-body" id="fnd-wiz-body" tabindex="-1" aria-live="polite"></div>

                <div class="fnd-btnbar">
                    <button class="fnd-btn fnd-btn-secondary" id="fnd-prev" disabled aria-disabled="true">← <?php esc_html_e('Back','foundation-frontdesk'); ?></button>
                    <div class="fnd-right">
                        <button class="fnd-btn fnd-btn-primary" id="fnd-next"><?php esc_html_e('Next →','foundation-frontdesk'); ?></button>
                        <button class="fnd-btn fnd-btn-primary" id="fnd-finish" style="display:none;"><?php esc_html_e('Finish & Apply ✅','foundation-frontdesk'); ?></button>
                    </div>
                </div>
                <div class="fnd-help" id="fnd-save-status" role="status" aria-live="polite"></div>
            </div>
        </div>

        <script>
        (function(){
            const initial = <?php echo wp_json_encode($state); ?>;
            const state = { step:0, totalSteps:6, data: initial, nonce:'<?php echo esc_js($nonce); ?>' };

            const el = {
                root: document.getElementById('fnd-conversa-wizard'),
                body: document.getElementById('fnd-wiz-body'),
                prev: document.getElementById('fnd-prev'),
                next: document.getElementById('fnd-next'),
                finish: document.getElementById('fnd-finish'),
                dots: document.querySelectorAll('[data-stepdot]'),
                status: document.getElementById('fnd-save-status'),
                themeBtn: document.getElementById('fnd-theme-toggle'),
                progressAria: document.getElementById('fnd-progress-aria'),
            };

            // THEME toggle (default Light, persisted)
            const THEME_KEY = 'fnd_wizard_theme';
            function applyTheme(mode){
                const m = (mode === 'dark') ? 'dark' : 'light';
                el.root.setAttribute('data-theme', m);
                if (el.themeBtn){
                    el.themeBtn.textContent = (m === 'dark') ? 'Light mode' : 'Dark mode';
                    el.themeBtn.setAttribute('aria-pressed', (m === 'dark') ? 'true' : 'false');
                }
                try{ localStorage.setItem(THEME_KEY, m); }catch(e){}
            }
            let mode = 'light';
            try { mode = localStorage.getItem(THEME_KEY) || 'light'; } catch(e){}
            applyTheme(mode);
            el.themeBtn?.addEventListener('click', ()=> applyTheme(el.root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark'));

            const SWATCHES = ['#DF157C','#179AD6','#F4C946','#7C59A9','#83BE56','#DE7450','#1e6167','#04ad93','#0f172a','#ffffff','#000000'];

            function stepDotsUpdate(){
                el.dots.forEach((d,i)=>d.classList.toggle('is-active', i<=state.step));
                el.prev.disabled = state.step===0;
                el.prev.setAttribute('aria-disabled', state.step===0?'true':'false');
                el.next.style.display = (state.step === state.totalSteps-1) ? 'none' : '';
                el.finish.style.display = (state.step === state.totalSteps-1) ? '' : 'none';
                el.progressAria.textContent = 'Step ' + (state.step+1) + ' of ' + state.totalSteps;
            }
            function setStep(i){ state.step = Math.max(0, Math.min(state.totalSteps-1, i)); render(); el.body.focus(); }

            function row(label, id, help, html){
                return `<div class="fnd-row"><label for="${id}" class="fnd-label">${label}</label>${html}${help?`<div class="fnd-help">${help}</div>`:''}</div>`;
            }
            function colorRow(label, key, help){
                const id = 'c_'+key, val = state.data[key] || '#000000';
                const sw = SWATCHES.map(c=>`<button type="button" class="fnd-swatch" data-color="${c}" aria-label="Use ${c}" style="background:${c}" tabindex="0"></button>`).join('');
                return `
                  <div class="fnd-row">
                    <label for="${id}" class="fnd-label">${label}</label>
                    <div class="fnd-row cols-3">
                      <input id="${id}" class="fnd-input" type="color" value="${val}" data-key="${key}">
                      <input class="fnd-input" type="text" value="${val}" data-key="${key}" data-mirror="#${id}" aria-label="${label} hex">
                      <div class="fnd-swatch-row">${sw}</div>
                    </div>
                    ${help?`<div class="fnd-help">${help}</div>`:''}
                  </div>`;
            }

            // Color contrast helpers
            function hexToRgb(h){ const r=/^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(h); return r?{r:parseInt(r[1],16),g:parseInt(r[2],16),b:parseInt(r[3],16)}:null; }
            function relL(c){ const a=[c.r,c.g,c.b].map(v=>{v/=255;return v<=0.03928? v/12.92 : Math.pow((v+0.055)/1.055,2.4)}); return 0.2126*a[0]+0.7152*a[1]+0.0722*a[2]; }
            function contrastRatio(fg,bg){ const F=hexToRgb(fg), B=hexToRgb(bg); if(!F||!B) return null; const L1=relL(F), L2=relL(B); const l1=Math.max(L1,L2), l2=Math.min(L1,L2); return (l1+0.05)/(l2+0.05); }
            function bestTextOn(bgHex){
              const white = '#FFFFFF', black = '#0F172A';
              const rW = contrastRatio(white, bgHex) || 0;
              const rB = contrastRatio(black, bgHex) || 0;
              return rW >= rB ? white : black;
            }
            function colorChip(label, color){
              const txt = bestTextOn(color);
              return `<span class="fnd-chip fnd-chip-solid" style="background:${color};color:${txt}"><span class="chip-label">${label}</span></span>`;
            }
            function keyMask(v){ return v ? '••••••••' : '—'; }
            function humanKbMode(mode){ return (mode==='faqs') ? 'Show FAQs in chat' : 'Open a web page'; }

            // Accurate chat preview
            function preview(){
                const hbg   = state.data.header_bg   || '#1e6167';
                const htx   = state.data.header_text || '#ffffff';
                const btnBg = state.data.button_bg   || '#04ad93';
                const btnTx = state.data.button_text || '#07332f';
                const bodyTx= state.data.body_text   || '#0f172a';
                const name  = state.data.brand_name  || 'Frontdesk AI';
                const greet = (state.data.greeting || '').replace('{bot_name}', name);
                const offline = (state.data.provider || 'offline') === 'offline';
                const badgeText = offline ? 'Offline (admin)' : 'Online';
                const kbLabel = state.data.kb_button_label || 'Knowledge Base';
                const kbAlt = !(htx.toLowerCase()==='#fff'||htx.toLowerCase()==='#ffffff');

                return `
                  <div class="fnd-live-preview">
                    <div class="fnd-chat">
                      <div class="fnd-chat-header" style="background:${hbg};color:${htx}">
                        <div class="fnd-chat-meta">
                          <div class="fnd-chat-logo" aria-hidden="true">
                            <svg width="22" height="22" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="${btnBg}"/></svg>
                          </div>
                          <div>
                            <div class="fnd-chat-title" style="color:${htx}">${name}</div>
                            <div class="fnd-chat-sub" style="color:${htx};opacity:.9">Foundation by <u>Inkfire Ltd</u></div>
                          </div>
                          <span class="fnd-pill" aria-label="${badgeText} status">${badgeText}</span>
                        </div>
                        <div class="fnd-actions">
                          <button type="button" class="fnd-kb-btn ${kbAlt?'alt':''}" style="color:${htx}">${kbLabel}</button>
                          <button type="button" class="fnd-icon-btn ${kbAlt?'alt':''}" aria-label="Menu" title="Menu">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="${htx}" aria-hidden="true">
                              <circle cx="6" cy="12" r="2"></circle>
                              <circle cx="12" cy="12" r="2"></circle>
                              <circle cx="18" cy="12" r="2"></circle>
                            </svg>
                          </button>
                        </div>
                      </div>

                      <div class="fnd-chat-body" style="color:${bodyTx}">
                        <div class="fnd-bubble">
                          <div style="font-weight:700;margin-bottom:6px">${greet.split('\n')[0] || ''}</div>
                          <div>${greet.split('\n').slice(1).join('<br>')}</div>
                          <div class="fnd-bubble-time">2:22 am</div>
                        </div>
                      </div>

                      <div class="fnd-chat-footer">
                        <div class="fnd-composer">
                          <textarea aria-label="Type your message" placeholder="Type your message..."></textarea>
                          <button class="fnd-send" style="background:${btnBg};color:${btnTx}">Send</button>
                        </div>
                        <button class="fnd-bottom-cta" style="background:${btnBg};color:${btnTx}">Contact us</button>
                      </div>
                    </div>
                  </div>`;
            }

            function hero(title, subtitle){
              return `<div class="fnd-step-hero"><h2>${title}</h2>${subtitle ? `<p>${subtitle}</p>` : ``}</div>`;
            }

            function copySummaryToClipboard(){
              const s = state.data;
              const lines = [
                `Name: ${s.brand_name || ''}`,
                `Reply mode: ${s.provider==='offline' ? 'Offline (no external AI)' : `Online (${s.provider})`}`,
                `OpenAI key: ${keyMask(s.openai_api_key)}`,
                `Gemini key: ${keyMask(s.gemini_api_key)}`,
                `Chat button position: ${s.position || 'bottom-right'}`,
                `Launcher: ${s.teaser_title || ''} — ${s.kb_button_label || 'Knowledge Base'} (${humanKbMode(s.kb_mode)}${s.kb_mode==='url' && s.kb_url ? `: ${s.kb_url}`:''})`,
                `Colours: header ${s.header_bg}/${s.header_text}, button ${s.button_bg}/${s.button_text}, body text ${s.body_text}`,
                `Greeting:\n${s.greeting || ''}`,
              ];
              const txt = lines.join('\n');
              navigator.clipboard.writeText(txt).then(()=>{
                el.status.textContent = 'Summary copied to clipboard.';
                setTimeout(()=> el.status.textContent = '', 2000);
              }).catch(()=>{});
            }

            function render(){
                stepDotsUpdate();
                const s = state.step;
                let html = '';

                if (s===0){
                    html = `
                      ${hero('Welcome! 👋 Let’s get your assistant ready.',
                             'We’ll set a name, colours, where the chat button goes, how replies are generated, and what your Help Centre button does. You can change anything later.')}
                      <div class="fnd-divider"></div>
                      <div class="fnd-row cols-2">
                        <div>
                          ${row('Assistant name','f_name','Use your brand or product name. Example: “Inkfire Support”.',
                          `<input id="f_name" class="fnd-input" type="text" value="${state.data.brand_name||''}" data-key="brand_name" placeholder="Frontdesk AI">`)}
                          ${row('Greeting message','f_greeting','This is the first message people see at the top of the chat.',
                          `<textarea id="f_greeting" class="fnd-textarea" rows="3" data-key="greeting">`+(state.data.greeting||'')+`</textarea>`)}
                          ${row('Chat button position','f_pos','Where the floating chat button appears on your site.',
                          `<select id="f_pos" class="fnd-select" data-key="position">
                              <option value="bottom-right"${state.data.position==='bottom-right'?' selected':''}>Bottom right</option>
                              <option value="bottom-left"${state.data.position==='bottom-left'?' selected':''}>Bottom left</option>
                              <option value="top-right"${state.data.position==='top-right'?' selected':''}>Top right</option>
                              <option value="top-left"${state.data.position==='top-left'?' selected':''}>Top left</option>
                          </select>`)}
                        </div>
                        <div class="fnd-preview-col">${preview()}</div>
                      </div>`;
                }

                if (s===1){
                    html = `
                      ${hero('Choose colours. The preview updates as you go.','')}
                      <div class="fnd-row cols-2">
                        <div>
                          <div style="display:flex;gap:8px;align-items:center;margin-bottom:4px;">
                            <strong>Theme colours</strong>
                            <button type="button" class="fnd-change" id="fnd-reset-colors">Reset to defaults</button>
                          </div>
                          ${colorRow('Header background colour','header_bg','This is the top bar colour of the chat.')}
                          ${colorRow('Header text colour','header_text','Text shown on the top bar.')}
                          ${colorRow('Button background colour','button_bg','The main action button colour.')}
                          ${colorRow('Button text colour','button_text','The label colour on the main buttons.')}
                          ${colorRow('Body text colour','body_text','General chat text colour.')}
                          <div id="btn-contrast-note" class="fnd-help" role="note"></div>
                        </div>
                        <div class="fnd-preview-col">${preview()}</div>
                      </div>`;
                }

                if (s===2){
                    const prov = state.data.provider || 'offline';
                    const offlineHelp = 'Offline mode answers from your site content, FAQs, and built‑in rules. No data leaves your site. Great for privacy, staging, or if you don’t have keys yet.';
                    html = `
                      ${hero('How should replies be generated? 🧠','Offline: No external AI. Works without any keys. Online: Uses an AI provider (OpenAI or Google Gemini).')}
                      <div class="fnd-row cols-2">
                        <div>
                          ${row('AI provider','f_provider','Pick a provider. Choose “Offline” to keep everything on your site.',
                          `<select id="f_provider" class="fnd-select" data-key="provider">
                              <option value="openai"${prov==='openai'?' selected':''}>OpenAI</option>
                              <option value="gemini"${prov==='gemini'?' selected':''}>Google Gemini</option>
                              <option value="offline"${prov==='offline'?' selected':''}>Offline</option>
                          </select>
                          <div class="fnd-help" style="margin-top:8px">${offlineHelp}</div>`)}
                          
                          <div id="api-fields" ${prov==='offline' ? 'style="display:none"' : ''}>
                            ${row('OpenAI API key','f_openai','Required only if you choose OpenAI. <a class="fnd-mini-link" href="https://platform.openai.com/api-keys" target="_blank" rel="noopener">Get an OpenAI API key ↗</a>',
                            `<input id="f_openai" class="fnd-input" type="password" autocomplete="new-password" data-key="openai_api_key" value="${state.data.openai_api_key ? '••••••••' : ''}" placeholder="sk-...">`)}
                            
                            ${row('Google Gemini API key','f_gem','Required only if you choose Gemini. <a class="fnd-mini-link" href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener">Get a Gemini API key ↗</a>',
                            `<input id="f_gem" class="fnd-input" type="password" autocomplete="new-password" data-key="gemini_api_key" value="${state.data.gemini_api_key ? '••••••••' : ''}" placeholder="AIza...">`)}
                            
                            <div class="fnd-help">Your keys are stored in your WordPress database. We don’t send them anywhere else.</div>
                          </div>
                        </div>
                        <div class="fnd-preview-col">${preview()}</div>
                      </div>`;
                }

                if (s===3){
                    html = `
                      ${hero('Launcher card & Help Centre 📚','Control the small card near the chat button and what the Help Centre button does.')}
                      <div class="fnd-row cols-2">
                        <div>
                          ${row('Launcher headline','f_teaser','A short friendly line to invite people to chat.',
                          `<input id="f_teaser" class="fnd-input" type="text" data-key="teaser_title" value="${state.data.teaser_title||''}" placeholder="Got questions? Let us help.">`)}
                          ${row('Help Centre button label','f_kbl','Text on the Help Centre button.',
                          `<input id="f_kbl" class="fnd-input" type="text" data-key="kb_button_label" value="${state.data.kb_button_label||''}" placeholder="Knowledge Base">`)}
                          ${row('When someone clicks Help Centre…','f_kbm','Choose what happens.',
                          `<select id="f_kbm" class="fnd-select" data-key="kb_mode">
                              <option value="faqs"${state.data.kb_mode==='faqs'?' selected':''}>Show FAQs inside the chat</option>
                              <option value="url"${state.data.kb_mode==='url'?' selected':''}>Open a web page</option>
                          </select>`)}
                          ${row('Help Centre web page (URL)','f_kbu','Only used if “Open a web page” is selected.',
                          `<input id="f_kbu" class="fnd-input" type="url" data-key="kb_url" value="${state.data.kb_url||''}" placeholder="https://example.com/help">`)}
                        </div>
                        <div class="fnd-preview-col">${preview()}</div>
                      </div>`;
                }

                if (s===4){
                    html = `
                      ${hero('Optional prompts 💡','')}
                      <div class="fnd-row cols-2">
                        <div>
                          ${row('Placeholder text in the message box','f_opening','Optional. Shown inside the chat input before the user types.',
                          `<input id="f_opening" class="fnd-input" type="text" data-key="opening" value="${state.data.opening||''}" placeholder="Ask me anything about our services">`)}
                          ${row('Helpful tips (one per line)','f_tips','Optional. Short tips shown to help people get started.',
                          `<textarea id="f_tips" class="fnd-textarea" rows="4" data-key="tips">`+(state.data.tips||'')+`</textarea>`)}
                        </div>
                        <div class="fnd-preview-col">${preview()}</div>
                      </div>`;
                }

                if (s===5){
                  const sdata = state.data;
                  const maskedOA = keyMask(sdata.openai_api_key);
                  const maskedGM = keyMask(sdata.gemini_api_key);
                  const modeText = sdata.provider==='offline' ? 'Offline (no external AI)' : `Online (${sdata.provider})`;
                  const chips = [
                    colorChip('Header', sdata.header_bg || '#1e6167'),
                    colorChip('Header text', sdata.header_text || '#ffffff'),
                    colorChip('Button', sdata.button_bg || '#04ad93'),
                    colorChip('Button text', sdata.button_text || '#07332f'),
                    colorChip('Body text', sdata.body_text || '#0f172a'),
                  ].join('');

                  function changeBtn(label, step){ return `<button type="button" class="fnd-change" data-go-step="${step}">Change</button>`; }

                  html = `
                    ${hero('Review & finish ✅','')}
                    <div class="fnd-row cols-2">
                      <div>
                        <div class="fnd-summary">
                          <div style="display:flex;justify-content:flex-end;margin-bottom:8px;">
                            <button type="button" class="fnd-change" id="fnd-copy-summary">Copy summary</button>
                          </div>
                          <dl class="fnd-kv">
                            <dt>Name:</dt><dd>${sdata.brand_name||''} ${changeBtn('Change',0)}</dd>
                            <dt>Reply mode:</dt><dd>${modeText} ${changeBtn('Change',2)}</dd>
                            <dt>OpenAI key:</dt><dd>${maskedOA} ${changeBtn('Change',2)}</dd>
                            <dt>Gemini key:</dt><dd>${maskedGM} ${changeBtn('Change',2)}</dd>
                            <dt>Chat button position:</dt><dd>${sdata.position||'bottom-right'} ${changeBtn('Change',0)}</dd>
                            <dt>Launcher:</dt>
                            <dd>
                              ${sdata.teaser_title || '—'} —
                              <em>${sdata.kb_button_label || 'Knowledge Base'}</em>
                              (${humanKbMode(sdata.kb_mode)}${sdata.kb_mode==='url' && sdata.kb_url ? `: ${sdata.kb_url}`:''})
                              ${changeBtn('Change',3)}
                            </dd>
                            <dt>Colours:</dt>
                            <dd><div class="fnd-chips">${chips}</div> ${changeBtn('Change',1)}</dd>
                            <dt>Greeting:</dt>
                            <dd><div style="white-space:pre-wrap">${sdata.greeting||''}</div> ${changeBtn('Change',0)}</dd>
                          </dl>
                        </div>
                      </div>
                      <div class="fnd-preview-col">${preview()}</div>
                    </div>`;
                }

                el.body.innerHTML = html;

                // Attach step jumpers
                el.body.querySelectorAll('[data-go-step]').forEach(b=>{
                  b.addEventListener('click', ()=> setStep(parseInt(b.getAttribute('data-go-step'),10)));
                });

                // Copy summary
                const copyBtn = el.body.querySelector('#fnd-copy-summary');
                if (copyBtn) copyBtn.addEventListener('click', copySummaryToClipboard);

                // Reset colours
                const resetBtn = el.body.querySelector('#fnd-reset-colors');
                if (resetBtn){
                  resetBtn.addEventListener('click', ()=>{
                    state.data.header_bg   = '#1e6167';
                    state.data.header_text = '#ffffff';
                    state.data.button_bg   = '#04ad93';
                    state.data.button_text = '#07332f';
                    state.data.body_text   = '#0f172a';
                    render();
                  });
                }

                // Button contrast note
                (function(){
                  const note = el.body.querySelector('#btn-contrast-note');
                  if(!note) return;
                  function update(){
                    const bg = state.data.button_bg || '#04ad93';
                    const fg = state.data.button_text || '#07332f';
                    const r  = contrastRatio(fg,bg);
                    if(!r) return;
                    const ratio = r.toFixed(2);
                    note.innerHTML = (r < 4.5)
                      ? `⚠️ Button text contrast is ${ratio}:1 (below 4.5:1). Consider a darker/lighter label colour.`
                      : `✅ Button text contrast is ${ratio}:1.`;
                  }
                  update();
                  el.body.querySelectorAll('[data-key="button_bg"],[data-key="button_text"]').forEach(i=> i.addEventListener('input', update));
                })();

                // Inputs
                el.body.querySelectorAll('[data-key]').forEach(inp=>{
                    const key = inp.getAttribute('data-key');
                    inp.addEventListener('input', e=>{
                        if (inp.matches('#f_provider')) {
                            state.data.provider = e.target.value;
                            // Toggle API block
                            const block = el.body.querySelector('#api-fields');
                            if (block) block.style.display = (state.data.provider==='offline') ? 'none' : '';
                            render();
                            return;
                        }
                        if (inp.type === 'password'){
                            if (e.target.value && e.target.value !== '••••••••') state.data[key] = e.target.value.trim();
                        } else {
                            // checkbox not used anymore
                            state.data[key] = e.target.value;
                        }
                        const mirrorSel = inp.getAttribute('data-mirror');
                        if (mirrorSel){
                            const mirror = el.body.querySelector(mirrorSel);
                            if (mirror) mirror.value = e.target.value;
                        }
                        const mate = el.body.querySelector('[data-key="'+key+'"]:not(#'+(inp.id||'')+')');
                        if (mate && !mate.getAttribute('data-mirror')) mate.value = e.target.value;

                        if (['header_bg','header_text','button_bg','button_text','body_text','greeting','brand_name','provider','kb_button_label'].includes(key)){
                            render();
                        }
                    });
                });
                el.body.querySelectorAll('.fnd-swatch').forEach(b=>{
                    b.addEventListener('click', ()=>{
                        const color = b.getAttribute('data-color');
                        const cInput = b.closest('.fnd-row').querySelector('input[type="color"]');
                        const key = cInput ? cInput.getAttribute('data-key') : null;
                        if (!key) return;
                        state.data[key] = color; cInput.value = color;
                        const textHex = b.closest('.fnd-row').querySelector('input[type="text"][data-key="'+key+'"]'); if (textHex) textHex.value = color;
                        render();
                    });
                    b.addEventListener('keydown', (e)=>{ if(e.key==='Enter' || e.key===' '){ e.preventDefault(); b.click(); } });
                });
            }

            el.prev.addEventListener('click', ()=> setStep(state.step-1));
            el.next.addEventListener('click', ()=> setStep(state.step+1));
            document.addEventListener('keydown', e=>{
                if (e.key==='ArrowRight' && el.next.style.display!=='none') setStep(state.step+1);
                if (e.key==='ArrowLeft' && !el.prev.disabled) setStep(state.step-1);
            });

            // Save -> derive mode_offline from provider for compatibility
            el.finish.addEventListener('click', async function(){
                el.status.textContent = '<?php echo esc_js(__('Saving…','foundation-frontdesk')); ?>';
                try{
                    // keep compatibility with server expecting mode_offline flag
                    state.data.mode_offline = (state.data.provider === 'offline');

                    const form = new FormData();
                    form.append('action','fnd_conversa_save_wizard');
                    form.append('nonce', state.nonce);
                    form.append('payload', JSON.stringify(state.data));
                    const r = await fetch(ajaxurl,{method:'POST',credentials:'same-origin',body:form});
                    const j = await r.json();
                    if (!r.ok || !j || !j.success) throw new Error((j && j.data) ? j.data : 'Save failed');
                    el.status.innerHTML = '<span class="fnd-success"><?php echo esc_js(__('Saved! Redirecting…','foundation-frontdesk')); ?></span>';
                    setTimeout(()=>{ window.location = '<?php echo esc_url(admin_url('admin.php?page=foundation-conversa-settings')); ?>'; }, 600);
                }catch(err){
                    el.status.innerHTML = '<span class="fnd-danger">'+(err && err.message ? err.message : 'Error')+'</span>';
                }
            });

            // Init
            render();
        })();
        </script>
        <?php
    }

    /** AJAX: persist wizard data into fnd_conversa_options */
    public static function ajax_save_wizard() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'foundation-frontdesk'), 403);
        }
        check_ajax_referer('fnd_conversa_wizard_save', 'nonce');

        $payload = isset($_POST['payload']) ? json_decode(stripslashes((string)$_POST['payload']), true) : [];
        if (!is_array($payload)) wp_send_json_error(__('Invalid payload', 'foundation-frontdesk'), 400);

        // Helpers
        $hex  = function($v,$d){ $v=trim((string)$v); return preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i',$v)?$v:$d; };
        $bool = function($v){ if (is_bool($v)) return $v; if (is_numeric($v)) return (bool)$v; $v=strtolower(trim((string)$v)); return in_array($v,['1','true','on','yes'],true); };

        $prov_in  = sanitize_key($payload['provider'] ?? 'offline');             // openai|gemini|offline
        $mode_off = $bool($payload['mode_offline'] ?? ($prov_in==='offline'));   // keep backward-compat flag

        // Preserve existing options not touched by wizard
        $existing = get_option('fnd_conversa_options', []);

        $save = [
            // Provider
            'fd_provider'         => $prov_in,
            'fd_force_offline'    => $mode_off ? 1 : 0,

            // Keys (overwrite only if clear-text provided)
            'openai_api_key'      => (isset($payload['openai_api_key']) && $payload['openai_api_key'] && $payload['openai_api_key'] !== '••••••••')
                                      ? sanitize_text_field($payload['openai_api_key']) : ($existing['openai_api_key'] ?? ''),
            'gemini_api_key'      => (isset($payload['gemini_api_key']) && $payload['gemini_api_key'] && $payload['gemini_api_key'] !== '••••••••')
                                      ? sanitize_text_field($payload['gemini_api_key']) : ($existing['gemini_api_key'] ?? ''),

            // Identity & copy
            'bot_name'            => sanitize_text_field($payload['brand_name'] ?? 'Frontdesk AI'),
            'greeting_text'       => sanitize_textarea_field($payload['greeting'] ?? ''),

            // Colours
            'ui_header_bg'        => $hex(($payload['header_bg']  ?? ''),'#1e6167'),
            'ui_header_text'      => $hex(($payload['header_text']?? ''),'#ffffff'),
            'ui_button_color'     => $hex(($payload['button_bg']  ?? ''),'#04ad93'),
            'ui_button_text_color'=> $hex(($payload['button_text']?? ''),'#07332f'),
            'ui_text_color'       => $hex(($payload['body_text']  ?? ''),'#0f172a'),

            // Position
            'ui_position_corner'  => sanitize_key(str_replace('-','_', ($payload['position'] ?? 'bottom_right'))),

            // Launcher / Help Centre
            'teaser_title'        => sanitize_text_field($payload['teaser_title'] ?? ''),
            'kb_button_label'     => sanitize_text_field($payload['kb_button_label'] ?? ''),
            'kb_mode'             => in_array(($payload['kb_mode'] ?? 'faqs'), ['faqs','url'], true) ? $payload['kb_mode'] : 'faqs',
            'kb_url'              => esc_url_raw($payload['kb_url'] ?? ''),
        ];

        update_option('fnd_conversa_options', array_merge($existing ?: [], $save), false);
        wp_send_json_success(['saved'=>true]);
    }
}

if (class_exists('Foundation_Conversa_Onboarding')) {
    Foundation_Conversa_Onboarding::init();
}
