# AI Provider for Claude on Bedrock

Amazon Bedrock Claude provider for WordPress AI Client.

## Description

This plugin extends the WordPress AI Client SDK to enable Claude model access through Amazon Bedrock Runtime API. It supports text generation, tool use, function calling, and structured outputs with JSON schemas.

## Supported Models

| Model | Bedrock Model ID |
|---|---|
| Claude Opus 4.6 | `anthropic.claude-opus-4-20250514` |
| Claude Sonnet 4.6 | `anthropic.claude-sonnet-4-20250514` |
| Claude Haiku 4.5 | `anthropic.claude-haiku-4-20250514` |

## Requirements

- PHP 8.1 or higher
- WordPress 6.9 or higher
- WordPress AI Client SDK (`wordpress/php-ai-client`)
- AWS account with Bedrock access

## Installation

1. Download or clone the plugin into `wp-content/plugins/ai-provider-for-bedrock/`
2. Run `composer install` from the plugin directory
3. Activate the plugin in WordPress admin
4. Configure AWS credentials (see Configuration section)

## Configuration

### Option 1: PHP Constants (recommended for production)

Add to your `wp-config.php`:

```php
define('BEDROCK_ACCESS_KEY_ID', 'AKIAIOSFODNN7EXAMPLE');
define('BEDROCK_SECRET_ACCESS_KEY', 'wJalrXUtnFEMI/K7MDENG/bPlusCfrISMYEXAMPLE');
define('BEDROCK_REGION', 'eu-north-1');
```

### Option 2: WordPress Settings

Navigate to **Settings > Bedrock AI Provider** in the WordPress admin panel and enter your AWS credentials.

### Credential Priority

1. PHP constants (highest priority)
2. WordPress options (database)
3. Defaults (empty for keys, `eu-north-1` for region)

### AWS IAM Permissions

The IAM user needs the `bedrock:InvokeModel` permission:

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
- AWS SigV4 request signing via AWS SDK

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
vendor/bin/phpunit
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
