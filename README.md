# Orion AI Shopping Assistant

Private WooCommerce plugin that provides a grounded AI shopping assistant through OpenRouter or Google Gemini.

## Current development line

`0.15.0` develops AI Improvement Tickets on top of the acceptance-frozen `0.14.0` baseline.

Current functionality:

- deterministic resolution targets: Knowledge Base, WooCommerce catalogue, product-data quality, AI routing, AI provider or technical;
- Knowledge Base publication restricted to genuine knowledge gaps;
- complete store-policy evidence required for every requested fact;
- server-side pagination for AI Gaps and Customer Conversations;
- private repeated-gap groups with occurrence and open counts;
- direct access from a repeated group to all linked conversations, gaps and traces.

`0.14.0` remains frozen at code baseline `b9e132e` and documentation head `0df42ed`. Pull requests remain Draft and unmerged.

## Requirements

- WordPress 6.2+
- WooCommerce 8.0+
- PHP 8.0+

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
wp orion-ai gaps schema
wp orion-ai gaps stats
wp orion-ai gaps groups
wp orion-ai gaps list --group=<id>
wp orion-ai gaps show <id>
```

Gap commands omit private text by default. Use `--details` only on a trusted console.

## Safety contract

- Customers communicate only with AI.
- Gap type and grouping are internal prioritisation signals, not automatic training.
- A policy answer is shown only when every requested fact has document evidence.
- No gap or group automatically changes Knowledge Base, WooCommerce products, routing or code.
- Only separately written and explicitly confirmed guidance can enter the Knowledge Base.
- Customer dialogue, traces and diagnostics remain private admin data.

See `docs/ai-improvement-tickets-0.15.0.md`.
