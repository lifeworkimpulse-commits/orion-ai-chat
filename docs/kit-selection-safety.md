# Kit selection safety

Version 0.11.4 hardens semantic routing and project-kit selection without replacing AI suitability decisions with a fixed product allowlist.

## Delivery policy routing

Questions about sending, delivering, shipping, collecting or getting an order to a destination are routed deterministically to `store_policy`. The guard combines fulfilment intent with a destination, postcode or policy-question form. A product request that merely contains a word such as `pallet` is not forced into policy routing.

## Complete roller systems

The selector may satisfy the logical `roller` role with either:

- one product whose catalogue text explicitly includes both a roller frame and at least one sleeve; or
- one frame plus one sleeve with matching stated widths.

A frame alone, sleeve alone, unknown roller accessory, or incompatible pair is rejected after AI selection and returned as a missing `roller` role. The selector can return multiple products for the logical role. Display roles distinguish `roller`, `roller_frame` and `roller_sleeve`.

For projects of at least 10 m², mini, radiator and explicit 4-inch roller candidates are excluded from the main-application role. For floor projects, candidates described only for gloss, emulsion, walls or ceilings are excluded unless their catalogue text also establishes floor/coating suitability.

## Tray compatibility

When both widths are stated, a tray narrower than the selected roller is rejected. Selection diagnostics include `selection_rejections` and `roller_system_complete`.

## Floor-specific kits

- Dust sheets are not automatically searched when the floor itself is the project surface.
- Cleaner/degreaser remains an active role for floor or garage projects.
- Filler, sandpaper and scraper remain conditional unless surface damage is known.
- Primer remains conditional unless surface state establishes a reason to use it.

All decisions remain grounded in live title, description, category and attribute data. The deterministic layer validates kit completeness and explicit incompatibilities; it does not invent product suitability.
