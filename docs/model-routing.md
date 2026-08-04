# Model routing and fallback

Version 0.9.0 supports separate models and timeouts for semantic routing, product selection, and final answers.

## Settings

Open **WooCommerce → AI Assistant → Assistant settings**.

- **Routing model** — must support function calling and reliable structured output.
- **Product selection model** — validates candidate products against catalogue roles.
- **Answer model** — writes customer-facing answers.
- **Fallback provider/model** — attempted once only after provider errors, timeouts, empty responses, or invalid required structured output.
- **Timeouts** — 5–60 seconds per stage.

Leave a stage model blank to inherit the selected provider's default model. Leave the fallback model blank to inherit the fallback provider's default model.

Fallback is not used when knowledge is genuinely unsupported, products are absent, or clarification is required.

## Evaluation

The evaluation suite does not create conversations, handoffs, or customer quota usage.

```text
wp orion-ai evaluate --provider=openrouter --model=openrouter/free
wp orion-ai evaluate --provider=openrouter --model=openai/gpt-5.6-luna
wp orion-ai evaluate --provider=google --model=gemini-3.6-flash
wp orion-ai evaluate --provider=openrouter --model=openai/gpt-5.6-luna --fallback-provider=google --fallback-model=gemini-3.6-flash
wp orion-ai evaluate --provider=google --model=gemini-3.6-flash --format=json
```

The report includes pass/fail, final intent, diagnostic status, case latency, pass rate, average latency, and total tokens.

## Traces

Each model result includes an `attempts` list. When fallback is used, traces include `fallback_used: true` and both primary and fallback provider/model outcomes.
