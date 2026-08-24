# Orion AI Shopping Assistant

Private WooCommerce plugin that provides a grounded AI shopping assistant through OpenRouter or Google Gemini.

## Current development line

`0.15.0` develops AI Improvement Tickets on top of the acceptance-frozen `0.14.0` Conversation Review and AI Knowledge Gaps baseline.

Stage 1 assigns every gap a deterministic resolution target: Knowledge Base, WooCommerce catalogue, product-data quality, AI routing, AI provider or technical engineering. Only genuine Knowledge Base gaps may publish reviewed guidance. All other gaps display the source system that must be fixed.

`0.14.0` remains frozen at code baseline `b9e132e` and documentation head `0df42ed`. Pull requests remain Draft and unmerged.

## Requirements

- WordPress 6.2+
- WooCommerce 8.0+
- PHP 8.0+
- DOM and mbstring
- HTTPS and outbound access to the configured AI provider

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
wp orion-ai gaps list --type=knowledge
wp orion-ai gaps show <id>
```

Gap commands omit private text by default. Use `--details` only on a trusted console.

## Safety contract

- Customers communicate only with AI.
- Authorized managers can review retained conversations and gaps.
- Gap type is derived from deterministic failure reason, never from customer instructions.
- No gap automatically changes Knowledge Base, WooCommerce products, routing or code.
- Only separately written and explicitly confirmed guidance can enter the Knowledge Base.
- Customer dialogue, traces and diagnostics are never automatically used as knowledge.

See `docs/ai-improvement-tickets-0.15.0.md` and `docs/acceptance-0.14.0.md`.
