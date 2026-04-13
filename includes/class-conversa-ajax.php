<?php
if (!defined('ABSPATH')) exit;

/**
 * Foundation: Conversa — AJAX (Chat only)
 * Rev 7 (2025-08-20)
 * - Supports api_key OR openai_api_key setting
 * - Filterable model/params
 * - RAG-first, then naive site context
 * - Strict nonce, sanitization, size guards
 */
class Foundation_Conversa_Ajax {

    /** Wire AJAX actions (chat only) */
    public static function init() {
        add_action('wp_ajax_fnd_conversa_chat',        [__CLASS__, 'chat']);
        add_action('wp_ajax_nopriv_fnd_conversa_chat', [__CLASS__, 'chat']);
        // NOTE: contact endpoints live in the Frontdesk (main) class.
    }

    /** Option helper (also falls back to openai_api_key) */
    private static function opt($key, $default = '') {
        $opts = get_option('fnd_conversa_options', []);
        if (isset($opts[$key]) && $opts[$key] !== '') return $opts[$key];
        if ($key === 'api_key' && !empty($opts['openai_api_key'])) return $opts['openai_api_key'];
        return $default;
    }

    /** Sanitize rich text to clean plain text */
    private static function clean($html) {
        $txt = wp_strip_all_tags((string)$html);
        $txt = html_entity_decode($txt, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $txt = preg_replace('/\s+/u', ' ', $txt);
        return trim($txt);
    }

    /**
     * Fallback context when no RAG index is present.
     * Returns [ 'context' => string, 'sources' => [ {title,url}, ... ] ]
     */
    private static function naive_site_context(string $user_q, int $max_posts = 6, int $chars_per = 1200) : array {
        $post_types = apply_filters('fnd_conversa_search_post_types', ['page','post']);
        $q = new WP_Query([
            'post_type'        => $post_types,
            's'                => $user_q,
            'posts_per_page'   => $max_posts,
            'post_status'      => 'publish',
            'no_found_rows'    => true,
            'orderby'          => 'relevance',
            'suppress_filters' => false,
            'fields'           => 'ids',
        ]);
        $pieces = []; $sources = [];
        if (!empty($q->posts)) {
            foreach ($q->posts as $pid) {
                $title = get_the_title($pid);
                $url   = get_permalink($pid);
                $raw   = apply_filters('the_content', get_post_field('post_content', $pid));
                $text  = self::clean($raw);
                if ($text === '') continue;
                $snippet = mb_substr($text, 0, $chars_per);
                $pieces[] = "TITLE: {$title}\nURL: {$url}\nCONTENT: {$snippet}";
                $sources[] = ['title' => $title, 'url' => $url];
            }
        }
        return [ 'context' => $pieces ? implode("\n\n---\n\n", $pieces) : '', 'sources' => $sources ];
    }

    /** Build a strict system prompt using site data */
    private static function system_prompt($persona_key, $context_text) : string {
        $site  = get_bloginfo('name');
        $bot   = self::opt('bot_name', 'Conversa');
        $hours = trim((string) self::opt('opening_hours', ''));
        $alt   = trim((string) self::opt('alt_contact', ''));

        $persona = 'Friendly Customer Support';
        if (function_exists('fnd_conversa_personalities')) {
            $map = fnd_conversa_personalities();
            if (isset($map[$persona_key])) $persona = $map[$persona_key];
        }

        $guard = "You are {$bot}, a {$persona} assistant for {$site}. Answer ONLY using the website context between <context> tags. If the answer is not clearly in the context, reply with: \"I’m not sure based on our site info.\" Then offer our contact options. Be concise and accurate. Use bullet points for steps.";
        if ($hours !== '') $guard .= ' Opening hours: ' . preg_replace('/\s+/', ' ', $hours) . '.';
        if ($alt   !== '') $guard .= ' Alternative contact: ' . preg_replace('/\s+/', ' ', $alt) . '.';

        if ($context_text !== '') {
            $guard .= "\n\n<context>\n" . $context_text . "\n</context>";
        }
        return $guard;
    }

    /** AI chat endpoint — requires POST + nonce; returns JSON */
    public static function chat() {
        // Method & nonce checks
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            wp_send_json_error(['message' => 'Invalid request method.'], 405);
        }
        $nonce = isset($_POST['nonce']) ? sanitize_text_field((string)$_POST['nonce']) : '';
        if (empty($nonce) || ! wp_verify_nonce($nonce, 'fnd_conversa')) {
            wp_send_json_error(['message' => 'Invalid security token.'], 403);
        }

        // Input
        $message = isset($_POST['message']) ? trim(wp_unslash((string)$_POST['message'])) : '';
        if ($message === '') {
            wp_send_json_error(['message' => 'Message was empty.'], 400);
        }
        if (mb_strlen($message) > 2000) {
            $message = mb_substr($message, 0, 2000);
        }

        // Personality key (accept 'persona' or 'personality')
        $persona = isset($_POST['persona']) ? sanitize_key($_POST['persona'])
                  : ( isset($_POST['personality']) ? sanitize_key($_POST['personality'])
                  : self::opt('default_personality', 'friendly_support') );

        // API key
        $api_key = self::opt('api_key', '');
        if (empty($api_key)) {
            wp_send_json_error(['message' => 'OpenAI API key is not configured in Conversa settings.'], 500);
        }

        // Context: RAG first, fallback to naive site context
        $ctx = ['context' => '', 'sources' => []];
        if (class_exists('Foundation_Conversa_RAG')) {
            try {
                $ctx = Foundation_Conversa_RAG::retrieve_context($message, 6);
            } catch (Throwable $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) error_log('Conversa RAG error: ' . $e->getMessage());
            }
        }
        if (empty($ctx['context'])) {
            $ctx = self::naive_site_context($message, 6, 1400);
        }

