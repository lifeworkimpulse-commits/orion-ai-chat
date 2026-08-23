# Orion AI Assistant 0.14.0 Acceptance

This checklist validates conversation review and safe knowledge-gap publishing. It does not test manager replies to customers because that workflow has been removed.

## 1. Update and migrate

```powershell
Set-Location 'W:\domains\myorionchat.com\wp-content\plugins\orion-ai-assistant'
git fetch origin
git switch feature/manager-queue-0.14.0
git pull origin feature/manager-queue-0.14.0
git log -1 --oneline

& 'W:\modules\php\PHP_8.1\php.exe' 'W:\tools\wp-cli.phar' orion-ai migrate --apply --path='W:\domains\myorionchat.com'
& 'W:\modules\php\PHP_8.1\php.exe' 'W:\tools\wp-cli.phar' option get orion_ai_schema_version --path='W:\domains\myorionchat.com'
```

Expected schema: `0.12.0`.

## 2. Local checks

```powershell
& 'W:\modules\php\PHP_8.1\php.exe' 'W:\userdata\composer\composer.phar' test
& 'W:\modules\php\PHP_8.1\php.exe' 'W:\userdata\composer\composer.phar' analyse
& 'W:\modules\php\PHP_8.1\php.exe' 'W:\tools\wp-cli.phar' orion-ai gaps stats --path='W:\domains\myorionchat.com'
```

## 3. Review all conversations

1. Open the storefront chat in one browser tab.
2. Send two or three ordinary questions and confirm Orion answers them.
3. Open **WooCommerce → AI Assistant → Conversations** in the admin tab.
4. Open the newest conversation.

Pass conditions:

- every customer question appears once;
- every successful AI answer appears once and in order;
- dates, message count and gap count are visible;
- no client hash, session token or API key is rendered;
- only a user with `manage_woocommerce` can access the screen.

## 4. Create an unanswered knowledge gap

Ask a real store-policy question for which the current knowledge base has no approved answer. Do not invent a policy merely for the test.

Alternative technical trigger:

```text
I need Orion product SKU MANAGER-QUEUE-TEST-DO-NOT-EXIST. Confirm its stock and manufacturer warranty.
```

Pass conditions:

- Orion does not invent the product, stock or warranty;
- Orion says the unanswered request was recorded for review;
- **AI gaps** shows a new item;
- the item links to the complete conversation and trace;
- the failure reason is visible.

## 5. Save a review without publishing

In the gap enter:

- a descriptive knowledge title;
- separately written verified guidance;
- an optional source URL.

Click **Save review**.

Pass conditions:

- status becomes `Reviewing`;
- the guidance is not yet present under Knowledge Base;
- the original customer question has not been copied into a knowledge document;
- no message is sent to the original customer.

## 6. Publish verified guidance

Use a real, confirmed store fact. The guidance must contain at least 20 characters. Click **Add to knowledge base** and confirm.

Pass conditions:

- a manual knowledge document is created and indexed;
- status becomes `Added to knowledge`;
- the gap displays the linked document ID and publication time;
- the document contains the manager-written guidance and optional source link only;
- it does not contain the customer dialogue, trace or diagnostic context;
- the review history records publication without copying the guidance text into its payload.

Open **Knowledge base**, inspect the document, and edit or delete it if the test content should not remain in production knowledge.

## 7. Verify future AI use

For a real store-policy gap, start a new browser chat and ask the same question in different words.

Expected result: Orion retrieves the reviewed document and answers only from its confirmed guidance. If the original gap concerned a missing catalogue product, adding policy guidance will not create that product; catalogue data must instead be corrected in WooCommerce.

## 8. Ignore and reopen

- Create another gap.
- Click **Ignore gap** and confirm status `Ignored`.
- Click **Reopen** and confirm status `New`.
- No knowledge document should be created by either action.

## 9. Read-only diagnostics

```powershell
& 'W:\modules\php\PHP_8.1\php.exe' 'W:\tools\wp-cli.phar' orion-ai gaps list --limit=20 --path='W:\domains\myorionchat.com'
& 'W:\modules\php\PHP_8.1\php.exe' 'W:\tools\wp-cli.phar' orion-ai gaps show ID --path='W:\domains\myorionchat.com'
```

Default output must omit customer question, reviewed guidance and diagnostic context. Use `--details` only on the trusted local console.

## 10. Regression evaluation

```powershell
& 'W:\modules\php\PHP_8.1\php.exe' 'W:\tools\wp-cli.phar' orion-ai evaluate --provider=openrouter --model='openai/gpt-5.6-luna' --path='W:\domains\myorionchat.com'
```

Pass target: `14/14`. Conversation logging and knowledge-gap capture must not change accepted product routing and selection.

Keep PR #3 Draft until browser review, safe publication and the frozen evaluation pass locally.
