# Manager Queue 0.14.0

## Goal

Turn manager follow-up from a terminal chatbot response into an operational, auditable queue without weakening catalogue evidence or exposing private trace data to customers.

## Version boundary

- Base: accepted `0.13.0` code commit `85b0fe9` plus documentation freeze `6624face`.
- Development branch: `feature/manager-queue-0.14.0`.
- Plugin version: `0.14.0`.
- Schema version: `0.10.0`.
- The `0.13.0` branch remains frozen and unmerged.

## Queue contract

Each item stores:

- customer question and conversation ID;
- originating trace ID;
- deterministic reason code / failure stage;
- status: `new`, `in_progress`, `resolved` or `dismissed`;
- priority: `normal` or manager-assigned `urgent`;
- assigned WordPress user;
- resolution note and completion timestamp;
- existing redacted diagnostic context.

## Safety rules

- Only users with `manage_woocommerce` may view or mutate queue items.
- All writes require a WordPress nonce and server-side transition validation.
- Customer-facing REST endpoints never expose queue context, traces or manager notes.
- An item is not automatically marked urgent from model output.
- Resolving a queue item does not automatically publish an answer or alter the knowledge base.
- Terminal items may only be reopened to `new`; direct terminal-to-terminal changes are rejected.

## Delivery stages

### Stage 1 — Queue domain and storage

- schema migration;
- structured queue metadata;
- safe status-transition policy;
- filtered repository methods and status counts;
- backwards-compatible `resolve()` behavior;
- unit tests for the transition policy.

### Stage 2 — Manager workspace

- status and priority filters;
- queue counters;
- claim, resolve, dismiss and reopen actions;
- manager notes;
- trace links and concise evidence summary;
- capability, nonce, sanitization and escaping checks.

### Stage 3 — Resolution workflow

- optional approved response draft;
- explicit manager action before any customer-visible response;
- event/audit history;
- no automatic knowledge-base publication.

### Stage 4 — Operations and acceptance

- WP-CLI queue diagnostics;
- retention behavior;
- browser acceptance workflow;
- regression evaluation against the frozen 14-case suite.
