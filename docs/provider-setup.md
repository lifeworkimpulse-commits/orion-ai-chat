# AI provider setup

Orion AI Assistant supports one active provider at a time.

## OpenRouter

1. Open **WooCommerce → AI Assistant**.
2. Choose **OpenRouter**.
3. Enter an OpenRouter API key and model, then save.
4. Use **Test OpenRouter**.

For production, define the key in `wp-config.php`:

```php
define( 'ORION_AI_OPENROUTER_KEY', 'your-key' );
```

## Google Gemini

1. Create a Gemini API key in Google AI Studio.
2. Open **WooCommerce → AI Assistant**.
3. Choose **Google Gemini**.
4. Enter the Google key and a model available to the account, then save.
5. Use **Test Google Gemini**.

For production:

```php
define( 'ORION_AI_GOOGLE_KEY', 'your-key' );
```

The admin screen never renders a stored key back into the password field. It shows a fixed mask and whether the key comes from `wp-config.php` or the WordPress database. A key defined in `wp-config.php` cannot be removed from the plugin UI.

The Google free tier can have lower quotas and different data-processing terms than paid usage. Do not send payment details, order addresses or unnecessary personal data to an AI provider.
