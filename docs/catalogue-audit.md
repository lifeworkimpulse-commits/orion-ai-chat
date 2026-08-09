# Catalogue audit

Version 0.11.3 provides a grouped, severity-aware, read-only WooCommerce catalogue audit.

```text
wp orion-ai catalogue-audit
wp orion-ai catalogue-audit --details
wp orion-ai catalogue-audit --format=json
wp orion-ai catalogue-audit --limit=100
```

The audit never changes products.

## Severity

- `critical`: prevents reliable sale or AI recommendation, such as missing descriptions/categories, broken variable products, missing prices, duplicate SKUs, or suitability conflicts.
- `warning`: needs review but may not block sale, such as missing simple-product SKU, missing image, short description, duplicate title, malformed SKU, variation SKU/attribute gaps, or unknown material suitability.
- `info`: informational operational detail, currently including a variable parent without its own SKU when variations may carry unique SKUs.

## Grouped duplicate findings

Duplicate SKU and duplicate title findings are emitted once per group instead of once per product or variation. Group rows include `entity_ids` and `affected_product_ids` in JSON. Readiness percentages still count every affected parent product.

The summary exposes:

```text
duplicate_sku_groups
duplicate_title_groups
malformed_sku_groups
```

`malformed_sku` identifies values that look like multiple imported SKU values concatenated into one field, including repeated parentheses or empty `()` fragments. It is separate from duplicate detection because a malformed SKU can occur on only one entity or on several entities.

## Suitability refinements

Suitability checks apply only to material-like products. Rollers, sleeves, frames, brushes, trays, masking tape, dust sheets, fixings, tools, PPE, carpet/surface protection, mixer or filler taps, and body-filler tool sets are excluded. Actual paints, primers, coatings, fillers, adhesives, sealants and silicone products remain in scope.

## Readiness

`operational_readiness_percent` excludes products with any critical finding. `ai_readiness_percent` excludes products with content, suitability, broken-variable or price findings relevant to reliable AI recommendations.
