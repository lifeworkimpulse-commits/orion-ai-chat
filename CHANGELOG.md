# Changelog

## 0.14.0 — Manager Queue (development)

- Started a separate branch from the accepted and frozen `0.13.0` line.
- Added a queue-domain policy for `new`, `in_progress`, `resolved` and `dismissed` states.
- Added conservative `normal` and manager-assigned `urgent` priorities.
- Extended handoff storage with trace ID, reason code, assignee, resolution note and completion time.
- Added filtered queue retrieval, status counts and validated workflow transitions.
- Added an admin manager workspace with status counters, filters, assignment, private notes and trace links.
- Added capability and item-specific nonce checks for claim, resolve, dismiss and reopen actions.
- Added explicit customer-response drafts, approval and one-time delivery to the originating conversation.
- Added per-item queue activity history without storing private note or response text in event payloads.
- Added aggregate audit events for successful queue changes and manager-response delivery.
- Prevented dismissed requests from delivering previously approved responses.
- Bumped the plugin version to `0.14.0` and the database schema to `0.11.0`.

## 0.13.0 — Open Semantic Planning (release candidate)

- Started a separate development branch without merging the `0.12.0` draft pull request.
- Bumped the plugin version to `0.13.0`; the database schema remains `0.9.0`.
- Opened the semantic need contract so the AI can create safe free-form product need keys instead of choosing from a closed canonical-role enum.
- Preserved known roles as optional hints for specialist validators, ordering and the existing `0.12.0` compatibility layer.
- Added per-need live catalogue candidate groups for known and previously unseen product types.
- Required every accepted AI selection to identify explicit catalogue evidence and use high or medium confidence.
- Separated direct product-function evidence from unresolved size, fit, capacity and cross-product compatibility claims.
- Added one bounded text compliance review for unresolved required or core function matches.
- Added trace-level selection evidence, confidence, uncertainty and safe usage metrics.
- Added bounded optional OpenRouter vision review for at most three unresolved live product candidates; unsupported or failed vision safely retains the text result.
- Restricted image evidence to visible product type and visible components, never hidden contents, dimensions, capacity, coverage, technical suitability or compatibility.
- Added conservative multimodal capability detection; unverified models remain text-only unless explicitly enabled.
- Added safe recovery from malformed or truncated tool-call JSON and stage-specific output budgets for routing, selection and final answers.
- Added semantic canonical hints for known painting functions while preserving previously unseen product needs as open keys.
- Preserved new-plaster preparation evidence, negated surface conditions and quantity uncertainty across routing and product selection.
- Expanded the live routing evaluation from 9 to 14 scenarios, including five previously unseen open product needs.
- Added a documented `0.13.0` acceptance workflow and richer read-only diagnostics.
- Kept deterministic validation as a safety and evidence layer rather than a catalogue-domain decision engine.
- Acceptance-frozen the code baseline at `85b0fe9` after the final OpenRouter evaluation passed 14/14 on 2026-08-22.

## 0.12.0 — Stabilization

- Added a central canonical role registry shared by routing and product planning.
- Extracted deterministic routing rules for delivery, dimensions and floor-project handling.
- Added role-specific preparation routing that respects negated conditions such as no cracks, no oil and no previous coating.
- Added structured product-fact extraction for functions, surfaces, included components, roller/tray widths, pack volume and explicit coverage evidence.
- Extracted complete-kit validation from the semantic planner.
- Added deterministic recovery for complete roller systems and verified optional core-kit roles when card capacity remains.
- Added explicit quantity guidance so pack volume alone is never treated as proof of project coverage.
- Preserved structured `select_products` output and live WooCommerce ID revalidation.
- Added unit tests for role aliases, delivery routing, dimensions, surface-condition negation, roller evidence, compatible systems, tray width and pack-volume evidence.
- Expanded permanent CI across PHP 8.0–8.3 with unit tests, static analysis and evaluation-suite validation.
- Replaced one-line bootstrap and core routing/planning implementations with maintainable formatted code.
- Documented production-catalogue benchmarks and release gates.

## 0.11.6

- Required explicit evidence that a roller set contains both frame and sleeve.
- Prioritized core painting-kit roles before conditional preparation roles.

## 0.11.0–0.11.5

- Added the semantic catalogue index, optimized retrieval, severity-aware catalogue audit, deterministic delivery routing and structured product selection.
