<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Foundation: Frontdesk AI — Setup Wizard
 *
 * Renders as a hidden overlay on the settings page.
 * Opened by JS (auto on first install, button any time after).
 * Saves via AJAX → Frontdesk_Config::save().
 *
 * NOT a separate admin page. No menu registration here.
 */
class Foundation_Frontdesk_Onboarding {

    const OPEN_FLAG  = 'fnd_frontdesk_wizard_open';
    const NONCE_KEY  = 'fnd_frontdesk_wizard_save';

    public static function init(): void {
        add_action( 'wp_ajax_fnd_frontdesk_save_wizard', [ __CLASS__, 'ajax_save' ] );
    }

    public static function on_activate(): void {
        update_option( self::OPEN_FLAG, '1', false );
    }

    // =========================================================================
    // Wizard HTML — called inline from the settings page render
    // =========================================================================

    public static function render_wizard_html( array $v, array $presets ): void {
        $nonce         = wp_create_nonce( self::NONCE_KEY );
        $settings_url  = esc_url( admin_url( 'admin.php?page=' . Foundation_Frontdesk_Admin::PAGE_SLUG ) );
        $blog          = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

        // JS state — keys match Frontdesk_Config exactly
        $state = [
            'bot_name'             => $v['bot_name']              ?: ( $blog ?: 'Frontdesk AI' ),
            'avatar_url'           => $v['avatar_url']            ?: '',
            'staff_photos'         => Frontdesk_Config::staff_photos_array( $v['staff_photos'] ?? '[]' ),
            'greeting_text'        => $v['greeting_text']         ?: "Hi! I'm {bot_name}. How can I help today?\nOpening hours: {hours}\nYou can also {contact}.",
            'ui_position_corner'   => str_replace( '_', '-', $v['ui_position_corner'] ?: 'bottom_right' ),
            'ui_header_bg'         => $v['ui_header_bg']          ?: '#1e6167',
            'ui_header_text'       => $v['ui_header_text']        ?: '#ffffff',
            'ui_button_color'      => $v['ui_button_color']       ?: '#04ad93',
            'ui_button_text_color' => $v['ui_button_text_color']  ?: '#ffffff',
            'provider'             => $v['provider']              ?: 'offline',
            'openai_api_key'       => $v['openai_key_saved'] ? '••••••••' : '',
            'fallback_enabled'     => (bool) $v['fallback_enabled'],
            'runtime_mode'         => $v['runtime_mode']          ?: 'faq_only',
            'teaser_title'         => $v['teaser_title']          ?: 'Got questions? Let us help.',
            'teaser_body'          => $v['teaser_body']           ?: 'Ask our assistant or browse the help centre.',
            'kb_button_label'      => $v['kb_button_label']       ?: 'Help Centre',
            'input_placeholder'    => $v['input_placeholder']     ?: 'Type your message…',
            'opening_hours'        => $v['opening_hours']         ?: "Mon\xe2\x80\x93Fri: 9am\xe2\x80\x935pm\nSat\xe2\x80\x93Sun: Closed",
            'alt_contact'          => $v['alt_contact']           ?: '',
            'contact_email'        => $v['contact_email']         ?: get_option( 'admin_email' ),
            'enable_contact'       => (bool) $v['enable_contact'],
            'contact_btn_color'    => $v['contact_btn_color']     ?: '',
            'contact_btn_text_color' => $v['contact_btn_text_color'] ?: '#ffffff',
            'recaptcha_site_key'   => $v['recaptcha_site_key']    ?: '',
            'recaptcha_secret_key' => '',
        ];
        ?>

        <?php /* Overlay wrapper — hidden by default, shown by JS */ ?>
        <div id="fnd-wizard-overlay"
             role="dialog" aria-modal="true" aria-labelledby="fnd-wiz-title"
             style="display:none;position:fixed;inset:0;z-index:999999;overflow:auto">

        <style>
            /* ── Wizard shell ─────────────────────────────────────────────── */
            #fnd-wizard-overlay{
              background:var(--fnd-wiz-bg,#eef2f7);
              font:16px/1.5 "Plus Jakarta Sans",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;
              color:var(--fnd-wiz-text,#0f172a);
            }
            #fnd-wizard-overlay[data-theme="dark"]{
              --fnd-wiz-bg:#0c111a;--fnd-wiz-panel:#161b22;--fnd-wiz-panel-alt:#1c2330;
              --fnd-wiz-text:#f5f7fb;--fnd-wiz-muted:#9aa4b2;--fnd-wiz-border:#2a2f3c;
              --fnd-wiz-secondary:#2b3243;
              --fnd-wiz-cp-body:#1a2030;--fnd-wiz-cp-bubble:#242c3d;--fnd-wiz-cp-footer:#161b26;
            }
            #fnd-wizard-overlay *{box-sizing:border-box}
            .wiz-shell{max-width:1100px;margin:0 auto;padding:32px 24px 60px}
            .wiz-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;gap:12px}
            .wiz-title{font-size:1.5rem;font-weight:800;color:var(--fnd-wiz-text,#0f172a)}
            .wiz-top-actions{display:flex;gap:8px;align-items:center}
            .wiz-progress{display:grid;grid-template-columns:repeat(6,1fr);gap:5px;margin-bottom:20px}
            .wiz-dot{height:7px;border-radius:999px;background:var(--fnd-wiz-border,#cbd5e1);transition:background .2s}
            .wiz-dot.active{background:#04ad93}
            .wiz-card{background:var(--fnd-wiz-panel,#fff);border:1px solid var(--fnd-wiz-border,#e5e7eb);
              border-radius:18px;padding:22px;box-shadow:0 10px 30px rgba(0,0,0,.07);
              min-height:440px;max-height:calc(100vh - 280px);overflow:auto}
            .wiz-nav{display:flex;justify-content:space-between;gap:12px;margin-top:18px;align-items:center}
            .wiz-hero{background:#04ad93;color:#fff;padding:16px 20px;margin:-22px -22px 18px;border-radius:18px 18px 0 0}
            .wiz-hero h2{margin:0 0 5px;font-weight:800;font-size:1.1rem;color:#fff}
            .wiz-hero p{margin:0;opacity:.94;font-size:.93rem}
            .wiz-row{display:grid;gap:14px;margin:12px 0}
            .wiz-cols-2{grid-template-columns:1.4fr 1fr}
            @media(max-width:900px){.wiz-cols-2{grid-template-columns:1fr}}
            .wiz-label{font-weight:700;font-size:13px;display:block;margin-bottom:5px;color:var(--fnd-wiz-text,#0f172a)}
            .wiz-input,.wiz-select,.wiz-textarea{width:100%;background:var(--fnd-wiz-panel-alt,#f8fafc);
              color:var(--fnd-wiz-text,#0f172a);border:1px solid var(--fnd-wiz-border,#e5e7eb);
              border-radius:11px;padding:11px 13px;outline:none;font:inherit}
            .wiz-input:focus,.wiz-select:focus,.wiz-textarea:focus{border-color:#04ad93;box-shadow:0 0 0 3px rgba(4,173,147,.18)}
            .wiz-textarea{min-height:82px;resize:vertical}
            .wiz-help{color:var(--fnd-wiz-muted,#64748b);font-size:.88rem;margin-top:5px}
            .wiz-divider{height:1px;background:var(--fnd-wiz-border,#e5e7eb);margin:14px 0}
            .wiz-check-row{display:flex;gap:8px;align-items:center;padding:4px 0}
            .wiz-check-row label{font-size:13px;font-weight:500;cursor:pointer}
            .wiz-swatch-grid{display:flex;flex-wrap:wrap;gap:7px;margin-top:8px}
            .wiz-swatch{width:30px;height:30px;border-radius:7px;border:1px solid var(--fnd-wiz-border,#e5e7eb);cursor:pointer}
            .wiz-color-row{display:grid;grid-template-columns:auto 1fr;gap:8px;align-items:center}
            .wiz-color-swatch{width:36px;height:36px;border-radius:8px;border:1px solid var(--fnd-wiz-border,#e5e7eb);cursor:pointer;padding:0}
            .wiz-summary{background:var(--fnd-wiz-panel-alt,#f8fafc);border:1px dashed var(--fnd-wiz-border,#e5e7eb);border-radius:14px;padding:16px}
            .wiz-kv{display:grid;grid-template-columns:auto 1fr;gap:8px 16px;align-items:start;font-size:13px}
            .wiz-kv dt{font-weight:700}
            .wiz-kv dd{margin:0}
            .wiz-btn{border:0;border-radius:11px;padding:11px 16px;cursor:pointer;font-weight:800;font:inherit}
            .wiz-btn:focus-visible{outline:3px solid #04ad93;outline-offset:2px}
            .wiz-btn-primary{background:#04ad93;color:#fff}
            .wiz-btn-primary:hover{filter:brightness(.92)}
            .wiz-btn-secondary{background:var(--fnd-wiz-secondary,#e9eef5);color:var(--fnd-wiz-text,#0f172a)}
            .wiz-btn-ghost{background:transparent;color:var(--fnd-wiz-muted,#64748b);border:1px solid var(--fnd-wiz-border,#e5e7eb)}
            .wiz-preview{border-radius:16px;border:1px solid var(--fnd-wiz-border,#e5e7eb);overflow:hidden}
            .wiz-cp-header{padding:12px 15px;display:flex;align-items:center;justify-content:space-between;gap:10px}
            .wiz-cp-meta{display:flex;align-items:center;gap:10px}
            .wiz-cp-logo{width:32px;height:32px;border-radius:8px;background:rgba(255,255,255,.2);display:grid;place-items:center}
            .wiz-cp-name{font-weight:800;font-size:13px}
            .wiz-cp-byline{font-size:11px;opacity:.85}
            .wiz-cp-badge{padding:4px 8px;border-radius:999px;font-size:10px;font-weight:700;background:#ee744d;color:#fff}
            .wiz-cp-body{padding:13px 15px;background:var(--fnd-wiz-cp-body,#f8fafc);min-height:100px}
            .wiz-cp-bubble{background:var(--fnd-wiz-cp-bubble,#fff);border:1px solid var(--fnd-wiz-border,#e8ecf0);border-radius:11px;padding:11px 13px;font-size:12px;line-height:1.5;max-width:90%;color:var(--fnd-wiz-text,#0f172a)}
            .wiz-cp-time{font-size:10px;color:var(--fnd-wiz-muted,#94a3b8);margin-top:4px}
            .wiz-cp-footer{border-top:1px solid var(--fnd-wiz-border,#e5e7eb);padding:11px 13px;background:var(--fnd-wiz-cp-footer,#fff)}
            .wiz-cp-irow{display:grid;grid-template-columns:1fr auto;gap:8px;margin-bottom:8px;align-items:center}
            .wiz-cp-input{padding:7px 10px;border:1px solid var(--fnd-wiz-border,#e5e7eb);border-radius:7px;font-size:11px;color:var(--fnd-wiz-muted,#64748b);background:var(--fnd-wiz-cp-body,#f8fafc);width:100%}
            .wiz-cp-send{border:none;border-radius:7px;padding:7px 13px;font-size:11px;font-weight:700;cursor:pointer}
            .wiz-cp-cta{width:100%;border:none;border-radius:9px;padding:9px;font-size:11px;font-weight:700;cursor:pointer}
            .wiz-change{font-size:12px;padding:4px 9px;border-radius:7px;background:var(--fnd-wiz-secondary,#e9eef5);color:var(--fnd-wiz-text,#0f172a);border:0;cursor:pointer;font:inherit;font-weight:600}
            .wiz-status{font-size:12px;margin-top:10px;min-height:18px}
            .wiz-success{color:#059669}
            .wiz-danger{color:#dc2626}
            @media(prefers-reduced-motion:reduce){*{animation-duration:.001ms!important;transition-duration:.001ms!important}}
        </style>

        <div class="wiz-shell">
            <div class="wiz-top">
                <div class="wiz-title" id="fnd-wiz-title">✨ Frontdesk AI Setup</div>
                <div class="wiz-top-actions">
                    <button type="button" class="wiz-btn wiz-btn-ghost" id="fnd-wizard-theme-btn" aria-pressed="false" style="padding:8px 12px;font-size:13px">Dark mode</button>
                    <button type="button" class="wiz-btn wiz-btn-ghost" id="fnd-wizard-close" style="padding:8px 12px;font-size:13px" aria-label="Close wizard">✕ Close</button>
                </div>
            </div>

            <div class="wiz-progress" aria-hidden="true" id="wiz-dots">
                <?php for ( $i = 0; $i < 6; $i++ ) : ?>
                    <div class="wiz-dot<?php echo $i === 0 ? ' active' : ''; ?>" data-i="<?php echo $i; ?>"></div>
                <?php endfor; ?>
            </div>

            <div class="sr-only" role="status" aria-live="polite" id="wiz-aria"></div>
            <div class="wiz-card" id="wiz-body" tabindex="-1"></div>

            <div class="wiz-nav">
                <button type="button" class="wiz-btn wiz-btn-secondary" id="wiz-prev" disabled>← Back</button>
                <div style="display:flex;gap:10px;align-items:center">
                    <div class="wiz-status" id="wiz-status"></div>
                    <button type="button" class="wiz-btn wiz-btn-primary" id="wiz-next">Next →</button>
                    <button type="button" class="wiz-btn wiz-btn-primary" id="wiz-finish" style="display:none">Apply settings ✅</button>
                </div>
            </div>
        </div>
        </div><!-- #fnd-wizard-overlay -->

        <script>
        (function(){
            const TOTAL   = 6;
            const AJAX    = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
            const RETURN  = <?php echo wp_json_encode( $settings_url . '&frontdesk_saved=1' ); ?>;
            const NONCE   = <?php echo wp_json_encode( $nonce ); ?>;
            const SWATCHES= ['#DF157C','#179AD6','#F4C946','#7C59A9','#83BE56','#DE7450','#1e6167','#04ad93','#0f172a','#ffffff','#000000'];

            const state = { step: 0, data: <?php echo wp_json_encode( $state ); ?> };

            const el = {
                overlay:  document.getElementById('fnd-wizard-overlay'),
                body:     document.getElementById('wiz-body'),
                prev:     document.getElementById('wiz-prev'),
                next:     document.getElementById('wiz-next'),
                finish:   document.getElementById('wiz-finish'),
                dots:     document.querySelectorAll('[data-i]'),
                status:   document.getElementById('wiz-status'),
                aria:     document.getElementById('wiz-aria'),
                themeBtn: document.getElementById('fnd-wizard-theme-btn'),
            };

            // ── Theme ──────────────────────────────────────────────────────
            function applyTheme(m){
                m = m === 'dark' ? 'dark' : 'light';
                el.overlay.setAttribute('data-theme', m);
                el.themeBtn.textContent = m === 'dark' ? 'Light mode' : 'Dark mode';
                el.themeBtn.setAttribute('aria-pressed', m === 'dark' ? 'true' : 'false');
                try{ localStorage.setItem('fnd_wiz_theme', m); }catch(e){}
            }
            let t = 'light';
            try{ t = localStorage.getItem('fnd_wiz_theme') || 'light'; }catch(e){}
            applyTheme(t);
            el.themeBtn.addEventListener('click', () => applyTheme(el.overlay.getAttribute('data-theme') === 'dark' ? 'light' : 'dark'));

            // ── Colour utils ───────────────────────────────────────────────
            function hexToRgb(h){ const r=/^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(h); return r?{r:parseInt(r[1],16),g:parseInt(r[2],16),b:parseInt(r[3],16)}:null; }
            function relL(c){ const a=[c.r,c.g,c.b].map(v=>{v/=255;return v<=.03928?v/12.92:Math.pow((v+.055)/1.055,2.4)}); return .2126*a[0]+.7152*a[1]+.0722*a[2]; }
            function contrast(fg,bg){ const F=hexToRgb(fg),B=hexToRgb(bg); if(!F||!B)return null; const l1=Math.max(relL(F),relL(B)),l2=Math.min(relL(F),relL(B)); return (l1+.05)/(l2+.05); }
            function colorChip(label,hex){ const fg = (contrast('#fff',hex)||0)>=(contrast('#0f172a',hex)||0)?'#fff':'#0f172a'; return `<span style="display:inline-flex;align-items:center;gap:5px;background:${hex};color:${fg};padding:5px 10px;border-radius:999px;font-size:11px;font-weight:700">${label}</span>`; }

            // ── Preview ────────────────────────────────────────────────────
            function preview(){
                const d   = state.data;
                const hbg = d.ui_header_bg || '#1e6167';
                const htx = d.ui_header_text || '#ffffff';
                const btn = d.ui_button_color || '#04ad93';
                const btx = d.ui_button_text_color || '#ffffff';
                const name = esc(d.bot_name || 'Frontdesk AI');
                const kbl  = esc(d.kb_button_label || 'Help Centre');
                const greetRaw = String(d.greeting_text || '').replace(/\r?\n/g, '\n');
                const greet = esc(greetRaw).replace(/\{bot_name\}/g, name);
                const lines = greet.split('\n');
                const badge = d.provider === 'offline' ? 'Offline' : 'Online';
                const badgeBg = d.provider === 'offline' ? '#ee744d' : '#059669';
                return `<div class="wiz-preview">
                  <div class="wiz-cp-header" style="background:${hbg};color:${htx}">
                    <div class="wiz-cp-meta">
                      <div class="wiz-cp-logo"><svg width="16" height="16" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="${btn}"/></svg></div>
                      <div><div class="wiz-cp-name" style="color:${htx}">${name}</div><div class="wiz-cp-byline" style="color:${htx}">Support assistant</div></div>
                      <span class="wiz-cp-badge" style="background:${badgeBg}">${badge}</span>
                    </div>
                    <div style="display:flex;gap:6px">
                      <button type="button" class="wiz-btn wiz-btn-ghost" style="padding:5px 9px;font-size:11px;color:${htx};border-color:rgba(255,255,255,.25)">${kbl}</button>
                    </div>
                  </div>
                  <div class="wiz-cp-body">
                    <div class="wiz-cp-bubble">
                      <strong>${lines[0]||''}</strong>${lines.slice(1).map(l=>`<br>${l}`).join('')}
                      <div class="wiz-cp-time">Just now</div>
                    </div>
                  </div>
                  <div class="wiz-cp-footer">
                    <div class="wiz-cp-irow">
                      <div class="wiz-cp-input">${esc(d.input_placeholder||'Type your message…')}</div>
                      <button type="button" class="wiz-cp-send" style="background:${btn};color:${btx}">Send</button>
                    </div>
                    <button type="button" class="wiz-cp-cta" style="background:${btn};color:${btx}">Contact us</button>
                  </div>
                </div>`;
            }

            function esc(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
            function hero(t,s){ return `<div class="wiz-hero"><h2>${t}</h2>${s?`<p>${s}</p>`:''}</div>`; }
            function field(label,id,help,input){ return `<div><label class="wiz-label" for="${id}">${label}</label>${input}${help?`<div class="wiz-help">${help}</div>`:''}</div>`; }
            function colorRow(label,key){
                const id='wc_'+key, val=state.data[key]||'#000000';
                const sw=SWATCHES.map(c=>`<button type="button" class="wiz-swatch" data-color="${c}" aria-label="Use ${c}" style="background:${c}"></button>`).join('');
                return `<div>
                  <label class="wiz-label">${label}</label>
                  <div class="wiz-color-row">
                    <input class="wiz-color-swatch" type="color" value="${val}" data-key="${key}" data-pair="${id}">
                    <input class="wiz-input" type="text" id="${id}" value="${val}" data-key="${key}" data-pair-src="input[type=color][data-key='${key}']" style="font-family:monospace">
                  </div>
                  <div class="wiz-swatch-grid">${sw}</div>
                </div>`;
            }
            function goStep(btn,step){ return `<button type="button" class="wiz-change" data-go="${step}">${btn}</button>`; }

            // ── Steps ──────────────────────────────────────────────────────
            function renderStep(s){
                if(s===0) return `
                  ${hero('Welcome! 👋 Set up your assistant.','Give it a name, upload a logo, write the greeting, and choose where the button sits. Everything can be changed any time.')}
                  <div class="wiz-row wiz-cols-2">
                    <div style="display:grid;gap:14px">
                      ${field('Assistant name','wf_name','Shown in the chat header.',`<input class="wiz-input" id="wf_name" type="text" data-key="bot_name" value="${esc(state.data.bot_name)}" placeholder="Frontdesk AI">`)}
                      ${field('Logo or avatar','wf_avatar','Shown in the chat header. Leave blank to use the site logo.',`<div style="display:flex;gap:8px;align-items:center">
                        ${state.data.avatar_url?`<img src="${esc(state.data.avatar_url)}" style="width:36px;height:36px;border-radius:8px;object-fit:cover;flex-shrink:0" alt="">`:``}
                        <input class="wiz-input" id="wf_avatar" type="url" data-key="avatar_url" value="${esc(state.data.avatar_url||'')}" placeholder="https://example.com/logo.png" style="flex:1;min-width:0">
                        <button type="button" class="wiz-change" id="wiz-avatar-pick" style="flex-shrink:0">Upload</button>
                      </div>`)}
                      ${field('Opening greeting','wf_greet','Put each line on its own line. Use {bot_name}, {hours}, {contact}.',`<textarea class="wiz-textarea" id="wf_greet" data-key="greeting_text">${esc(state.data.greeting_text)}</textarea>`)}
                      ${field('Chat button position','wf_pos','',`<select class="wiz-select" id="wf_pos" data-key="ui_position_corner">
                        <option value="bottom-right"${state.data.ui_position_corner==='bottom-right'?' selected':''}>Bottom right</option>
                        <option value="bottom-left"${state.data.ui_position_corner==='bottom-left'?' selected':''}>Bottom left</option>
                        <option value="top-right"${state.data.ui_position_corner==='top-right'?' selected':''}>Top right</option>
                        <option value="top-left"${state.data.ui_position_corner==='top-left'?' selected':''}>Top left</option>
                      </select>`)}
                    </div>
                    <div style="position:sticky;top:0">${preview()}</div>
                  </div>`;

                if(s===1) return `
                  ${hero('Choose colours.','The preview updates live.')}
                  <div class="wiz-row wiz-cols-2">
                    <div style="display:grid;gap:14px">
                      <div style="display:flex;gap:8px;align-items:center">
                        <strong style="font-size:13px">Colours</strong>
                        <button type="button" class="wiz-change" id="wiz-reset-colours">Reset defaults</button>
                      </div>
                      ${colorRow('Header background','ui_header_bg')}
                      ${colorRow('Header text','ui_header_text')}
                      ${colorRow('Button background','ui_button_color')}
                      ${colorRow('Button text','ui_button_text_color')}
                      <div id="wiz-contrast-note" style="font-size:12px;min-height:16px"></div>
                    </div>
                    <div style="position:sticky;top:0">${preview()}</div>
                  </div>`;

                if(s===2){
                    const prov = state.data.provider || 'offline';
                    return `
                  ${hero('How should replies be generated? 🧠','Offline means everything runs from your site — no data leaves your server. OpenAI means AI-powered answers.')}
                  <div class="wiz-row wiz-cols-2">
                    <div style="display:grid;gap:14px">
                      ${field('Reply engine','wf_prov','Offline is great as a default — you can add OpenAI later.',`<select class="wiz-select" id="wf_prov" data-key="provider">
                        <option value="offline"${prov==='offline'?' selected':''}>Offline — site content + FAQs</option>
                        <option value="openai"${prov==='openai'?' selected':''}>OpenAI</option>
                      </select>`)}
                      <div id="wiz-ai-fields" ${prov==='offline'?'style="display:none"':''}>
                        ${field('OpenAI API key','wf_key','Stored server-side only. Leave as ••••••••to keep the current key.',`<input class="wiz-input" id="wf_key" type="password" autocomplete="new-password" data-key="openai_api_key" value="${state.data.openai_api_key?'••••••••':''}" placeholder="sk-…">`)}
                        ${field('Knowledge source','wf_rtm','',`<select class="wiz-select" id="wf_rtm" data-key="runtime_mode">
                          <option value="faq_only"${state.data.runtime_mode==='faq_only'?' selected':''}>FAQs only</option>
                          <option value="site_index"${state.data.runtime_mode==='site_index'?' selected':''}>Site index</option>
                          <option value="ai_site_index"${state.data.runtime_mode==='ai_site_index'?' selected':''}>OpenAI + site index</option>
                        </select>`)}
                      </div>
                      <div class="wiz-check-row">
                        <input type="checkbox" id="wf_fb" data-key="fallback_enabled" ${state.data.fallback_enabled?'checked':''}>
                        <label for="wf_fb">Enable offline fallback if OpenAI is unavailable</label>
                      </div>
                    </div>
                    <div style="position:sticky;top:0">${preview()}</div>
                  </div>`;
                }

                if(s===3){
                    const photos = Array.isArray(state.data.staff_photos) ? [...state.data.staff_photos] : [];
                    while(photos.length < 4) photos.push('');
                    const photoRows = photos.map((url,i)=>`
                      <div style="display:flex;gap:8px;align-items:center;margin-top:6px">
                        ${url?`<img src="${esc(url)}" style="width:32px;height:32px;border-radius:50%;object-fit:cover;flex-shrink:0;border:2px solid #e2e8f0" alt="">`:``}
                        <input class="wiz-input wiz-photo-inp" type="url" data-photo-index="${i}" value="${esc(url)}" placeholder="Photo ${i+1} URL — paste or upload" style="flex:1;min-width:0">
                        <button type="button" class="wiz-change wiz-photo-pick" data-photo-slot="${i}" style="flex-shrink:0">Upload</button>
                      </div>`).join('');
                    return `
                  ${hero('Launcher card &amp; team photos 📸','The popup above the chat button and the staff photos shown in it.')}
                  <div class="wiz-row wiz-cols-2">
                    <div style="display:grid;gap:14px">
                      ${field('Teaser heading','wf_tt','Bold line in the popup above the chat button.',`<input class="wiz-input" id="wf_tt" type="text" data-key="teaser_title" value="${esc(state.data.teaser_title)}">`)}
                      ${field('Help Centre button label','wf_kbl','',`<input class="wiz-input" id="wf_kbl" type="text" data-key="kb_button_label" value="${esc(state.data.kb_button_label)}">`)}
                      ${field('Message placeholder','wf_ip','',`<input class="wiz-input" id="wf_ip" type="text" data-key="input_placeholder" value="${esc(state.data.input_placeholder)}">`)}
                      ${field('Contact button colour','wf_cbc','Leave blank to use the main accent colour.',`<div style="display:flex;gap:8px;align-items:center"><input type="color" value="${esc(state.data.contact_btn_color||'#04ad93')}" data-key="contact_btn_color" style="width:36px;height:36px;padding:2px;border:1px solid #e2e8f0;border-radius:6px;cursor:pointer"><input class="wiz-input" id="wf_cbc" type="text" data-key="contact_btn_color" value="${esc(state.data.contact_btn_color)}" placeholder="e.g. #DE7450 — blank = accent" style="font-family:monospace;flex:1"></div>`)}
                      <div>
                        <label class="wiz-label">Staff photos <span style="font-weight:400;color:var(--fnd-wiz-muted,#64748b)">(up to 4, shown as circles in the popup)</span></label>
                        ${photoRows}
                      </div>
                    </div>
                    <div style="position:sticky;top:0">${preview()}</div>
                  </div>`;
                }

                if(s===4) return `
                  ${hero('Business information 🏢','Powers offline answers and the {hours} / {contact} placeholders in your greeting.')}
                  <div class="wiz-row wiz-cols-2">
                    <div style="display:grid;gap:14px">
                      ${field('Opening hours','wf_oh','Put each day range on its own line. Used by {hours}.',`<textarea class="wiz-textarea" id="wf_oh" data-key="opening_hours" style="min-height:70px">${esc(state.data.opening_hours)}</textarea>`)}
                      ${field('Alternative contact text','wf_ac','Used by {contact} in the greeting.',`<input class="wiz-input" id="wf_ac" type="text" data-key="alt_contact" value="${esc(state.data.alt_contact)}" placeholder="email us at hello@example.com">`)}
                      <div class="wiz-divider"></div>
                      ${field('Lead capture email','wf_ce','Where visitor messages are sent.',`<input class="wiz-input" id="wf_ce" type="email" data-key="contact_email" value="${esc(state.data.contact_email)}">`)}
                      <div class="wiz-check-row">
                        <input type="checkbox" id="wf_ec" data-key="enable_contact" ${state.data.enable_contact?'checked':''}>
                        <label for="wf_ec">Enable the Contact us / lead capture form</label>
                      </div>
                      <div class="wiz-divider"></div>
                      <div style="font-size:13px;font-weight:700;margin-bottom:6px">🔒 Spam protection — reCAPTCHA v3 <span style="font-weight:400;color:var(--fnd-wiz-muted,#64748b)">(optional — <a href="https://www.google.com/recaptcha/admin/create" target="_blank" rel="noopener" style="color:inherit">get free keys</a>)</span></div>
                      ${field('Site key (public)','wf_rc_site','Paste your reCAPTCHA v3 site key. Leave blank to skip.',`<input class="wiz-input" id="wf_rc_site" type="text" data-key="recaptcha_site_key" value="${esc(state.data.recaptcha_site_key)}" placeholder="6Le…" autocomplete="off">`)}
                      ${field('Secret key (server-side)','wf_rc_secret','Never shared publicly. Used to verify tokens server-side.',`<input class="wiz-input" id="wf_rc_secret" type="password" data-key="recaptcha_secret_key" value="${state.data.recaptcha_secret_key?'••••••••':''}" placeholder="6Le…" autocomplete="new-password">`)}
                    </div>
                    <div style="position:sticky;top:0">${preview()}</div>
                  </div>`;

                if(s===5){
                    const d = state.data;
                    const pText = d.provider==='offline' ? 'Offline (site content + FAQs)' : `OpenAI (${d.runtime_mode||'faq_only'})`;
                    const chips = [
                        colorChip('Header bg', d.ui_header_bg||'#1e6167'),
                        colorChip('Header text', d.ui_header_text||'#fff'),
                        colorChip('Button bg', d.ui_button_color||'#04ad93'),
                        colorChip('Button text', d.ui_button_text_color||'#fff'),
                    ].join(' ');
                    const avatarHtml = d.avatar_url
                        ? `<img src="${esc(d.avatar_url)}" style="width:28px;height:28px;border-radius:6px;object-fit:cover;vertical-align:middle;margin-right:6px" alt=""> ${esc(d.avatar_url.split('/').pop()||d.avatar_url)}`
                        : '<em style="color:var(--fnd-wiz-muted,#64748b)">Site logo (default)</em>';
                    const photoArr = Array.isArray(d.staff_photos) ? d.staff_photos.filter(Boolean) : [];
                    const photosHtml = photoArr.length
                        ? photoArr.map(u=>`<img src="${esc(u)}" style="width:28px;height:28px;border-radius:50%;object-fit:cover;border:2px solid #fff;margin-left:-6px;vertical-align:middle" alt="">`).join('')
                        : '<em style="color:var(--fnd-wiz-muted,#64748b)">None</em>';
                    return `
                  ${hero('Review &amp; apply ✅','')}
                  <div class="wiz-row wiz-cols-2">
                    <div>
                      <div class="wiz-summary">
                        <dl class="wiz-kv">
                          <dt>Name:</dt><dd>${esc(d.bot_name||'')} ${goStep('Change',0)}</dd>
                          <dt>Logo:</dt><dd>${avatarHtml} ${goStep('Change',0)}</dd>
                          <dt>Provider:</dt><dd>${pText} ${goStep('Change',2)}</dd>
                          <dt>Position:</dt><dd>${d.ui_position_corner||'bottom-right'} ${goStep('Change',0)}</dd>
                          <dt>Teaser:</dt><dd>${esc(d.teaser_title||'—')} ${goStep('Change',3)}</dd>
                          <dt>Team photos:</dt><dd style="display:flex;align-items:center;gap:0;padding-left:6px">${photosHtml} ${goStep('Change',3)}</dd>
                          <dt>Hours:</dt><dd><span style="white-space:pre-wrap;font-size:12px">${esc(String(d.opening_hours||'—').replace(/\r?\n/g,'\n'))}</span> ${goStep('Change',4)}</dd>
                          <dt>Contact email:</dt><dd>${esc(d.contact_email||'—')} ${goStep('Change',4)}</dd>
                          <dt>Colours:</dt><dd style="display:flex;gap:4px;flex-wrap:wrap;margin-top:2px">${chips} ${goStep('Change',1)}</dd>
                          <dt>Greeting:</dt><dd><span style="white-space:pre-wrap;font-size:12px">${esc(String(d.greeting_text||'').replace(/\r?\n/g,'\n'))}</span> ${goStep('Change',0)}</dd>
                        </dl>
                      </div>
                      <p class="wiz-help" style="margin-top:10px">You can fine-tune anything on the settings page after saving.</p>
                    </div>
                    <div style="position:sticky;top:0">${preview()}</div>
                  </div>`;
                }
                return '';
            }

            // ── Nav ────────────────────────────────────────────────────────
            function syncNav(){
                el.dots.forEach((d,i)=>d.classList.toggle('active',i<=state.step));
                el.prev.disabled = state.step===0;
                el.next.style.display  = state.step===TOTAL-1?'none':'';
                el.finish.style.display= state.step===TOTAL-1?'':'none';
                el.aria.textContent = `Step ${state.step+1} of ${TOTAL}`;
            }
            function setStep(i){ state.step=Math.max(0,Math.min(TOTAL-1,i)); render(); el.body.focus(); }

            // ── Render ─────────────────────────────────────────────────────
            function render(){
                syncNav();
                el.body.innerHTML = renderStep(state.step);

                // Step-jump buttons
                el.body.querySelectorAll('[data-go]').forEach(b=>b.addEventListener('click',()=>setStep(+b.getAttribute('data-go'))));

                // Reset colours
                const resetBtn=el.body.querySelector('#wiz-reset-colours');
                if(resetBtn) resetBtn.addEventListener('click',()=>{
                    Object.assign(state.data,{ui_header_bg:'#1e6167',ui_header_text:'#ffffff',ui_button_color:'#04ad93',ui_button_text_color:'#ffffff'});
                    render();
                });

                // Contrast note
                const note=el.body.querySelector('#wiz-contrast-note');
                function updateContrast(){
                    if(!note)return;
                    const r=contrast(state.data.ui_button_text_color||'#fff',state.data.ui_button_color||'#04ad93');
                    if(!r)return;
                    note.innerHTML=r<4.5?`⚠️ Button contrast ${r.toFixed(2)}:1 — below WCAG AA`:`✅ Button contrast ${r.toFixed(2)}:1`;
                }
                if(note){ updateContrast(); el.body.querySelectorAll('[data-key="ui_button_color"],[data-key="ui_button_text_color"]').forEach(i=>i.addEventListener('input',updateContrast)); }

                // Provider toggle
                const provSel=el.body.querySelector('[data-key="provider"]');
                const aiFields=el.body.querySelector('#wiz-ai-fields');
                if(provSel&&aiFields){
                    provSel.addEventListener('input',()=>{
                        state.data.provider=provSel.value;
                        aiFields.style.display=provSel.value==='offline'?'none':'';
                        render();
                    });
                }

                // Generic inputs
                el.body.querySelectorAll('[data-key]').forEach(inp=>{
                    const key=inp.getAttribute('data-key');
                    inp.addEventListener('input',e=>{
                        if(inp.type==='password'){ if(e.target.value&&e.target.value!=='••••••••') state.data[key]=e.target.value.trim(); }
                        else if(inp.type==='checkbox'){ state.data[key]=e.target.checked; }
                        else { state.data[key]=e.target.value; }
                        // Sync paired colour inputs
                        if(inp.type==='color'){
                            const pair=el.body.querySelector(`input[type=text][data-key="${key}"]`); if(pair) pair.value=e.target.value;
                        }
                        const visual=['ui_header_bg','ui_header_text','ui_button_color','ui_button_text_color','bot_name','greeting_text','provider','kb_button_label','input_placeholder'];
                        if(visual.includes(key)) render();
                    });
                });

                // Colour swatches
                el.body.querySelectorAll('.wiz-swatch').forEach(sw=>{
                    sw.addEventListener('click',()=>{
                        const color=sw.getAttribute('data-color');
                        const parent=sw.closest('div');
                        const cPicker=parent?.parentElement?.querySelector(`input[type=color][data-key]`);
                        const key=cPicker?.getAttribute('data-key');
                        if(!key)return;
                        state.data[key]=color;
                        cPicker.value=color;
                        const hex=el.body.querySelector(`input[type=text][data-key="${key}"]`);
                        if(hex) hex.value=color;
                        render();
                    });
                });

                // Text→color sync
                el.body.querySelectorAll('input[type=text][data-key]').forEach(txt=>{
                    txt.addEventListener('input',()=>{
                        if(/^#[0-9a-f]{6}$/i.test(txt.value)){
                            const picker=el.body.querySelector(`input[type=color][data-key="${txt.getAttribute('data-key')}"]`);
                            if(picker) picker.value=txt.value;
                        }
                    });
                });

                // Avatar / logo media picker (step 0)
                const avatarBtn=el.body.querySelector('#wiz-avatar-pick');
                if(avatarBtn){
                    avatarBtn.addEventListener('click',()=>{
                        if(typeof wp==='undefined'||!wp.media) return;
                        const frame=wp.media({title:'Select logo or avatar',button:{text:'Use this image'},multiple:false,library:{type:'image'}});
                        frame.on('select',()=>{
                            const att=frame.state().get('selection').first().toJSON();
                            const url=(att.sizes&&att.sizes.thumbnail&&att.sizes.thumbnail.url)||att.url||'';
                            const inp=el.body.querySelector('#wf_avatar');
                            if(inp&&url){ inp.value=url; state.data.avatar_url=url; render(); }
                        });
                        frame.open();
                    });
                }

                // Staff photo inputs — manual URL typing (step 3)
                el.body.querySelectorAll('.wiz-photo-inp').forEach(inp=>{
                    inp.addEventListener('input',()=>{
                        const idx=parseInt(inp.getAttribute('data-photo-index'),10);
                        if(!Array.isArray(state.data.staff_photos)) state.data.staff_photos=[];
                        while(state.data.staff_photos.length<=idx) state.data.staff_photos.push('');
                        state.data.staff_photos[idx]=inp.value.trim();
                    });
                });

                // Staff photo media pickers (step 3)
                el.body.querySelectorAll('.wiz-photo-pick').forEach(btn=>{
                    btn.addEventListener('click',()=>{
                        if(typeof wp==='undefined'||!wp.media) return;
                        const slot=parseInt(btn.getAttribute('data-photo-slot'),10);
                        const frame=wp.media({title:'Select staff photo',button:{text:'Use this photo'},multiple:false,library:{type:'image'}});
                        frame.on('select',()=>{
                            const att=frame.state().get('selection').first().toJSON();
                            const url=(att.sizes&&att.sizes.medium&&att.sizes.medium.url)||att.url||'';
                            if(!Array.isArray(state.data.staff_photos)) state.data.staff_photos=[];
                            while(state.data.staff_photos.length<=slot) state.data.staff_photos.push('');
                            state.data.staff_photos[slot]=url;
                            render();
                        });
                        frame.open();
                    });
                });
            }

            // ── Controls ───────────────────────────────────────────────────
            el.prev.addEventListener('click',()=>setStep(state.step-1));
            el.next.addEventListener('click',()=>setStep(state.step+1));
            document.addEventListener('keydown',e=>{
                if(!el.overlay||el.overlay.style.display==='none')return;
                const tag=document.activeElement?.tagName;
                if(['INPUT','TEXTAREA','SELECT'].includes(tag))return;
                if(e.key==='ArrowRight'&&el.next.style.display!=='none') setStep(state.step+1);
                if(e.key==='ArrowLeft'&&!el.prev.disabled) setStep(state.step-1);
            });

            el.finish.addEventListener('click', async function(){
                el.finish.disabled=true;
                el.status.innerHTML='<em>Saving…</em>';
                try{
                    const fd=new FormData();
                    fd.append('action','fnd_frontdesk_save_wizard');
                    fd.append('nonce',NONCE);
                    fd.append('payload',JSON.stringify(state.data));
                    const r=await fetch(AJAX,{method:'POST',credentials:'same-origin',body:fd});
                    const j=await r.json();
                    if(!r.ok||!j?.success) throw new Error(j?.data||'Save failed');
                    el.status.innerHTML='<span class="wiz-success">✅ Saved!</span>';
                    setTimeout(()=>window.location=RETURN,700);
                }catch(err){
                    el.finish.disabled=false;
                    el.status.innerHTML=`<span class="wiz-danger">${err?.message||'Error'}</span>`;
                }
            });

            render();
        })();
        </script>
        <?php
    }

    // =========================================================================
    // AJAX save — maps wizard state → Frontdesk_Config::save()
    // =========================================================================

    public static function ajax_save(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied.', 403 );
        }
        check_ajax_referer( self::NONCE_KEY, 'nonce' );

        $raw = isset( $_POST['payload'] ) ? json_decode( stripslashes( (string) wp_unslash( $_POST['payload'] ) ), true ) : null;
        if ( ! is_array( $raw ) ) {
            wp_send_json_error( 'Invalid payload.', 400 );
        }

        $hex = static fn( string $v, string $d ) => preg_match( '/^#[0-9a-f]{3,8}$/i', trim( $v ) ) ? trim( $v ) : $d;

        $payload = [
            // Step 0
            'bot_name'             => sanitize_text_field( $raw['bot_name']           ?? '' ),
            'avatar_url'           => esc_url_raw( (string)( $raw['avatar_url']       ?? '' ) ),
            'greeting_text'        => sanitize_textarea_field( $raw['greeting_text']  ?? '' ),
            'ui_position_corner'   => sanitize_key( str_replace( '-', '_', $raw['ui_position_corner'] ?? 'bottom_right' ) ),
            // Step 1
            'ui_header_bg'         => $hex( (string)($raw['ui_header_bg']         ?? ''), '#1e6167' ),
            'ui_header_text'       => $hex( (string)($raw['ui_header_text']        ?? ''), '#ffffff' ),
            'ui_button_color'      => $hex( (string)($raw['ui_button_color']       ?? ''), '#04ad93' ),
            'ui_button_text_color' => $hex( (string)($raw['ui_button_text_color']  ?? ''), '#ffffff' ),
            // Step 2
            'provider'             => sanitize_key( $raw['provider']               ?? 'offline' ),
            'runtime_mode'         => sanitize_key( $raw['runtime_mode']           ?? 'faq_only' ),
            'fallback_enabled'     => ! empty( $raw['fallback_enabled'] ) ? 1 : 0,
            // Step 3
            'teaser_title'         => sanitize_text_field( $raw['teaser_title']    ?? '' ),
            'teaser_body'          => sanitize_text_field( $raw['teaser_body']     ?? '' ),
            'kb_button_label'      => sanitize_text_field( $raw['kb_button_label'] ?? '' ),
            'input_placeholder'    => sanitize_text_field( $raw['input_placeholder']?? '' ),
            'staff_photos'         => wp_json_encode( array_values( array_filter( array_slice(
                                          array_map( 'esc_url_raw', (array)( $raw['staff_photos'] ?? [] ) ),
                                          0, 4
                                      ) ) ) ),
            // Step 4
            'opening_hours'          => sanitize_textarea_field( $raw['opening_hours']          ?? '' ),
            'alt_contact'            => sanitize_text_field( $raw['alt_contact']               ?? '' ),
            'contact_email'          => sanitize_email( $raw['contact_email']                  ?? '' ),
            'enable_contact'         => ! empty( $raw['enable_contact'] ) ? 1 : 0,
            'contact_btn_color'      => preg_match( '/^#[0-9a-f]{3,8}$/i', trim( (string)($raw['contact_btn_color'] ?? '') ) ) ? trim( (string)$raw['contact_btn_color'] ) : '',
            'contact_btn_text_color' => preg_match( '/^#[0-9a-f]{3,8}$/i', trim( (string)($raw['contact_btn_text_color'] ?? '#ffffff') ) ) ? trim( (string)$raw['contact_btn_text_color'] ) : '#ffffff',
            // style_mode forced manual since user configured colours
            'style_mode'           => 'manual',
        ];

        // Keep existing OpenAI key unless new clear-text provided
        $raw_key = (string)( $raw['openai_api_key'] ?? '' );
        if ( $raw_key !== '' && $raw_key !== '••••••••' ) {
            $payload['openai_api_key'] = sanitize_text_field( $raw_key );
        }

        // Keep existing reCAPTCHA secret unless new clear-text provided
        $rc_secret = (string)( $raw['recaptcha_secret_key'] ?? '' );
        if ( $rc_secret !== '' && $rc_secret !== '••••••••' ) {
            $payload['recaptcha_secret_key'] = sanitize_text_field( $rc_secret );
        }
        $rc_site = sanitize_text_field( (string)( $raw['recaptcha_site_key'] ?? '' ) );
        if ( $rc_site !== '' ) {
            $payload['recaptcha_site_key'] = $rc_site;
        }

        Frontdesk_Config::save( $payload );
        wp_send_json_success( [ 'saved' => true ] );
    }
}
