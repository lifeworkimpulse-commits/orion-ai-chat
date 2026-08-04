# Retrieval and kit optimisation

Version 0.11.1 reduces selection cost and makes visible product cards consistent with reported roles.

- Each unique candidate is transmitted to the selection model once.
- Roles reference candidate IDs instead of repeating full product descriptions.
- Required roles receive up to eight candidates; optional roles receive up to six.
- The compact product description is limited to 450 characters.
- Primer, cleaner, filler, sandpaper and scraper are deferred when the saved project state does not indicate new, porous, stained, damaged, cracked, loose, flaking or dirty surfaces.
- Cards are prioritised as principal product, roller, tray, brush, masking tape, dust sheet, then conditional preparation.
- Missing roles are calculated after the configured card limit, so hidden selections are no longer reported as supplied.
- Selection diagnostics include unique product count, role candidate count, payload size, pre-limit selections and displayed product count.
