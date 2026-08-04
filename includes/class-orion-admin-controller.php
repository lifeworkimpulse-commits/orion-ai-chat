<?php
if (!defined('ABSPATH')) { exit; }

final class Orion_Admin_Controller {
    private Orion_Knowledge_Base $knowledge;
    private Orion_Conversation_Service $conversations;

    public function __construct(Orion_Knowledge_Base $knowledge, Orion_Conversation_Service $conversations) {
        $this->knowledge = $knowledge;
        $this->conversations = $conversations;
    }

    public function register(): void {
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_post_orion_ai_import', array($this, 'handle_import'));
        add_action('admin_post_orion_ai_document', array($this, 'handle_document'));
        add_action('admin_post_orion_ai_manual_document', array($this, 'handle_manual_document'));
        add_action('admin_post_orion_ai_reindex_knowledge', array($this, 'handle_reindex'));
        add_action('admin_post_orion_ai_test_provider', array($this, 'handle_test_provider'));
        add_action('admin_post_orion_ai_remove_key', array($this, 'handle_remove_key'));
        add_action('admin_post_orion_ai_resolve_handoff', array($this, 'handle_resolve_handoff'));
    }

    public function admin_menu(): void {
        add_submenu_page('woocommerce', 'Orion AI Assistant', 'AI Assistant', 'manage_woocommerce', 'orion-ai-assistant', array($this, 'render'));
    }

    public function register_settings(): void {
        register_setting('orion_ai_group', Orion_AI_Settings::OPTION, array('sanitize_callback' => array(Orion_AI_Settings::class, 'sanitize')));
    }

    public function render(): void {
        if (!current_user_can('manage_woocommerce')) return;
        $tab = sanitize_key(wp_unslash($_GET['tab'] ?? 'settings'));
        if (!in_array($tab, array('settings', 'knowledge', 'handoffs', 'traces', 'analytics'), true)) $tab = 'settings';
        $tabs = array('settings' => 'Assistant settings', 'knowledge' => 'Knowledge base', 'handoffs' => 'Manager queue', 'traces' => 'AI traces', 'analytics' => 'Analytics');

        echo '<div class="wrap orion-admin"><h1>Orion AI Assistant</h1>';
        if (!empty($_GET['orion_notice'])) {
            echo '<div class="notice notice-info is-dismissible"><p>' . esc_html(wp_unslash($_GET['orion_notice'])) . '</p></div>';
        }
        echo '<nav class="nav-tab-wrapper">';
        foreach ($tabs as $key => $label) {
            echo '<a class="nav-tab ' . ($tab === $key ? 'nav-tab-active' : '') . '" href="' . esc_url(add_query_arg(array('page' => 'orion-ai-assistant', 'tab' => $key), admin_url('admin.php'))) . '">' . esc_html($label) . '</a>';
        }
        echo '</nav>';
        if ($tab === 'knowledge') $this->render_knowledge();
        elseif ($tab === 'handoffs') $this->render_handoffs();
        elseif ($tab === 'traces') $this->render_traces();
        elseif ($tab === 'analytics') $this->render_analytics();
        else $this->render_settings();
        echo '</div><style>.orion-admin .orion-card{max-width:900px;background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:22px;margin-top:20px}.orion-admin .orion-doc{border-top:1px solid #eee;padding:12px 0}.orion-admin .orion-doc summary{cursor:pointer;font-weight:600}.orion-admin textarea{max-width:100%}.orion-admin .orion-metrics{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px}.orion-admin .orion-metric{padding:16px;border:1px solid #dcdcde;border-radius:8px;background:#fff}.orion-admin .orion-metric strong{display:block;font-size:26px}</style>';
    }

