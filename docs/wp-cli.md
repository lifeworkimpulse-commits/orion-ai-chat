# Orion AI WP-CLI

Run commands from the WordPress root. On the local OSPanel installation:

```cmd
php W:\tools\wp-cli.phar orion-ai status
```

## Read-only commands

```text
wp orion-ai status
wp orion-ai test-provider --provider=openrouter
wp orion-ai test-provider --provider=google
wp orion-ai handoffs list
wp orion-ai handoffs list --status=resolved
wp orion-ai handoffs list --format=json
```

## Write commands

Write operations default to a dry run and require `--apply`:

```text
wp orion-ai migrate
wp orion-ai migrate --apply
wp orion-ai reindex
wp orion-ai reindex --apply
wp orion-ai cleanup
wp orion-ai cleanup --apply
wp orion-ai handoffs resolve 12
wp orion-ai handoffs resolve 12 --apply
```

Before running write operations on staging or production, confirm the site target and create a database backup.

## Status checks

The status command reports:

- plugin and schema versions;
- WordPress and WooCommerce versions;
- active AI provider and key source without revealing the key;
- required custom tables and the semantic `state_json` column;
- knowledge document and chunk counts;
- new manager handoffs;
- cleanup cron scheduling.
