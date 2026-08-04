# AI diagnostics

Version 0.8 records a redacted trace for each customer request.

## WP-CLI

```text
wp orion-ai diagnose --message="Can you get a pallet to M1?"
wp orion-ai diagnose --provider=google --model=gemini-3.6-flash --message="My garage is six by four and I want a durable grey finish."
wp orion-ai traces list
wp orion-ai traces list --format=json
wp orion-ai traces show 12
```

`diagnose` calls the configured model and catalogue/knowledge lookup pipeline, but does not create a conversation, consume a customer session question, or create a manager handoff.

## Admin

Open **WooCommerce → AI Assistant → AI traces**. The list shows provider, model, semantic intent, topic, outcome, failure stage, duration, and date. Open a trace to inspect classification, context, knowledge lookup, catalogue candidate counts, selected product roles, missing roles, usage, and handoff reference.

## Privacy

Keys, secrets, authorization headers, cookies and nonces are redacted. Trace messages and diagnostic payloads follow the plugin retention setting and are removed by the daily cleanup process.