        // Compose request
        $system = self::system_prompt($persona, $ctx['context']);

        $model       = apply_filters('fnd_conversa_openai_model', 'gpt-4o-mini');
        $temperature = (float) apply_filters('fnd_conversa_openai_temperature', 0.2);
        $max_tokens  = (int) apply_filters('fnd_conversa_openai_max_tokens', 600);

        $messages = [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user',   'content' => wp_strip_all_tags($message)],
        ];
        $body = [
            'model'       => $model,
            'messages'    => $messages,
            'temperature' => $temperature,
            'max_tokens'  => $max_tokens,
        ];

        // Call OpenAI
        $resp = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body'       => wp_json_encode($body),
            'timeout'    => 45,
            'user-agent' => 'Foundation-Conversa/' . (defined('FND_CONVERSA_VERSION') ? FND_CONVERSA_VERSION : 'dev'),
        ]);

        if (is_wp_error($resp)) {
            if (defined('WP_DEBUG') && WP_DEBUG) error_log('Conversa OpenAI error: ' . $resp->get_error_message());
            wp_send_json_error(['message' => 'Upstream request failed.'], 500);
        }

        $code = wp_remote_retrieve_response_code($resp);
        $raw  = wp_remote_retrieve_body($resp);
        $json = json_decode($raw, true);
        if ($code >= 400 || !is_array($json)) {
            $msg = isset($json['error']['message']) ? $json['error']['message'] : 'Upstream API error';
            if (defined('WP_DEBUG') && WP_DEBUG) error_log('Conversa OpenAI bad response (' . $code . '): ' . $raw);
            wp_send_json_error(['message' => $msg], 500);
        }

        $text = $json['choices'][0]['message']['content'] ?? '';
        if ($text === '') {
            if (defined('WP_DEBUG') && WP_DEBUG) error_log('Conversa: empty completion: ' . $raw);
            wp_send_json_error(['message' => 'No text generated.'], 500);
        }

        // Append short source list (titles only)
        if (!empty($ctx['sources'])) {
            $src_lines = array_map(function($s){
                return '• ' . (isset($s['title']) ? $s['title'] : 'Source');
            }, array_slice($ctx['sources'], 0, 6));
            $text .= "\n\nSources:\n" . implode("\n", $src_lines);
        }

        // Return safe HTML
        wp_send_json_success(['reply' => wp_kses_post($text)], 200);
    }
}

Foundation_Conversa_Ajax::init();
