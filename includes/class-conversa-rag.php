<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Foundation: Conversa — RAG (Embeddings) engine
 *  - Maintains local embeddings table
 *  - Indexes site content (posts/pages/CPTs + optional ACF fields)
 *  - AJAX batch indexer with progress + stop
 *  - Retrieval helper for chat (semantic + optional FULLTEXT prefilter)
 *  - Housekeeping for orphaned rows on post delete
 *
 * Build: 2025-08-20
 */
class Foundation_Conversa_RAG {
    const TABLE = 'fnd_conversa_chunks';

    public static function init() {
        add_action('plugins_loaded', [__CLASS__, 'maybe_create_table']);

        // AJAX: Start/Status/Stop
        add_action('wp_ajax_fnd_conversa_rag_start',  [__CLASS__, 'ajax_start']);
        add_action('wp_ajax_fnd_conversa_rag_status', [__CLASS__, 'ajax_status']);
        add_action('wp_ajax_fnd_conversa_rag_stop',   [__CLASS__, 'ajax_stop']);

        // Cleanup on post delete/trashed
        add_action('deleted_post', [__CLASS__, 'delete_embeddings_for_post']);
        add_action('trashed_post', [__CLASS__, 'delete_embeddings_for_post']);
    }

    /** Option helper (supports both openai_api_key and api_key) */
    private static function opt($key, $default = '') {
        $opts = get_option('fnd_conversa_options', []);
        if ($key === 'api_key') {
            // prefer explicit OpenAI key if present
            if (!empty($opts['openai_api_key'])) return $opts['openai_api_key'];
        }
        return isset($opts[$key]) && $opts[$key] !== '' ? $opts[$key] : $default;
    }

    /** Create table if missing (adds indexes; FULLTEXT added only if missing) */
    public static function maybe_create_table() {
        global $wpdb;
        $table   = $wpdb->prefix . self::TABLE;
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT UNSIGNED NULL,
            post_type VARCHAR(40) NULL,
            url TEXT NULL,
            title TEXT NULL,
            chunk_index INT UNSIGNED NOT NULL DEFAULT 0,
            content MEDIUMTEXT NULL,
            embedding MEDIUMTEXT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY post_id_idx (post_id),
            KEY post_type_idx (post_type),
            KEY updated_idx (updated_at)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        // Add FULLTEXT index on content if it's missing and supported
        self::maybe_add_fulltext_index($table);
    }

