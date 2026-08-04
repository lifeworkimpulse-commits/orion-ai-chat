<?php
/**
 * Plugin Name: Orion AI Shopping Assistant
 * Description: Semantic WooCommerce AI assistant powered by OpenRouter or Google Gemini.
 * Version: 0.8.4
 * Author: Orion Supplies
 * Requires at least: 6.2
 * Requires PHP: 8.0
 * WC requires at least: 8.0
 * License: GPL-2.0-or-later
 * Text Domain: orion-ai-assistant
 */
if(!defined('ABSPATH')){exit;}define('ORION_AI_VERSION','0.8.4');define('ORION_AI_SCHEMA_VERSION','0.8.0');define('ORION_AI_FILE',__FILE__);define('ORION_AI_DIR',plugin_dir_path(__FILE__));define('ORION_AI_URL',plugin_dir_url(__FILE__));
foreach(array('class-orion-settings.php','class-orion-environment.php','class-orion-cleanup.php','interface-orion-ai-provider.php','class-orion-openrouter-client.php','class-orion-gemini-client.php','class-orion-ai-provider-factory.php','class-orion-knowledge-base.php','class-orion-product-search.php','class-orion-rate-limiter.php','class-orion-conversation-service.php','class-orion-intent-classifier.php','class-orion-context-manager.php','class-orion-semantic-product-planner.php','class-orion-manager-handoff.php','class-orion-trace-service.php','class-orion-diagnostics-service.php','class-orion-chat-orchestrator.php','class-orion-rest-controller.php','class-orion-admin-controller.php','class-orion-ai-assistant.php','class-orion-cli-command.php')as$file)require_once ORION_AI_DIR.'includes/'.$file;
register_activation_hook(__FILE__,array('Orion_AI_Assistant','activate'));register_deactivation_hook(__FILE__,array('Orion_AI_Assistant','deactivate'));add_action('plugins_loaded',static function():void{if(Orion_Environment::ready())Orion_AI_Assistant::instance();elseif(is_admin())add_action('admin_notices',static function():void{echo'<div class="notice notice-error"><p><strong>Orion AI Assistant:</strong> '.esc_html(implode(' ',Orion_Environment::issues())).'</p></div>';});});