    private function render_settings(): void {
        $settings = Orion_AI_Settings::get();
        $openrouter_status = Orion_AI_Settings::key_configured('openrouter', $settings) ? '•••••••••••••••• — configured via ' . Orion_AI_Settings::key_source('openrouter', $settings) : 'Not configured';
        $google_status = Orion_AI_Settings::key_configured('google', $settings) ? '•••••••••••••••• — configured via ' . Orion_AI_Settings::key_source('google', $settings) : 'Not configured';
        echo '<section class="orion-card"><h2>Assistant settings</h2><p>Choose one active provider. Keys are never rendered back into the page. For production, define <code>ORION_AI_OPENROUTER_KEY</code> or <code>ORION_AI_GOOGLE_KEY</code> in <code>wp-config.php</code>.</p><form method="post" action="options.php">';
        settings_fields('orion_ai_group');
        echo '<p><label><input type="checkbox" name="orion_ai_settings[enabled]" value="1" ' . checked($settings['enabled'], '1', false) . '> Enable assistant</label></p>';
        echo '<p><label><strong>AI provider</strong><br><select name="orion_ai_settings[provider]"><option value="openrouter" ' . selected($settings['provider'], 'openrouter', false) . '>OpenRouter</option><option value="google" ' . selected($settings['provider'], 'google', false) . '>Google Gemini</option></select></label></p>';
        $this->field('OpenRouter API key', 'openrouter_api_key', 'password', '', $openrouter_status . '. Paste a new key only to replace it.');
        $this->field('OpenRouter model', 'openrouter_model', 'text', $settings['openrouter_model'], 'Use openrouter/free for testing or a fixed tool-capable model for production.');
        $this->field('Google Gemini API key', 'google_api_key', 'password', '', $google_status . '. Paste a new key only to replace it.');
        $this->field('Google Gemini model', 'google_model', 'text', $settings['google_model'], 'Use a model available to your Google AI Studio account with function calling support.');
        $this->field('Routing model', 'routing_model', 'text', $settings['routing_model'], 'Blank inherits the active provider model. Use a fixed tool-capable model for reliable classification.');
        $this->field('Product selection model', 'selection_model', 'text', $settings['selection_model'], 'Blank inherits the active provider model.');
        $this->field('Answer model', 'answer_model', 'text', $settings['answer_model'], 'Blank inherits the active provider model.');
        echo '<p><label><strong>Fallback provider</strong><br><select name="orion_ai_settings[fallback_provider]"><option value="none" ' . selected($settings['fallback_provider'], 'none', false) . '>Disabled</option><option value="openrouter" ' . selected($settings['fallback_provider'], 'openrouter', false) . '>OpenRouter</option><option value="google" ' . selected($settings['fallback_provider'], 'google', false) . '>Google Gemini</option></select></label></p>';
        $this->field('Fallback model', 'fallback_model', 'text', $settings['fallback_model'], 'Leave blank to use the fallback provider default model.');
        $this->field('Routing timeout (seconds)', 'routing_timeout', 'number', (string)$settings['routing_timeout']);
        $this->field('Selection timeout (seconds)', 'selection_timeout', 'number', (string)$settings['selection_timeout']);
        $this->field('Answer timeout (seconds)', 'answer_timeout', 'number', (string)$settings['answer_timeout']);
        echo '<p class="description">Save settings before testing a provider.</p>';
        $this->field('Chat title', 'title', 'text', $settings['title']);
        $this->field('Greeting', 'greeting', 'textarea', $settings['greeting']);
        $this->field('Daily-limit message', 'limit_message', 'textarea', $settings['limit_message']);
        $this->field('Primary colour', 'primary_color', 'color', $settings['primary_color']);
        echo '<p><label><strong>Position</strong><br><select name="orion_ai_settings[position]"><option value="right" ' . selected($settings['position'], 'right', false) . '>Bottom right</option><option value="left" ' . selected($settings['position'], 'left', false) . '>Bottom left</option></select></label></p>';
        foreach (array(
            'questions_per_session' => 'Questions per session',
            'sessions_per_day' => 'Sessions per day',
            'session_minutes' => 'Session timeout (minutes)',
            'requests_per_minute' => 'Maximum requests per minute per visitor',
            'max_products' => 'Maximum product cards',
            'retention_days' => 'Retention days (0 keeps conversations indefinitely)',
        ) as $key => $label) {
            $this->field($label, $key, 'number', (string) $settings[$key]);
        }
        $this->field('System instructions', 'system_prompt', 'textarea', $settings['system_prompt']);
        submit_button('Save settings');
        echo '</form><hr><h3>Connection tools</h3>';
        foreach (array('openrouter' => 'OpenRouter', 'google' => 'Google Gemini') as $provider => $label) {
            echo '<div style="display:flex;gap:8px;align-items:center;margin:8px 0"><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('orion_ai_test_provider');
            echo '<input type="hidden" name="action" value="orion_ai_test_provider"><input type="hidden" name="provider" value="' . esc_attr($provider) . '"><button class="button">Test ' . esc_html($label) . '</button></form>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('orion_ai_remove_key');
            echo '<input type="hidden" name="action" value="orion_ai_remove_key"><input type="hidden" name="provider" value="' . esc_attr($provider) . '"><button class="button button-link-delete">Remove saved key</button></form></div>';
        }
        echo '</section>';
    }

