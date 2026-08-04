# Catalogue audit

Version 0.10.0 adds a read-only WooCommerce catalogue readiness audit.

```text
wp orion-ai catalogue-audit
wp orion-ai catalogue-audit --details
wp orion-ai catalogue-audit --format=json
wp orion-ai catalogue-audit --limit=100
```

The audit never changes products. It checks published products for missing or short descriptions, missing useful categories, images, SKUs and prices, duplicate SKUs, variable products without variations, variation prices/attributes, unknown application suitability, and floor-versus-wall/ceiling conflicts.

The default output shows summary metrics and issue counts. `--details` prints up to 200 issue rows. JSON output contains all collected issue rows and can be saved to a file for analysis.

A suitability warning is evidence that product data is insufficient for reliable AI matching; it is not a claim that the product itself is unsuitable. Product information should be confirmed against manufacturer documentation before editing catalogue data.
