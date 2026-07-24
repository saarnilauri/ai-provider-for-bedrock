# AI Provider for Claude on Bedrock

A third-party provider for Amazon Bedrock Claude in the [PHP AI Client SDK](https://github.com/WordPress/php-ai-client). Works as both a Composer package and a WordPress plugin.

This project is independent and is not affiliated with, endorsed by, or sponsored by Amazon.

## Description

This plugin extends the WordPress AI Client SDK to enable Claude model access through Amazon Bedrock Runtime API. It supports text generation, tool use, function calling, and structured outputs with JSON schemas.

## Supported Models

The plugin discovers the available Claude models at runtime from the Amazon
Bedrock [inference profiles API](https://docs.aws.amazon.com/bedrock/latest/userguide/inference-profiles-support.html)
and caches the result for one hour. All active Anthropic models in the
configured region are listed — for example Claude Opus, Sonnet, Haiku, and
Fable families, as available in your account's region.

If the API cannot be reached (e.g. the credential lacks the
`bedrock:ListInferenceProfiles` permission), the plugin falls back to a
hardcoded list:

| Model | Bedrock Model ID |
|---|---|
| Claude Opus 4.6 | `anthropic.claude-opus-4-6-v1` |
| Claude Sonnet 4.6 | `anthropic.claude-sonnet-4-6` |
| Claude Haiku 4.5 | `anthropic.claude-haiku-4-5-20251001-v1:0` |

Notes:

- Claude models do not support on-demand invocation of the bare foundation-model ID, so the plugin exposes them as cross-region inference profile IDs, prefixed with the geographic area derived from the configured region (e.g. `eu.anthropic.claude-sonnet-4-6` for `eu-north-1`, `us.` for US regions).
- A listed model is not necessarily enabled for your AWS account. Anthropic models are served from the AWS Marketplace and are enabled account-wide on their first invocation — which must be made by a principal with AWS Marketplace permissions (e.g. test the model once in the Bedrock console playground as an administrator). A Bedrock API key alone cannot enable new models and gets a `403` until the model has been enabled.
- Automatic model selection defaults to a curated known-good model (Claude Opus 4.6); newly discovered models are listed after the curated ones and can be selected explicitly.

## Requirements

- ⚠️ PHP 8.1 or higher
- WordPress 6.9 or higher
- WordPress AI Client SDK (`wordpress/php-ai-client`)
- AWS account with Bedrock access

## Installation

1. Download or clone the plugin into `wp-content/plugins/ai-provider-for-bedrock/`
2. Run `composer install` from the plugin directory
3. Activate the plugin in WordPress admin
4. Configure AWS credentials (see Configuration section)

## Configuration

Two authentication methods are supported:

- **Bedrock API key** (simplest): generate one in the Amazon Bedrock console under **API keys**. It is sent as a Bearer token.
- **IAM access key pair**: requests are signed with AWS Signature V4. Also works with IAM users/roles you manage yourself.

When both are configured, the API key takes precedence.

### Option 1: WordPress core connectors page (recommended)

The provider declares API key authentication, so it appears on the WordPress
core AI connectors settings page alongside other providers. Enter your Bedrock
API key there — no plugin-specific configuration needed. A key stored this way
takes precedence over the plugin's own settings.

### Option 2: PHP Constants (recommended for production)

Add to your `wp-config.php`, either:

```php
define('BEDROCK_API_KEY', '...your Bedrock API key...');
define('BEDROCK_REGION', 'eu-north-1');
```

or:

```php
define('BEDROCK_ACCESS_KEY_ID', 'XXXXXXXXXXXISEXAMPLE');
define('BEDROCK_SECRET_ACCESS_KEY', 'wJalrXUtnFEMI/K7MDENG/bPlusCfrxyISEXAMPLE');
define('BEDROCK_REGION', 'eu-north-1');
```

### Option 3: WordPress Settings

Navigate to **Settings > Bedrock AI Provider** in the WordPress admin panel and enter your credentials. This page is also where the AWS region is configured (the connectors page only stores the API key).

### Credential Priority

1. API key from the core connectors page or the `BEDROCK_API_KEY` constant/environment variable
2. Plugin settings: `BEDROCK_API_KEY` option, then IAM key pair (constants before options)
3. Defaults (empty for keys, `eu-north-1` for region)

### AWS IAM Permissions

When using an IAM access key pair, the IAM user needs the `bedrock:InvokeModel` permission:

```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": "bedrock:InvokeModel",
            "Resource": "arn:aws:bedrock:*::foundation-model/anthropic.*"
        }
    ]
}
```

## Features

- Text generation with Claude models via Bedrock
- System instructions
- Tool use and function calling
- Structured outputs with JSON schemas
- Token usage tracking
- Bedrock API key (Bearer token) or AWS SigV4 request signing via AWS SDK

## Available Regions

- `eu-north-1` (default)
- `us-east-1`
- `us-west-2`
- `eu-west-1`
- `ap-southeast-1`

## Development

### Running Tests

```bash
composer install
composer test:unit
```

Integration tests make real API calls to Amazon Bedrock. Copy `.env.example`
to `.env`, fill in your credentials, and run:

```bash
composer test:integration
```

### Static Analysis

```bash
vendor/bin/phpstan analyse src/
```

### Code Style

```bash
vendor/bin/phpcs src/ plugin.php
```

## License

GPL-2.0-or-later
