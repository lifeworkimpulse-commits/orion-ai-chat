# Orion AI Shopping Assistant

Private WooCommerce plugin that provides a grounded AI shopping assistant through OpenRouter or Google Gemini.

## Current development version

`0.13.0` is the open semantic planning development line. It starts from the unmerged `0.12.0` stabilization baseline and adds open AI-generated product needs, per-need live catalogue retrieval, evidence-based selection, generic uncertainty handling and bounded optional vision review.

The `0.12.0` and `0.13.0` pull requests remain draft and are not merged.

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
wp orion-ai diagnose --message="<prompt>"
```

## Architecture

- AI produces an open list of project needs from arbitrary customer language.
- Known roles remain optional hints and validator hooks, not a closed allowlist.
- Every need receives its own live WooCommerce candidate set.
- AI selections require explicit catalogue evidence and high or medium confidence.
- Function evidence is separated from unresolved size, fit, capacity and compatibility details.
- Images may be reviewed only after text processing leaves an unresolved need, using at most three relevant live product images.
- Deterministic code remains responsible for live IDs, stock, price, candidate membership, specialist compatibility checks and unsupported-claim prevention.
- Text-only operation remains complete when vision is unavailable.

See `docs/open-semantic-planning-0.13.0.md` for the architecture and `docs/acceptance-0.13.0.md` for the release checklist.

## Privacy

The plugin stores conversations, usage metadata and operational events in custom tables. Configure finite retention and disclose AI processing in the store privacy policy. Conditional vision review sends only public WooCommerce product image URLs for unresolved catalogue candidates; it does not send customer-uploaded images.
