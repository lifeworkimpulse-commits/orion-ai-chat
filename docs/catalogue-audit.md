# Catalogue audit

Version 0.11.2 provides a severity-aware, read-only WooCommerce catalogue audit.

```text
wp orion-ai catalogue-audit
wp orion-ai catalogue-audit --details
wp orion-ai catalogue-audit --format=json
wp orion-ai catalogue-audit --limit=100
```

The audit never changes products.

## Severity

- `critical`: prevents reliable sale or AI recommendation, such as missing descriptions/categories, broken variable products, missing prices, duplicate SKUs, or suitability conflicts.
- `warning`: needs review but may not block sale, such as missing simple-product SKU, missing image, short description, duplicate title, variation SKU/attribute gaps, or unknown material suitability.
- `info`: informational operational detail, currently including a variable parent without its own SKU when variations may carry unique SKUs.

## Improvements

- Variable parents without a SKU are distinguished from simple products without a SKU.
- Variable products with no children use one critical `broken_variable_product` finding instead of separate missing-price and no-variation findings.
- Variation SKUs are checked and included in duplicate-SKU detection.
- Duplicate normalised product titles are reported with all matching IDs.
- Suitability checks apply only to material-like products and exclude rollers, sleeves, frames, brushes, trays, masking tape, dust sheets, fixings, tools and PPE.
- Summary output separates operational readiness from AI readiness.

`operational_readiness_percent` excludes products with any critical finding. `ai_readiness_percent` excludes products with content, suitability, broken-variable or price findings relevant to reliable AI recommendations.
