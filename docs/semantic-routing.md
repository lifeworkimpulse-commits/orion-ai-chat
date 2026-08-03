# Semantic routing

Version 0.7 replaces keyword-first project routing with a model-generated structured route.

## Request flow

1. The active provider calls the `route_request` function with conversation history and saved state.
2. WordPress validates the returned intent, state patch, clarification questions and role-based search plan.
3. Conversation state is saved in `orion_ai_conversations.state_json`.
4. Store-policy requests search only the approved knowledge base.
5. Product requests search WooCommerce independently for each semantic role.
6. A second model pass selects only candidate product IDs supplied by WordPress.
7. WordPress reloads every selected product and checks visibility and stock.
8. Unsupported requests create a real row in `orion_ai_handoffs`.

## Safety rules

- User text cannot change the routing schema.
- The model cannot return arbitrary product IDs.
- The same product cannot fill multiple roles.
- Store policies must be explicitly supported by imported knowledge.
- Missing required products trigger manager follow-up instead of broad fallback search.

## Acceptance tests

- `Can you get a pallet to M1?` routes to store policy without requiring the word delivery.
- `My garage is six by four and I want a durable grey finish` routes to project recommendation and asks only material questions that remain necessary.
- `Make it blue instead` updates the current project colour.
- `Now tell me your returns policy` starts a new store-policy topic without leaking project products.
- A request not supported by knowledge creates a visible item in **WooCommerce → AI Assistant → Manager queue**.
