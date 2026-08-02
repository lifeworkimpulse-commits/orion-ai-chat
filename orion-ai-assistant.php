<?php
/**
 * Plugin Name: Orion AI Shopping Assistant
 * Description: Global WooCommerce AI chat assistant powered by OpenRouter, with live catalogue tools and a managed knowledge base.
 * Version: 0.4.1
 * Author: Orion Supplies
 * Requires at least: 6.2
 * Requires PHP: 8.0
 * WC requires at least: 8.0
 * License: GPL-2.0-or-later
 * Text Domain: orion-ai-assistant
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
define( 'ORION_AI_VERSION', '0.4.1' );
define( 'ORION_AI_SCHEMA_VERSION', '0.4.0' );
define( 'ORION_AI_FILE', __FILE__ );
define( 'ORION_AI_DIR', plugin_dir_path( __FILE__ ) );
define( 'ORION_AI_URL', plugin_dir_url( __FILE__ ) );
foreach ( array(
 'class-orion-settings.php','class-orion-environment.php','class-orion-cleanup.php','class-orion-openrouter-client.php','class-orion-knowledge-base.php','class-orion-product-search.php','class-orion-rate-limiter.php','class-orion-conversation-service.php','class-orion-ceiling-calculator.php','class-orion-tool-executor.php','class-orion-chat-orchestrator.php','class-orion-rest-controller.php','class-orion-admin-controller.php','class-orion-ai-assistant.php'
) as $file ) require_once ORION_AI_DIR . 'includes/' . $file;
register_activation_hook( __FILE__, array( 'Orion_AI_Assistant', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Orion_AI_Assistant', 'deactivate' ) );
add_action( 'plugins_loaded', static function (): void {
    if ( Orion_Environment::ready() ) Orion_AI_Assistant::instance();
    elseif ( is_admin() ) add_action( 'admin_notices', static function (): void {
        echo '<div class="notice notice-error"><p><strong>Orion AI Assistant:</strong> ' . esc_html( implode( ' ', Orion_Environment::issues() ) ) . '</p></div>';
    } );
} );
