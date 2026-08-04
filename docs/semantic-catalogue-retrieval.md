# Semantic catalogue retrieval

Version 0.11.0 introduces a searchable WooCommerce catalogue index and AI reranking.

The index stores the product title, short and full descriptions, categories, tags, attributes, variation attributes, product type, visibility and stock status. Runtime retrieval searches all indexed fields and supplies a broader candidate pool to the selection model. PHP retrieval is used for recall; the model decides semantic suitability from the actual product data.

Selected IDs are still constrained to the supplied live candidates and reloaded from WooCommerce before presentation.

## Operations

```text
wp orion-ai migrate --apply
wp orion-ai catalogue-index
wp orion-ai catalogue-index --apply
wp orion-ai catalogue-index --apply --limit=100
```

The index is updated when WooCommerce products are created or updated. A full reindex removes stale rows. Product deletion removes its index row.

Roller sleeves, frames and bundled sets must be described according to their actual contents. A sleeve may be recommended as a partial roller accessory with a compatible frame still required. A bundle may satisfy multiple roles only when its title or description explicitly identifies it as a set, kit or bundle.