    private function render_knowledge(): void {
        $documents = $this->knowledge->all();
        echo '<section class="orion-card"><h2>Knowledge base</h2><p>Import only approved public pages from this site. New and edited documents are split into searchable fragments automatically.</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('orion_ai_import');
        echo '<input type="hidden" name="action" value="orion_ai_import"><p><input class="regular-text" type="url" required name="url" placeholder="https://example.com/delivery-and-returns/"></p>';
        submit_button('Import page', 'secondary', 'submit', false);
        echo '</form><hr><h3>Add manual document</h3><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('orion_ai_manual_document');
        echo '<input type="hidden" name="action" value="orion_ai_manual_document"><p><input class="widefat" name="title" required placeholder="Document title"></p><p><textarea class="widefat code" name="markdown" rows="6" required placeholder="Store policy or product guidance in Markdown..."></textarea></p>';
        submit_button('Create manual document', 'secondary', 'submit', false);
        echo '</form><p><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('orion_ai_reindex_knowledge');
        echo '<input type="hidden" name="action" value="orion_ai_reindex_knowledge">';
        submit_button('Reindex all existing documents', 'secondary', 'submit', false);
        echo '</form></p>';
        if (!$documents) echo '<p class="description">No documents imported yet.</p>';
        foreach ($documents as $document) {
            echo '<details class="orion-doc"><summary>' . esc_html($document['title']) . '</summary><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('orion_ai_document');
            echo '<input type="hidden" name="action" value="orion_ai_document"><input type="hidden" name="document_id" value="' . (int) $document['id'] . '">';
            if ($document['source_url']) echo '<p class="description"><a href="' . esc_url($document['source_url']) . '" target="_blank" rel="noopener">' . esc_html($document['source_url']) . '</a></p>';
            else echo '<p class="description">Manual document</p>';
            echo '<p><input class="widefat" name="title" value="' . esc_attr($document['title']) . '"></p><p><textarea class="widefat code" rows="14" name="markdown">' . esc_textarea($document['markdown']) . '</textarea></p><p><button class="button button-primary" name="document_action" value="save">Save and reindex</button> ';
            if ($document['source_url']) echo '<button class="button" name="document_action" value="refresh">Refresh from URL</button> ';
            echo '<button class="button button-link-delete" name="document_action" value="delete" onclick="return confirm(\'Delete this document and its search fragments?\')">Delete</button></p></form></details>';
        }
        echo '</section>';
    }

