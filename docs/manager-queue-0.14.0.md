# Conversation Review and AI Knowledge Gaps 0.14.0

The branch keeps its historical name `feature/manager-queue-0.14.0`, but the manager-to-customer response workflow was removed before acceptance.

## Product model

- Customers communicate only with Orion AI.
- Every retained customer/AI dialogue is available to authorized store managers.
- Requests Orion cannot answer safely are recorded as AI knowledge gaps.
- A manager reviews the complete conversation and trace.
- The manager writes separate verified guidance and may attach a source URL.
- Only that reviewed guidance is explicitly published and indexed in the Knowledge Base.
- Original customer messages, diagnostics and model output are never published automatically.

## Admin screens

### Conversations

Shows recent retained conversations with their first question, message count, gap count and last activity. Opening a conversation displays every retained customer question and AI answer in chronological order.

### AI gaps

Shows unanswered requests with their conversation, trace, deterministic reason, diagnostic context, review history, knowledge title, verified guidance, optional source URL and linked knowledge document.

Statuses are displayed as New, Reviewing, Added to knowledge and Ignored.

## Publishing rules

Publishing requires `manage_woocommerce`, an item-specific nonce, a knowledge title, at least 20 characters of separately written verified guidance and explicit confirmation.

External source links are stored as evidence and are not fetched automatically. Same-site public pages can be imported through the existing Knowledge Base importer.

## Failure capture

Customer messages are stored before AI routing. Classification and final-answer provider failures therefore remain visible and create gaps. Product, policy and evidence failures use the same safe review path.

## Privacy and retention

- Conversation access requires `manage_woocommerce`.
- Conversation messages follow configured retention.
- Open gaps remain until reviewed or ignored.
- Added and ignored gaps follow retention.
- Knowledge documents have an independent lifecycle.
- Development databases upgraded from earlier `0.14.0` schemas may retain unused response columns; no runtime code reads or delivers them.

## Accepted versions

- Plugin: `0.14.0`.
- Schema: `0.12.1`.
- Accepted code baseline: `b9e132e`.
- Live evaluation: `14/14`, average `4832 ms`, `12399` tokens.

See `docs/acceptance-0.14.0.md` for the frozen acceptance record.
