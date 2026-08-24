# Changelog

## 0.15.0 — AI Improvement Tickets (development)

- Added deterministic gap types and source-specific recommended actions.
- Restricted Knowledge Base publication to genuine knowledge gaps.
- Added server-side AI Gap pagination with 10, 20 or 50 rows.
- Added server-side Customer Conversation pagination with 20, 50 or 100 rows.
- Added private repeated-gap groups based on existing intent/topic signals and conservative token similarity.
- Added occurrence counts, open counts and direct group filtering in admin.
- Added grouped read-only WP-CLI diagnostics.
- Backfilled existing gaps into groups during schema migration.
- Bumped the plugin version to `0.15.0` and schema to `0.13.1`.

## 0.14.0 — Conversation Review and AI Knowledge Gaps (release candidate)

- Added retained conversation review and safe AI knowledge gaps.
- Added explicit reviewed-guidance publication without automatic customer-text ingestion.
- Acceptance-frozen code at `b9e132e` after a 14/14 live evaluation and browser Knowledge Base round trip.

## 0.13.0 — Open Semantic Planning (release candidate)

- Added open AI-generated product needs, evidence-based selection and bounded uncertainty handling.
- Acceptance-frozen code at `85b0fe9` after a 14/14 live evaluation.

## 0.12.0 — Stabilization

- Added shared canonical roles, deterministic routing rules, specialist kit validation and permanent CI.
