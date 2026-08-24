# AI Improvement Tickets 0.15.0

## Goal

Turn AI gaps into the correct kind of improvement work instead of treating every unanswered request as Knowledge Base content.

## Version boundary

- Base: acceptance-frozen `0.14.0` documentation head `0df42ed` and code baseline `b9e132e`.
- Branch: `feature/ai-improvement-tickets-0.15.0`.
- Plugin: `0.15.0`.
- Schema: `0.13.0`.

## Stage 1 — Deterministic resolution target

Every gap receives one closed `gap_type` derived from its deterministic failure reason:

- `knowledge` — approved store policy or factual guidance is missing;
- `catalogue` — the requested product is absent or unpublished;
- `product_data` — a product exists but its description, attributes or compatibility evidence is insufficient;
- `routing` — intent classification or semantic routing needs adjustment;
- `provider` — model, timeout, rate limit or fallback failed;
- `technical` — another engineering investigation is required.

The type controls the recommended action. Only `knowledge` gaps may publish reviewed guidance to the Knowledge Base. Other types must be fixed in WooCommerce, catalogue data, routing/provider configuration or code.

Existing `0.14.0` rows are backfilled from `reason_code` during migration. No customer text or model output is used to determine the resolution target.

### Admin pagination

- Gap queries use SQL `LIMIT` and `OFFSET`; the full filtered result set is not loaded into PHP.
- The default is 10 gaps per page, with 20 and 50 as explicit alternatives.
- Status, type and reason filters are preserved between pages.
- Review actions return the manager to the same filtered page.
- The current visible range and total filtered result count are displayed above and below the list.

## Planned stages

1. Admin type filters, recommendations and publication boundary. Complete.
2. Similar-gap grouping and occurrence counts.
3. Internal improvement-ticket lifecycle and linked evidence.
4. Regression-case creation and acceptance.

## Safety

- Type assignment is deterministic and does not execute model instructions.
- No gap automatically changes products, Knowledge Base or code.
- Knowledge publication remains capability/nonce protected and requires explicit reviewed guidance.
- Private conversations and diagnostics remain admin-only.
