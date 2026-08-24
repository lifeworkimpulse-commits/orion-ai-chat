# Orion AI Assistant 0.14.0 Acceptance

## Accepted baseline

`0.14.0` was accepted locally on 2026-08-24.

- Accepted code baseline: `b9e132e` (`fix: enforce AI gap schema migration`).
- Plugin version: `0.14.0`.
- Schema version: `0.12.1`.
- Development branch: `feature/manager-queue-0.14.0`.
- Pull request: Draft PR #3; do not merge without explicit approval.

## Automated regression

OpenRouter model: `openai/gpt-5.6-luna`.

```text
Passed: 14/14 (100%)
Average latency: 4832 ms
Total tokens: 12399
Success: Evaluation passed.
```

All accepted `0.13.0` routing scenarios remained green, including deterministic delivery routing, context updates, ceiling and garage kits, bathroom sealant and all five open product-need cases.

GitHub CI also passed:

- PHP syntax on 8.0, 8.1, 8.2 and 8.3;
- PHPUnit;
- stabilization-core PHPStan;
- assets and evaluation schema.

## Manual conversation and gap acceptance

The following workflow passed in the local WordPress browser:

1. A delivery question for Belfast postcode `BT7 1NN` was stored in the retained conversation.
2. Orion refused to invent an unsupported delivery price or time.
3. The unanswered request was recorded as AI knowledge gap `#28`.
4. The full customer/AI dialogue was visible under **Conversations**.
5. The gap and its trace were visible under **AI gaps**.
6. Separately written verified guidance was explicitly added to the Knowledge Base.
7. A paraphrased follow-up question was answered from the new reviewed guidance: delivery cost `£7.99` and approximately `3 working days`.
8. The customer dialogue itself was not published as knowledge.
9. No manager-written response was delivered directly to the customer.

## Accepted product contract

- Customers communicate only with Orion AI.
- Authorized managers can review every retained customer question and AI answer.
- Unsupported requests and provider failures create AI knowledge gaps.
- A manager writes separate verified guidance and may attach a source URL.
- Only explicitly confirmed guidance is indexed in the Knowledge Base.
- Customer messages, traces, diagnostics and model output are never published automatically.
- Missing catalogue products must be fixed in WooCommerce rather than invented through Knowledge Base text.

## Operational commands

```powershell
& 'W:\modules\php\PHP_8.1\php.exe' 'W:\tools\wp-cli.phar' orion-ai gaps schema --path='W:\domains\myorionchat.com'
& 'W:\modules\php\PHP_8.1\php.exe' 'W:\tools\wp-cli.phar' orion-ai gaps stats --path='W:\domains\myorionchat.com'
& 'W:\modules\php\PHP_8.1\php.exe' 'W:\tools\wp-cli.phar' orion-ai gaps list --limit=20 --path='W:\domains\myorionchat.com'
& 'W:\modules\php\PHP_8.1\php.exe' 'W:\tools\wp-cli.phar' orion-ai evaluate --provider=openrouter --model='openai/gpt-5.6-luna' --path='W:\domains\myorionchat.com'
```

## Release decision

The `0.14.0` feature line is acceptance-frozen. New functionality should be developed on a new branch. PR #3 remains Draft and unmerged until explicit approval.
