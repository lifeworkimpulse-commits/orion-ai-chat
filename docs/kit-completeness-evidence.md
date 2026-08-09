# Kit completeness evidence

Version 0.11.6 tightens roller-component evidence and guarantees that core painting-kit roles are planned before conditional preparation items.

## Roller evidence

A frame-only product must remain `roller_frame` even when its description mentions compatible sleeves. It can satisfy the logical `roller` role only when a separate sleeve with the same stated width is selected.

A single product satisfies the complete roller role only when one of these conditions is supported by catalogue text:

- its title explicitly names both a frame and sleeve;
- its title identifies a multi-piece roller-and-frame set;
- an inclusion statement such as `includes`, `contains`, `comes with` or `supplied with` explicitly lists both components.

Compatibility or recommendation wording does not prove that both components are included.

## Complete painting-kit priorities

When semantic state says the customer wants everything needed, a complete kit or all materials, the planner guarantees these optional logical roles before conditional preparation roles:

```text
roller
tray
brush
masking_tape
dust_sheet (non-floor projects only)
cleaner (floor and garage projects)
```

The principal material remains the only required role by default. The planner keeps at most ten roles and drops lower-priority conditional preparation roles first. This prevents primer, filler, sandpaper or scraper from displacing masking tape or another core application item.
