<?php
if (!defined('ABSPATH')) { exit; }

class Frontdesk_Offline_Provider implements Frontdesk_AI_Provider {

    public function answer(array $request, array $context, array $config): array {
        $query  = trim((string) ($request['message'] ?? ''));
        $chunks = $context['chunks'] ?? [];
        $status = $context['status'] ?? [];

        // --- Greeting / intro ------------------------------------------------
        if (!empty($status['is_greeting'])) {
            $text = $chunks[0]['text'] ?? __('Hi there! How can I help?', 'foundation-frontdesk');
            return Frontdesk_Response::success($text, [
                'provider' => 'offline',
                'mode'     => 'greeting',
                'actions'  => [
                    ['type' => 'faq', 'label' => $config['kb_button_label'] ?? __('Browse FAQs', 'foundation-frontdesk')],
                ],
            ]);
        }

        // --- Hours query (fast path before chunk logic) ----------------------
        if (preg_match('/hours|open|close|when.*open|trading/i', $query)) {
            $hours = Frontdesk_Config::interpolate((string) ($config['opening_hours'] ?? ''));
            if (!empty($hours)) {
                return Frontdesk_Response::success($hours, [
                    'provider' => 'offline',
                    'mode'     => 'offline',
                    'actions'  => [
                        ['type' => 'contact', 'label' => $config['contact_button_label'] ?? __('Contact us', 'foundation-frontdesk')],
                    ],
                ]);
            }
        }

        if (empty($chunks)) {
            return $this->no_match_response($config);
        }

        // --- Route by source type --------------------------------------------
        $first = $chunks[0];
        $type  = $first['type'] ?? 'page';

        if ($type === 'faq') {
            return $this->faq_response($chunks, $context, $config);
        }

        if (in_array($type, ['page', 'post', 'site_index'], true)) {
            return $this->page_response($chunks, $context, $config, $query);
        }

        // Generic fallback — just return the first chunk text cleanly
        return Frontdesk_Response::success(
            $this->clean_text($first['text'] ?? ''),
            [
                'provider' => 'offline',
                'mode'     => Frontdesk_Config::runtime_mode(),
                'sources'  => $context['sources'] ?? [],
            ]
        );
    }

    // -------------------------------------------------------------------------
    // Response composers
    // -------------------------------------------------------------------------

    private function faq_response(array $chunks, array $context, array $config): array {
        $first = $chunks[0];
        $score = (float) ($first['score'] ?? 1.0);

        // High-confidence single match — answer directly
        if (count($chunks) === 1 || $score > 0.7) {
            $answer = $this->clean_text($first['text'] ?? '');
            if (!empty($first['title'])) {
                $answer = $first['title'] . "\n\n" . $answer;
            }
            return Frontdesk_Response::success($answer, [
                'provider' => 'offline',
                'mode'     => 'faq',
                'sources'  => $context['sources'] ?? [],
                'actions'  => [
                    ['type' => 'contact', 'label' => $config['contact_button_label'] ?? __('Contact us', 'foundation-frontdesk')],
                ],
            ]);
        }

        // Multiple partial matches — show the best one and note there's more
        $answer = __("Here's what I found that might help:", 'foundation-frontdesk') . "\n\n";
        foreach (array_slice($chunks, 0, 2) as $chunk) {
            if (!empty($chunk['title'])) {
                $answer .= '**' . $chunk['title'] . "**\n";
            }
            $answer .= $this->clean_text($chunk['text'] ?? '') . "\n\n";
        }

        return Frontdesk_Response::success(rtrim($answer), [
            'provider' => 'offline',
            'mode'     => 'faq',
            'sources'  => $context['sources'] ?? [],
            'actions'  => [
                ['type' => 'faq',     'label' => $config['kb_button_label'] ?? __('Browse all FAQs', 'foundation-frontdesk')],
                ['type' => 'contact', 'label' => $config['contact_button_label'] ?? __('Contact us', 'foundation-frontdesk')],
            ],
        ]);
    }

    private function page_response(array $chunks, array $context, array $config, string $query): array {
        $first = $chunks[0];

        // Single good match
        if (!empty($first['text'])) {
            $text  = $this->clean_text($first['text']);
            $title = $first['title'] ?? '';
            $url   = $first['url'] ?? '';

            if (!empty($title)) {
                $answer = sprintf(
                    __("I found some information about this on our site:\n\n**%s**\n\n%s", 'foundation-frontdesk'),
                    $title,
                    $text
                );
            } else {
                $answer = $text;
            }

            if ($url) {
                $answer .= "\n\n" . sprintf(
                    __('You can read more here: %s', 'foundation-frontdesk'),
                    $url
                );
            }

            return Frontdesk_Response::success($answer, [
                'provider' => 'offline',
                'mode'     => 'site_search',
                'sources'  => $context['sources'] ?? [],
                'actions'  => [
                    ['type' => 'contact', 'label' => $config['contact_button_label'] ?? __('Contact us', 'foundation-frontdesk')],
                ],
            ]);
        }

        return $this->no_match_response($config);
    }

    private function no_match_response(array $config): array {
        $notice = (string) ($config['offline_notice'] ?? '');
        if ($notice === '') {
            $notice = __("I wasn't able to find a direct answer to that. Try browsing the FAQ section, or get in touch and someone will help.", 'foundation-frontdesk');
        }
        return Frontdesk_Response::error($notice, [
            'provider'   => 'offline',
            'mode'       => 'offline',
            'error_code' => 'offline_no_match',
            'actions'    => [
                ['type' => 'faq',     'label' => $config['kb_button_label'] ?? __('Browse FAQs', 'foundation-frontdesk')],
                ['type' => 'contact', 'label' => $config['contact_button_label'] ?? __('Contact us', 'foundation-frontdesk')],
            ],
        ]);
    }

    // -------------------------------------------------------------------------

    private function clean_text(string $text): string {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = wp_strip_all_tags($text);
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        return trim($text);
    }

    public function validate(array $config): array {
        return [
            'ok'      => true,
            'message' => __('Offline mode is ready.', 'foundation-frontdesk'),
        ];
    }
}
