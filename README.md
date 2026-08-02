# Orion AI Shopping Assistant

A private WooCommerce plugin that provides a global AI shopping assistant powered by OpenRouter.

## Requirements

- WordPress 6.2+
- WooCommerce 8.0+
- PHP 8.0+ with DOM and mbstring
- HTTPS and outbound access to OpenRouter

## Local installation

Clone this repository directly into `wp-content/plugins/orion-ai-assistant`, activate it, then open **WooCommerce → AI Assistant**.

For production, store the API key in `wp-config.php`:

```php
define( 'ORION_AI_OPENROUTER_KEY', 'your-key' );
```

Never commit API keys. Use a fixed tool-capable model in production; `openrouter/free` is intended only for testing.

## Development

```bash
composer install
composer lint
composer analyse
composer test
```

## Privacy

The plugin stores conversations, usage metadata and operational events in custom tables. Configure a finite retention period and disclose AI processing in the site's privacy policy.
