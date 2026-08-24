# Changelog

## 0.15.0 — AI Improvement Tickets (development)

- Started a separate branch from the acceptance-frozen `0.14.0` line.
- Added deterministic gap types: knowledge, catalogue, product data, routing, provider and technical.
- Added recommended source-specific actions for every gap type.
- Restricted Knowledge Base publication to genuine knowledge gaps.
- Added type filters and recommendations to the admin gap workspace.
- Added server-side gap pagination with 10, 20 or 50 rows per page.
- Added server-side conversation pagination with 20, 50 or 100 rows per page.
- Preserved filters and current pages during navigation and review actions.
- Added direct WooCommerce Products navigation for catalogue and product-data gaps.
- Added gap-type counts and filtering to read-only WP-CLI diagnostics.
- Backfilled existing gaps from deterministic reason codes.
- Bumped the plugin version to `0.15.0` and schema to `0.13.0`.

## 0.14.0 — Conversation Review and AI Knowledge Gaps (release candidate)

- Added retained conversation review and safe AI knowledge gaps.
- Added explicit reviewed-guidance publication without automatic customer-text ingestion.
- Added read-only gap diagnostics and schema health reporting.
- Acceptance-frozen code at `b9e132e` after a 14/14 live evaluation and browser Knowledge Base round trip.

## 0.13.0 — Open Semantic Planning (release candidate)

- Added open AI-generated product needs, evidence-based selection and bounded uncertainty handling.
- Acceptance-frozen code at `85b0fe9` after a 14/14 live evaluation.

## 0.12.0 — Stabilization

- Added shared canonical roles, deterministic routing rules, specialist kit validation and permanent CI.
