<?php
/**
 * Plugin Name: Orion AI Shopping Assistant
 * Description: Global WooCommerce AI chat assistant powered by OpenRouter, with live catalogue tools and a managed knowledge base.
 * Version: 0.3.0
 * Author: Orion Supplies
 * Requires at least: 6.2
 * Requires PHP: 8.0
 * WC requires at least: 8.0
 * Text Domain: orion-ai-assistant
 */

if (!defined('ABSPATH')) { exit; }

define('ORION_AI_VERSION', '0.3.0');
define('ORION_AI_SCHEMA_VERSION', '0.3.0');
define('ORION_AI_FILE', __FILE__);
define('ORION_AI_DIR', plugin_dir_path(__FILE__));
define('ORION_AI_URL', plugin_dir_url(__FILE__));

require_once ORION_AI_DIR . 'includes/class-orion-settings.php';
require_once ORION_AI_DIR . 'includes/class-orion-openrouter-client.php';
require_once ORION_AI_DIR . 'includes/class-orion-knowledge-base.php';
require_once ORION_AI_DIR . 'includes/class-orion-product-search.php';
require_once ORION_AI_DIR . 'includes/class-orion-rate-limiter.php';
require_once ORION_AI_DIR . 'includes/class-orion-conversation-service.php';
require_once ORION_AI_DIR . 'includes/class-orion-ceiling-calculator.php';
require_once ORION_AI_DIR . 'includes/class-orion-tool-executor.php';
require_once ORION_AI_DIR . 'includes/class-orion-chat-orchestrator.php';
require_once ORION_AI_DIR . 'includes/class-orion-rest-controller.php';
require_once ORION_AI_DIR . 'includes/class-orion-admin-controller.php';
require_once ORION_AI_DIR . 'includes/class-orion-ai-assistant.php';

register_activation_hook(__FILE__, array('Orion_AI_Assistant', 'activate'));
add_action('plugins_loaded', static function () {
    Orion_AI_Assistant::instance();
});
