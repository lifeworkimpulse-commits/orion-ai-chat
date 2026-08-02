<?php
if (!defined('ABSPATH')) { exit; }

final class Orion_Knowledge_Base {
    private string $table;
    private string $chunks_table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'orion_ai_documents';
        $this->chunks_table = $wpdb->prefix . 'orion_ai_document_chunks';
    }

    public function import_url(string $url): array {
        $url = esc_url_raw(trim($url));
        $home_host = strtolower((string) wp_parse_url(home_url('/'), PHP_URL_HOST));
        $url_host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
        $scheme = strtolower((string) wp_parse_url($url, PHP_URL_SCHEME));
        if (!$url || $url_host !== $home_host || !in_array($scheme, array('http', 'https'), true)) {
            return array('ok' => false, 'message' => 'Only HTTP(S) URLs from this WordPress site are allowed.');
        }

        $response = wp_safe_remote_get($url, array(
            'timeout' => 25,
            'redirection' => 3,
            'user-agent' => 'OrionAIKnowledgeImporter/0.3',
        ));
        if (is_wp_error($response)) return array('ok' => false, 'message' => $response->get_error_message());
        if ((int) wp_remote_retrieve_response_code($response) !== 200) {
            return array('ok' => false, 'message' => 'The page could not be loaded.');
        }
        $content_type = strtolower((string) wp_remote_retrieve_header($response, 'content-type'));
        if ($content_type !== '' && !str_contains($content_type, 'text/html')) {
            return array('ok' => false, 'message' => 'Only HTML pages can be imported.');
        }
        $html = wp_remote_retrieve_body($response);
        if (strlen($html) > 2000000) return array('ok' => false, 'message' => 'The page is too large to import.');

        [$title, $markdown] = $this->html_to_markdown($html);
        if (mb_strlen($markdown) < 80) {
            return array('ok' => false, 'message' => 'Not enough readable page content was found.');
        }

        global $wpdb;
        $now = current_time('mysql', true);
        $data = array(
            'source_url' => $url,
            'title' => $title ?: $url,
            'markdown' => $markdown,
            'content_hash' => hash('sha256', $markdown),
            'updated_at' => $now,
        );
        $document_id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->table} WHERE source_url = %s", $url));
        if ($document_id) {
            $wpdb->update($this->table, $data, array('id' => $document_id));
        } else {
            $data['created_at'] = $now;
            $wpdb->insert($this->table, $data);
            $document_id = (int) $wpdb->insert_id;
        }
        $this->reindex_document($document_id, $markdown);
        return array('ok' => true, 'message' => 'Page imported and indexed into the knowledge base.');
    }

    public function create_manual(string $title, string $markdown): array {
        $title = sanitize_text_field(wp_unslash($title));
        $markdown = wp_kses_post(wp_unslash($markdown));
        if ($title === '' || mb_strlen(trim($markdown)) < 20) {
            return array('ok' => false, 'message' => 'Enter a title and at least 20 characters of knowledge.');
        }

        global $wpdb;
        $now = current_time('mysql', true);
        $ok = $wpdb->insert($this->table, array(
            'source_url' => '',
            'title' => $title,
            'markdown' => $markdown,
            'content_hash' => hash('sha256', $markdown),
            'created_at' => $now,
            'updated_at' => $now,
        ));
        if ($ok) $this->reindex_document((int) $wpdb->insert_id, $markdown);
        return array('ok' => (bool) $ok, 'message' => $ok ? 'Manual document created and indexed.' : 'The document could not be created.');
    }

    public function refresh_document(int $id): array {
        global $wpdb;
        $url = (string) $wpdb->get_var($wpdb->prepare("SELECT source_url FROM {$this->table} WHERE id = %d", $id));
        if ($url === '') return array('ok' => false, 'message' => 'Manual documents do not have a source URL.');
        return $this->import_url($url);
    }

    public function save_document(int $id, string $title, string $markdown): bool {
        global $wpdb;
        $title = sanitize_text_field(wp_unslash($title));
        $markdown = wp_kses_post(wp_unslash($markdown));
        $saved = false !== $wpdb->update($this->table, array(
            'title' => $title,
            'markdown' => $markdown,
            'content_hash' => hash('sha256', $markdown),
            'updated_at' => current_time('mysql', true),
        ), array('id' => $id));
        if ($saved) $this->reindex_document($id, $markdown);
        return $saved;
    }

    public function delete_document(int $id): void {
        global $wpdb;
        $wpdb->delete($this->chunks_table, array('document_id' => $id));
        $wpdb->delete($this->table, array('id' => $id));
    }

    public function all(): array {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM {$this->table} ORDER BY updated_at DESC", ARRAY_A) ?: array();
    }

    public function reindex_all(): int {
        $count = 0;
        foreach ($this->all() as $document) {
            $this->reindex_document((int) $document['id'], (string) $document['markdown']);
            $count++;
        }
        return $count;
    }

    public function relevant(string $query, int $limit = 3): array {
        global $wpdb;
        $limit = max(1, min(6, $limit));
        $tokens = $this->keywords($query);
        if (!$tokens) return array();

        $chunks = $wpdb->get_results(
            "SELECT c.document_id, c.heading, c.content, d.title, d.source_url FROM {$this->chunks_table} c INNER JOIN {$this->table} d ON d.id = c.document_id ORDER BY c.document_id ASC, c.position ASC",
            ARRAY_A
        ) ?: array();

        if (!$chunks) {
            return $this->legacy_relevant($query, $tokens, $limit);
        }

        $phrase = strtolower(trim($query));
        foreach ($chunks as &$chunk) {
            $title = strtolower((string) $chunk['title']);
            $heading = strtolower((string) $chunk['heading']);
            $body = strtolower((string) $chunk['content']);
            $score = 0;
            foreach ($tokens as $token) {
                $score += substr_count($title, $token) * 8;
                $score += substr_count($heading, $token) * 4;
                $score += min(8, substr_count($body, $token));
            }
            if (strlen($phrase) > 5 && str_contains($body, $phrase)) $score += 20;
            $chunk['_score'] = $score;
        }
        unset($chunk);
        usort($chunks, static fn($a, $b) => $b['_score'] <=> $a['_score']);
        $chunks = array_slice(array_filter($chunks, static fn($chunk) => $chunk['_score'] > 0), 0, $limit);

        return array_map(static fn($chunk) => array(
            'title' => $chunk['title'] . ($chunk['heading'] ? ' — ' . $chunk['heading'] : ''),
            'source_url' => $chunk['source_url'],
            'markdown' => mb_substr($chunk['content'], 0, 2400),
            'score' => $chunk['_score'],
        ), $chunks);
    }

    private function reindex_document(int $document_id, string $markdown): void {
        global $wpdb;
        $wpdb->delete($this->chunks_table, array('document_id' => $document_id));
        $chunks = $this->chunk_markdown($markdown);
        $now = current_time('mysql', true);
        foreach ($chunks as $position => $chunk) {
            $wpdb->insert($this->chunks_table, array(
                'document_id' => $document_id,
                'position' => $position,
                'heading' => $chunk['heading'],
                'content' => $chunk['content'],
                'content_hash' => hash('sha256', $chunk['content']),
                'created_at' => $now,
            ));
        }
    }

    private function chunk_markdown(string $markdown): array {
        $parts = preg_split('/\n\s*\n/u', trim($markdown)) ?: array();
        $chunks = array();
        $buffer = '';
        $heading = '';
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') continue;
            if (preg_match('/^#{1,3}\s+(.+)/u', $part, $matches)) $heading = trim($matches[1]);
            if ($buffer !== '' && mb_strlen($buffer) + mb_strlen($part) + 2 > 1600) {
                $chunks[] = array('heading' => $heading, 'content' => $buffer);
                $buffer = '';
            }
            $buffer .= ($buffer === '' ? '' : "\n\n") . $part;
        }
        if ($buffer !== '') $chunks[] = array('heading' => $heading, 'content' => $buffer);
        return $chunks ?: array(array('heading' => '', 'content' => mb_substr($markdown, 0, 2400)));
    }

    private function legacy_relevant(string $query, array $tokens, int $limit): array {
        $docs = $this->all();
        $phrase = strtolower(trim($query));
        foreach ($docs as &$doc) {
            $title = strtolower($doc['title']);
            $body = strtolower($doc['markdown']);
            $score = 0;
            foreach ($tokens as $token) {
                $score += substr_count($title, $token) * 8;
                $score += min(8, substr_count($body, $token));
            }
            if (strlen($phrase) > 5 && str_contains($body, $phrase)) $score += 20;
            $doc['_score'] = $score;
        }
        unset($doc);
        usort($docs, static fn($a, $b) => $b['_score'] <=> $a['_score']);
        $matched = array_slice(array_filter($docs, static fn($doc) => $doc['_score'] > 0), 0, $limit);
        return array_map(static fn($doc) => array(
            'title' => $doc['title'],
            'source_url' => $doc['source_url'],
            'markdown' => mb_substr($doc['markdown'], 0, 2400),
            'score' => $doc['_score'],
        ), $matched);
    }

    private function keywords(string $query): array {
        $stop = array('what', 'which', 'with', 'that', 'this', 'from', 'have', 'does', 'your', 'about', 'would', 'could', 'where', 'when', 'there', 'their', 'the', 'and', 'for', 'you', 'are', 'can', 'how');
        $tokens = preg_split('/[^\p{L}\p{N}-]+/u', mb_strtolower($query)) ?: array();
        return array_values(array_unique(array_filter($tokens, static fn($token) => mb_strlen($token) > 2 && !in_array($token, $stop, true))));
    }

    private function html_to_markdown(string $html): array {
        if (!class_exists('DOMDocument')) return array('', trim(wp_strip_all_tags($html)));
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);
        foreach (array('//script', '//style', '//noscript', '//svg', '//nav', '//header', '//footer', '//form', '//aside', '//*[@aria-hidden="true"]') as $query) {
            foreach ($xpath->query($query) as $node) $node->parentNode?->removeChild($node);
        }
        $title = '';
        $nodes = $dom->getElementsByTagName('title');
        if ($nodes->length) $title = trim($nodes->item(0)->textContent);
        $main = $xpath->query('//main')->item(0) ?: $xpath->query('//*[@role="main"]')->item(0) ?: $dom->getElementsByTagName('body')->item(0);
        if (!$main) return array($title, '');
        $out = array();
        foreach ($xpath->query('.//h1|.//h2|.//h3|.//p|.//li', $main) as $node) {
            $text = preg_replace('/\s+/u', ' ', trim($node->textContent));
            if (!$text || mb_strlen($text) < 2) continue;
            $tag = strtolower($node->nodeName);
            $prefix = array('h1' => '# ', 'h2' => '## ', 'h3' => '### ', 'li' => '- ')[$tag] ?? '';
            $out[] = $prefix . $text;
        }
        return array($title, trim(implode("\n\n", array_values(array_unique($out)))));
    }
}
