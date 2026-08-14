# Orion AI Shopping Assistant

Private WooCommerce plugin that provides a grounded AI shopping assistant through OpenRouter or Google Gemini.

## Current development version

`0.13.0` is the open semantic planning development line. It starts from the unmerged `0.12.0` stabilization baseline and moves product planning from a closed role list toward AI-generated needs, open catalogue retrieval and evidence-based ranking.

The `0.12.0` pull request remains draft and is not merged.

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

## Architecture direction

- AI produces an open list of project needs from arbitrary customer language.
- Known roles remain optional hints and validator hooks, not a closed allowlist.
- Live WooCommerce title, description, category and attributes drive retrieval and ranking.
- Images may be used as secondary evidence when the configured provider supports vision.
- Deterministic code remains responsible for live IDs, stock, price, explicit compatibility evidence and unsupported-claim prevention.

See `docs/open-semantic-planning-0.13.0.md` for the development scope and migration plan.

## Privacy

The plugin stores conversations, usage metadata and operational events in custom tables. Configure finite retention and disclose AI processing in the store privacy policy.
