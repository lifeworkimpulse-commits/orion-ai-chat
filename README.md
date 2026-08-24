# Orion AI Shopping Assistant

Private WooCommerce plugin that provides a grounded AI shopping assistant through OpenRouter or Google Gemini.

## Current development line

`0.15.0` develops AI Improvement Tickets on top of the acceptance-frozen `0.14.0` baseline.

Current functionality:

- deterministic source-specific gap types;
- complete store-policy evidence required for every requested fact;
- server-side pagination for AI Gaps and Customer Conversations;
- private repeated-gap groups with occurrence and open counts;
- one manager-controlled internal improvement ticket per group;
- priority, owner, fix plan, verification evidence and audited lifecycle;
- direct links to every related conversation, gap and trace.

`0.14.0` remains frozen at code baseline `b9e132e` and documentation head `0df42ed`. Pull requests remain Draft and unmerged.

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
wp orion-ai gaps ticket <group-id>
wp orion-ai gaps show <id>
```

Commands omit private text by default. Use `--details` only on a trusted console.

## Safety contract

- Gap type, grouping and priority suggestions are internal signals, not automatic training.
- A policy answer is shown only when every requested fact has document evidence.
- No gap, group or ticket automatically changes Knowledge Base, products, routing or code.
- Ticket resolution requires manager-written verification evidence.
- Customer dialogue, traces and diagnostics remain private admin data.

See `docs/ai-improvement-tickets-0.15.0.md`.
