# Orion AI Shopping Assistant

Private WooCommerce plugin that provides a grounded AI shopping assistant through OpenRouter or Google Gemini.

## Current release candidate

`0.14.0` is the acceptance-frozen Conversation Review and AI Knowledge Gaps line. The accepted code baseline is `b9e132e`; it passed the frozen 14-case OpenRouter evaluation and the complete browser workflow from unsupported question to reviewed Knowledge Base answer.

Managers can review every retained customer/AI dialogue. Requests Orion cannot answer are collected as gaps with their trace and failure reason. A manager can write separate verified guidance, attach an optional source URL and explicitly index only that reviewed guidance.

Customers communicate only with AI. There is no manager-response delivery workflow, and customer messages are never published to the Knowledge Base automatically.

The `0.12.0`, `0.13.0` and `0.14.0` pull requests remain Draft and unmerged. New feature work must use a new branch.

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

## Operations

```bash
wp orion-ai status
wp orion-ai migrate --apply
wp orion-ai catalogue-index --apply
wp orion-ai catalogue-audit --details
wp orion-ai evaluate --provider=openrouter --model=<model>
wp orion-ai gaps schema
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
- Knowledge publication requires separately written, verified guidance and explicit confirmation.
- Customer dialogue, traces and diagnostics are never automatically used as knowledge.

See `docs/open-semantic-planning-0.13.0.md`, `docs/manager-queue-0.14.0.md` and `docs/acceptance-0.14.0.md`.
