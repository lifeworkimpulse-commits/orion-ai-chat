# AI Improvement Tickets 0.15.0

## Version boundary

- Base: acceptance-frozen `0.14.0` documentation head `0df42ed` and code baseline `b9e132e`.
- Branch: `feature/ai-improvement-tickets-0.15.0`.
- Plugin: `0.15.0`.
- Schema: `0.13.2`.

## Stage 1 — Resolution target and scalable review

Every gap receives one deterministic type: knowledge, catalogue, product data, routing, provider or technical. Only knowledge gaps may publish reviewed guidance. AI Gaps and Customer Conversations use server-side pagination.

## Stage 2 — Repeated gap groups

Each gap joins a stable private group based on existing intent/topic signals plus deterministic type and reason, with conservative token similarity as fallback. Groups show occurrence and open counts and never perform an automatic change.

Store-policy answers require evidence for every independently requested fact. Any missing condition, value, price, date, duration, area or guarantee creates a gap instead of a partial answer.

## Stage 3 — Internal improvement tickets

One group can have one manager-created internal ticket with:

- title and source-specific fix plan;
- priority: low, normal, high or critical;
- manager owner;
- optional internal reference URL;
- lifecycle: Backlog → Planned → In progress → Validating → Resolved;
- Dismissed and controlled reopen paths;
- required verification evidence before resolution;
- immutable audit events for creation, updates and status changes;
- all linked gaps, conversations and traces visible below the ticket.

Priority is suggested from occurrence/open counts, but the manager chooses the final value. Creating or resolving a ticket does not automatically edit Knowledge Base, WooCommerce, routing, code or linked gap statuses.

Active tickets preserve linked evidence from normal terminal-gap cleanup. Resolved or dismissed tickets return to the configured retention lifecycle.

## Read-only diagnostics

```bash
wp orion-ai gaps schema
wp orion-ai gaps stats
wp orion-ai gaps groups
wp orion-ai gaps ticket <group-id>
wp orion-ai gaps ticket <group-id> --details
```

Private ticket plans, verification and representative customer text require `--details`.

## Safety

- Customer text remains non-executable private evidence.
- No model output or customer claim becomes knowledge automatically.
- Ticket writes require `manage_woocommerce` and a group-specific nonce.
- Invalid lifecycle shortcuts are rejected server-side.
- Resolution requires written verification evidence.
