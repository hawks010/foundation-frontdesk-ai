<?php
if (!defined('ABSPATH')) { exit; }

class Frontdesk_Knowledge {

    /**
     * Words to ignore when scoring relevance.
     */
    private static array $stopwords = [
        'a','an','the','is','are','was','were','be','been','being',
        'have','has','had','do','does','did','will','would','could',
        'should','may','might','shall','can','i','you','we','they',
        'he','she','it','me','him','her','us','them','my','your',
        'our','their','this','that','these','those','what','how',
        'when','where','who','which','and','or','but','not','no',
        'in','on','at','to','for','of','with','by','from','about',
        'into','through','during','before','after','above','below',
        'up','down','out','off','over','under','again','then','so',
        'if','tell','me','just','get','go','like','please','hi',
        'hello','hey','thanks','thank','ok','okay','yes','yeah','yep',
    ];

    /**
     * Queries considered greetings — return a helpful intro instead of searching.
     */
    private static array $greeting_patterns = [
        '/^(hi|hey|hello|howdy|hiya|sup|yo|good\s*(morning|afternoon|evening|day))[!.,\s]*$/i',
        '/^(how\s+are\s+you|how\s+do\s+you\s+do|how\'s\s+it\s+going)[!.,\s?]*$/i',
        '/^(help|can\s+you\s+help|i\s+need\s+help|help\s+me)[!.,\s?]*$/i',
        '/^(what\s+can\s+you\s+do|what\s+do\s+you\s+know|what\s+can\s+i\s+ask)[!.,\s?]*$/i',
    ];

    // -------------------------------------------------------------------------
    // Main entry point
    // -------------------------------------------------------------------------

    public static function search(string $query, array $config): array {
        $query = trim($query);
        if ($query === '') {
            return self::empty_result();
        }

        // Detect pure greetings — return a warm intro with FAQ topics listed.
        if (self::is_greeting($query)) {
            return self::greeting_result($config);
        }

        $chunks  = [];
        $sources = [];
        $status  = ['used_faqs' => false, 'used_index' => false, 'fallback_search' => false];

        // --- Layer 1: FAQ word-score matching ----------------------------------
        $faq_chunks = self::search_faqs($query, $config);
        if (!empty($faq_chunks)) {
            $status['used_faqs'] = true;
            foreach ($faq_chunks as $c) {
                $chunks[]  = $c;
                $sources[] = ['title' => $c['title'], 'url' => $c['url']];
            }
        }

        // --- Layer 2: RAG vector/keyword index --------------------------------
        if (empty($chunks) && self::is_index_available() && class_exists('Foundation_Frontdesk_RAG')) {
            $ctx = Foundation_Frontdesk_RAG::retrieve_context($query, 6);
            $rag_chunks = (array) ($ctx['chunks'] ?? []);
            if (!empty($rag_chunks)) {
                $status['used_index'] = true;
                foreach ($rag_chunks as $chunk) {
                    if (!is_array($chunk)) continue;
                    $text = sanitize_textarea_field($chunk['text'] ?? '');
                    if ($text === '') continue;
                    $chunks[]  = [
                        'title' => sanitize_text_field($chunk['title'] ?? __('Site content', 'foundation-frontdesk')),
                        'url'   => esc_url_raw($chunk['url'] ?? ''),
                        'text'  => $text,
                        'type'  => sanitize_key((string) ($chunk['type'] ?? 'page')) ?: 'page',
                        'score' => (float) ($chunk['score'] ?? 0.5),
                    ];
                    $sources[] = [
                        'title' => sanitize_text_field($chunk['title'] ?? ''),
                        'url'   => esc_url_raw($chunk['url'] ?? ''),
                    ];
                }
            } elseif (!empty($ctx['context'])) {
                $status['used_index'] = true;
                $chunks[]  = [
                    'title' => __('Site knowledge index', 'foundation-frontdesk'),
                    'url'   => '',
                    'text'  => sanitize_textarea_field((string) $ctx['context']),
                    'type'  => 'site_index',
                    'score' => 0.5,
                ];
                foreach ((array) ($ctx['sources'] ?? []) as $src) {
                    if (!is_array($src)) continue;
                    $sources[] = [
                        'title' => sanitize_text_field((string) ($src['title'] ?? '')),
                        'url'   => esc_url_raw((string) ($src['url'] ?? '')),
                    ];
                }
            }
        }

        // --- Layer 3: WP_Query live search ------------------------------------
        if (empty($chunks)) {
            $status['fallback_search'] = true;
            $chunks  = self::wp_search($query, $config);
            $sources = array_map(fn($c) => ['title' => $c['title'], 'url' => $c['url']], $chunks);
        }

        return [
            'chunks'  => $chunks,
            'sources' => array_values(array_filter($sources, fn($s) => !empty($s['title']))),
            'status'  => $status,
        ];
    }

    // -------------------------------------------------------------------------
    // FAQ word-score matching
    // -------------------------------------------------------------------------

