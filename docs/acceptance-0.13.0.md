# Orion AI Assistant 0.13.0 Acceptance

This checklist validates the open semantic planning development line without merging either draft pull request.

## 1. Update and static checks

```powershell
git pull origin feature/open-semantic-planning-0.13.0
$env:Path = 'W:\modules\php\PHP_8.1;' + $env:Path
& 'W:\modules\php\PHP_8.1\php.exe' 'W:\userdata\composer\composer.phar' test
& 'W:\modules\php\PHP_8.1\php.exe' 'W:\userdata\composer\composer.phar' analyse
```

Pass when PHPUnit and PHPStan report no errors.

## 2. Runtime status and catalogue health

```powershell
& 'W:\modules\php\PHP_8.1\php.exe' 'W:\tools\wp-cli.phar' orion-ai status --path='W:\domains\myorionchat.com'
& 'W:\modules\php\PHP_8.1\php.exe' 'W:\tools\wp-cli.phar' orion-ai catalogue-audit --path='W:\domains\myorionchat.com'
```

Record indexed product count, critical catalogue issues and AI readiness. Catalogue warnings do not automatically fail the plugin, but critical issues affecting selected products must be resolved or excluded.

## 3. Live routing evaluation

```powershell
& 'W:\modules\php\PHP_8.1\php.exe' 'W:\tools\wp-cli.phar' orion-ai evaluate --provider=openrouter --model='openai/gpt-5.6-luna' --path='W:\domains\myorionchat.com'
```

Pass target: `14/14`. The suite covers delivery, context updates, ceiling and garage kits plus open needs for sealant tools, diamond drill bits, waterproof membranes, tile levelling clips and wood repair resin.

## 4. Read-only full-pipeline diagnostics

Run each prompt with `orion-ai diagnose --message="..."` and inspect `products.selected`, `missing_needs`, `required_missing`, `selection_evidence` and `diagnostic.attempts`.

1. `I need to seal a 5 mm gap around a bathroom sink. Find white sanitary silicone and an application gun.`
2. `I need a 10 mm diamond drill bit for porcelain wall tiles.`
3. `I am waterproofing a shower wall before tiling. Find a suitable waterproof membrane.`
4. `I am laying large-format floor tiles and need tile levelling clips.`
5. `I need a repair resin for a rotten section of an indoor wooden window frame.`

A selected product must have explicit evidence and high or medium confidence. Any unresolved fit, capacity or compatibility must remain in `uncertainty`. Missing catalogue products are acceptable only when reported honestly and without an invented substitute.

## 5. Browser regressions

Use a fresh chat for each scenario:

- underspecified garage floor request must clarify;
- fully specified 24 m² concrete garage floor request must return a verified kit;
- 10 m² bare-new-plaster white ceiling request must preserve the stabilized preparation and roller behavior;
- delivery question must use the knowledge path;
- bathroom sealant and gun must not claim 280 ml compatibility with a gun documented only for 310/400 ml cartridges.

Confirm card links, category links, stock, price formatting and VAT line breaks.

## 6. Provider fallback

Test the configured Google fallback separately. Direct Google operation remains text-only in 0.13.0; failed or unavailable vision must never break normal selection.

## 7. Trace gates

For successful product requests confirm:

- `status: completed`;
- open `need_key` values are preserved;
- selected IDs belong to the corresponding candidate set;
- `selection_evidence`, `confidence` and `uncertainty` are visible;
- `selection_rejections`, `selector_missing_needs` and `required_missing` are accurate;
- `vision_review_selected` appears only when image evidence improved the result;
- no credentials are present.

## Release decision

Keep PR #2 Draft until the routing evaluation, browser regressions, fallback test and representative trace review pass. Do not merge PR #1 or PR #2 without explicit approval.
