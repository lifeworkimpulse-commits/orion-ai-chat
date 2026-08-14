# Open Semantic Planning 0.13.0

## Goal

Let the AI understand arbitrary customer projects and discover suitable live WooCommerce products without requiring every possible product need, customer answer or future catalogue item to be encoded as a PHP rule.

## Design principle

AI decides relevance. Deterministic code verifies evidence and live catalogue truth.

## Target flow

1. Classify the conversation and collect only information that materially changes product suitability.
2. Produce an open product-need plan. Each need has a free-form key, description, required flag, constraints and an optional known-role hint.
3. Retrieve live candidates using title, description, categories, tags and attributes. Known role aliases may improve recall but must not gate unknown needs.
4. Ask the AI to rerank only retrieved product IDs and cite the catalogue fields supporting each choice.
5. Optionally inspect images for a small number of ambiguous candidates when vision is supported. Image evidence is secondary and cannot prove coverage, instructions or hidden bundle contents.
6. Revalidate product existence, visibility, stock, price and supported claims before returning cards.
7. Ask a clarification question or report uncertainty when evidence is insufficient.

## What remains deterministic

- Security, permissions, rate limits and session handling.
- Live WooCommerce ID, visibility, stock and purchasability checks.
- No invented prices, links, coverage, delivery policies or product IDs.
- Explicit bundle/component and dimensional compatibility validation when a compatibility claim is made.
- Product-card limits, traceability and provider fallback.

## What moves to AI planning

- The project-specific list of needed materials and tools.
- Synonyms and search phrases for each need.
- Relevance ranking across new or previously unseen product types.
- Whether a known role is relevant to the current project.
- Evidence-based explanations and uncertainty reporting.

## Migration stages

### Stage 1 — Open need contract — implemented

- The routing tool accepts safe free-form product need keys instead of a closed role enum.
- Known canonical roles remain optional `role_hint` metadata for existing specialist validators and ordering.
- Normalized classification output exposes the open plan as `needs` while retaining `search_plan` as a compatibility alias.
- Unknown need keys continue into catalogue retrieval rather than being discarded.

### Stage 2 — Open retrieval and reranking

- Search every need without rejecting unknown role names.
- Require selected IDs to come from that need's candidate set.
- Return evidence fields and uncertainty per selection.

### Stage 3 — Generic validation

- Apply specialist validators only when their domain is detected.
- Keep unknown product types eligible when live catalogue evidence supports them.
- Separate unsupported claims from unsuitable products.

### Stage 4 — Optional vision

- Detect provider vision capability.
- Send only top ambiguous candidates and their image URLs.
- Record whether image evidence affected the decision.
- Continue safely when vision is unavailable.

## Release gates

- Existing `0.12.0` evaluations remain green.
- Unknown free-form needs can retrieve and select new catalogue products.
- Known validators still reject invented IDs and unsupported compatibility claims.
- New WooCommerce products become searchable through existing index hooks without code changes.
- Text-only operation remains complete when image analysis is disabled.