    private function render_handoffs(): void {
        $items = (new Orion_Manager_Handoff())->all();
        echo '<section class="orion-card"><h2>Manager follow-up queue</h2><p>Questions appear here only when the assistant cannot provide a confirmed answer or suitable product set.</p>';
        if (!$items) echo '<p>No follow-up requests.</p>';
        foreach ($items as $item) {
            $context = json_decode((string)$item['context_json'], true);
            echo '<article class="orion-doc"><p><strong>#' . (int)$item['id'] . ' — ' . esc_html(ucfirst($item['status'])) . '</strong><br><small>' . esc_html($item['created_at']) . '</small></p>';
            echo '<p>' . nl2br(esc_html($item['question'])) . '</p>';
            if (is_array($context)) echo '<details><summary>Context</summary><pre style="white-space:pre-wrap">' . esc_html(wp_json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre></details>';
            if ('resolved' !== $item['status']) {
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
                wp_nonce_field('orion_ai_resolve_handoff');
                echo '<input type="hidden" name="action" value="orion_ai_resolve_handoff"><input type="hidden" name="handoff_id" value="' . (int)$item['id'] . '"><button class="button button-primary">Mark resolved</button></form>';
            }
            echo '</article>';
        }
        echo '</section>';
    }

    private function render_traces(): void {
        $service = new Orion_AI_Trace_Service();
        $selected = absint($_GET['trace_id'] ?? 0);
        echo '<section class="orion-card"><h2>AI request traces</h2><p>Diagnostic pipeline data with secrets redacted. Customer messages are retained according to the plugin retention setting.</p>';
        if ($selected) {
            $trace = $service->get($selected);
            if ($trace) echo '<p><a href="' . esc_url(add_query_arg(array('page'=>'orion-ai-assistant','tab'=>'traces'),admin_url('admin.php'))) . '">← Back to traces</a></p><pre style="white-space:pre-wrap;max-height:700px;overflow:auto">' . esc_html(wp_json_encode($trace, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '</pre>';
            else echo '<p>Trace not found.</p>';
            echo '</section>'; return;
        }
        $items = $service->all(100);
        if (!$items) echo '<p>No traces recorded yet.</p>';
        else { echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Provider/model</th><th>Intent/topic</th><th>Status</th><th>Failure</th><th>Duration</th><th>Date</th></tr></thead><tbody>';
            foreach ($items as $item) { $url=add_query_arg(array('page'=>'orion-ai-assistant','tab'=>'traces','trace_id'=>(int)$item['id']),admin_url('admin.php')); echo '<tr><td><a href="'.esc_url($url).'">#'.(int)$item['id'].'</a></td><td>'.esc_html($item['provider'].' / '.$item['model']).'</td><td>'.esc_html($item['intent'].' / '.$item['topic']).'</td><td>'.esc_html($item['status']).'</td><td>'.esc_html(trim($item['failure_stage'].' '.$item['failure_reason'])).'</td><td>'.(int)$item['duration_ms'].' ms</td><td>'.esc_html($item['created_at']).'</td></tr>'; }
            echo '</tbody></table>'; }
        echo '</section>';
    }

    private function render_analytics(): void {
        $analytics = $this->conversations->analytics();
        echo '<section class="orion-card"><h2>Analytics — last ' . (int) $analytics['days'] . ' days</h2><div class="orion-metrics"><div class="orion-metric"><span>Conversations started</span><strong>' . (int) $analytics['conversations'] . '</strong></div>';
        foreach ($analytics['events'] as $event) {
            echo '<div class="orion-metric"><span>' . esc_html(str_replace('_', ' ', $event['event_type'])) . '</span><strong>' . (int) $event['total'] . '</strong></div>';
        }
        echo '</div><p class="description">Events are aggregate operational metrics. Customer message text is not shown on this screen.</p></section>';
    }

    public function handle_resolve_handoff(): void {
        $this->guard('orion_ai_resolve_handoff');
        $id = absint($_POST['handoff_id'] ?? 0);
        $ok = $id > 0 && (new Orion_Manager_Handoff())->resolve($id);
        $this->redirect('handoffs', $ok ? 'Follow-up marked resolved.' : 'Follow-up could not be updated.');
    }

    public function handle_test_provider(): void {
        $this->guard('orion_ai_test_provider');
        $provider = sanitize_key(wp_unslash($_POST['provider'] ?? 'openrouter'));
        $settings = Orion_AI_Settings::get();
        $settings['provider'] = in_array($provider, array('openrouter', 'google'), true) ? $provider : 'openrouter';
        $result = Orion_AI_Provider_Factory::create($settings)->chat(array(array('role' => 'user', 'content' => 'Reply with exactly: Connection successful')));
        $message = !empty($result['ok']) ? ucfirst($settings['provider']) . ' connection successful.' : (string)($result['error'] ?? 'Connection failed.');
        $this->redirect('settings', $message);
    }

    public function handle_remove_key(): void {
        $this->guard('orion_ai_remove_key');
        $provider = sanitize_key(wp_unslash($_POST['provider'] ?? 'openrouter'));
        $settings = Orion_AI_Settings::get();
        $key = 'google' === $provider ? 'google_api_key' : 'openrouter_api_key';
        $constant = 'google' === $provider ? 'ORION_AI_GOOGLE_KEY' : 'ORION_AI_OPENROUTER_KEY';
        if (defined($constant) && trim((string)constant($constant)) !== '') {
            $this->redirect('settings', 'This key is defined in wp-config.php and cannot be removed here.');
        }
        $settings[$key] = '';
        update_option(Orion_AI_Settings::OPTION, $settings, false);
        $this->redirect('settings', ucfirst($provider) . ' saved key removed.');
    }

    public function handle_import(): void {
        $this->guard('orion_ai_import');
        $result = $this->knowledge->import_url((string) wp_unslash($_POST['url'] ?? ''));
        $this->redirect('knowledge', $result['message']);
    }

    public function handle_document(): void {
        $this->guard('orion_ai_document');
        $id = absint($_POST['document_id'] ?? 0);
        $action = sanitize_key(wp_unslash($_POST['document_action'] ?? 'save'));
        if ($action === 'delete') {
            $this->knowledge->delete_document($id);
            $message = 'Document deleted.';
        } elseif ($action === 'refresh') {
            $message = $this->knowledge->refresh_document($id)['message'];
        } else {
            $this->knowledge->save_document($id, (string) wp_unslash($_POST['title'] ?? ''), (string) wp_unslash($_POST['markdown'] ?? ''));
            $message = 'Document saved and reindexed.';
        }
        $this->redirect('knowledge', $message);
    }

    public function handle_manual_document(): void {
        $this->guard('orion_ai_manual_document');
        $result = $this->knowledge->create_manual((string) wp_unslash($_POST['title'] ?? ''), (string) wp_unslash($_POST['markdown'] ?? ''));
        $this->redirect('knowledge', $result['message']);
    }

    public function handle_reindex(): void {
        $this->guard('orion_ai_reindex_knowledge');
        $this->redirect('knowledge', $this->knowledge->reindex_all() . ' document(s) reindexed.');
    }

    private function guard(string $action): void {
        if (!current_user_can('manage_woocommerce')) wp_die('Forbidden');
        check_admin_referer($action);
    }

    private function redirect(string $tab, string $message): void {
        wp_safe_redirect(add_query_arg(array('page' => 'orion-ai-assistant', 'tab' => $tab, 'orion_notice' => $message), admin_url('admin.php')));
        exit;
    }

    private function field(string $label, string $key, string $type, string $value, string $help = ''): void {
        echo '<p><label><strong>' . esc_html($label) . '</strong><br>';
        if ($type === 'textarea') {
            echo '<textarea class="large-text" rows="4" name="orion_ai_settings[' . esc_attr($key) . ']">' . esc_textarea($value) . '</textarea>';
        } else {
            echo '<input class="regular-text" type="' . esc_attr($type) . '" name="orion_ai_settings[' . esc_attr($key) . ']" value="' . esc_attr($value) . '"' . ($type === 'number' ? ' min="0"' : '') . '>';
        }
        if ($help) echo '<br><span class="description">' . esc_html($help) . '</span>';
        echo '</label></p>';
    }
}
