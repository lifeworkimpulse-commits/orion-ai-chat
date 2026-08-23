# Orion AI Shopping Assistant

Private WooCommerce plugin that provides a grounded AI shopping assistant through OpenRouter or Google Gemini.

## Current development line

`0.14.0` develops an operational Manager Queue on top of the accepted `0.13.0` semantic-planning baseline. It adds manager assignment, status and priority workflows, private notes, trace links, explicit response drafts and approvals, one-time customer delivery, audit history and read-only WP-CLI diagnostics.

`0.13.0` remains the acceptance-frozen code baseline at commit `85b0fe9`. The `0.12.0`, `0.13.0` and `0.14.0` pull requests remain draft and are not merged.

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
wp orion-ai queue stats
wp orion-ai queue list --status=new
wp orion-ai queue show <id>
```

Queue list and show commands omit private customer, manager and diagnostic text by default. Use `--details` only on a trusted console when that text is intentionally required.

## Architecture

- AI produces an open list of project needs from arbitrary customer language.
- Known roles remain optional hints and validator hooks, not a closed allowlist.
- Every need receives its own live WooCommerce candidate set.
- AI selections require explicit catalogue evidence and high or medium confidence.
- Function evidence is separated from unresolved size, fit, capacity and compatibility details.
- Verified multimodal models may review images only after text processing leaves an unresolved required or core need.
- Deterministic code remains responsible for live IDs, stock, price, candidate membership, specialist compatibility checks and unsupported-claim prevention.
- Manager Queue receives only requests that cannot be answered safely from confirmed evidence.
- Private manager notes and diagnostics remain admin-only.
- Customer responses require explicit approval and are delivered at most once to the originating retained session.
- No manager action publishes to the knowledge base automatically.

See `docs/open-semantic-planning-0.13.0.md`, `docs/manager-queue-0.14.0.md` and `docs/acceptance-0.14.0.md`.

## Privacy

The plugin stores conversations, queue items, private manager notes, approved customer responses, usage metadata and operational events in custom tables. Configure finite retention and disclose AI processing in the store privacy policy. Terminal queue items are removed after retention; open work remains until a manager resolves or dismisses it. Approved responses expire when their retained source conversation is removed. Conditional vision review sends only public WooCommerce product image URLs and never customer-uploaded images.
