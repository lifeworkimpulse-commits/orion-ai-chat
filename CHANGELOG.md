# Changelog

## 0.14.0 — Conversation Review and AI Knowledge Gaps (development)

- Added an admin Conversations screen for every retained customer question and AI answer.
- Added message and gap counts plus direct full-dialogue review.
- Reframed unanswered handoffs as AI knowledge gaps rather than manager replies.
- Removed all runtime customer-response draft, approval and delivery behavior.
- Added gap states displayed as New, Reviewing, Added to knowledge and Ignored.
- Added filters by state and deterministic failure reason.
- Added separate knowledge title, verified guidance, optional source URL and linked document metadata.
- Added explicit one-click publication and immediate indexing of reviewed guidance.
- Prevented automatic publication of customer messages, traces, diagnostics or model output.
- Stored customer questions before AI routing so provider failures remain visible for review.
- Added gap capture for classification and final-answer provider failures.
- Added read-only `wp orion-ai gaps` diagnostics with private text hidden by default.
- Added per-gap review history without copying guidance text into event payloads.
- Bumped the development schema to `0.12.0`.

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
- Required explicit roller-system evidence and prioritized core kit roles.
