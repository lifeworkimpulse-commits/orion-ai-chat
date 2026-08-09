# Selection output reliability

Version 0.11.5 stabilises canonical role names, reduces irrelevant catalogue payload and makes the product selector use a structured function response.

## Canonical roles

Routing output is normalised to canonical names before planning. Common compact forms such as `maskingtape`, `dustsheet`, `primarycoating`, `rollerframe` and `rollersleeve` map to the internal role contract. The routing tool schema also constrains new output to supported role names.

For floor and garage projects, `dust_sheet` is removed after canonicalisation. Project requests with an area or project type and a multi-role plan are normalised to `project_recommendation` rather than `product_search`.

## Structured selection

The selection model must call `select_products` once. Its role values are restricted dynamically to roles that have live candidate IDs. Plain JSON content remains a compatibility fallback. Invalid responses expose tool-call count, usage, attempts and a content excerpt in diagnostics.

## Role-aware recall filtering

Retrieval remains high-recall and semantic, but candidates must have basic catalogue evidence for their requested product function:

- primary coating: paint/coating evidence and not an obvious tool, board, tile, carpet, adhesive or filler;
- cleaner: cleaner, degreaser or sugar-soap evidence;
- brush: decorating/paint/masonry brush evidence and not storage, vapour pads or dustpans;
- tray: tray, scuttle or an explicitly named painting/roller set;
- masking tape and dust sheet: corresponding product-function evidence;
- roller: the existing complete-system and project suitability checks.

These checks remove obvious cross-category noise; the AI still decides final suitability from title, description, categories and attributes.

## Evaluation

The core suite now includes nine cases and verifies canonical garage-floor roles plus the absence of `dust_sheet` from a floor project.
