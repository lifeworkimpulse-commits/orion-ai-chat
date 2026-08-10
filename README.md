# Orion AI Shopping Assistant

Private WooCommerce plugin that provides a grounded AI shopping assistant through OpenRouter or Google Gemini.

## Current development version

`0.12.0` is the stabilization release. It centralizes semantic role contracts, extracts structured product facts, isolates deterministic routing and kit validation, and adds permanent unit-test CI.

## Requirements

- WordPress 6.2+
- WooCommerce 8.0+
- PHP 8.0+
- DOM and mbstring
- HTTPS and outbound access to the configured AI provider

## Local installation

Clone the repository into `wp-content/plugins/orion-ai-assistant`, activate it, then open **WooCommerce → AI Assistant**.

For production, keep credentials in `wp-config.php`:

```php
define('ORION_AI_OPENROUTER_KEY','your-key');
define('ORION_AI_GOOGLE_KEY','your-key');
```

Never commit API keys.

## Development

```bash
composer install
composer test
composer analyse
composer lint
```

CI validates PHP 8.0–8.3, PHP syntax, unit tests, static analysis, JavaScript syntax and evaluation JSON.

## Operations

```bash
wp orion-ai status
wp orion-ai migrate --apply
wp orion-ai catalogue-index --apply
wp orion-ai catalogue-audit --details
wp orion-ai evaluate --provider=openrouter --model=<model>
```

## Architecture

- `Orion_Role_Registry`: canonical product-role contract and priorities.
- `Orion_Routing_Rules`: deterministic delivery, dimension and project-state rules.
- `Orion_Product_Facts`: normalized functions, surfaces, components, widths and coverage evidence.
- `Orion_Kit_Validator`: complete roller systems and tray compatibility.
- `Orion_Semantic_Product_Planner`: retrieval, structured AI selection and live-product validation.

See `docs/stabilization-0.12.0.md` for release scope and rollout gates.

## Privacy

The plugin stores conversations, usage metadata and operational events in custom tables. Configure finite retention and disclose AI processing in the store privacy policy.
