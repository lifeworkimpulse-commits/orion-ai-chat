# Conversation Review and AI Knowledge Gaps 0.14.0

The branch keeps its historical name `feature/manager-queue-0.14.0`, but the customer-response manager queue has been removed. Managers do not reply to individual customers through the assistant.

## Product model

- Customers communicate only with Orion AI.
- Every retained customer/AI dialogue is available to authorized store managers.
- Requests Orion cannot answer safely are recorded as AI knowledge gaps.
- A manager reviews the complete conversation and trace.
- The manager writes separate verified guidance and may attach a source URL.
- Only that reviewed guidance is explicitly published and indexed in the knowledge base.
- The original customer message, diagnostic context and model output are never published automatically.

## Admin screens

### Conversations

Shows up to 150 recent retained conversations with:

- conversation ID;
- first customer question;
- message count;
- knowledge-gap count;
- last activity.

Opening a conversation shows every retained customer question and AI answer in order, plus links to related gaps.

### AI gaps

Shows unanswered requests with:

- customer question;
- full-conversation link;
- trace link;
- deterministic failure reason;
- diagnostic context;
- review history;
- knowledge title;
- verified guidance;
- optional source URL;
- linked knowledge document after publication.

Statuses are displayed as:

- `new` → New;
- `in_progress` → Reviewing;
- `resolved` → Added to knowledge;
- `dismissed` → Ignored.

## Publishing rules

Publishing requires:

1. a manager with `manage_woocommerce`;
2. an item-specific WordPress nonce;
3. a non-empty knowledge title;
4. at least 20 characters of separately written verified guidance;
5. explicit confirmation of **Add to knowledge base**.

The source URL is evidence metadata. External pages are not fetched automatically. A link by itself is not enough to teach the AI because the assistant needs reviewed factual text. Same-site public pages can still be imported through the existing Knowledge Base importer.

The created manual document contains only the manager-written guidance and optional source link. It is indexed immediately. The customer question remains only in the retained conversation and gap record.

## Capturing failures

Customer messages are stored before routing. This means the Conversations screen still shows the question when classification or final-answer provider calls fail. Those provider failures also create AI gaps for later review.

Product, policy and evidence failures continue to create gaps through the existing safe fallback path.

## Privacy and retention

- Conversation access requires `manage_woocommerce`.
- Conversation messages follow the configured conversation retention period.
- Open gaps remain until reviewed or ignored so improvement work is not silently lost.
- Added/ignored gaps and their audit rows are removed after retention.
- Knowledge documents have their own lifecycle and are not deleted when the originating conversation expires.
- Existing development databases upgraded from schema `0.11.0` may retain unused response columns; no runtime code reads or delivers them.

## Schema

- Plugin version: `0.14.0`.
- Schema version: `0.12.0`.
- Added reviewed-knowledge metadata to `orion_ai_handoffs`.
- Removed all runtime manager-response approval and customer-delivery behavior.

## Operations

```bash
wp orion-ai gaps stats
wp orion-ai gaps list --status=new
wp orion-ai gaps show <id>
wp orion-ai gaps show <id> --details
```

Private question, guidance and diagnostics require explicit `--details` in WP-CLI.

See `docs/acceptance-0.14.0.md` for local validation.
