# Orion AI Shopping Assistant

Private WooCommerce plugin that provides a grounded AI shopping assistant through OpenRouter or Google Gemini.

## Current development line

`0.14.0` adds conversation review and a safe AI Knowledge Gaps workflow on top of the accepted `0.13.0` semantic-planning baseline.

Managers can review every retained customer/AI dialogue. Requests Orion cannot answer are collected as gaps with their trace and failure reason. A manager can write separate verified guidance, attach an optional source URL and explicitly index only that reviewed guidance in the Knowledge Base.

Customers continue to communicate only with AI. There is no manager-response delivery workflow, and customer messages are never published to the knowledge base automatically.

`0.13.0` remains the acceptance-frozen code baseline at commit `85b0fe9`. Pull requests remain draft and unmerged.

## Requirements

- WordPress 6.2+
- WooCommerce 8.0+
- PHP 8.0+
- DOM and mbstring
- HTTPS and outbound access to the configured AI provider

## Local installation

Clone into `wp-content/plugins/orion-ai-assistant`, activate, then open **WooCommerce → AI Assistant**.

Keep production credentials in `wp-config.php`:

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

CI validates PHP 8.0–8.3, unit tests, static analysis, JavaScript syntax and evaluation JSON.

## Operations

```bash
wp orion-ai status
wp orion-ai migrate --apply
wp orion-ai catalogue-index --apply
wp orion-ai catalogue-audit --details
wp orion-ai evaluate --provider=openrouter --model=<model>
wp orion-ai diagnose --message="<prompt>"
wp orion-ai gaps stats
wp orion-ai gaps list --status=new
wp orion-ai gaps show <id>
```

Gap commands omit private text by default. Use `--details` only on a trusted console.

## Architecture

- AI creates open product needs from arbitrary customer language.
- Every need receives its own live WooCommerce candidate set.
- Accepted selections require explicit catalogue evidence.
- Deterministic code validates IDs, stock, price, candidate membership and unsupported claims.
- Every retained customer question and successful AI answer can be reviewed by authorized managers.
- Unanswered requests and provider failures create AI gaps.
- Knowledge publication requires separately written, verified guidance and explicit manager confirmation.
- Customer dialogue, traces and diagnostics are never automatically used as knowledge.

See `docs/open-semantic-planning-0.13.0.md`, `docs/manager-queue-0.14.0.md` and `docs/acceptance-0.14.0.md`.

## Privacy

The plugin stores conversations, knowledge gaps, usage metadata and operational events in custom tables. Configure finite retention and disclose AI processing in the store privacy policy. Conversation access and knowledge publication require `manage_woocommerce`.