    /** Check for FULLTEXT index existence */
    private static function has_fulltext_index($table) {
        global $wpdb;
        // INFORMATION_SCHEMA (works on MySQL/MariaDB)
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(1)
                   FROM INFORMATION_SCHEMA.STATISTICS
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME   = %s
                    AND INDEX_NAME   = %s",
                $table, 'content_ft'
            )
        );
        if ((int)$exists > 0) return true;

        // Fallback: SHOW INDEX
        $idx = $wpdb->get_results(
            $wpdb->prepare('SHOW INDEX FROM ' . $table . ' WHERE Key_name=%s', 'content_ft')
        );
        return !empty($idx);
    }

    /** Add FULLTEXT index safely (no duplicate-key errors) */
    private static function maybe_add_fulltext_index($table) {
        global $wpdb;
        if (self::has_fulltext_index($table)) return;

        $result = $wpdb->query("ALTER TABLE $table ADD FULLTEXT KEY content_ft (content)");
        if ($result === false && defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Conversa] FULLTEXT index could not be added (engine/version may not support it).');
        }
    }

    /** Remove embeddings for a specific post */
    public static function delete_embeddings_for_post($post_id) {
        if (!$post_id) return;
        global $wpdb; $table = $wpdb->prefix . self::TABLE;
        $wpdb->delete($table, ['post_id' => (int)$post_id], ['%d']);
    }

    /** Maintenance: purge orphaned rows where the post no longer exists */
    public static function clean_orphaned_embeddings() {
        global $wpdb; $table = $wpdb->prefix . self::TABLE;
        $wpdb->query(
            "DELETE e FROM $table e
             LEFT JOIN {$wpdb->posts} p ON e.post_id = p.ID
             WHERE e.post_id IS NOT NULL AND p.ID IS NULL"
        );
        return $wpdb->rows_affected;
    }

    /** Clean plain text */
    private static function clean($html) {
        $txt = wp_strip_all_tags((string)$html);
        $txt = html_entity_decode($txt, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $txt = preg_replace('/\s+/u', ' ', $txt);
        return trim($txt);
    }

    /** Build text for a post: Title + Content + ACF strings (if available) */
    private static function build_text_for_post($post_id) {
        $p = get_post($post_id); if (!$p) return '';
        if ($p->post_status !== 'publish') return '';

        $title   = get_the_title($p);
        $content = apply_filters('the_content', $p->post_content);
        $accum   = $title . "\n" . $content;

        // ACF fields: append simple string values
        if (function_exists('get_fields')) {
            $fields = get_fields($post_id);
            if (is_array($fields)) {
                foreach ($fields as $k => $v) {
                    if (is_string($v)) { $accum .= "\n" . $v; }
                }
            }
        }
        return self::clean($accum);
    }

    /** Sentence-aware chunker with overlap */
    private static function chunk_text($text, $max = 1400, $overlap = 150) {
        $text = trim((string)$text); if ($text === '') return [];
        $sentences = preg_split('/(?<=[.!?])\s+/u', $text) ?: [$text];
        $chunks = []; $current = '';
        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if ($sentence === '') continue;
            if (mb_strlen($current) + mb_strlen($sentence) + 1 > $max) {
                if ($current !== '') $chunks[] = trim($current);
                $tail = mb_substr($current, max(0, mb_strlen($current) - $overlap));
                $current = $tail . ' ' . $sentence;
            } else {
                $current .= ($current === '' ? '' : ' ') . $sentence;
            }
            if (count($chunks) > 100) break; // hard cap per post
        }
        if ($current !== '') $chunks[] = trim($current);
        return $chunks;
    }

    /** Call OpenAI embeddings */
    private static function embed_array($strings) {
        if (empty($strings)) return new WP_Error('empty_input','No text to embed');

        // Accept either key slot
        $api_key = self::opt('api_key', '');
        if (!$api_key) $api_key = self::opt('openai_api_key', '');
        if (!$api_key) return new WP_Error('no_key','OpenAI API key missing');

        $body = [ 'model' => 'text-embedding-3-small', 'input' => array_values($strings) ];
        $resp = wp_remote_post('https://api.openai.com/v1/embeddings', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
                'User-Agent'    => 'Foundation-Conversa/1.0 (+wp)',
            ],
            'body'    => wp_json_encode($body),
            'timeout' => 60,
        ]);
        if (is_wp_error($resp)) return $resp;

        $code = wp_remote_retrieve_response_code($resp);
        $raw  = wp_remote_retrieve_body($resp);
        $json = json_decode($raw, true);

        if ($code >= 400 || !is_array($json) || empty($json['data'])) {
            $msg = is_array($json) && isset($json['error']['message']) ? $json['error']['message'] : 'unknown';
            return new WP_Error('api_error', 'Embeddings API error: ' . $msg);
        }
        return array_map(function($row){ return $row['embedding']; }, $json['data']);
    }

    /** Upsert chunks for a single post */
    private static function index_post($post_id) {
        global $wpdb; $table = $wpdb->prefix . self::TABLE;
        $p = get_post($post_id); if (!$p || $p->post_status !== 'publish') return 0;

        $text = self::build_text_for_post($post_id); if ($text === '') return 0;
        $chunks = self::chunk_text($text);
        if (!$chunks) return 0;

        $vecs = self::embed_array($chunks);
        if (is_wp_error($vecs)) return 0;

        // delete old rows for this post (fresh upsert)
        $wpdb->delete($table, ['post_id' => (int)$post_id]);

        $now   = current_time('mysql');
        $url   = get_permalink($post_id);
        $title = get_the_title($post_id);

        $i = 0; $ins = 0;
        foreach ($chunks as $idx => $c) {
            if (!isset($vecs[$idx]) || !is_array($vecs[$idx])) continue;
            $emb = wp_json_encode($vecs[$idx]);
            $ok = $wpdb->insert($table, [
                'post_id'     => (int)$post_id,
                'post_type'   => $p->post_type,
                'url'         => $url,
                'title'       => $title,
                'chunk_index' => $i++,
                'content'     => $c,
                'embedding'   => $emb,
                'updated_at'  => $now,
            ], [ '%d','%s','%s','%s','%d','%s','%s','%s' ]);
            if ($ok) $ins++;
        }
        return $ins;
    }

    /** Batch indexer with basic memory + time hygiene */
    private static function index_batch($status) {
        if (function_exists('set_time_limit')) { @set_time_limit(20); }
        if (function_exists('gc_collect_cycles')) { @gc_collect_cycles(); }
        if (function_exists('gc_mem_caches')) { @gc_mem_caches(); }

        $post_types = isset($status['post_types']) && is_array($status['post_types']) ? $status['post_types'] : ['page','post'];
        $offset     = intval($status['offset'] ?? 0);
        $batch      = intval($status['batch'] ?? 5);
        if ($batch < 1) $batch = 1; if ($batch > 25) $batch = 25;

        $q = new WP_Query([
            'post_type'      => $post_types,
            'post_status'    => 'publish',
            'orderby'        => 'ID',
            'order'          => 'ASC',
            'posts_per_page' => $batch,
            'offset'         => $offset,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ]);

        foreach ($q->posts as $pid) { self::index_post($pid); }
        $status['indexed'] = intval($status['indexed']) + count($q->posts);
        $status['offset']  = $offset + count($q->posts);
        if (count($q->posts) < $batch) { $status['status'] = 'complete'; }

        // Adaptive batch shrink if memory usage spikes (>50MB)
        if (function_exists('memory_get_usage') && memory_get_usage(true) > 50 * 1024 * 1024) {
            $status['batch'] = max(1, (int) floor($batch * 0.8));
        }
        return $status;
    }

    /** Start indexing */
    public static function ajax_start() {
        check_ajax_referer('fnd_conversa_admin', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'foundation-conversa')], 403);
        }

        $requested = isset($_POST['post_types']) && is_array($_POST['post_types'])
            ? array_map('sanitize_key', $_POST['post_types'])
            : ['page','post'];
        $allowed    = get_post_types(['public' => true], 'names');
        $post_types = array_values(array_intersect($requested, $allowed));
        if (!$post_types) $post_types = ['page','post'];

        // Approximate total; CPT totals accounted during batches
        $total = 0;
        foreach ($post_types as $pt) { $obj = wp_count_posts($pt); $total += (int) ($obj->publish ?? 0); }

        $status = [
            'status'     => 'scanning',
            'total'      => $total,
            'indexed'    => 0,
            'offset'     => 0,
            'batch'      => 5,
            'post_types' => $post_types,
            'started'    => time(),
        ];
        update_option('fnd_conversa_rag_status', $status, false);
        $status = self::index_batch($status);
        update_option('fnd_conversa_rag_status', $status, false);
        wp_send_json_success($status);
    }

    /** Polling + run next batch */
    public static function ajax_status() {
        check_ajax_referer('fnd_conversa_admin', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'foundation-conversa')], 403);
        }

        $status = get_option('fnd_conversa_rag_status', []);
        if (($status['status'] ?? '') === 'scanning') {
            $status = self::index_batch($status);
            update_option('fnd_conversa_rag_status', $status, false);
        }
        wp_send_json_success($status ?: ['status' => 'idle', 'total' => 0, 'indexed' => 0]);
    }

    /** Stop indexing */
    public static function ajax_stop() {
        check_ajax_referer('fnd_conversa_admin', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'foundation-conversa')], 403);
        }
        $st = get_option('fnd_conversa_rag_status', []);
        $st['status'] = 'stopped';
        update_option('fnd_conversa_rag_status', $st, false);
        wp_send_json_success($st);
    }

    /** Cosine similarity */
    private static function cos($a, $b) {
        $dot = 0.0; $na = 0.0; $nb = 0.0; $n = min(count($a), count($b));
        for ($i=0; $i<$n; $i++) { $dot += $a[$i]*$b[$i]; $na += $a[$i]*$a[$i]; $nb += $b[$i]*$b[$i]; }
        if ($na == 0 || $nb == 0) return 0.0;
        return $dot / (sqrt($na)*sqrt($nb));
    }

    /** Retrieve K best chunks for a query (FULLTEXT prefilter when available) */
    public static function retrieve_context($query, $k = 6) {
        global $wpdb; $table = $wpdb->prefix . self::TABLE;
        $query = trim((string)$query);
        if ($query === '') return ['context' => '', 'sources' => []];

        $vec = self::embed_array([$query]);
        if (is_wp_error($vec)) return ['context' => '', 'sources' => []];
        $qv = $vec[0];

        // Query expansion hook
        $synonyms = apply_filters('fnd_conversa_query_synonyms', [], $query);
        $search_terms = array_unique(array_filter(array_merge([$query], is_array($synonyms) ? $synonyms : [])));

        $rows = [];
        $has_fulltext = self::has_fulltext_index($table);

        if ($has_fulltext && $search_terms) {
            $prefilter_limit = (int) apply_filters('fnd_conversa_prefilter_limit', 500);
            $prepared = $wpdb->prepare(
                "SELECT * FROM $table
                  WHERE MATCH(content) AGAINST(%s IN BOOLEAN MODE)
                  ORDER BY updated_at DESC
                  LIMIT %d",
                implode(' ', $search_terms),
                $prefilter_limit
            );
            $rows = $wpdb->get_results($prepared);
        }
        if (!$rows) {
            $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table ORDER BY updated_at DESC LIMIT %d", 500));
        }
        if (!$rows) return ['context' => '', 'sources' => []];

        $scored = [];
        foreach ($rows as $r) {
            $emb = json_decode($r->embedding, true); if (!is_array($emb)) continue;
            $scored[] = [ 'score' => self::cos($qv, $emb), 'row' => $r ];
        }
        usort($scored, function($a,$b){ return $a['score'] <=> $b['score']; });
        $scored = array_reverse($scored);
        $top = array_slice($scored, 0, (int)$k);

        $pieces = []; $srcs = [];
        foreach ($top as $t) {
            $r = $t['row'];
            $pieces[] = "TITLE: {$r->title}\nURL: {$r->url}\nCONTENT: " . $r->content;
            $srcs[]   = ['title' => $r->title, 'url' => $r->url];
        }
        return [ 'context' => $pieces ? implode("\n\n---\n\n", $pieces) : '', 'sources' => $srcs ];
    }
}

