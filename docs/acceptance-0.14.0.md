# Orion AI Assistant 0.14.0 Manager Queue Acceptance

This checklist validates the Manager Queue development line without merging any draft pull request.

## 1. Update and migrate

```powershell
git fetch origin
git switch feature/manager-queue-0.14.0
git pull origin feature/manager-queue-0.14.0
& 'W:\modules\php\PHP_8.1\php.exe' 'W:\tools\wp-cli.phar' orion-ai migrate --apply --path='W:\domains\myorionchat.com'
& 'W:\modules\php\PHP_8.1\php.exe' 'W:\tools\wp-cli.phar' option get orion_ai_schema_version --path='W:\domains\myorionchat.com'
```

Expected schema: `0.11.0`.

## 2. Static and operational checks

```powershell
& 'W:\modules\php\PHP_8.1\php.exe' 'W:\userdata\composer\composer.phar' test
& 'W:\modules\php\PHP_8.1\php.exe' 'W:\userdata\composer\composer.phar' analyse
& 'W:\modules\php\PHP_8.1\php.exe' 'W:\tools\wp-cli.phar' orion-ai queue stats --path='W:\domains\myorionchat.com'
& 'W:\modules\php\PHP_8.1\php.exe' 'W:\tools\wp-cli.phar' orion-ai queue list --limit=20 --path='W:\domains\myorionchat.com'
```

The default list must not print the customer question, private manager note, approved response or diagnostic context. Use `queue show ID --details` only on a trusted local console.

## 3. Create a queue item

In a fresh browser chat, submit a request that cannot be confirmed from the live catalogue, for example:

```text
I need Orion product SKU MANAGER-QUEUE-TEST-DO-NOT-EXIST. Confirm its stock and manufacturer warranty.
```

Pass conditions:

- the customer receives a manager follow-up reference number;
- the queue item appears under **WooCommerce → AI Assistant → Manager queue**;
- status is `New`;
- priority is `Normal`;
- reason code and originating trace link are present;
- private manager note and customer response are separate fields.

## 4. Manager workflow

1. Click **Claim** and confirm the item becomes `In progress` and is assigned to the current WordPress user.
2. Change priority to `Urgent`, add a private note and save.
3. Verify status, priority and owner filters.
4. Save a customer response as a draft.
5. Confirm draft text is not shown to the customer.
6. Approve the exact customer response explicitly.
7. In the same browser, send another successful chat message.

Pass conditions:

- the response starts with `Update from our team for request #...`;
- only the approved customer response is shown;
- private notes and diagnostic context are absent;
- a subsequent message does not repeat the manager response;
- response status changes from `Draft` to `Approved` to `Delivered`;
- activity history records claim, details, approval and delivery;
- the trace response stage contains a non-zero `manager_response_id` only for the delivery request.

The previous session token may resume delivery after the normal chat timeout, but only when it belongs to the same visitor and the retained source conversation still exists.

## 5. Resolve, dismiss and reopen

- Resolving and dismissing require a private manager note.
- A resolved or dismissed item may be reopened only to `New`.
- Direct `Resolved → Dismissed` and `Dismissed → Resolved` transitions must be rejected.
- Dismissing an item with an approved but undelivered response must cancel delivery and return the response to draft state.
- No queue action may publish content to the knowledge base automatically.

## 6. Read-only diagnostics

```powershell
& 'W:\modules\php\PHP_8.1\php.exe' 'W:\tools\wp-cli.phar' orion-ai queue stats --format=table --path='W:\domains\myorionchat.com'
& 'W:\modules\php\PHP_8.1\php.exe' 'W:\tools\wp-cli.phar' orion-ai queue list --status=in_progress --format=table --path='W:\domains\myorionchat.com'
& 'W:\modules\php\PHP_8.1\php.exe' 'W:\tools\wp-cli.phar' orion-ai queue show ID --path='W:\domains\myorionchat.com'
```

Use `--details` only when private text is intentionally required.

## 7. Retention

```powershell
& 'W:\modules\php\PHP_8.1\php.exe' 'W:\tools\wp-cli.phar' orion-ai cleanup --path='W:\domains\myorionchat.com'
```

This is a dry run unless `--apply` is supplied. Runtime cleanup must:

- retain open queue items;
- delete terminal queue items only after the configured retention period;
- delete their dedicated history rows with them;
- mark approved responses `Expired` before their retained source conversation is deleted;
- never deliver an expired response.

## 8. Regression evaluation

```powershell
& 'W:\modules\php\PHP_8.1\php.exe' 'W:\tools\wp-cli.phar' orion-ai evaluate --provider=openrouter --model='openai/gpt-5.6-luna' --path='W:\domains\myorionchat.com'
```

Pass target: `14/14`. Manager Queue must not change the accepted routing, product selection, uncertainty or provider-fallback behavior from `0.13.0`.

## Release decision

Keep PR #3 Draft until migration, manager workflow, one-time delivery, cancellation, CLI diagnostics, retention behavior and the 14-case regression suite pass locally. Do not merge without explicit approval.
