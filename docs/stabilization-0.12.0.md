# Stabilization 0.12.0

Version 0.12.0 converts the semantic-routing prototype into a maintainable release candidate without weakening grounding. Models may select only supplied live WooCommerce IDs, and selected products are reloaded before presentation.

## Architecture

`Orion_Role_Registry` is the single source of truth for supported roles, aliases, optional-role policy and priority. `Orion_Routing_Rules` contains pure rules for delivery, dimensions, floor projects, complete-kit intent and surface conditions.

`Orion_Product_Facts` derives a bounded evidence object from live catalogue data:

```text
functions
surfaces
included_components
roller_component
roller_width_inches
tray_width_inches
coverage_m2_per_litre
is_bundle
```

Missing facts remain unknown. Compatibility wording does not prove inclusion, and pack volume does not prove project coverage.

`Orion_Kit_Validator` owns deterministic post-selection checks: an explicit frame-and-sleeve set or a matching frame/sleeve pair, rejection of incomplete systems, and rejection of trays narrower than the selected roller.

## Automated checks

CI runs on PHP 8.0–8.3 and validates Composer metadata, PHP syntax, PHPUnit tests, PHPStan, evaluation JSON and frontend JavaScript syntax.

## Production-catalogue benchmark

Before merging to `main`, test on a production-like catalogue and record Recall@K per role, final selection pass rate, missing required-role rate, false candidate rate, payload and token usage, median/p95 latency, fallback rate and invalid structured-output rate. Maintain 30–50 reviewed customer requests with expected intent, state, roles and acceptable product IDs.

## Release gates

- All CI jobs pass.
- Existing routing evaluations do not regress.
- No critical catalogue-audit finding affects acceptance products.
- The full catalogue index has been rebuilt.
- Delivery, garage-floor and complete-ceiling scenarios pass.
- A rollback tag or archive exists before deployment.

## Deferred product work

The expanded manager workflow (`new → in_progress → resolved`, manager note and customer response) is a separate schema/UI change. It should follow stabilization rather than be mixed into the planner refactor.
