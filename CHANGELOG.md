# Changelog

## 0.14.0 — Conversation Review and AI Knowledge Gaps (release candidate)

- Added an admin Conversations screen for every retained customer question and AI answer.
- Added message and gap counts plus direct full-dialogue review.
- Reframed unanswered handoffs as AI knowledge gaps rather than manager replies.
- Removed all runtime customer-response draft, approval and delivery behavior.
- Added New, Reviewing, Added to knowledge and Ignored states.
- Added filters by state and deterministic failure reason.
- Added separate knowledge title, verified guidance, optional source URL and linked document metadata.
- Added explicit publication and immediate indexing of reviewed guidance.
- Prevented automatic publication of customer messages, traces, diagnostics or model output.
- Stored customer questions before routing so provider failures remain visible.
- Added gap capture for classification and final-answer provider failures.
- Added read-only `wp orion-ai gaps` diagnostics and schema health reporting.
- Added explicit fallback migration checks for required AI-gap columns.
- Bumped the schema to `0.12.1`.
- Acceptance-frozen the code baseline at `b9e132e` on 2026-08-24.
- Passed the live OpenRouter evaluation at `14/14`, average `4832 ms`, `12399` tokens.
- Passed the browser round trip from gap `#28` to a reviewed Knowledge Base answer.

## 0.13.0 — Open Semantic Planning (release candidate)

- Added open AI-generated product needs and per-need live catalogue retrieval.
- Preserved known roles as optional specialist-validation hints rather than a closed allowlist.
- Required explicit catalogue evidence and high or medium confidence for selections.
- Added generic uncertainty handling and bounded optional vision review.
- Added safe recovery from malformed tool output and stage-specific output budgets.
- Expanded the live routing evaluation to 14 scenarios.
- Acceptance-frozen the code baseline at `85b0fe9` after the final evaluation passed 14/14.

## 0.12.0 — Stabilization

- Added shared canonical roles, deterministic routing rules and specialist kit validation.
- Added structured product facts and quantity uncertainty rules.
- Added unit tests and permanent CI across PHP 8.0–8.3.

## 0.11.0–0.11.6

- Added semantic catalogue indexing, readiness audit, deterministic delivery routing and structured product selection.