    private static function search_faqs(string $query, array $config): array {
        $faqs = self::get_faqs($config);
        if (empty($faqs)) return [];

        $query_words = self::tokenize($query);
        if (empty($query_words)) return [];

        $scored = [];
        foreach ($faqs as $faq) {
            $haystack  = ($faq['q'] ?? '') . ' ' . ($faq['a'] ?? '');
            $hay_words = self::tokenize($haystack);

            $matched = 0;
            foreach ($query_words as $w) {
                // word match OR partial stem match (first 4 chars)
                if (in_array($w, $hay_words, true)) {
                    $matched++;
                } elseif (strlen($w) > 4) {
                    $stem = substr($w, 0, 4);
                    foreach ($hay_words as $hw) {
                        if (str_starts_with($hw, $stem)) {
                            $matched += 0.6;
                            break;
                        }
                    }
                }
            }

            if ($matched === 0) continue;
            $score = $matched / count($query_words);
            if ($score < 0.25) continue; // need at least 25% of meaningful words to match

            $scored[] = [
                'title' => $faq['q'] ?? '',
                'url'   => $faq['url'] ?? '',
                'text'  => $faq['a'] ?? '',
                'type'  => 'faq',
                'score' => $score,
            ];
        }

        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, 3);
    }

    // -------------------------------------------------------------------------
    // WordPress post search (layer 3 fallback)
    // -------------------------------------------------------------------------

    private static function wp_search(string $query, array $config): array {
        $chunks = [];
        $post_types = self::selected_post_types($config);

        // First try WP native search
        $q = new WP_Query([
            'post_type'      => $post_types,
            'post_status'    => 'publish',
            's'              => $query,
            'posts_per_page' => 5,
            'no_found_rows'  => true,
            'orderby'        => 'relevance',
        ]);

        foreach ($q->posts as $post) {
            $chunks[] = self::post_to_chunk($post);
        }

        // If native search found nothing, try a word-scored content scan on recent posts
        if (empty($chunks)) {
            $query_words = self::tokenize($query);
            if (!empty($query_words)) {
                $recent = new WP_Query([
                    'post_type'      => $post_types,
                    'post_status'    => 'publish',
                    'posts_per_page' => 30,
                    'no_found_rows'  => true,
                    'orderby'        => 'date',
                ]);
                $scored = [];
                foreach ($recent->posts as $post) {
                    $haystack = strtolower(get_the_title($post) . ' ' . wp_strip_all_tags($post->post_content));
                    $matched  = 0;
                    foreach ($query_words as $w) {
                        if (strpos($haystack, $w) !== false) $matched++;
                    }
                    if ($matched > 0) {
                        $scored[] = ['post' => $post, 'score' => $matched / count($query_words)];
                    }
                }
                usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
                foreach (array_slice($scored, 0, 4) as $item) {
                    $chunks[] = self::post_to_chunk($item['post']);
                }
            }
        }

        return $chunks;
    }

    private static function post_to_chunk(WP_Post $post): array {
        return [
            'title' => get_the_title($post),
            'url'   => (string) get_permalink($post),
            'text'  => wp_trim_words(wp_strip_all_tags($post->post_content), 60),
            'type'  => $post->post_type,
            'score' => 0.4,
        ];
    }

    // -------------------------------------------------------------------------
    // Greeting handling
    // -------------------------------------------------------------------------

    private static function is_greeting(string $query): bool {
        foreach (self::$greeting_patterns as $pattern) {
            if (preg_match($pattern, $query)) return true;
        }
        return false;
    }

    private static function greeting_result(array $config): array {
        $faqs = self::get_faqs($config);
        $topics = array_slice(array_column($faqs, 'q'), 0, 5);

        $intro = __('Hi there! I can help you find information on this site.', 'foundation-frontdesk');
        if (!empty($topics)) {
            $intro .= ' ' . __('Here are some things people commonly ask about:', 'foundation-frontdesk');
            $intro .= "\n\n";
            foreach ($topics as $t) {
                $intro .= '• ' . $t . "\n";
            }
        }
        $intro .= "\n" . __('Just ask me anything and I\'ll do my best to help.', 'foundation-frontdesk');

        return [
            'chunks'  => [['title' => 'Greeting', 'url' => '', 'text' => $intro, 'type' => 'greeting', 'score' => 1.0]],
            'sources' => [],
            'status'  => ['is_greeting' => true, 'used_faqs' => false, 'used_index' => false, 'fallback_search' => false],
        ];
    }

    // -------------------------------------------------------------------------
    // Tokenizer
    // -------------------------------------------------------------------------

    private static function tokenize(string $text): array {
        $text  = function_exists('mb_strtolower') ? mb_strtolower($text) : strtolower($text);
        $text  = preg_replace('/[^a-z0-9\s\'-]/u', ' ', $text);
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        return array_values(array_filter(
            $words,
            fn($w) => strlen($w) >= 3 && !in_array($w, self::$stopwords, true)
        ));
    }

    // -------------------------------------------------------------------------
    // Helpers (public — used by other classes)
    // -------------------------------------------------------------------------

    public static function get_faqs(array $config): array {
        return Frontdesk_Config::faqs_array($config['faqs_json'] ?? '[]');
    }

    public static function selected_post_types(array $config): array {
        $types = explode(',', (string) ($config['rag_post_types'] ?? 'post,page'));
        $types = array_filter(array_map('sanitize_key', array_map('trim', $types)));
        return $types ?: ['post', 'page'];
    }

    public static function get_index_status(): array {
        $status    = get_option('fnd_frontdesk_rag_status', []);
        $status    = is_array($status) ? $status : [];
        $available = self::is_index_available();
        return [
            'available'  => $available,
            'status'     => $status['status'] ?? ($available ? 'available' : 'missing'),
            'indexed'    => (int) ($status['indexed'] ?? 0),
            'total'      => (int) ($status['total'] ?? 0),
            'post_types' => $status['post_types'] ?? [],
            'raw'        => $status,
        ];
    }

    public static function is_index_available(): bool {
        global $wpdb;
        if (empty($wpdb->prefix) || !class_exists('Foundation_Frontdesk_RAG')) return false;
        $table  = $wpdb->prefix . Foundation_Frontdesk_RAG::TABLE;
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($exists !== $table) return false;
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}") > 0;
    }

    private static function empty_result(): array {
        return ['chunks' => [], 'sources' => [], 'status' => []];
    }
}
