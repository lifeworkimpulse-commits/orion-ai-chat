# Manager Queue 0.14.0

## Goal

Turn manager follow-up from a terminal chatbot response into an operational, auditable queue without weakening catalogue evidence or exposing private trace data to customers.

## Version boundary

- Base: accepted `0.13.0` code commit `85b0fe9` plus documentation freeze `6624face`.
- Development branch: `feature/manager-queue-0.14.0`.
- Plugin version: `0.14.0`.
- Schema version: `0.11.0`.
- The `0.13.0` branch remains frozen and unmerged.

## Queue contract

Each item stores the customer question, conversation and trace IDs, deterministic reason code, status, priority, assignee, private resolution note, optional customer response and approval/delivery timestamps. A dedicated event table records queue state changes without copying private note or response text into analytics.

## Safety rules

- Only users with `manage_woocommerce` may view or mutate queue items.
- All writes require an item-specific WordPress nonce and server-side transition validation.
- Inputs are sanitized and all queue output is escaped.
- Customer-facing REST endpoints never expose queue context, traces or private manager notes.
- An item is never marked urgent from model output.
- Draft responses remain private until a manager explicitly approves the exact text.
- Only approved response text is delivered, once, on the next successful message for the originating retained session.
- A previous session token is accepted only when it belongs to the same hashed visitor identity.
- Provider failures and session-limit errors do not consume an approved response.
- Dismissing an item cancels a pending approved response.
- Expired conversations cannot accept or deliver an approved response.
- Resolving or approving an item never modifies the knowledge base.
- Terminal items may only be reopened to `new`; direct terminal-to-terminal changes are rejected.

## Delivered stages

### Stage 1 — Queue domain and storage

- structured queue metadata and schema migration;
- safe status-transition policy;
- filtered repository methods and status counts;
- backwards-compatible resolve behavior;
- unit tests for queue and response states.

### Stage 2 — Manager workspace

- status counters and filters;
- priority and current-manager filters;
- claim, save, resolve, dismiss and reopen actions;
- private manager notes, assignee visibility and trace links;
- capability, nonce, sanitization and escaping checks.

### Stage 3 — Approved response workflow

- separate private note and customer-response fields;
- explicit draft and approve actions;
- one-time delivery to the originating retained session;
- response status: `none`, `draft`, `approved`, `delivered`, `expired`;
- per-item activity history;
- aggregate events containing IDs and state only, never response text;
- no automatic knowledge-base publication.

### Stage 4 — Operations and acceptance

- read-only `wp orion-ai queue stats|list|show` diagnostics;
- private CLI fields hidden unless `--details` is explicitly requested;
- terminal queue retention and event cleanup;
- approved-response expiration before old conversations are deleted;
- browser acceptance workflow;
- frozen 14-case semantic regression gate.

See `docs/acceptance-0.14.0.md` for local validation.
