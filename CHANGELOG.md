# Changelog

## 0.12.0 — Stabilization

- Added a central canonical role registry shared by routing and product planning.
- Extracted deterministic routing rules for delivery, dimensions and floor-project handling.
- Added structured product-fact extraction for functions, surfaces, included components, roller/tray widths, pack volume and explicit coverage evidence.
- Extracted complete-kit validation from the semantic planner.
- Added deterministic recovery for complete roller systems and verified optional core-kit roles when card capacity remains.
- Added explicit quantity guidance so pack volume alone is never treated as proof of project coverage.
- Preserved structured `select_products` output and live WooCommerce ID revalidation.
- Added unit tests for role aliases, delivery routing, dimensions, roller evidence, compatible systems, tray width and pack-volume evidence.
- Expanded permanent CI across PHP 8.0–8.3 with unit tests, static analysis and evaluation-suite validation.
- Replaced one-line bootstrap and core routing/planning implementations with maintainable formatted code.
- Documented production-catalogue benchmarks and release gates.

## 0.11.6

- Required explicit evidence that a roller set contains both frame and sleeve.
- Prioritized core painting-kit roles before conditional preparation roles.

## 0.11.0–0.11.5

- Added the semantic catalogue index, optimized retrieval, severity-aware catalogue audit, deterministic delivery routing and structured product selection.
