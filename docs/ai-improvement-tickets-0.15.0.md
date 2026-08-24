# AI Improvement Tickets 0.15.0

## Goal

Turn AI gaps into the correct kind of improvement work instead of treating every unanswered request as Knowledge Base content.

## Version boundary

- Base: acceptance-frozen `0.14.0` documentation head `0df42ed` and code baseline `b9e132e`.
- Branch: `feature/ai-improvement-tickets-0.15.0`.
- Plugin: `0.15.0`.
- Schema: `0.13.1`.

## Stage 1 — Resolution target and scalable review

Every gap receives one deterministic type: knowledge, catalogue, product data, routing, provider or technical. Only knowledge gaps may publish reviewed guidance.

The gap workspace uses server-side pagination with 10, 20 or 50 rows. Customer Conversations uses 20, 50 or 100 rows. Only the current page is loaded, and navigation state is preserved.

## Stage 2 — Repeated gap groups

Each gap is linked to a stable private group. Grouping prefers the classifier's existing intent and topic combined with the deterministic failure type and reason. When those signals are unavailable, conservative normalized-token similarity is used.

The admin workspace shows repeated groups with:

- representative request;
- improvement type and reason;
- total occurrences;
- open occurrence count;
- direct filtered access to all linked gaps.

Existing gaps are grouped during schema migration. New gaps join a group when they are created. Counts are recomputed from linked rows rather than trusted from customer input.

Grouping is prioritisation only. It does not publish knowledge, modify products, change routing, close gaps or execute any instruction in customer text.

## Planned stages

1. Admin type filters, recommendations, publication boundary and pagination. Complete.
2. Similar-gap grouping and occurrence counts. Complete pending local validation.
3. Internal improvement-ticket lifecycle and linked evidence.
4. Regression-case creation and acceptance.

## Safety

- Customer text is normalized only for a non-executable private grouping fingerprint.
- No model output or customer claim becomes knowledge automatically.
- Knowledge publication remains capability/nonce protected and requires separately written verified guidance.
- Conversations, representative requests and diagnostics remain admin-only.
