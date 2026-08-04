# Grounded catalogue retrieval

Version 0.10.1 broadens candidate discovery without weakening final suitability validation.

Candidate discovery now combines exact WooCommerce search, token search, matching product-category pools, product descriptions, attributes and normalized search synonyms such as `matte` → `matt`, `painting` → `paint` and `interior` → `internal`.

Surface restrictions remain mandatory for core materials. Application tools such as rollers, brushes and trays are not rejected merely because their descriptions do not repeat the project surface.

Only the principal material role is allowed to block a recommendation by default. Preparation products and application tools are treated as optional unless the user explicitly asks for that exact product. Missing optional accessories are reported but do not suppress a confirmed primary product.

The semantic selector still receives a bounded list of live WooCommerce candidates and may only return supplied product IDs. Every selected product is reloaded from WooCommerce before presentation.
